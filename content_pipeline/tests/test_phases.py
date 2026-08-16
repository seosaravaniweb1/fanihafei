"""تست فازهای ۳ تا ۶ با ارائه‌دهنده‌های ساختگی (بدون شبکه و بدون هزینه)."""

from __future__ import annotations

import pytest

from content_pipeline.core import db
from content_pipeline.core.config import load_config
from content_pipeline.phases import p3_suggest, p5_image, p6_export


@pytest.fixture
def env(tmp_path):
    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["output"]["xlsx_path"] = str(tmp_path / "out.xlsx")
    yield conn, run_id, config
    conn.close()


def add_product(conn, run_id, title):
    with db.transaction(conn):
        return db.insert_canonical(conn, run_id, title, [], 0.95, False)


class FakeSerp:
    """جایگزین SerpClient با پاسخ ثابت."""

    def __init__(self, suggestions=None, images=None):
        self.suggestions = suggestions or {}
        self.images = images or []
        self.calls = 0

    def autocomplete(self, query):
        self.calls += 1
        return self.suggestions.get(query, [])

    def image_search(self, query, num=10, square_only=True):
        self.calls += 1
        return self.images


# ---------------------------------------------------------------------------
# فاز ۳
# ---------------------------------------------------------------------------


def test_suggest_marks_empty_results_as_no_suggest(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان الف")
    stats = p3_suggest.run(conn, run_id, config, FakeSerp({}), verbose=False)

    row = db.canonical_products(conn, run_id)[0]
    assert row["suggest_status"] == p3_suggest.NO_SUGGEST
    assert stats.without_suggest == 1
    # رکورد حذف نمی‌شود
    assert len(db.canonical_products(conn, run_id)) == 1


def test_keywords_are_stored_as_rows_not_a_blob(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت")
    serp = FakeSerp({"رمان تاوان خیانت": ["رمان تاوان خیانت pdf", "رمان تاوان خیانت آوا"]})
    p3_suggest.run(conn, run_id, config, serp, verbose=False)

    canonical_id = db.canonical_products(conn, run_id)[0]["id"]
    rows = db.keywords_for(conn, canonical_id)
    assert len(rows) == 2
    assert [r["position"] for r in rows] == [1, 2]


def test_lsi_conflict_goes_to_the_closest_product(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت آوا")
    add_product(conn, run_id, "جزوه ریاضی گسسته")
    serp = FakeSerp(
        {
            "رمان تاوان خیانت آوا": ["رمان تاوان خیانت آوا pdf"],
            "جزوه ریاضی گسسته": ["رمان تاوان خیانت آوا pdf"],
        }
    )
    stats = p3_suggest.run(conn, run_id, config, serp, verbose=False)

    assert stats.conflicts_resolved == 1
    owners = {
        row["canonical_id"]
        for row in db.all_keywords(conn, run_id)
        if row["keyword"] == "رمان تاوان خیانت آوا pdf"
    }
    assert len(owners) == 1
    winner = db.canonical_products(conn, run_id)
    winner_id = next(r["id"] for r in winner if "تاوان" in r["canonical_title"])
    assert owners == {winner_id}


def test_product_losing_all_keywords_becomes_no_suggest(env):
    conn, run_id, config = env
    add_product(conn, run_id, "رمان تاوان خیانت آوا")
    add_product(conn, run_id, "جزوه ریاضی گسسته")
    serp = FakeSerp(
        {
            "رمان تاوان خیانت آوا": ["رمان تاوان خیانت آوا pdf"],
            "جزوه ریاضی گسسته": ["رمان تاوان خیانت آوا pdf"],
        }
    )
    p3_suggest.run(conn, run_id, config, serp, verbose=False)
    loser = next(
        r for r in db.canonical_products(conn, run_id) if "ریاضی" in r["canonical_title"]
    )
    assert loser["suggest_status"] == p3_suggest.NO_SUGGEST


# ---------------------------------------------------------------------------
# فاز ۵
# ---------------------------------------------------------------------------


def _png(width: int, height: int) -> bytes:
    import struct

    return b"\x89PNG\r\n\x1a\n" + b"\x00" * 8 + struct.pack(">II", width, height) + b"\x00" * 16


def test_image_size_reads_png_header():
    assert p5_image.image_size(_png(600, 600))[:2] == (600, 600)


def test_dimension_rules_follow_the_spec():
    assert p5_image.acceptable_dimensions(600, 600) is True
    assert p5_image.acceptable_dimensions(600, 620) is True  # نسبت ۰.۹۷
    assert p5_image.acceptable_dimensions(600, 900) is False  # نسبت خارج از بازه
    assert p5_image.acceptable_dimensions(400, 400) is False  # کمتر از ۵۰۰×۵۰۰


class FakeFetcher:
    def __init__(self, payloads):
        self.payloads = payloads

    def fetch_bytes(self, url):
        return 200, self.payloads.get(url, b"")


class FakeVision:
    available = True

    def __init__(self, watermarked):
        self.watermarked = watermarked
        self.checked = []

    def has_watermark(self, data, media_type="image/jpeg"):
        self.checked.append(len(data))
        return len(data) in self.watermarked


def test_first_clean_image_wins(env):
    from content_pipeline.clients.serp_client import ImageResult

    conn, run_id, config = env
    add_product(conn, run_id, "رمان الف")
    dirty = _png(600, 600) + b"x"
    clean = _png(700, 700)
    serp = FakeSerp(
        images=[
            ImageResult(1, "https://i/dirty.png"),
            ImageResult(2, "https://i/clean.png"),
        ]
    )
    fetcher = FakeFetcher({"https://i/dirty.png": dirty, "https://i/clean.png": clean})
    vision = FakeVision(watermarked={len(dirty)})

    stats = p5_image.run(conn, run_id, config, serp, fetcher, llm_client=vision, verbose=False)
    assert stats.accepted == 1
    assert db.canonical_products(conn, run_id)[0]["image_url"] == "https://i/clean.png"
    assert stats.rejected_watermark == 1


def test_no_clean_image_leaves_field_empty(env):
    from content_pipeline.clients.serp_client import ImageResult

    conn, run_id, config = env
    add_product(conn, run_id, "رمان الف")
    small = _png(200, 200)
    serp = FakeSerp(images=[ImageResult(1, "https://i/small.png")])
    fetcher = FakeFetcher({"https://i/small.png": small})
    vision = FakeVision(watermarked=set())

    stats = p5_image.run(conn, run_id, config, serp, fetcher, llm_client=vision, verbose=False)
    assert stats.accepted == 0
    assert stats.rejected_size == 1
    assert db.canonical_products(conn, run_id)[0]["image_url"] is None


# ---------------------------------------------------------------------------
# خروجی
# ---------------------------------------------------------------------------


def test_export_splits_tabs_by_suggest_status_and_review(env):
    conn, run_id, config = env
    with db.transaction(conn):
        a = db.insert_canonical(conn, run_id, "محصول الف", [], 0.95, False)
        b = db.insert_canonical(conn, run_id, "محصول ب", [], 0.60, True)
        db.update_canonical(conn, a, suggest_status=p3_suggest.HAS_SUGGEST)
        db.update_canonical(conn, b, suggest_status=p3_suggest.NO_SUGGEST)
        db.insert_keyword(conn, a, "محصول الف pdf", 1, 0.9)

    sheets = p6_export.build_sheets(conn, run_id, config)
    assert [len(s.rows) for s in sheets] == [1, 1, 1]
    assert sheets[0].header == p6_export.HEADER
    assert "محصول الف pdf" in sheets[0].rows[0][2]


def test_export_writes_a_local_file(env):
    conn, run_id, config = env
    with db.transaction(conn):
        canonical_id = db.insert_canonical(conn, run_id, "محصول الف", [], 1.0, False)
        db.update_canonical(conn, canonical_id, suggest_status=p3_suggest.HAS_SUGGEST)
    stats = p6_export.run(conn, run_id, config, push_to_sheets=False, verbose=False)
    assert stats.local_path
    assert stats.main_rows == 1


# ---------------------------------------------------------------------------
# فاز ۴
# ---------------------------------------------------------------------------


class FakeSearchSerp:
    def __init__(self, results):
        self.results = results

    def search(self, query, num=10):
        return self.results


class PageFetcher:
    def __init__(self, pages):
        self.pages = pages

    def fetch(self, url, allow_browser=True, force_browser=False):
        from content_pipeline.core.http import FetchResult

        body = self.pages.get(url)
        if body is None:
            return FetchResult(url, 404, "", via="fake")
        return FetchResult(url, 200, body, url, "fake")


def _article(words: int, headings: int = 0) -> str:
    return (
        "<article>"
        + "<h2>عنوان</h2>" * headings
        + ("کلمه " * words)
        + "</article>"
    )


def test_benchmark_picks_the_richest_page_without_llm(env):
    from content_pipeline.clients.serp_client import OrganicResult

    conn, run_id, config = env
    canonical_id = add_product(conn, run_id, "رمان الف")
    with db.transaction(conn):
        db.update_canonical(conn, canonical_id, suggest_status=p3_suggest.HAS_SUGGEST)

    serp = FakeSearchSerp(
        [
            OrganicResult(1, "کم‌محتوا", "https://a.ir/thin"),
            OrganicResult(2, "پرمحتوا", "https://b.ir/rich"),
        ]
    )
    fetcher = PageFetcher(
        {"https://a.ir/thin": _article(120), "https://b.ir/rich": _article(1500, headings=6)}
    )
    from content_pipeline.phases import p4_benchmark

    stats = p4_benchmark.run(conn, run_id, config, serp, fetcher, llm_client=None, verbose=False)
    row = db.canonical_products(conn, run_id)[0]
    assert stats.winners == 1
    assert row["benchmark_url"] == "https://b.ir/rich"
    assert row["benchmark_score"] > 0


def test_benchmark_only_scores_three_candidates_with_llm(env):
    from content_pipeline.clients.serp_client import OrganicResult
    from content_pipeline.phases import p4_benchmark

    conn, run_id, config = env
    canonical_id = add_product(conn, run_id, "رمان الف")
    with db.transaction(conn):
        db.update_canonical(conn, canonical_id, suggest_status=p3_suggest.HAS_SUGGEST)

    urls = [f"https://s{i}.ir/x" for i in range(6)]
    serp = FakeSearchSerp([OrganicResult(i + 1, f"t{i}", u) for i, u in enumerate(urls)])
    fetcher = PageFetcher({u: _article(300 + index * 100, 2) for index, u in enumerate(urls)})

    seen = {}

    class FakeJudge:
        available = True

        def score_candidates(self, title, candidates):
            seen["count"] = len(candidates)
            best = candidates[-1]["candidate_id"]
            return {best: {"score": 9.5, "reason": "بهترین"}}

    p4_benchmark.run(conn, run_id, config, serp, fetcher, llm_client=FakeJudge(), verbose=False)
    assert seen["count"] == 3  # فقط سه کاندیدای برتر به LLM می‌روند
    assert db.canonical_products(conn, run_id)[0]["benchmark_score"] == 9.5


def test_benchmark_skips_products_without_suggest(env):
    from content_pipeline.phases import p4_benchmark

    conn, run_id, config = env
    canonical_id = add_product(conn, run_id, "رمان الف")
    with db.transaction(conn):
        db.update_canonical(conn, canonical_id, suggest_status=p3_suggest.NO_SUGGEST)
    assert p4_benchmark.targets(conn, run_id) == []
