"""تست فاز ۲ — بلاک‌بندی، توکن تمایزدهنده و قانون ادغام.

سناریوی مرجع داکیومنت («آوا / مهسا / ناشناس / ۴ برادر») اینجا قفل شده است.
تا وقتی این تست‌ها سبز نشوند، سراغ فاز بعد نروید.
"""

from __future__ import annotations

import pytest

from content_pipeline.core import db, normalizer
from content_pipeline.core.config import load_config
from content_pipeline.core.entities import EntityExtractor
from content_pipeline.phases import p2_resolve
from content_pipeline.phases.p2_resolve import (
    Candidate,
    SafeClusters,
    candidate_pairs,
    classify_pair,
    pick_canonical_title,
    resolve,
)


@pytest.fixture(scope="module")
def extractor() -> EntityExtractor:
    return EntityExtractor()


def make(extractor: EntityExtractor, raw_id: int, title: str) -> Candidate:
    return Candidate(
        raw_id=raw_id,
        raw_title=title,
        normalized_title=normalizer.normalize(title),
        entity=extractor.extract(title),
    )


GOLDEN = [
    "رمان تاوان خیانت آوا",
    "دانلود رمان تاوان خیانت آوا pdf",
    "رمان تاوان خیانت مهسا",
    "رمان تاوان خیانت از ناشناس",
    "رمان تاوان خیانت ۴ برادر",
]


@pytest.fixture
def golden(extractor):
    return [make(extractor, i, t) for i, t in enumerate(GOLDEN)]


# ---------------------------------------------------------------------------
# مرحله B — توکن تمایزدهنده (جدول داکیومنت)
# ---------------------------------------------------------------------------


@pytest.mark.parametrize(
    "title,expected",
    [
        ("رمان تاوان خیانت آوا", ["آوا"]),
        ("رمان تاوان خیانت مهسا", ["مهسا"]),
        ("رمان تاوان خیانت از ناشناس", []),
        ("رمان تاوان خیانت ۴ برادر", ["۴ برادر"]),
    ],
)
def test_entity_tokens_match_the_spec_table(extractor, title, expected):
    assert extractor.extract(title).tokens == expected


# ---------------------------------------------------------------------------
# مرحله C — قانون ادغام
# ---------------------------------------------------------------------------


def test_same_entity_tokens_merge(golden):
    verdict, score, confidence, _ = classify_pair(golden[0], golden[1])
    assert verdict == "merge"
    assert score >= 0.80
    assert confidence >= 0.9


def test_different_non_empty_entity_tokens_never_merge(golden):
    """خط قرمز: آوا در برابر مهسا."""
    verdict, _, _, reason = classify_pair(golden[0], golden[2])
    assert verdict == "no_merge"
    assert "خط قرمز" in reason


def test_number_entity_is_also_a_red_line(golden):
    assert classify_pair(golden[0], golden[4])[0] == "no_merge"


def test_one_empty_side_is_flagged_for_review_not_merged(golden):
    """طبق نسخه ۲: بدون LLM، این حالت مستقیم به بازبینی دستی می‌رود."""
    verdict, _, _, reason = classify_pair(golden[0], golden[3])
    assert verdict == "review"
    assert "بازبینی" in reason


def test_low_similarity_never_merges(extractor):
    a = make(extractor, 1, "جزوه ریاضی گسسته دانشگاهی")
    b = make(extractor, 2, "نمونه قرارداد اجاره مغازه")
    assert classify_pair(a, b)[0] == "no_merge"


def test_similarity_is_computed_on_normalized_title(extractor):
    """کلمات تبلیغاتی نباید روی تصمیم اثر بگذارند."""
    plain = make(extractor, 1, "رمان تاوان خیانت آوا")
    noisy = make(extractor, 2, "دانلود رایگان رمان تاوان خیانت آوا pdf کامل ۱۴۰۴")
    verdict, score, _, _ = classify_pair(plain, noisy)
    assert verdict == "merge"
    assert score == pytest.approx(1.0)


# ---------------------------------------------------------------------------
# خوشه‌بندی کامل
# ---------------------------------------------------------------------------


def test_golden_scenario_produces_four_products(golden):
    groups, _, review, stats = resolve(golden)
    assert len(groups) == 4, [[golden[i].raw_title for i in g] for g in groups]

    merged = [g for g in groups if len(g) > 1]
    assert len(merged) == 1
    assert set(merged[0]) == {0, 1}  # فقط دو نسخه‌ی «آوا» ادغام شدند
    assert stats.red_lines >= 1
    assert stats.merged_pairs == 1


def test_ambiguous_pair_flags_both_sides_for_review(golden):
    _, _, review, stats = resolve(golden)
    assert stats.review_pairs >= 1
    assert 3 in review  # «از ناشناس»


def test_no_llm_and_no_network_is_required(golden, monkeypatch):
    """فاز ۲ باید کاملاً آفلاین و رایگان باشد."""
    import urllib.request

    def explode(*args, **kwargs):  # pragma: no cover - نباید صدا زده شود
        raise AssertionError("فاز ۲ نباید هیچ درخواست شبکه‌ای بزند")

    monkeypatch.setattr(urllib.request, "urlopen", explode)
    groups, _, _, _ = resolve(golden)
    assert len(groups) == 4


def test_red_line_survives_transitive_merge(extractor):
    """A~B و B~C مجازند، ولی A و C خط قرمز دارند: نباید یک خوشه شوند."""
    items = [
        make(extractor, 1, "رمان تاوان خیانت آوا"),
        make(extractor, 2, "رمان تاوان خیانت"),
        make(extractor, 3, "رمان تاوان خیانت مهسا"),
    ]
    groups, _, _, _ = resolve(items)
    for group in groups:
        assert not ({0, 2} <= set(group))


# ---------------------------------------------------------------------------
# مرحله A — بلاک‌بندی
# ---------------------------------------------------------------------------


def test_blocking_reduces_pair_count(extractor):
    items = [make(extractor, i, f"جزوه ریاضی فصل {i}") for i in range(20)]
    items += [make(extractor, 100 + i, f"نمونه قرارداد اجاره نوع {i}") for i in range(20)]
    pairs = candidate_pairs(items)
    total = len(items) * (len(items) - 1) // 2
    assert len(pairs) < total
    assert all(not (a < 20 <= b) for a, b in pairs)


def test_blocking_keeps_true_duplicates_together(golden):
    assert (0, 1) in candidate_pairs(golden)


# ---------------------------------------------------------------------------
# عنوان نهایی و خوشه‌ی امن
# ---------------------------------------------------------------------------


def test_canonical_title_is_the_most_complete_one(extractor):
    members = [
        make(extractor, 1, "رمان تاوان خیانت"),
        make(extractor, 2, "دانلود رمان تاوان خیانت آوا pdf"),
    ]
    title = pick_canonical_title(members, normalizer.DEFAULT_CONFIG)
    assert "آوا" in title


def test_safe_clusters_refuses_forbidden_union():
    clusters = SafeClusters(3)
    clusters.forbid(0, 2)
    assert clusters.union(0, 1) is True
    assert clusters.union(1, 2) is False
    assert sorted(len(c) for c in clusters.clusters()) == [1, 2]


# ---------------------------------------------------------------------------
# اجرای فاز روی دیتابیس
# ---------------------------------------------------------------------------


def _seed(conn, run_id):
    with db.transaction(conn):
        for index, title in enumerate(GOLDEN):
            db.insert_raw_product(
                conn,
                run_id=run_id,
                source_domain=f"site{index % 2}.ir",
                source_url=f"https://site{index % 2}.ir/p/{index}",
                raw_title=title,
                normalized_title=normalizer.normalize(title),
                topic_score=0.9,
                is_relevant=True,
            )


def test_phase2_writes_canonical_products_and_mapping(tmp_path):
    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["database"]["path"] = str(tmp_path / "runs.db")
    _seed(conn, run_id)

    stats = p2_resolve.run(conn, run_id, config, verbose=False)
    products = db.canonical_products(conn, run_id)

    assert len(products) == 4 == stats.canonical_count
    titles = [p["canonical_title"] for p in products]
    assert any("آوا" in t for t in titles)
    assert any("مهسا" in t for t in titles)

    ava = next(p for p in products if "آوا" in p["canonical_title"])
    assert len(db.source_urls_for(conn, int(ava["id"]))) == 2
    assert sorted(db.source_domains_for(conn, int(ava["id"]))) == ["site0.ir", "site1.ir"]
    assert any(p["needs_review"] for p in products)
    conn.close()


def test_phase2_rerun_does_not_duplicate(tmp_path):
    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["database"]["path"] = str(tmp_path / "runs.db")
    _seed(conn, run_id)

    p2_resolve.run(conn, run_id, config, verbose=False)
    p2_resolve.run(conn, run_id, config, verbose=False)
    assert len(db.canonical_products(conn, run_id)) == 4
    conn.close()


# ---------------------------------------------------------------------------
# رگرسیون: نام مدرس/نویسنده نباید گم شود
# ---------------------------------------------------------------------------


def test_two_professors_are_never_merged(extractor):
    """اگر «استاد» نشانگر نام نباشد، هر دو عنوان توکن یکسان می‌گیرند و
    جزوه‌ی دو استاد متفاوت اشتباهی یکی می‌شود — دقیقاً همان merge غیرقابل بازگشت."""
    a = make(extractor, 1, "دانلود جزوه ریاضی عمومی ۱ استاد کریمی")
    b = make(extractor, 2, "جزوه ریاضی عمومی ۱ استاد رضایی")
    assert extractor.extract(a.raw_title).tokens == ["۱", "کریمی"]
    assert extractor.extract(b.raw_title).tokens == ["۱", "رضایی"]
    verdict, _, _, reason = classify_pair(a, b)
    assert verdict == "no_merge"
    assert "خط قرمز" in reason


@pytest.mark.parametrize(
    "marker", ["استاد", "دکتر", "مهندس", "نویسنده", "از", "نوشته"]
)
def test_person_markers_capture_the_following_name(extractor, marker):
    tokens = extractor.extract(f"جزوه شیمی آلی {marker} محمدی").tokens
    assert "محمدی" in tokens


def test_number_does_not_swallow_a_person_marker(extractor):
    assert extractor.extract("جزوه ریاضی ۲ استاد کریمی").tokens == ["۲", "کریمی"]
    # ولی «۴ برادر» طبق جدول داکیومنت یک واحد می‌ماند
    assert extractor.extract("رمان تاوان خیانت ۴ برادر").tokens == ["۴ برادر"]


def test_same_professor_still_merges(extractor):
    a = make(extractor, 1, "دانلود جزوه ریاضی عمومی ۱ استاد کریمی")
    b = make(extractor, 2, "جزوه ریاضی عمومی ۱ استاد کریمی pdf رایگان")
    assert classify_pair(a, b)[0] == "merge"


# ---------------------------------------------------------------------------
# شیر اطمینان: هر دو طرف بدون توکن تمایزدهنده
# ---------------------------------------------------------------------------


def test_both_empty_tokens_merge_by_default(extractor):
    """رفتار پیش‌فرض = دقیقاً قانون داکیومنت (توکن‌های یکسان → ادغام)."""
    a = make(extractor, 1, "رمان شب سرد")
    b = make(extractor, 2, "رمان شب گرم")
    assert a.entity.is_empty and b.entity.is_empty
    assert classify_pair(a, b)[0] == "merge"


def test_empty_token_threshold_sends_doubtful_pairs_to_review(extractor):
    a = make(extractor, 1, "رمان شب سرد")
    b = make(extractor, 2, "رمان شب گرم")
    verdict, score, _, _ = classify_pair(a, b, empty_token_threshold=0.95)
    assert score < 0.95
    assert verdict == "review"


def test_empty_token_threshold_still_merges_identical_titles(extractor):
    a = make(extractor, 1, "دانلود رمان شب سرد pdf")
    b = make(extractor, 2, "رمان شب سرد رایگان")
    assert classify_pair(a, b, empty_token_threshold=0.95)[0] == "merge"


def test_canonical_title_prefers_the_clean_variant_on_a_tie(extractor):
    """در تساویِ توکن معنادار، عنوان بدون کلمات تبلیغاتی انتخاب می‌شود."""
    members = [
        make(extractor, 1, "جزوه ریاضی عمومی ۱ استاد کریمی pdf رایگان"),
        make(extractor, 2, "دانلود جزوه ریاضی عمومی ۱ استاد کریمی"),
        make(extractor, 3, "جزوه ریاضی عمومی ۱ استاد کریمی"),
    ]
    assert pick_canonical_title(members, normalizer.DEFAULT_CONFIG) == (
        "جزوه ریاضی عمومی ۱ استاد کریمی"
    )
