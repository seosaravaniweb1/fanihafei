"""تست مرحله A و C فاز ۲ — بلاک‌بندی و قانون ادغام.

سناریوی مرجع داکیومنت («آوا / مهسا / ناشناس / ۴ برادر») اینجا قفل شده است.
تا وقتی این تست‌ها سبز نشوند، فازهای بعدی معنا ندارند.
"""

from __future__ import annotations

import pytest

from content_pipeline.core.entities import EntityExtractor
from content_pipeline.phases.p2_resolve import (
    Candidate,
    SafeClusters,
    candidate_pairs,
    classify_pair,
    pick_canonical_title,
    resolve,
)
from content_pipeline.core import normalizer


@pytest.fixture(scope="module")
def extractor() -> EntityExtractor:
    return EntityExtractor()


def make(extractor: EntityExtractor, raw_id: int, title: str) -> Candidate:
    return Candidate(
        raw_id=raw_id,
        raw_title=title,
        display_title=normalizer.normalize_display(title),
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
# قانون ادغام (مرحله C)
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


def test_one_empty_side_goes_to_llm(golden):
    verdict, _, _, _ = classify_pair(golden[0], golden[3])
    assert verdict == "ask_llm"


def test_low_similarity_never_merges(extractor):
    a = make(extractor, 1, "جزوه ریاضی گسسته دانشگاهی")
    b = make(extractor, 2, "نمونه قرارداد اجاره مغازه")
    assert classify_pair(a, b)[0] == "no_merge"


# ---------------------------------------------------------------------------
# خوشه‌بندی کامل
# ---------------------------------------------------------------------------


def test_golden_scenario_produces_four_products(golden):
    groups, _, review, stats = resolve(golden, llm_client=None, verbose=False)
    assert len(groups) == 4, [[golden[i].raw_title for i in g] for g in groups]

    merged = [g for g in groups if len(g) > 1]
    assert len(merged) == 1
    assert set(merged[0]) == {0, 1}  # فقط دو نسخه‌ی «آوا» ادغام شدند
    assert stats.red_lines >= 1
    # جفت مبهم بدون LLM ادغام نمی‌شود و برای بازبینی علامت می‌خورد
    assert {0, 3} & review


def test_unsure_pairs_are_flagged_not_merged(golden):
    _, _, review, stats = resolve(golden, llm_client=None, verbose=False)
    assert stats.merged_pairs == 1
    assert review, "جفت مبهم باید برای بازبینی دستی علامت بخورد"


def test_llm_merge_is_applied_when_confident(golden):
    class FakeLLM:
        available = True

        def judge_merges(self, pairs):
            return {
                p["pair_id"]: {"decision": "merge", "confidence": 0.9, "reason": "same book"}
                for p in pairs
            }

    groups, confidences, _, stats = resolve(golden, llm_client=FakeLLM(), verbose=False)
    # «از ناشناس» با هر چهار عنوان دیگر جفت مبهم می‌سازد
    assert stats.llm_pairs == 4
    merged = max(groups, key=len)
    assert set(merged) == {0, 1, 3}
    # اطمینان LLM هرگز به سطح «قطعی» نمی‌رسد
    assert min(confidences[i] for i in merged) < 0.95


def test_llm_unsure_blocks_merge(golden):
    class UnsureLLM:
        available = True

        def judge_merges(self, pairs):
            return {
                p["pair_id"]: {"decision": "unsure", "confidence": 0.3, "reason": "نامشخص"}
                for p in pairs
            }

    groups, _, review, stats = resolve(golden, llm_client=UnsureLLM(), verbose=False)
    assert stats.unsure == 4
    assert all(3 not in g or g == [3] for g in groups)
    assert 3 in review


def test_red_line_survives_transitive_merge(extractor):
    """A~B و B~C مجازند، ولی A و C خط قرمز دارند: نباید یک خوشه شوند."""
    items = [
        make(extractor, 1, "رمان تاوان خیانت آوا"),
        make(extractor, 2, "رمان تاوان خیانت"),
        make(extractor, 3, "رمان تاوان خیانت مهسا"),
    ]
    groups, _, _, _ = resolve(items, llm_client=None, verbose=False)
    for group in groups:
        assert not ({0, 2} <= set(group))


# ---------------------------------------------------------------------------
# بلاک‌بندی (مرحله A)
# ---------------------------------------------------------------------------


def test_blocking_reduces_pair_count(extractor):
    items = [make(extractor, i, f"جزوه ریاضی فصل {i}") for i in range(20)]
    items += [make(extractor, 100 + i, f"نمونه قرارداد اجاره نوع {i}") for i in range(20)]
    pairs = candidate_pairs(items)
    total = len(items) * (len(items) - 1) // 2
    assert len(pairs) < total
    # هیچ جفتی بین دو موضوع کاملاً متفاوت ساخته نمی‌شود
    assert all(not (a < 20 <= b) for a, b in pairs)


def test_blocking_keeps_true_duplicates_together(golden):
    assert (0, 1) in candidate_pairs(golden)


# ---------------------------------------------------------------------------
# انتخاب عنوان نهایی
# ---------------------------------------------------------------------------


def test_canonical_title_is_the_most_complete_one(extractor):
    members = [
        make(extractor, 1, "رمان تاوان خیانت"),
        make(extractor, 2, "دانلود رمان تاوان خیانت آوا pdf"),
    ]
    config = normalizer.DEFAULT_CONFIG
    title = pick_canonical_title(members, config)
    assert "آوا" in title


# ---------------------------------------------------------------------------
# ساختار خوشه‌ی امن
# ---------------------------------------------------------------------------


def test_safe_clusters_refuses_forbidden_union():
    clusters = SafeClusters(3)
    clusters.forbid(0, 2)
    assert clusters.union(0, 1) is True
    assert clusters.union(1, 2) is False
    assert sorted(len(c) for c in clusters.clusters()) == [1, 2]


# ---------------------------------------------------------------------------
# اجرای فاز ۲ روی دیتابیس (یکپارچگی و idempotency)
# ---------------------------------------------------------------------------


def test_phase2_writes_canonical_products_and_mapping(tmp_path):
    from content_pipeline.core import db
    from content_pipeline.core.config import load_config
    from content_pipeline.phases import p2_resolve

    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["resolve"]["llm_arbitration"] = False

    with db.transaction(conn):
        for index, title in enumerate(GOLDEN):
            db.insert_raw_product(
                conn,
                run_id=run_id,
                source_domain="a.ir",
                source_url=f"https://a.ir/p/{index}",
                raw_title=title,
                normalized_title=normalizer.normalize(title),
                display_title=normalizer.normalize_display(title),
                topic_score=0.9,
                is_relevant=True,
            )

    stats = p2_resolve.run(conn, run_id, config, llm_client=None, verbose=False)
    products = db.canonical_products(conn, run_id)

    assert len(products) == 4 == stats.canonical_count
    titles = [p["canonical_title"] for p in products]
    assert any("آوا" in t for t in titles)
    assert any("مهسا" in t for t in titles)

    ava = next(p for p in products if "آوا" in p["canonical_title"])
    assert len(db.source_urls_for(conn, int(ava["id"]))) == 2
    assert any(p["needs_review"] for p in products)

    # توکن‌های تمایزدهنده روی رکورد خام هم ذخیره شده‌اند
    stored = conn.execute(
        "SELECT entity_tokens FROM raw_products WHERE raw_title=?", (GOLDEN[0],)
    ).fetchone()["entity_tokens"]
    assert "آوا" in stored
    conn.close()


def test_phase2_rerun_does_not_duplicate(tmp_path):
    from content_pipeline.core import db
    from content_pipeline.core.config import load_config
    from content_pipeline.phases import p2_resolve

    conn = db.connect(tmp_path / "runs.db")
    run_id = db.create_run(conn, "رمان", run_id="r1")
    config = load_config(None)
    config.raw["resolve"]["llm_arbitration"] = False

    with db.transaction(conn):
        for index, title in enumerate(GOLDEN):
            db.insert_raw_product(
                conn, run_id, "a.ir", f"https://a.ir/p/{index}", title,
                normalizer.normalize(title), normalizer.normalize_display(title), 0.9, True,
            )

    p2_resolve.run(conn, run_id, config, llm_client=None, verbose=False)
    p2_resolve.run(conn, run_id, config, llm_client=None, verbose=False)
    assert len(db.canonical_products(conn, run_id)) == 4
    conn.close()
