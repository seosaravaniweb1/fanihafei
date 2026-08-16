"""فاز ۳ — گوگل ساجست.

* برای هر ``canonical_product`` یک کوئری ساجست
* استفاده از SerpApi/DataForSEO (نه endpoint غیررسمی گوگل — بلاک می‌شوی)
* هر کلمه یک ردیف در ``lsi_keywords`` (نه رشته‌ی درهم در یک سلول)
* جلوگیری از تداخل LSI: یک کلمه فقط به محصولی می‌ماند که شباهت بیشتری دارد
* نتیجه‌ی خالی → ``suggest_status = 'no_suggest'`` و رکورد حذف نمی‌شود
"""

from __future__ import annotations

import sqlite3
from collections import defaultdict
from dataclasses import dataclass

from ..core import db, normalizer, similarity
from ..core.config import Config
from ..clients.serp_client import SerpClient

HAS_SUGGEST = "has_suggest"
NO_SUGGEST = "no_suggest"


@dataclass
class SuggestStats:
    products: int = 0
    queried: int = 0
    with_suggest: int = 0
    without_suggest: int = 0
    keywords_stored: int = 0
    conflicts_resolved: int = 0
    failures: int = 0

    def render(self) -> str:
        return (
            f"فاز ۳ — محصولات: {self.products}، کوئری: {self.queried}، "
            f"دارای ساجست: {self.with_suggest}، بدون ساجست: {self.without_suggest}، "
            f"کلمات: {self.keywords_stored}، تداخل حل‌شده: {self.conflicts_resolved}"
        )


def estimate_calls(conn: sqlite3.Connection, run_id: str, serp: SerpClient) -> int:
    """تعداد کال‌هایی که واقعاً زده می‌شود (کش‌شده‌ها شمرده نمی‌شوند)."""
    count = 0
    for row in db.canonical_products(conn, run_id):
        params = {"q": row["canonical_title"], "hl": serp.config.hl, "gl": serp.config.gl}
        if not serp.cached("autocomplete", params):
            count += 1
    return count


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    serp: SerpClient,
    verbose: bool = True,
) -> SuggestStats:
    norm_config = normalizer.config_from_mapping(config.normalizer)
    stats = SuggestStats()
    products = db.canonical_products(conn, run_id)
    stats.products = len(products)

    for row in products:
        canonical_id = int(row["id"])
        title = row["canonical_title"] or ""
        try:
            suggestions = serp.autocomplete(title)
            stats.queried += 1
        except Exception as exc:
            stats.failures += 1
            if verbose:
                print(f"  هشدار: ساجست برای «{title}» ناموفق بود: {exc}")
            continue

        normalized_title = normalizer.normalize(title, norm_config)
        with db.transaction(conn):
            for position, keyword in enumerate(suggestions, start=1):
                keyword = keyword.strip()
                if not keyword:
                    continue
                score = similarity.token_set_ratio(
                    normalizer.normalize(keyword, norm_config), normalized_title
                )
                db.insert_keyword(conn, canonical_id, keyword, position, round(score, 4))
                stats.keywords_stored += 1
            db.update_canonical(
                conn,
                canonical_id,
                suggest_status=HAS_SUGGEST if suggestions else NO_SUGGEST,
            )
        if suggestions:
            stats.with_suggest += 1
        else:
            stats.without_suggest += 1

    stats.conflicts_resolved = resolve_conflicts(conn, run_id)
    _refresh_status(conn, run_id)
    return stats


def resolve_conflicts(conn: sqlite3.Connection, run_id: str) -> int:
    """اگر یک LSI به دو محصول نسبت داده شد، فقط برای نزدیک‌ترین محصول می‌ماند."""
    owners: dict[str, list[tuple[int, float, int]]] = defaultdict(list)
    for row in db.all_keywords(conn, run_id):
        owners[row["keyword"]].append(
            (int(row["canonical_id"]), float(row["similarity"] or 0.0), int(row["position"]))
        )

    removed = 0
    with db.transaction(conn):
        for keyword, claims in owners.items():
            if len(claims) < 2:
                continue
            # برنده: بیشترین شباهت؛ در تساوی، جایگاه بهتر (کوچک‌تر) و سپس id کوچک‌تر
            winner = max(claims, key=lambda c: (c[1], -c[2], -c[0]))
            for canonical_id, _, _ in claims:
                if canonical_id != winner[0]:
                    db.delete_keyword(conn, canonical_id, keyword)
                    removed += 1
    return removed


def _refresh_status(conn: sqlite3.Connection, run_id: str) -> None:
    """پس از حذف تداخل‌ها ممکن است محصولی بدون کلمه بماند."""
    with db.transaction(conn):
        for row in db.canonical_products(conn, run_id):
            if row["suggest_status"] is None:
                continue
            count = conn.execute(
                "SELECT COUNT(*) AS c FROM lsi_keywords WHERE canonical_id=?", (row["id"],)
            ).fetchone()["c"]
            db.update_canonical(
                conn,
                int(row["id"]),
                suggest_status=HAS_SUGGEST if count else NO_SUGGEST,
            )
