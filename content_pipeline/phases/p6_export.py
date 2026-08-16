"""خروجی نهایی — گوگل‌شیت و فایل محلی.

ستون‌های خروجی دقیقاً طبق جدول بخش ۱ داکیومنت. سه تب:

* «خوراک محتوایی» — محصولات ``has_suggest``
* «آرشیو آینده»   — محصولات ``no_suggest`` (حذف نمی‌شوند)
* «بازبینی دستی»  — محصولاتی که ``needs_review`` دارند
"""

from __future__ import annotations

import sqlite3
from dataclasses import dataclass

from ..core import db
from ..core.config import Config
from ..clients.sheets_client import Sheet, SheetsWriter, write_xlsx
from .p3_suggest import HAS_SUGGEST, NO_SUGGEST

HEADER = [
    "canonical_title",
    "suggest_status",
    "lsi_keywords",
    "benchmark_url",
    "benchmark_score",
    "image_url",
    "source_urls",
    "confidence",
    "needs_review",
]


@dataclass
class ExportStats:
    main_rows: int = 0
    archive_rows: int = 0
    review_rows: int = 0
    local_path: str = ""
    sheet_url: str = ""

    def render(self) -> str:
        parts = [
            f"خروجی — خوراک محتوایی: {self.main_rows}، "
            f"آرشیو آینده: {self.archive_rows}، بازبینی دستی: {self.review_rows}"
        ]
        if self.local_path:
            parts.append(f"فایل محلی: {self.local_path}")
        if self.sheet_url:
            parts.append(f"گوگل‌شیت: {self.sheet_url}")
        return "\n".join(parts)


def build_row(conn: sqlite3.Connection, row: sqlite3.Row) -> list:
    keywords = [k["keyword"] for k in db.keywords_for(conn, int(row["id"]))]
    return [
        row["canonical_title"],
        row["suggest_status"] or NO_SUGGEST,
        "، ".join(keywords),
        row["benchmark_url"] or "",
        row["benchmark_score"] if row["benchmark_score"] is not None else "",
        row["image_url"] or "",
        " | ".join(db.source_urls_for(conn, int(row["id"]))),
        round(float(row["merge_confidence"] or 0.0), 3),
        bool(row["needs_review"]),
    ]


def build_sheets(conn: sqlite3.Connection, run_id: str, config: Config) -> list[Sheet]:
    tabs = config.get("output.tabs", {}) or {}
    main: list[list] = []
    archive: list[list] = []
    review: list[list] = []

    for row in db.canonical_products(conn, run_id):
        record = build_row(conn, row)
        if row["needs_review"]:
            review.append(record)
        if (row["suggest_status"] or NO_SUGGEST) == HAS_SUGGEST:
            main.append(record)
        else:
            archive.append(record)

    main.sort(key=lambda r: (-len(r[2]), r[0]))
    archive.sort(key=lambda r: r[0])
    review.sort(key=lambda r: r[7])

    return [
        Sheet(tabs.get("main", "خوراک محتوایی"), HEADER, main),
        Sheet(tabs.get("archive", "آرشیو آینده"), HEADER, archive),
        Sheet(tabs.get("review", "بازبینی دستی"), HEADER, review),
    ]


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    push_to_sheets: bool = True,
    verbose: bool = True,
) -> ExportStats:
    sheets = build_sheets(conn, run_id, config)
    stats = ExportStats(
        main_rows=len(sheets[0].rows),
        archive_rows=len(sheets[1].rows),
        review_rows=len(sheets[2].rows),
    )

    xlsx_path = config.get("output.xlsx_path", "content_pipeline/data/output.xlsx")
    if xlsx_path:
        target = str(xlsx_path).replace("{run_id}", run_id)
        stats.local_path = write_xlsx(target, sheets)

    if push_to_sheets:
        writer = SheetsWriter(
            config.get("output.sheet_id", ""), config.get("output.service_account_json", "")
        )
        if writer.available:
            # تنها نوشتن در شیت، به‌صورت batch (رعایت rate limit)
            stats.sheet_url = writer.write(sheets)
        elif verbose:
            print("  گوگل‌شیت تنظیم نشده است؛ فقط خروجی محلی ساخته شد.")

    return stats
