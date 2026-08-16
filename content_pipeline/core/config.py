"""بارگذاری و اعتبارسنجی ``config.yaml``."""

from __future__ import annotations

import os
import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

try:  # pragma: no cover - yaml همیشه در requirements هست
    import yaml
except ImportError:  # pragma: no cover
    yaml = None  # type: ignore[assignment]

_ENV_PATTERN = re.compile(r"\$\{([A-Z0-9_]+)(?::-([^}]*))?\}")


def _expand_env(value: Any) -> Any:
    """پشتیبانی از ``${ENV_VAR}`` و ``${ENV_VAR:-default}`` در مقادیر رشته‌ای."""
    if isinstance(value, str):

        def repl(match: re.Match[str]) -> str:
            return os.environ.get(match.group(1), match.group(2) or "")

        return _ENV_PATTERN.sub(repl, value)
    if isinstance(value, dict):
        return {k: _expand_env(v) for k, v in value.items()}
    if isinstance(value, list):
        return [_expand_env(v) for v in value]
    return value


@dataclass
class SiteConfig:
    """تنظیمات یک دامنه‌ی رقیب."""

    domain: str
    base_url: str
    #: الگوهای regex برای شناسایی URL محصول (خالی = همه‌ی URLها)
    product_url_include: list[str] = field(default_factory=list)
    product_url_exclude: list[str] = field(default_factory=list)
    #: CSS selector برای عنوان محصول (پیش‌فرض: h1 → og:title → <title>)
    title_selector: str | None = None
    #: صفحاتی که کراول محدود از آن‌ها شروع می‌شود (وقتی sitemap نیست)
    seed_urls: list[str] = field(default_factory=list)
    #: اجبار به استفاده از Playwright برای این دامنه
    js: bool = False
    max_pages: int = 500
    max_depth: int = 3

    @classmethod
    def from_mapping(cls, data: dict) -> "SiteConfig":
        base_url = data["base_url"].rstrip("/")
        domain = data.get("domain") or re.sub(r"^https?://", "", base_url).split("/")[0]
        return cls(
            domain=domain,
            base_url=base_url,
            product_url_include=list(data.get("product_url_include", []) or []),
            product_url_exclude=list(data.get("product_url_exclude", []) or []),
            title_selector=data.get("title_selector"),
            seed_urls=list(data.get("seed_urls", []) or []),
            js=bool(data.get("js", False)),
            max_pages=int(data.get("max_pages", 500)),
            max_depth=int(data.get("max_depth", 3)),
        )


@dataclass
class Config:
    raw: dict[str, Any]
    path: Path | None = None

    # -- دسترسی‌های پرتکرار -------------------------------------------------
    @property
    def db_path(self) -> str:
        return self.get("database.path", "content_pipeline/data/runs.db")

    @property
    def target_topic(self) -> str:
        return self.get("topic.target", "")

    @property
    def topic_examples(self) -> list[str]:
        return self.get("topic.examples", []) or []

    @property
    def relevance_threshold(self) -> float:
        return float(self.get("topic.threshold", 0.65))

    @property
    def review_threshold(self) -> float:
        return float(self.get("topic.review_threshold", 0.50))

    @property
    def sites(self) -> list[SiteConfig]:
        return [SiteConfig.from_mapping(s) for s in self.get("sites", []) or []]

    @property
    def normalizer(self) -> dict:
        return self.get("normalizer", {}) or {}

    def get(self, dotted: str, default: Any = None) -> Any:
        node: Any = self.raw
        for part in dotted.split("."):
            if not isinstance(node, dict) or part not in node:
                return default
            node = node[part]
        return node if node is not None else default

    def require(self, dotted: str) -> Any:
        value = self.get(dotted)
        if value in (None, "", []):
            raise ConfigError(f"مقدار الزامی در config موجود نیست: {dotted}")
        return value


class ConfigError(RuntimeError):
    pass


DEFAULTS: dict[str, Any] = {
    "database": {"path": "content_pipeline/data/runs.db"},
    "topic": {"target": "", "examples": [], "threshold": 0.65, "review_threshold": 0.50},
    "sites": [],
    "crawl": {
        "requests_per_second": 1.0,
        "user_agent": "ContentFeedBot/1.0 (+set-a-contact-url-in-config)",
        "respect_robots": True,
        "timeout": 20,
        "playwright_fallback": True,
    },
    "resolve": {
        "similarity_threshold": 0.80,
        "blocking_top_tokens": 2,
        "llm_arbitration": True,
        "max_llm_pairs": 200,
    },
    "serp": {"provider": "none", "api_key": "", "gl": "ir", "hl": "fa", "location": "Iran"},
    "llm": {
        # داکیومنت «claude-sonnet-4-6» را نام برده بود؛ این شناسه‌ی نسل فعلیِ
        # همان رده (Sonnet) است. برای تغییر، همین مقدار را عوض کنید.
        "model": "claude-sonnet-5",
        "api_key": "",
        "max_tokens": 8000,
        "effort": "low",
        "price_input_per_mtok": 3.0,
        "price_output_per_mtok": 15.0,
    },
    "cost": {"max_cost": None, "confirm_each_phase": True},
    "cache": {"ttl_days": 14},
    "image": {"min_size": 500, "aspect_tolerance": 0.1, "candidates": 5},
    "benchmark": {"serp_results": 10, "llm_candidates": 3},
    "output": {
        "xlsx_path": "content_pipeline/data/output.xlsx",
        "sheet_id": "",
        "service_account_json": "",
        "tabs": {
            "main": "خوراک محتوایی",
            "archive": "آرشیو آینده",
            "review": "بازبینی دستی",
        },
    },
}


def _deep_merge(base: dict, override: dict) -> dict:
    out = dict(base)
    for key, value in override.items():
        if isinstance(value, dict) and isinstance(out.get(key), dict):
            out[key] = _deep_merge(out[key], value)
        else:
            out[key] = value
    return out


def load_config(path: str | Path | None = None) -> Config:
    """خواندن config با پیش‌فرض‌های امن. نبودن فایل خطا نیست."""
    data: dict[str, Any] = {}
    resolved: Path | None = None
    if path:
        resolved = Path(path)
        if not resolved.exists():
            raise ConfigError(f"فایل config پیدا نشد: {resolved}")
        if yaml is None:  # pragma: no cover
            raise ConfigError("برای خواندن config به PyYAML نیاز است: pip install pyyaml")
        data = yaml.safe_load(resolved.read_text(encoding="utf-8")) or {}
    merged = _deep_merge(DEFAULTS, _expand_env(data))
    # کلیدهای API از محیط هم خوانده می‌شوند تا در فایل ذخیره نشوند.
    merged["llm"]["api_key"] = merged["llm"].get("api_key") or os.environ.get(
        "ANTHROPIC_API_KEY", ""
    )
    merged["serp"]["api_key"] = merged["serp"].get("api_key") or os.environ.get(
        "SERP_API_KEY", ""
    )
    return Config(raw=merged, path=resolved)
