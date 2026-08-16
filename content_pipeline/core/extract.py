"""استخراج از HTML: عنوان، لینک‌ها، متن اصلی و معیارهای کمّی.

اگر ``selectolax`` و ``trafilatura`` نصب باشند از آن‌ها استفاده می‌شود
(سریع‌تر و دقیق‌تر)، وگرنه یک پارسر جایگزین با کتابخانه‌ی استاندارد کار می‌کند
تا ابزار بدون وابستگی سنگین هم قابل اجرا باشد.
"""

from __future__ import annotations

import html
import re
import urllib.parse
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from html.parser import HTMLParser

try:  # pragma: no cover
    from selectolax.parser import HTMLParser as SelectolaxParser  # type: ignore
except ImportError:  # pragma: no cover
    SelectolaxParser = None  # type: ignore[assignment]

try:  # pragma: no cover
    import trafilatura  # type: ignore
except ImportError:  # pragma: no cover
    trafilatura = None  # type: ignore[assignment]

_TAG_RE = re.compile(r"<[^>]+>")
_SCRIPT_RE = re.compile(r"<(script|style|noscript)[^>]*>.*?</\1>", re.S | re.I)
_WS_RE = re.compile(r"\s+")


def strip_tags(fragment: str) -> str:
    return _WS_RE.sub(" ", html.unescape(_TAG_RE.sub(" ", fragment))).strip()


# ---------------------------------------------------------------------------
# عنوان
# ---------------------------------------------------------------------------


def extract_title(page_html: str, selector: str | None = None) -> str:
    """عنوان محصول: selector سفارشی → h1 → og:title → <title>."""
    if not page_html:
        return ""
    if selector and SelectolaxParser is not None:  # pragma: no cover - نیازمند selectolax
        node = SelectolaxParser(page_html).css_first(selector)
        if node is not None:
            text = node.text(strip=True)
            if text:
                return text
    for pattern in (
        r"<h1[^>]*>(.*?)</h1>",
        r'<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']',
        r'<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']og:title["\']',
        r"<title[^>]*>(.*?)</title>",
    ):
        match = re.search(pattern, page_html, re.S | re.I)
        if match:
            text = strip_tags(match.group(1))
            if text:
                return text
    return ""


# ---------------------------------------------------------------------------
# لینک‌ها
# ---------------------------------------------------------------------------


class _LinkParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[str] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        if tag != "a":
            return
        for name, value in attrs:
            if name == "href" and value:
                self.links.append(value)


def extract_links(page_html: str, base_url: str) -> list[str]:
    """لینک‌های داخل صفحه، مطلق‌شده و بدون fragment."""
    parser = _LinkParser()
    try:
        parser.feed(page_html)
    except Exception:  # pragma: no cover - HTML خراب
        pass
    out: list[str] = []
    seen: set[str] = set()
    for href in parser.links:
        if href.startswith(("mailto:", "tel:", "javascript:", "#")):
            continue
        absolute = urllib.parse.urljoin(base_url, href)
        absolute, _ = urllib.parse.urldefrag(absolute)
        absolute = absolute.rstrip("/") or absolute
        if absolute not in seen:
            seen.add(absolute)
            out.append(absolute)
    return out


def same_domain(url: str, domain: str) -> bool:
    netloc = urllib.parse.urlsplit(url).netloc.lower()
    domain = domain.lower()
    return netloc == domain or netloc.endswith("." + domain)


# ---------------------------------------------------------------------------
# sitemap
# ---------------------------------------------------------------------------


def parse_sitemap(xml_text: str) -> tuple[list[str], list[str]]:
    """خروجی: ``(urls, nested_sitemaps)`` — از sitemap index هم پشتیبانی می‌کند."""
    urls: list[str] = []
    nested: list[str] = []
    try:
        root = ET.fromstring(xml_text.strip())
    except ET.ParseError:
        # بعضی سایت‌ها sitemap را به‌صورت متن ساده می‌دهند
        return [line.strip() for line in xml_text.splitlines() if line.startswith("http")], []
    tag = root.tag.split("}")[-1]
    for child in root:
        loc = child.find("{*}loc")
        if loc is None or not (loc.text or "").strip():
            continue
        value = loc.text.strip()
        if tag == "sitemapindex":
            nested.append(value)
        else:
            urls.append(value)
    return urls, nested


# ---------------------------------------------------------------------------
# متن اصلی و معیارهای کمّی (فاز ۴)
# ---------------------------------------------------------------------------


@dataclass
class ContentMetrics:
    word_count: int = 0
    h2_count: int = 0
    h3_count: int = 0
    has_table: bool = False
    has_list: bool = False
    image_count: int = 0
    published: str = ""
    text: str = field(default="", repr=False)

    def score(self) -> float:
        """امتیاز کمّی ۰..۱۰ برای انتخاب سه کاندیدای برتر."""
        words = min(self.word_count / 1500.0, 1.0) * 4.0
        headings = min((self.h2_count + self.h3_count) / 8.0, 1.0) * 2.5
        structure = (1.0 if self.has_table else 0.0) + (1.0 if self.has_list else 0.0)
        images = min(self.image_count / 4.0, 1.0) * 1.0
        freshness = 0.5 if self.published else 0.0
        return round(words + headings + structure + images + freshness, 2)


_DATE_META = (
    r'article:published_time["\'][^>]+content=["\']([^"\']+)',
    r'["\']datePublished["\']\s*:\s*["\']([^"\']+)',
    r'article:modified_time["\'][^>]+content=["\']([^"\']+)',
)


def extract_content(page_html: str, url: str = "") -> ContentMetrics:
    """متن اصلی + معیارهای ساختاری. هدر/فوتر/سایدبار حذف می‌شود."""
    if not page_html:
        return ContentMetrics()

    text = ""
    if trafilatura is not None:  # pragma: no cover - نیازمند trafilatura
        try:
            text = trafilatura.extract(page_html, url=url or None, include_comments=False) or ""
        except Exception:
            text = ""
    if not text:
        text = _fallback_main_text(page_html)

    body = _SCRIPT_RE.sub(" ", page_html)
    metrics = ContentMetrics(
        word_count=len(text.split()),
        h2_count=len(re.findall(r"<h2[\s>]", body, re.I)),
        h3_count=len(re.findall(r"<h3[\s>]", body, re.I)),
        has_table=bool(re.search(r"<table[\s>]", body, re.I)),
        has_list=bool(re.search(r"<(ul|ol)[\s>]", body, re.I)),
        image_count=len(re.findall(r"<img[\s>]", body, re.I)),
        text=text,
    )
    for pattern in _DATE_META:
        match = re.search(pattern, page_html, re.I)
        if match:
            metrics.published = match.group(1)[:32]
            break
    return metrics


def _fallback_main_text(page_html: str) -> str:
    """استخراج ساده: بزرگ‌ترین بلوکِ ``<article>``/``<main>`` یا کل ``<body>``."""
    body = _SCRIPT_RE.sub(" ", page_html)
    for pattern in (r"<article[^>]*>(.*?)</article>", r"<main[^>]*>(.*?)</main>"):
        blocks = re.findall(pattern, body, re.S | re.I)
        if blocks:
            return strip_tags(max(blocks, key=len))
    match = re.search(r"<body[^>]*>(.*?)</body>", body, re.S | re.I)
    return strip_tags(match.group(1) if match else body)


def extract_images(page_html: str, base_url: str) -> list[str]:
    urls: list[str] = []
    for match in re.finditer(r"<img[^>]+src=[\"']([^\"']+)[\"']", page_html, re.I):
        urls.append(urllib.parse.urljoin(base_url, match.group(1)))
    return urls
