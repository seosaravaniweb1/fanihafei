"""کلاینت SERP (نتایج گوگل، ساجست و تصویر).

طبق داکیومنت، اسکرپ مستقیم گوگل و endpoint غیررسمی
``suggestqueries.google.com`` در مقیاس ممنوع است (بلاک می‌شوی). این ماژول
فقط از ارائه‌دهنده‌های رسمی استفاده می‌کند:

* ``serpapi``     — https://serpapi.com
* ``dataforseo``  — https://dataforseo.com
* ``mock``        — خواندن از فایل JSON محلی برای تست و توسعه
* ``none``        — پیش‌فرض؛ هر کالی خطای واضح می‌دهد

همه‌ی متدها از ``CachedCaller`` عبور می‌کنند، پس اجرای دوباره‌ی یک فاز
هزینه‌ی جدید ندارد.
"""

from __future__ import annotations

import base64
import json
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any


class SerpUnavailable(RuntimeError):
    """ارائه‌دهنده‌ی SERP تنظیم نشده است."""


@dataclass
class SerpConfig:
    provider: str = "none"
    api_key: str = ""
    login: str = ""  # DataForSEO
    password: str = ""  # DataForSEO
    gl: str = "ir"
    hl: str = "fa"
    location: str = "Iran"
    timeout: int = 40
    cost_per_search: float = 0.01
    cost_per_autocomplete: float = 0.01
    cost_per_image_search: float = 0.01
    mock_path: str = ""


@dataclass
class OrganicResult:
    position: int
    title: str
    link: str
    snippet: str = ""


@dataclass
class ImageResult:
    position: int
    image_url: str
    thumbnail: str = ""
    source: str = ""
    width: int = 0
    height: int = 0


def _http_json(
    url: str, timeout: int, data: bytes | None = None, headers: dict[str, str] | None = None
) -> Any:
    request = urllib.request.Request(url, data=data, headers=headers or {})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        return json.loads(response.read().decode("utf-8", "replace"))


class SerpClient:
    """رابط یکسان روی ارائه‌دهنده‌های مختلف."""

    def __init__(self, config: SerpConfig, caller: Any | None = None) -> None:
        self.config = config
        self.caller = caller

    @property
    def available(self) -> bool:
        provider = self.config.provider
        if provider == "serpapi":
            return bool(self.config.api_key)
        if provider == "dataforseo":
            return bool(self.config.login and self.config.password)
        if provider == "mock":
            return bool(self.config.mock_path)
        return False

    def _require(self) -> None:
        if not self.available:
            raise SerpUnavailable(
                "ارائه‌دهنده‌ی SERP تنظیم نشده است. در config.yaml بخش serp را پر کنید "
                "(provider: serpapi یا dataforseo) یا SERP_API_KEY را ست کنید."
            )

    def _call(self, endpoint: str, params: dict, fn, cost: float) -> Any:
        if self.caller is None:
            return fn()
        return self.caller.call(
            provider=f"serp:{self.config.provider}",
            endpoint=endpoint,
            params=params,
            fn=fn,
            cost_unit=cost,
        )

    def cached(self, endpoint: str, params: dict) -> bool:
        if self.caller is None:
            return False
        return self.caller.peek(f"serp:{self.config.provider}", endpoint, params)

    # -- ساجست (فاز ۳) -------------------------------------------------------
    def autocomplete(self, query: str) -> list[str]:
        self._require()
        params = {"q": query, "hl": self.config.hl, "gl": self.config.gl}
        raw = self._call(
            "autocomplete", params, lambda: self._autocomplete_raw(query), self.config.cost_per_autocomplete
        )
        return _parse_suggestions(self.config.provider, raw)

    def _autocomplete_raw(self, query: str) -> Any:
        if self.config.provider == "serpapi":
            url = "https://serpapi.com/search.json?" + urllib.parse.urlencode(
                {
                    "engine": "google_autocomplete",
                    "q": query,
                    "hl": self.config.hl,
                    "gl": self.config.gl,
                    "api_key": self.config.api_key,
                }
            )
            return _http_json(url, self.config.timeout)
        if self.config.provider == "dataforseo":
            return self._dataforseo(
                "/v3/serp/google/autocomplete/live/advanced",
                [
                    {
                        "keyword": query,
                        "language_code": self.config.hl,
                        "location_name": self.config.location,
                    }
                ],
            )
        return _mock_section(self.config.mock_path, "autocomplete", query)

    # -- سرچ ارگانیک (فاز ۴) -------------------------------------------------
    def search(self, query: str, num: int = 10) -> list[OrganicResult]:
        self._require()
        params = {"q": query, "num": num, "hl": self.config.hl, "gl": self.config.gl}
        raw = self._call(
            "search", params, lambda: self._search_raw(query, num), self.config.cost_per_search
        )
        return _parse_organic(self.config.provider, raw, num)

    def _search_raw(self, query: str, num: int) -> Any:
        if self.config.provider == "serpapi":
            url = "https://serpapi.com/search.json?" + urllib.parse.urlencode(
                {
                    "engine": "google",
                    "q": query,
                    "num": num,
                    "hl": self.config.hl,
                    "gl": self.config.gl,
                    "location": self.config.location,
                    "api_key": self.config.api_key,
                }
            )
            return _http_json(url, self.config.timeout)
        if self.config.provider == "dataforseo":
            return self._dataforseo(
                "/v3/serp/google/organic/live/advanced",
                [
                    {
                        "keyword": query,
                        "language_code": self.config.hl,
                        "location_name": self.config.location,
                        "depth": num,
                    }
                ],
            )
        return _mock_section(self.config.mock_path, "search", query)

    # -- تصویر (فاز ۵) -------------------------------------------------------
    def image_search(self, query: str, num: int = 10, square_only: bool = True) -> list[ImageResult]:
        self._require()
        params = {"q": query, "num": num, "square": square_only}
        raw = self._call(
            "image_search",
            params,
            lambda: self._image_raw(query, num, square_only),
            self.config.cost_per_image_search,
        )
        return _parse_images(self.config.provider, raw, num)

    def _image_raw(self, query: str, num: int, square_only: bool) -> Any:
        if self.config.provider == "serpapi":
            query_params = {
                "engine": "google_images",
                "q": query,
                "hl": self.config.hl,
                "gl": self.config.gl,
                "api_key": self.config.api_key,
            }
            if square_only:
                # فیلتر نسبت ابعاد مربعی + حداقل اندازه‌ی متوسط
                query_params["imgar"] = "s"
                query_params["imgsz"] = "m"
            url = "https://serpapi.com/search.json?" + urllib.parse.urlencode(query_params)
            return _http_json(url, self.config.timeout)
        if self.config.provider == "dataforseo":
            return self._dataforseo(
                "/v3/serp/google/images/live/advanced",
                [
                    {
                        "keyword": query,
                        "language_code": self.config.hl,
                        "location_name": self.config.location,
                        "depth": num,
                    }
                ],
            )
        return _mock_section(self.config.mock_path, "image_search", query)

    # -- DataForSEO ----------------------------------------------------------
    def _dataforseo(self, path: str, payload: list[dict]) -> Any:
        token = base64.b64encode(
            f"{self.config.login}:{self.config.password}".encode("utf-8")
        ).decode("ascii")
        return _http_json(
            "https://api.dataforseo.com" + path,
            self.config.timeout,
            data=json.dumps(payload).encode("utf-8"),
            headers={"Authorization": f"Basic {token}", "Content-Type": "application/json"},
        )


# ---------------------------------------------------------------------------
# پارسرها
# ---------------------------------------------------------------------------


def _dataforseo_items(raw: Any) -> list[dict]:
    try:
        return raw["tasks"][0]["result"][0].get("items") or []
    except (KeyError, IndexError, TypeError):
        return []


def _parse_suggestions(provider: str, raw: Any) -> list[str]:
    if raw is None:
        return []
    if provider == "serpapi":
        return [s.get("value", "") for s in (raw.get("suggestions") or []) if s.get("value")]
    if provider == "dataforseo":
        return [i.get("suggestion", "") for i in _dataforseo_items(raw) if i.get("suggestion")]
    if isinstance(raw, list):
        return [str(x) for x in raw]
    return list(raw.get("suggestions") or [])


def _parse_organic(provider: str, raw: Any, num: int) -> list[OrganicResult]:
    if raw is None:
        return []
    results: list[OrganicResult] = []
    if provider == "serpapi":
        for index, item in enumerate(raw.get("organic_results") or [], start=1):
            if item.get("link"):
                results.append(
                    OrganicResult(
                        position=item.get("position", index),
                        title=item.get("title", ""),
                        link=item["link"],
                        snippet=item.get("snippet", ""),
                    )
                )
    elif provider == "dataforseo":
        for index, item in enumerate(_dataforseo_items(raw), start=1):
            if item.get("type") == "organic" and item.get("url"):
                results.append(
                    OrganicResult(
                        position=item.get("rank_absolute", index),
                        title=item.get("title", ""),
                        link=item["url"],
                        snippet=item.get("description", "") or "",
                    )
                )
    else:
        for index, item in enumerate(raw if isinstance(raw, list) else raw.get("results", []), 1):
            results.append(
                OrganicResult(
                    position=index,
                    title=item.get("title", ""),
                    link=item.get("link", ""),
                    snippet=item.get("snippet", ""),
                )
            )
    return results[:num]


def _parse_images(provider: str, raw: Any, num: int) -> list[ImageResult]:
    if raw is None:
        return []
    results: list[ImageResult] = []
    if provider == "serpapi":
        for index, item in enumerate(raw.get("images_results") or [], start=1):
            link = item.get("original") or item.get("thumbnail")
            if link:
                results.append(
                    ImageResult(
                        position=item.get("position", index),
                        image_url=link,
                        thumbnail=item.get("thumbnail", ""),
                        source=item.get("source", ""),
                        width=int(item.get("original_width") or 0),
                        height=int(item.get("original_height") or 0),
                    )
                )
    elif provider == "dataforseo":
        for index, item in enumerate(_dataforseo_items(raw), start=1):
            link = item.get("source_url") or item.get("encoded_url") or item.get("url")
            if link:
                results.append(
                    ImageResult(
                        position=index,
                        image_url=link,
                        source=item.get("domain", ""),
                        width=int(item.get("original_width") or 0),
                        height=int(item.get("original_height") or 0),
                    )
                )
    else:
        for index, item in enumerate(raw if isinstance(raw, list) else raw.get("images", []), 1):
            results.append(
                ImageResult(
                    position=index,
                    image_url=item.get("image_url", ""),
                    width=int(item.get("width") or 0),
                    height=int(item.get("height") or 0),
                )
            )
    return results[:num]


def _mock_section(path: str, section: str, query: str) -> Any:
    """خواندن پاسخ ساختگی از فایل JSON برای تست بدون هزینه."""
    if not path or not Path(path).exists():
        return None
    data = json.loads(Path(path).read_text(encoding="utf-8"))
    bucket = data.get(section) or {}
    return bucket.get(query, bucket.get("*"))


def serp_from_config(config: Any, caller: Any | None = None) -> SerpClient:
    return SerpClient(
        SerpConfig(
            provider=config.get("serp.provider", "none"),
            api_key=config.get("serp.api_key", ""),
            login=config.get("serp.login", ""),
            password=config.get("serp.password", ""),
            gl=config.get("serp.gl", "ir"),
            hl=config.get("serp.hl", "fa"),
            location=config.get("serp.location", "Iran"),
            timeout=int(config.get("serp.timeout", 40)),
            cost_per_search=float(config.get("serp.cost_per_search", 0.01)),
            cost_per_autocomplete=float(config.get("serp.cost_per_autocomplete", 0.01)),
            cost_per_image_search=float(config.get("serp.cost_per_image_search", 0.01)),
            mock_path=config.get("serp.mock_path", ""),
        ),
        caller=caller,
    )
