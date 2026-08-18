"""فاز ۱ — کراول و تشخیص موضوعی.

ترتیب تلاش برای هر دامنه:
1. ``robots.txt`` → آدرس sitemap
2. پارس ``sitemap.xml`` (و sitemap indexها)
3. اگر sitemap نبود → کراول محدود با عمق مشخص، فقط داخل همان دامنه
4. هر صفحه با ``curl_cffi``؛ در صورت ۴۰۳/محتوای خالی → Playwright

سپس شباهت هر عنوان با بردار مرجع موضوع سنجیده می‌شود.
"""

from __future__ import annotations

import csv
import re
import sqlite3
import urllib.parse
from collections import deque
from dataclasses import dataclass, field
from pathlib import Path
from typing import Iterable

from ..core import db, extract, normalizer
from ..core.config import Config, SiteConfig
from ..core.embeddings import TopicMatcher
from ..core.http import Fetcher

_SITEMAP_GUESSES = ("/sitemap.xml", "/sitemap_index.xml", "/sitemap-index.xml", "/wp-sitemap.xml")


@dataclass
class CrawlStats:
    pages_fetched: int = 0
    titles_found: int = 0
    inserted: int = 0
    relevant: int = 0
    borderline: int = 0
    failures: int = 0
    per_site: dict[str, int] = field(default_factory=dict)

    def render(self) -> str:
        return (
            f"فاز ۱ — صفحات: {self.pages_fetched}، عنوان: {self.titles_found}، "
            f"ذخیره‌شده: {self.inserted}، مرتبط: {self.relevant}، "
            f"مرزی: {self.borderline}، خطا: {self.failures}"
        )


def collect_urls(site: SiteConfig, fetcher: Fetcher, max_urls: int) -> list[str]:
    """URLهای کاندیدای یک دامنه: اول sitemap، در نبودش کراول محدود."""
    urls = _from_sitemaps(site, fetcher, max_urls)
    if not urls:
        urls = _from_crawl(site, fetcher, max_urls)
    return _filter_urls(urls, site)[:max_urls]


def _from_sitemaps(site: SiteConfig, fetcher: Fetcher, max_urls: int) -> list[str]:
    candidates = list(fetcher.robots.sitemaps(site.base_url))
    if not candidates:
        candidates = [urllib.parse.urljoin(site.base_url + "/", guess.lstrip("/")) for guess in _SITEMAP_GUESSES]

    seen: set[str] = set()
    queue = deque(candidates)
    urls: list[str] = []
    while queue and len(urls) < max_urls * 4:
        sitemap_url = queue.popleft()
        if sitemap_url in seen:
            continue
        seen.add(sitemap_url)
        result = fetcher.fetch(sitemap_url, allow_browser=False)
        if result.status != 200 or not result.html.strip():
            continue
        found, nested = extract.parse_sitemap(result.html)
        urls.extend(found)
        for child in nested:
            if child not in seen and len(seen) < 60:
                queue.append(child)
    return urls


def _from_crawl(site: SiteConfig, fetcher: Fetcher, max_urls: int) -> list[str]:
    """BFS محدود به همان دامنه با سقف عمق و تعداد صفحه."""
    seeds = site.seed_urls or [site.base_url]
    queue: deque[tuple[str, int]] = deque((s, 0) for s in seeds)
    visited: set[str] = set()
    collected: list[str] = []
    while queue and len(visited) < site.max_pages and len(collected) < max_urls * 3:
        url, depth = queue.popleft()
        if url in visited or depth > site.max_depth:
            continue
        visited.add(url)
        result = fetcher.fetch(url, force_browser=site.js)
        if not result.ok:
            continue
        collected.append(url)
        if depth == site.max_depth:
            continue
        for link in extract.extract_links(result.html, result.final_url or url):
            if link not in visited and extract.same_domain(link, site.domain):
                queue.append((link, depth + 1))
    return collected


def _filter_urls(urls: Iterable[str], site: SiteConfig) -> list[str]:
    include = [re.compile(p) for p in site.product_url_include]
    exclude = [re.compile(p) for p in site.product_url_exclude]
    out: list[str] = []
    seen: set[str] = set()
    for url in urls:
        if not extract.same_domain(url, site.domain):
            continue
        if any(pattern.search(url) for pattern in exclude):
            continue
        if include and not any(pattern.search(url) for pattern in include):
            continue
        clean, _ = urllib.parse.urldefrag(url)
        if clean not in seen:
            seen.add(clean)
            out.append(clean)
    return out


def sites_for_run(conn: sqlite3.Connection, run_id: str, config: Config):
    """سایت‌های یک اجرا: اول فهرست خودِ اجرا (از پنل)، وگرنه ``sites`` در config.

    هر اجرا فهرست سایت‌های خودش را نگه می‌دارد تا اجرای بعدی با فهرست دیگر،
    اجرای قبلی را دست‌کاری نکند.
    """
    urls = db.run_sites(conn, run_id)
    if not urls:
        return config.sites
    defaults = {
        "max_depth": config.get("crawl.max_depth", 3),
        "max_pages": config.get("crawl.max_pages", 500),
    }
    return [SiteConfig.from_entry(url, defaults) for url in urls]


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    fetcher: Fetcher,
    matcher: TopicMatcher,
    limit: int | None = None,
    verbose: bool = True,
) -> CrawlStats:
    norm_config = normalizer.config_from_mapping(config.normalizer)
    stats = CrawlStats()
    sites = sites_for_run(conn, run_id, config)
    if not sites:
        raise ValueError(
            "هیچ سایتی برای این اجرا ثبت نشده است. از تب «شروع» پنل لینک سایت‌ها"
            " را وارد کنید یا کلید sites را در config پر کنید."
        )

    for site in sites:
        max_urls = min(site.max_pages, limit or site.max_pages)
        if verbose:
            print(f"  [{site.domain}] جمع‌آوری URL...")
        urls = collect_urls(site, fetcher, max_urls)
        if verbose:
            print(f"  [{site.domain}] {len(urls)} آدرس کاندیدا")

        found_here = 0
        for url in urls:
            result = fetcher.fetch(url, force_browser=site.js)
            stats.pages_fetched += 1
            if not result.ok:
                stats.failures += 1
                continue
            title = extract.extract_title(
                result.html, site.title_selector, site.domain, site.site_name
            )
            if not title.strip():
                continue
            stats.titles_found += 1
            found_here += 1
            with db.transaction(conn):
                db.insert_raw_product(
                    conn,
                    run_id=run_id,
                    source_domain=site.domain,
                    source_url=result.final_url or url,
                    raw_title=title,
                    normalized_title=normalizer.normalize(title, norm_config),
                )
        stats.per_site[site.domain] = found_here

    stats.inserted = conn.execute(
        "SELECT COUNT(*) AS c FROM raw_products WHERE run_id=?", (run_id,)
    ).fetchone()["c"]
    score_relevance(conn, run_id, config, matcher, verbose=verbose, stats=stats)
    return stats


def score_relevance(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    matcher: TopicMatcher,
    verbose: bool = True,
    stats: CrawlStats | None = None,
) -> CrawlStats:
    """محاسبه‌ی ``topic_score`` و تعیین ``is_relevant``.

    * ``score >= threshold``        → مرتبط
    * ``review_threshold..threshold`` → مرزی (``is_relevant IS NULL``) برای بازبینی دستی
    * کمتر                          → نامرتبط
    """
    stats = stats or CrawlStats()
    fallback = (config.relevance_threshold, config.review_threshold)
    auto = bool(config.get("run.auto_threshold", True)) and bool(matcher.thresholds)
    if verbose and auto:
        limits = "، ".join(
            f"{label}: {rel}/{rev}" for label, (rel, rev) in matcher.thresholds.items()
        )
        print(f"  آستانه‌ی کالیبره‌شده (مرتبط/مرزی) — {limits}")
    rows = conn.execute(
        "SELECT id, raw_title, normalized_title FROM raw_products WHERE run_id=?", (run_id,)
    ).fetchall()

    batch_size = 64
    for start in range(0, len(rows), batch_size):
        chunk = rows[start : start + batch_size]
        titles = [r["normalized_title"] or r["raw_title"] or "" for r in chunk]
        verdicts = matcher.classify_batch(titles)
        with db.transaction(conn):
            for row, (label, score) in zip(chunk, verdicts):
                threshold, review = matcher.limits(label, fallback) if auto else fallback
                if score >= threshold:
                    is_relevant: bool | None = True
                    stats.relevant += 1
                elif score >= review:
                    is_relevant = None
                    stats.borderline += 1
                else:
                    is_relevant = False
                db.update_raw_relevance(
                    conn, int(row["id"]), float(score), is_relevant, label or None
                )

    path = export_borderline(conn, run_id, config)
    if verbose and path:
        print(f"  عنوان‌های مرزی برای بازبینی دستی: {path}")
    return stats


def export_borderline(conn: sqlite3.Connection, run_id: str, config: Config) -> str | None:
    """ذخیره‌ی عنوان‌های مرزی در یک فایل جدا (طبق داکیومنت فاز ۱)."""
    rows = db.borderline_raw_products(conn, run_id)
    if not rows:
        return None
    target = Path(config.db_path).parent / "review" / f"{run_id}_borderline.csv"
    target.parent.mkdir(parents=True, exist_ok=True)
    with target.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["id", "topic_score", "raw_title", "normalized_title", "source_url"])
        for row in rows:
            writer.writerow(
                [
                    row["id"],
                    round(row["topic_score"] or 0.0, 4),
                    row["raw_title"],
                    row["normalized_title"],
                    row["source_url"],
                ]
            )
    return str(target)
