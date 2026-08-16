"""فاز ۴ — بنچمارک محتوا.

برای هر محصول با ``suggest_status = 'has_suggest'``:

1. سرچ عنوان در گوگل از طریق SERP API → ۱۰ نتیجه‌ی ارگانیک
2. باز کردن هر لینک و استخراج متن با ``trafilatura``
3. محاسبه‌ی معیارهای کمّی (تعداد کلمه، هدینگ، جدول/لیست/تصویر، تاریخ)
4. فقط سه کاندیدای برتر → ارزیابی کیفی LLM (هزینه یک‌سوم می‌شود)
5. برنده در ``benchmark_url`` و نمره در ``benchmark_score``
"""

from __future__ import annotations

import sqlite3
from dataclasses import dataclass
from typing import Any

from ..core import db, extract
from ..core.config import Config
from ..core.http import Fetcher
from ..clients.serp_client import SerpClient
from .p3_suggest import HAS_SUGGEST


@dataclass
class BenchmarkStats:
    products: int = 0
    searched: int = 0
    pages_fetched: int = 0
    llm_calls: int = 0
    winners: int = 0
    failures: int = 0

    def render(self) -> str:
        return (
            f"فاز ۴ — محصولات: {self.products}، سرچ: {self.searched}، "
            f"صفحات بررسی‌شده: {self.pages_fetched}، ارزیابی LLM: {self.llm_calls}، "
            f"برنده: {self.winners}، خطا: {self.failures}"
        )


def targets(conn: sqlite3.Connection, run_id: str) -> list[sqlite3.Row]:
    return db.canonical_products(conn, run_id, suggest_status=HAS_SUGGEST)


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    serp: SerpClient,
    fetcher: Fetcher,
    llm_client: Any | None = None,
    verbose: bool = True,
) -> BenchmarkStats:
    stats = BenchmarkStats()
    serp_results = int(config.get("benchmark.serp_results", 10))
    llm_candidates = int(config.get("benchmark.llm_candidates", 3))

    rows = targets(conn, run_id)
    stats.products = len(rows)
    for row in rows:
        canonical_id = int(row["id"])
        title = row["canonical_title"] or ""
        try:
            results = serp.search(title, num=serp_results)
            stats.searched += 1
        except Exception as exc:
            stats.failures += 1
            if verbose:
                print(f"  هشدار: سرچ «{title}» ناموفق بود: {exc}")
            continue

        scored: list[dict] = []
        for result in results:
            fetched = fetcher.fetch(result.link)
            stats.pages_fetched += 1
            if not fetched.ok:
                continue
            metrics = extract.extract_content(fetched.html, result.link)
            if metrics.word_count < 80:
                continue
            scored.append(
                {
                    "candidate_id": str(result.position),
                    "url": result.link,
                    "text": metrics.text,
                    "quant_score": metrics.score(),
                    "metrics": {
                        "word_count": metrics.word_count,
                        "h2": metrics.h2_count,
                        "h3": metrics.h3_count,
                        "table": metrics.has_table,
                        "list": metrics.has_list,
                        "images": metrics.image_count,
                        "published": metrics.published,
                    },
                }
            )

        if not scored:
            continue
        scored.sort(key=lambda c: c["quant_score"], reverse=True)
        shortlist = scored[:llm_candidates]

        winner = shortlist[0]
        final_score = winner["quant_score"]
        if llm_client is not None and getattr(llm_client, "available", False) and len(shortlist) > 1:
            try:
                verdicts = llm_client.score_candidates(title, shortlist)
                stats.llm_calls += 1
                if verdicts:
                    best_id = max(
                        verdicts, key=lambda key: verdicts[key].get("score", 0.0)
                    )
                    match = next(
                        (c for c in shortlist if c["candidate_id"] == best_id), shortlist[0]
                    )
                    winner = match
                    final_score = float(verdicts[best_id].get("score", final_score))
            except Exception as exc:  # pragma: no cover - وابسته به شبکه
                if verbose:
                    print(f"  هشدار: ارزیابی کیفی «{title}» ناموفق بود: {exc}")

        with db.transaction(conn):
            db.update_canonical(
                conn,
                canonical_id,
                benchmark_url=winner["url"],
                benchmark_score=round(float(final_score), 2),
            )
        stats.winners += 1

    return stats
