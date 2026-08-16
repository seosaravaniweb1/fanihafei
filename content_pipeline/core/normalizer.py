"""فاز ۰ — نرمال‌سازی فارسی.

این ماژول پیش‌نیاز حیاتی همه‌ی فازهاست. هیچ عنوانی نباید بدون عبور از این خط
وارد مقایسه، بلاک‌بندی یا ادغام شود.

دو سطح خروجی داریم:

* :func:`normalize_display` — شکل خوانا برای نمایش (نیم‌فاصله حفظ می‌شود،
  کلمات ایستا حذف نمی‌شوند). برای ساخت ``canonical_title`` استفاده می‌شود.
* :func:`normalize` — شکل تطبیق (matching). نیم‌فاصله حذف، کلمات ایستای تجاری
  حذف، اعداد یکسان‌سازی. این خروجی در ستون ``normalized_title`` ذخیره می‌شود.

پیاده‌سازی فقط با کتابخانه‌ی استاندارد است. اگر ``hazm`` نصب باشد از آن فقط
به‌عنوان یک مرحله‌ی اختیاری اضافه استفاده می‌شود (بخش‌های حیاتی مستقل‌اند).
"""

from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass
from typing import Iterable, Sequence

ZWNJ = "‌"

# ---------------------------------------------------------------------------
# جدول‌های کاراکتری
# ---------------------------------------------------------------------------

#: حروف عربی و واریانت‌ها → معادل فارسی استاندارد
CHAR_MAP: dict[str, str] = {
    "ي": "ی",  # ي عربی
    "ى": "ی",  # ى
    "ۍ": "ی",  # ۍ
    "ې": "ی",  # ې
    "ك": "ک",  # ك عربی
    "ڪ": "ک",  # ڪ
    "ڬ": "ک",
    "ة": "ه",  # ة
    "ۀ": "ه",  # ۀ
    "أ": "ا",  # أ
    "إ": "ا",  # إ
    "ٱ": "ا",  # ٱ
    "ؤ": "و",  # ؤ
    "ئ": "ی",  # ئ
    "۴": "۴",  # ۴ (no-op، فقط برای خوانایی جدول)
}

#: کاراکترهای صفر-عرض که باید حذف شوند (نیم‌فاصله جداگانه مدیریت می‌شود)
_ZERO_WIDTH = "​‍‎‏⁠﻿"

#: اعراب، کشیده و علائم تشکیل
_DIACRITICS = re.compile(
    "["
    "ً-ٟ"  # فتحه/کسره/ضمه/تشدید/سکون ...
    "ٰ"  # الف خنجری
    "ـ"  # کشیده (tatweel)
    "ؐ-ؚ"
    "ۖ-ۭ"
    "]"
)

_PERSIAN_DIGITS = "۰۱۲۳۴۵۶۷۸۹"
_ARABIC_DIGITS = "٠١٢٣٤٥٦٧٨٩"
_ASCII_DIGITS = "0123456789"

_TO_FA_DIGITS = {
    **{c: _PERSIAN_DIGITS[i] for i, c in enumerate(_ASCII_DIGITS)},
    **{c: _PERSIAN_DIGITS[i] for i, c in enumerate(_ARABIC_DIGITS)},
}
_TO_EN_DIGITS = {
    **{c: _ASCII_DIGITS[i] for i, c in enumerate(_PERSIAN_DIGITS)},
    **{c: _ASCII_DIGITS[i] for i, c in enumerate(_ARABIC_DIGITS)},
}

#: هر چیزی که حرف/رقم/فاصله/نیم‌فاصله نیست → فاصله
_KEEP = re.compile(
    r"[^"
    r"ء-غف-يٮ-ۓ۰-۹"  # عربی/فارسی
    r"a-zA-Z"
    r"0-9٠-٩"
    r"‌ \t\n"
    r"]"
)

_MULTISPACE = re.compile(r"[ \t\n\r  - ]+")

# ---------------------------------------------------------------------------
# کلمات ایستا
# ---------------------------------------------------------------------------

#: لیست صریح داکیومنت — حذف این‌ها غیرقابل مذاکره است.
CORE_STOPWORDS: frozenset[str] = frozenset(
    {
        "دانلود",
        "خرید",
        "رایگان",
        "pdf",
        "فایل",
        "کامل",
        "جدید",
        "نسخه",
        "اورجینال",
    }
)

#: افزوده‌های متداول تجاری — پیش‌فرض روشن، ولی از config قابل خاموش‌کردن است.
EXTRA_STOPWORDS: frozenset[str] = frozenset(
    {
        "دانلودی",
        "رایگانی",
        "خریدن",
        "لینک",
        "مستقیم",
        "آنلاین",
        "ورد",
        "word",
        "doc",
        "docx",
        "zip",
        "rar",
        "بهترین",
        "ویژه",
        "تخفیف",
        "ارزان",
        "قیمت",
        "با",
        "و",
        "در",
        "برای",
        "به",
        "از",
        "ی",
    }
)

#: نشانگرهای صریح نویسنده — بعدشان تقریباً همیشه نام شخص می‌آید.
AUTHOR_MARKERS: frozenset[str] = frozenset(
    {
        "از",
        "نوشته",
        "اثر",
        "قلم",
        "بقلم",
        "ترجمه",
        "مترجم",
        "تالیف",
        "تألیف",
        "مولف",
        "مؤلف",
        "گردآورنده",
        "نویسنده",
        "شاعر",
        "خواننده",
    }
)

#: نشانگرهای نقشی — ضعیف‌ترند، چون خودشان می‌توانند بخشی از عنوان باشند
#: («رمان استاد مغرور»). قاعده‌ی سخت‌گیرانه‌شان در :mod:`core.entities` است.
ROLE_MARKERS: frozenset[str] = frozenset({"استاد", "دکتر", "مهندس", "پروفسور"})

PERSON_MARKERS: frozenset[str] = AUTHOR_MARKERS | ROLE_MARKERS

#: کلماتی که با عدد یک واحد معنایی می‌سازند («جلد ۲»، «پارت ۵»)
VOLUME_MARKERS: frozenset[str] = frozenset(
    {"جلد", "پارت", "قسمت", "فصل", "بخش", "سری", "شماره", "دوره", "ترم", "سال"}
)

#: عبارت‌های چندکلمه‌ای که به‌صورت دنباله حذف می‌شوند.
PHRASE_STOPWORDS: tuple[tuple[str, ...], ...] = (
    ("پی", "دی", "اف"),
    ("دانلود", "رایگان"),
)

#: توکن‌هایی که «نویسنده‌ی نامشخص» را نشان می‌دهند و نباید توکن تمایزدهنده شوند.
UNKNOWN_MARKERS: frozenset[str] = frozenset(
    {"ناشناس", "نامشخص", "بینام", "بی‌نام", "بدوننام", "گمنام", "anonymous"}
)

_YEAR_RE = re.compile(r"^(1[23]\d{2}|14\d{2}|19\d{2}|20\d{2})$")


@dataclass(frozen=True)
class NormalizerConfig:
    """تنظیمات فاز ۰. همه‌ی مقادیر از ``config.yaml`` قابل تغییرند."""

    #: هدف یکسان‌سازی ارقام: ``"fa"`` یا ``"en"`` (فقط یکی، ولی ثابت)
    digit_target: str = "fa"
    #: حذف کلمات ایستای هسته (لیست داکیومنت)
    use_core_stopwords: bool = True
    #: حذف کلمات ایستای افزوده
    use_extra_stopwords: bool = True
    #: کلمات ایستای سفارشی کاربر
    custom_stopwords: frozenset[str] = frozenset()
    #: حذف سال‌های تنها (۱۴۰۴، 2024، ...)
    strip_years: bool = True
    #: رفتار نیم‌فاصله در خروجی تطبیق: ``"strip"`` یا ``"space"`` یا ``"keep"``
    zwnj: str = "strip"
    #: کمینه‌ی طول توکن برای ماندن در خروجی تطبیق
    min_token_len: int = 1

    def stopwords(self) -> frozenset[str]:
        words: set[str] = set()
        if self.use_core_stopwords:
            words |= CORE_STOPWORDS
        if self.use_extra_stopwords:
            # نشانگرها ساختاری‌اند نه محتوایی: «شب سرما نوشته الناز» و
            # «شب سرما الناز» باید عنوان نرمال‌شده‌ی یکسان بدهند.
            words |= EXTRA_STOPWORDS | PERSON_MARKERS | VOLUME_MARKERS
        words |= set(self.custom_stopwords)
        # کلمات ایستا هم باید نرمال شوند تا با توکن‌های نرمال‌شده تطبیق بخورند.
        return frozenset(_normalize_chars(w, self) for w in words)


DEFAULT_CONFIG = NormalizerConfig()


# ---------------------------------------------------------------------------
# مراحل
# ---------------------------------------------------------------------------


def _map_digits(text: str, target: str) -> str:
    table = _TO_FA_DIGITS if target == "fa" else _TO_EN_DIGITS
    return "".join(table.get(ch, ch) for ch in text)


def _normalize_chars(text: str, config: NormalizerConfig = DEFAULT_CONFIG) -> str:
    """نرمال‌سازی کاراکتری: عربی→فارسی، حذف اعراب/کشیده، یکسان‌سازی ارقام."""
    if not text:
        return ""
    text = unicodedata.normalize("NFC", text)
    text = "".join(CHAR_MAP.get(ch, ch) for ch in text)
    text = _DIACRITICS.sub("", text)
    text = text.translate({ord(c): None for c in _ZERO_WIDTH})
    text = _map_digits(text, config.digit_target)
    text = text.lower()
    text = _KEEP.sub(" ", text)
    return _clean_zwnj(text)


def _clean_zwnj(text: str) -> str:
    """نیم‌فاصله‌های تکراری/بی‌جا را پاک می‌کند (بدون حذف نیم‌فاصله‌ی درست)."""
    text = re.sub(ZWNJ + "+", ZWNJ, text)
    text = re.sub(r"\s*" + ZWNJ + r"\s*", lambda m: ZWNJ if m.group(0) == ZWNJ else " ", text)
    text = _MULTISPACE.sub(" ", text)
    # نیم‌فاصله در ابتدا/انتهای کلمه معنایی ندارد
    text = re.sub(r"(?<=\s)" + ZWNJ, "", text)
    text = re.sub(ZWNJ + r"(?=\s)", "", text)
    return text.strip(ZWNJ + " ")


#: «ی/ای» اضافه‌ی چسبیده با نیم‌فاصله («جزوه‌ی»، «جزوه‌ای») در شکل تطبیق
#: باید حذف شود، وگرنه «جزوه‌ی فیزیک» با «جزوه فیزیک» یکی نمی‌شود.
_EZAFE_RE = re.compile("ه" + ZWNJ + r"(?:ای|ی)(?=\s|$)")


def _apply_zwnj_policy(text: str, policy: str) -> str:
    if policy == "strip":
        text = _EZAFE_RE.sub("ه", text)
        return text.replace(ZWNJ, "")
    if policy == "space":
        return _MULTISPACE.sub(" ", text.replace(ZWNJ, " ")).strip()
    return text


def _is_year(token: str) -> bool:
    en = _map_digits(token, "en")
    return bool(_YEAR_RE.match(en))


def _drop_phrases(tokens: list[str], phrases: Sequence[tuple[str, ...]]) -> list[str]:
    if not phrases:
        return tokens
    out: list[str] = []
    i = 0
    while i < len(tokens):
        matched = 0
        for phrase in phrases:
            n = len(phrase)
            if n and tuple(tokens[i : i + n]) == phrase:
                matched = max(matched, n)
        if matched:
            i += matched
            continue
        out.append(tokens[i])
        i += 1
    return out


# ---------------------------------------------------------------------------
# API عمومی
# ---------------------------------------------------------------------------


def normalize_display(text: str, config: NormalizerConfig = DEFAULT_CONFIG) -> str:
    """شکل خوانا: نرمال‌سازی کاراکتری بدون حذف کلمات ایستا و بدون حذف نیم‌فاصله."""
    return _normalize_chars(text, config)


def tokenize(text: str, config: NormalizerConfig = DEFAULT_CONFIG) -> list[str]:
    """توکن‌های نرمال‌شده‌ی خام (بدون حذف کلمات ایستا)."""
    normalized = _apply_zwnj_policy(_normalize_chars(text, config), config.zwnj)
    return [t for t in normalized.split() if t]


def remove_stopwords(
    tokens: Iterable[str], config: NormalizerConfig = DEFAULT_CONFIG
) -> list[str]:
    """حذف کلمات ایستای تجاری، سال‌ها و توکن‌های خیلی کوتاه."""
    stops = config.stopwords()
    phrases = tuple(
        tuple(_normalize_chars(w, config) for w in phrase) for phrase in PHRASE_STOPWORDS
    )
    kept = _drop_phrases(list(tokens), phrases)
    out: list[str] = []
    for token in kept:
        if token in stops:
            continue
        if config.strip_years and _is_year(token):
            continue
        if len(token) < config.min_token_len:
            continue
        out.append(token)
    return out


def meaningful_tokens(text: str, config: NormalizerConfig = DEFAULT_CONFIG) -> list[str]:
    """توکن‌های معنادار — پایه‌ی همه‌ی مقایسه‌های فاز ۲."""
    return remove_stopwords(tokenize(text, config), config)


def normalize(text: str, config: NormalizerConfig = DEFAULT_CONFIG) -> str:
    """خروجی نهایی فاز ۰ که در ستون ``normalized_title`` ذخیره می‌شود."""
    return " ".join(meaningful_tokens(text, config))


def normalize_batch(
    texts: Iterable[str], config: NormalizerConfig = DEFAULT_CONFIG
) -> list[str]:
    return [normalize(t, config) for t in texts]


def config_from_mapping(data: dict | None) -> NormalizerConfig:
    """ساخت :class:`NormalizerConfig` از بخش ``normalizer`` در ``config.yaml``."""
    data = data or {}
    return NormalizerConfig(
        digit_target=data.get("digit_target", "fa"),
        use_core_stopwords=data.get("use_core_stopwords", True),
        use_extra_stopwords=data.get("use_extra_stopwords", True),
        custom_stopwords=frozenset(data.get("custom_stopwords", []) or []),
        strip_years=data.get("strip_years", True),
        zwnj=data.get("zwnj", "strip"),
        min_token_len=int(data.get("min_token_len", 1)),
    )


__all__ = [
    "ZWNJ",
    "CORE_STOPWORDS",
    "EXTRA_STOPWORDS",
    "UNKNOWN_MARKERS",
    "NormalizerConfig",
    "DEFAULT_CONFIG",
    "config_from_mapping",
    "meaningful_tokens",
    "normalize",
    "normalize_batch",
    "normalize_display",
    "remove_stopwords",
    "tokenize",
]
