"""تست فاز ۳ و خروجی، با کلاینت ساختگی (بدون شبکه)."""

from __future__ import annotations

import pytest

from content_pipeline.core import db
from content_pipeline.core.config import load_config
from content_pipeline.core.suggest import SuggestBlocked
from content_pipeline.output import exporter
from content_pipeline.phases import p3_suggest


@pytest.fixture
def env(tmp_path):
    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["database"]["path"] = str(tmp_path / "runs.db")
    config.raw["output"]["xlsx_path"] = str(tmp_path / "out.xlsx")
    yield conn, run_id, config
    conn.close()


def add_product(conn, run_id, title, needs_review=False, confidence=0.95):
    with db.transaction(conn):
        return db.insert_canonical(conn, run_id, title, [], confidence, needs_review)


class FakeSuggest:
    """جایگزین SuggestClient با پاسخ ثابت."""

    def __init__(self, suggestions=None, blow_up_after=None):
        self.suggestions = suggestions or {}
        self.blow_up_after = blow_up_after
        self.queries_sent = 0

        class _Config:
            max_per_session = 300
            delay_min = 0
            delay_max = 0

        self.config = _Config()

    def suggest(self, query):
        if self.blow_up_after is not None and self.queries_sent >= self.blow_up_after:
            raise SuggestBlocked("سه پاسخ خالی پشت‌سرهم")
        self.queries_sent += 1
        return self.suggestions.get(query, [])


# ---------------------------------------------------------------------------
# فاز ۳
# ---------------------------------------------------------------------------


def test_empty_result_becomes_no_suggest(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان الف")
    stats = p3_suggest.run(conn, run_id, config, FakeSuggest(), verbose=False)

    row = db.canonical_products(conn, run_id)[0]
    assert row["suggest_status"] == p3_suggest.NO_SUGGEST
    assert stats.without_suggest == 1
    assert len(db.canonical_products(conn, run_id)) == 1  # رکورد حذف نمی‌شود


def test_keywords_are_stored_as_rows_not_a_blob(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت")
    client = FakeSuggest({"رمان تاوان خیانت": ["رمان تاوان خیانت pdf", "رمان تاوان خیانت آوا"]})
    p3_suggest.run(conn, run_id, config, client, verbose=False)

    canonical_id = db.canonical_products(conn, run_id)[0]["id"]
    rows = db.keywords_for(conn, canonical_id)
    assert len(rows) == 2
    assert [r["position"] for r in rows] == [1, 2]


def test_lsi_conflict_goes_to_the_closest_product(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت آوا")
    add_product(conn, run_id, "جزوه ریاضی گسسته")
    client = FakeSuggest(
        {
            "رمان تاوان خیانت آوا": ["رمان تاوان خیانت آوا pdf"],
            "جزوه ریاضی گسسته": ["رمان تاوان خیانت آوا pdf"],
        }
    )
    stats = p3_suggest.run(conn, run_id, config, client, verbose=False)

    assert stats.conflicts_resolved == 1
    owners = {
        row["canonical_id"]
        for row in db.all_keywords(conn, run_id)
        if row["keyword"] == "رمان تاوان خیانت آوا pdf"
    }
    winner_id = next(
        r["id"] for r in db.canonical_products(conn, run_id) if "تاوان" in r["canonical_title"]
    )
    assert owners == {winner_id}


def test_product_losing_all_keywords_becomes_no_suggest(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت آوا")
    add_product(conn, run_id, "جزوه ریاضی گسسته")
    client = FakeSuggest(
        {
            "رمان تاوان خیانت آوا": ["رمان تاوان خیانت آوا pdf"],
            "جزوه ریاضی گسسته": ["رمان تاوان خیانت آوا pdf"],
        }
    )
    p3_suggest.run(conn, run_id, config, client, verbose=False)
    loser = next(
        r for r in db.canonical_products(conn, run_id) if "ریاضی" in r["canonical_title"]
    )
    assert loser["suggest_status"] == p3_suggest.NO_SUGGEST


def test_blocked_run_stops_but_keeps_what_it_had(env):
    conn, run_id, config = env
    for index in range(4):
        add_product(conn, run_id, f"محصول {index}")
    client = FakeSuggest({"محصول 0": ["الف"], "محصول 1": ["ب"]}, blow_up_after=2)

    stats = p3_suggest.run(conn, run_id, config, client, verbose=False)
    assert stats.stopped_early
    assert stats.with_suggest == 2
    # دو محصول باقی‌مانده هنوز در صف‌اند و با --resume-from 3 ادامه می‌یابند
    assert len(p3_suggest.pending_products(conn, run_id)) == 2


def test_resume_only_processes_pending_products(env):
    conn, run_id, config = env
    add_product(conn, run_id, "محصول الف")
    add_product(conn, run_id, "محصول ب")
    first = FakeSuggest({"محصول الف": ["الف ۱"], "محصول ب": ["ب ۱"]})
    p3_suggest.run(conn, run_id, config, first, verbose=False)

    second = FakeSuggest({"محصول الف": ["الف ۱"], "محصول ب": ["ب ۱"]})
    stats = p3_suggest.run(conn, run_id, config, second, verbose=False)
    assert stats.products == 0
    assert second.queries_sent == 0


# ---------------------------------------------------------------------------
# خروجی
# ---------------------------------------------------------------------------


def _prepare_export(conn, run_id):
    with db.transaction(conn):
        db.insert_raw_product(
            conn, run_id, "a.ir", "https://a.ir/1", "محصول الف", "محصول الف", 0.9, True
        )
        db.insert_raw_product(
            conn, run_id, "b.ir", "https://b.ir/x", "عنوان مرزی", "عنوان مرزی", 0.55, None
        )
        ready = db.insert_canonical(conn, run_id, "محصول الف", [], 0.95, False)
        archived = db.insert_canonical(conn, run_id, "محصول ب", [], 0.95, False)
        flagged = db.insert_canonical(conn, run_id, "محصول ج", [], 0.50, True)
        db.update_canonical(conn, ready, suggest_status=p3_suggest.HAS_SUGGEST)
        db.update_canonical(conn, archived, suggest_status=p3_suggest.NO_SUGGEST)
        db.update_canonical(conn, flagged, suggest_status=p3_suggest.NO_SUGGEST)
        db.insert_keyword(conn, ready, "محصول الف pdf", 1)
        db.insert_keyword(conn, ready, "محصول الف کامل", 2)
        raw_id = conn.execute(
            "SELECT id FROM raw_products WHERE source_url='https://a.ir/1'"
        ).fetchone()["id"]
        db.map_raw_to_canonical(conn, int(raw_id), ready)
    return ready


def test_three_sheets_are_produced(env):
    conn, run_id, config = env
    _prepare_export(conn, run_id)
    ready, archive, review = exporter.build_sheets(conn, run_id, config)

    assert ready.title == "آماده تولید محتوا"
    assert archive.title == "آرشیو آینده"
    assert review.title == "نیاز به بازبینی دستی"
    assert ready.header == exporter.READY_HEADER
    assert archive.header == exporter.ARCHIVE_HEADER


def test_ready_sheet_carries_keywords_and_counts(env):
    conn, run_id, config = env
    _prepare_export(conn, run_id)
    ready = exporter.build_sheets(conn, run_id, config)[0]

    row = ready.rows[0]
    assert row[0] == "محصول الف"
    assert row[1] == "محصول الف pdf | محصول الف کامل"
    assert row[2] == 2  # lsi_count
    assert row[3] == "a.ir"  # source_domains
    assert row[5] == 1  # merge_count


def test_archive_sheet_has_no_keyword_column(env):
    conn, run_id, config = env
    _prepare_export(conn, run_id)
    archive = exporter.build_sheets(conn, run_id, config)[1]
    assert "lsi_keywords" not in archive.header
    assert len(archive.rows) == 2  # محصول ب و ج


def test_review_sheet_mixes_merge_doubts_and_borderline_titles(env):
    conn, run_id, config = env
    _prepare_export(conn, run_id)
    review = exporter.build_sheets(conn, run_id, config)[2]

    kinds = {row[0] for row in review.rows}
    assert kinds == {"ادغام مبهم", "ارتباط مرزی با موضوع"}
    titles = {row[1] for row in review.rows}
    assert {"محصول ج", "عنوان مرزی"} <= titles


def test_export_writes_a_local_file(env):
    conn, run_id, config = env
    _prepare_export(conn, run_id)
    stats = exporter.run(conn, run_id, config, push_to_sheets=False, verbose=False)
    assert stats.local_path
    assert stats.ready_rows == 1
    assert stats.archive_rows == 2
    assert stats.review_rows == 2
