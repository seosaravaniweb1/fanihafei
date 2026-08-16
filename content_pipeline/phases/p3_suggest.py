"""فاز ۳ — گوگل ساجست و تفکیک دو گروه.

* برای هر ``canonical_product`` یک کوئری ساجست (با تأخیر و سقف نشست)
* نتایج در جدول ``lsi_keywords`` با ``canonical_id`` — نه رشته‌ی درهم در یک سلول
* جلوگیری از تداخل LSI: یک کلمه فقط برای نزدیک‌ترین محصول می‌ماند
* نتیجه‌ی خالی → ``no_suggest`` (رکورد حذف نمی‌شود؛ به شیت B می‌رود)

قواعد ایمنی endpoint غیررسمی در :mod:`core.suggest` اعمال شده‌اند.
"""

from __future__ import annotations

import sqlite3
from collections import defaultdict
from dataclasses import dataclass

from ..core import db, normalizer, similarity
from ..core.config import Config
from ..core.suggest import SessionLimitReached, SuggestBlocked, SuggestClient

HAS_SUGGEST = "has_suggest"
NO_SUGGEST = "no_suggest"


@dataclass
class SuggestStats:
    products: int = 0
    queried: int = 0
    from_cache: int = 0
    with_suggest: int = 0
    without_suggest: int = 0
    keywords_stored: int = 0
    conflicts_resolved: int = 0
    stopped_early: str = ""

    def render(self) -> str:
        line = (
            f"فاز ۳ — محصولات: {self.products}، کوئری زده‌شده: {self.queried}، "
            f"از کش: {self.from_cache}، دارای ساجست: {self.with_suggest}، "
            f"بدون ساجست: {self.without_suggest}، کلمات: {self.keywords_stored}، "
            f"تداخل حل‌شده: {self.conflicts_resolved}"
        )
        if self.stopped_early:
            line += f"\n  ⚠ توقف زودهنگام: {self.stopped_early}"
        return line


def pending_products(conn: sqlite3.Connection, run_id: str) -> list[sqlite3.Row]:
    """محصولاتی که هنوز وضعیت ساجست ندارند — پایه‌ی resume."""
    return conn.execute(
        "SELECT * FROM canonical_products WHERE run_id=? AND suggest_status IS NULL ORDER BY id",
        (run_id,),
    ).fetchall()


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    client: SuggestClient,
    verbose: bool = True,
) -> SuggestStats:
    norm_config = normalizer.config_from_mapping(config.normalizer)
    stats = SuggestStats()
    products = pending_products(conn, run_id)
    stats.products = len(products)

    for row in products:
        canonical_id = int(row["id"])
        title = row["canonical_title"] or ""
        before = client.queries_sent
        try:
            suggestions = client.suggest(title)
        except (SuggestBlocked, SessionLimitReached) as exc:
            # داده‌ی تا اینجا در دیتابیس محفوظ است؛ با --resume-from 3 ادامه می‌دهید.
            stats.stopped_early = str(exc)
            if verbose:
                print(f"  {exc}")
            break

        if client.queries_sent == before:
            stats.from_cache += 1
        else:
            stats.queried += 1

        with db.transaction(conn):
            for position, keyword in enumerate(suggestions, start=1):
                db.insert_keyword(conn, canonical_id, keyword.strip(), position)
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

    stats.conflicts_resolved = resolve_conflicts(conn, run_id, norm_config)
    _refresh_status(conn, run_id)
    return stats


def resolve_conflicts(
    conn: sqlite3.Connection, run_id: str, norm_config: normalizer.NormalizerConfig
) -> int:
    """اگر یک LSI به دو محصول نسبت داده شد، فقط برای نزدیک‌ترین محصول می‌ماند."""
    titles = {
        int(row["id"]): normalizer.normalize(row["canonical_title"] or "", norm_config)
        for row in db.canonical_products(conn, run_id)
    }
    owners: dict[str, list[tuple[int, int]]] = defaultdict(list)
    for row in db.all_keywords(conn, run_id):
        owners[row["keyword"]].append((int(row["canonical_id"]), int(row["position"])))

    removed = 0
    with db.transaction(conn):
        for keyword, claims in owners.items():
            if len(claims) < 2:
                continue
            normalized_keyword = normalizer.normalize(keyword, norm_config)

            def score(claim: tuple[int, int]) -> tuple[float, int, int]:
                canonical_id, position = claim
                text_score = similarity.token_set_ratio(
                    normalized_keyword, titles.get(canonical_id, "")
                )
                # در تساوی: جایگاه بهتر (کوچک‌تر)، سپس id کوچک‌تر
                return (text_score, -position, -canonical_id)

            winner = max(claims, key=score)
            for canonical_id, _ in claims:
                if canonical_id != winner[0]:
                    db.delete_keyword(conn, canonical_id, keyword)
                    removed += 1
    return removed


def _refresh_status(conn: sqlite3.Connection, run_id: str) -> None:
    """پس از حذف تداخل‌ها ممکن است محصولی بدون هیچ کلمه‌ای بماند."""
    with db.transaction(conn):
        for row in db.canonical_products(conn, run_id):
            if row["suggest_status"] is None:
                continue
            count = conn.execute(
                "SELECT COUNT(*) AS c FROM lsi_keywords WHERE canonical_id=?", (row["id"],)
            ).fetchone()["c"]
            db.update_canonical(
                conn, int(row["id"]), suggest_status=HAS_SUGGEST if count else NO_SUGGEST
            )
