"""تست‌های فاز ۰ روی نمونه‌های واقعی فارسی — حیاتی‌ترین تست پروژه."""

from __future__ import annotations

import pytest

from content_pipeline.core import normalizer as N


def test_arabic_letters_become_persian():
    assert N.normalize_display("كتاب عربي") == "کتاب عربی"
    assert N.normalize_display("مدرسة") == "مدرسه"
    assert N.normalize_display("مسئله") == "مسیله"  # ئ → ی (یکسان‌سازی تطبیقی)


def test_diacritics_and_tatweel_removed():
    assert N.normalize_display("کِتابِ مُفید") == "کتاب مفید"
    assert N.normalize_display("کــــتاب") == "کتاب"


def test_digits_unified_to_persian_by_default():
    assert N.normalize_display("جزوه 12 و ١٣") == "جزوه ۱۲ و ۱۳"


def test_digits_can_target_english():
    config = N.NormalizerConfig(digit_target="en")
    assert N.normalize_display("جزوه ۱۲", config) == "جزوه 12"


def test_zwnj_variants_collapse_to_same_matching_form():
    a = N.normalize("کتاب‌های درسی")
    b = N.normalize("کتاب های درسی")
    c = N.normalize("کتاب‌‌‌های درسی")
    assert a == c
    # حالت با فاصله‌ی کامل عمداً متفاوت می‌ماند (دو توکن جدا)
    assert a.replace(" ", "") == b.replace(" ", "")


def test_commercial_stopwords_removed():
    assert N.normalize("دانلود رایگان جزوه ریاضی pdf") == "جزوه ریاضی"
    assert N.normalize("خرید نمونه قرارداد کامل ۱۴۰۴") == "نمونه قرارداد"


def test_year_tokens_removed_but_other_numbers_kept():
    assert "۱۴۰۴" not in N.normalize("جزوه فیزیک ۱۴۰۴")
    assert "۲" in N.normalize("جزوه فیزیک جلد ۲")


def test_multiple_spaces_collapse():
    assert N.normalize_display("جزوه    ریاضی\t\tگسسته") == "جزوه ریاضی گسسته"


def test_punctuation_becomes_space():
    assert N.normalize_display("جزوه (ریاضی) — گسسته!") == "جزوه ریاضی گسسته"


def test_full_pipeline_on_realistic_titles():
    samples = [
        ("دانلود رمان تاوان خیانت آوا PDF کامل ۱۴۰۴", "رمان تاوان خیانت آوا"),
        ("خرید جزوه‌ی فیزیک ۱ دانشگاهی رایگان", "جزوه فیزیک ۱ دانشگاهی"),
        ("نمونه قرارداد اجاره مغازه (فایل ورد)", "نمونه قرارداد اجاره مغازه"),
        ("سوالات آيين نامه راهنمايي و رانندگي جديد", "سوالات آیین نامه راهنمایی رانندگی"),
    ]
    for raw, expected in samples:
        assert N.normalize(raw) == expected, raw


def test_raw_title_is_never_mutated():
    raw = "دانلود كتاب"
    N.normalize(raw)
    assert raw == "دانلود كتاب"


def test_empty_and_whitespace_input():
    assert N.normalize("") == ""
    assert N.normalize("   ") == ""
    assert N.meaningful_tokens("") == []


def test_stopwords_are_configurable():
    config = N.NormalizerConfig(use_extra_stopwords=False, custom_stopwords=frozenset({"جزوه"}))
    assert N.normalize("جزوه ریاضی", config) == "ریاضی"


def test_normalize_display_keeps_stopwords():
    assert "دانلود" in N.normalize_display("دانلود رمان")
    assert "دانلود" not in N.normalize("دانلود رمان")


def test_normalization_is_idempotent():
    once = N.normalize("دانلود كتاب‌هاي رياضي ۱۴۰۴")
    assert N.normalize(once) == once


@pytest.mark.parametrize(
    "a,b",
    [
        ("كتاب رياضي", "کتاب ریاضی"),
        ("دانلود جزوه شيمي", "جزوه شیمی"),
        ("رمان   تاوان    خیانت", "رمان تاوان خیانت"),
    ],
)
def test_equivalent_titles_normalize_identically(a, b):
    assert N.normalize(a) == N.normalize(b)
