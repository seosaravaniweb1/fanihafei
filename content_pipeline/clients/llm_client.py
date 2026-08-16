"""کلاینت Anthropic برای داوری‌های هوشمند پایپ‌لاین.

سه کاربرد:

* فاز ۲ — داوری ادغام وقتی توکن تمایزدهنده‌ی یک طرف خالی است
* فاز ۴ — ارزیابی کیفی سه کاندیدای برتر (نمره‌ی ۰ تا ۱۰)
* فاز ۵ — بررسی واترمارک/لوگو روی تصویر (Vision)

همه‌ی خروجی‌ها با structured outputs (``output_config.format``) گرفته می‌شوند
تا پارس‌کردن پاسخ نیازی به regex نداشته باشد، و همه‌ی کال‌ها از لایه‌ی کش
عبور می‌کنند.
"""

from __future__ import annotations

import base64
import hashlib
import json
from dataclasses import dataclass
from typing import Any, Sequence

try:  # pragma: no cover - وابسته به محیط
    import anthropic
except ImportError:  # pragma: no cover
    anthropic = None  # type: ignore[assignment]


class LLMUnavailable(RuntimeError):
    """کتابخانه یا کلید API در دسترس نیست."""


MERGE_SYSTEM = """تو داور ادغام محصولات در یک کاتالوگ فارسی هستی.
برای هر جفت عنوان تصمیم بگیر که آیا دقیقاً یک محصول‌اند یا نه.

قواعد:
- تفاوت در نام شخص، نام نویسنده، شماره‌ی جلد/پارت یا عدد تمایزدهنده یعنی دو محصول متفاوت.
- کلمات تبلیغاتی (دانلود، خرید، رایگان، pdf، کامل) اهمیتی ندارند.
- اگر یک طرف نویسنده/نام دارد و طرف دیگر «ناشناس» یا بدون نام است، فقط وقتی
  merge بده که مطمئنی همان محصول است؛ در غیر این صورت unsure بده.
- در حالت شک همیشه unsure بده. ادغام اشتباه غیرقابل بازیابی است."""

MERGE_SCHEMA = {
    "type": "object",
    "properties": {
        "decisions": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "pair_id": {"type": "string"},
                    "decision": {"type": "string", "enum": ["merge", "no_merge", "unsure"]},
                    "confidence": {"type": "number"},
                    "reason": {"type": "string"},
                },
                "required": ["pair_id", "decision", "confidence", "reason"],
                "additionalProperties": False,
            },
        }
    },
    "required": ["decisions"],
    "additionalProperties": False,
}

BENCHMARK_SYSTEM = """تو ارزیاب کیفیت محتوای وب فارسی هستی.
برای هر کاندیدا سه بعد را بسنج و یک نمره‌ی کلی ۰ تا ۱۰ بده:
جامعیت (پوشش کامل موضوع)، ساختار (هدینگ، ترتیب منطقی)، خوانایی.
فقط بر اساس متنی که داده شده قضاوت کن."""

BENCHMARK_SCHEMA = {
    "type": "object",
    "properties": {
        "scores": {
            "type": "array",
            "items": {
                "type": "object",
                "properties": {
                    "candidate_id": {"type": "string"},
                    "score": {"type": "number"},
                    "comprehensiveness": {"type": "number"},
                    "structure": {"type": "number"},
                    "readability": {"type": "number"},
                    "reason": {"type": "string"},
                },
                "required": ["candidate_id", "score", "reason"],
                "additionalProperties": False,
            },
        }
    },
    "required": ["scores"],
    "additionalProperties": False,
}

IMAGE_SYSTEM = """تو بازرس تصویر هستی. فقط به یک سؤال جواب می‌دهی:
آیا روی این تصویر لوگو، آدرس سایت، متن تبلیغاتی یا واترمارک وجود دارد؟
متنی که بخشی طبیعی از خود محصول است (مثل عنوان روی جلد کتاب) واترمارک نیست."""

IMAGE_SCHEMA = {
    "type": "object",
    "properties": {
        "has_watermark": {"type": "boolean"},
        "detail": {"type": "string"},
    },
    "required": ["has_watermark", "detail"],
    "additionalProperties": False,
}


@dataclass
class LLMConfig:
    model: str = "claude-sonnet-5"
    api_key: str = ""
    max_tokens: int = 8000
    effort: str = "low"
    price_input_per_mtok: float = 3.0
    price_output_per_mtok: float = 15.0


class LLMClient:
    """پوشش نازک روی ``anthropic.Anthropic`` با کش و محاسبه‌ی هزینه."""

    provider = "anthropic"

    def __init__(self, config: LLMConfig, caller: Any | None = None) -> None:
        self.config = config
        self.caller = caller
        self._client: Any = None

    # -- زیرساخت ------------------------------------------------------------
    @property
    def available(self) -> bool:
        return anthropic is not None and bool(self.config.api_key)

    def _ensure_client(self) -> Any:
        if self._client is not None:
            return self._client
        if anthropic is None:
            raise LLMUnavailable("کتابخانه‌ی anthropic نصب نیست: pip install anthropic")
        if not self.config.api_key:
            raise LLMUnavailable("ANTHROPIC_API_KEY تنظیم نشده است.")
        self._client = anthropic.Anthropic(api_key=self.config.api_key)
        return self._client

    def _cost_of(self, payload: dict) -> float:
        usage = payload.get("usage") or {}
        return (
            usage.get("input_tokens", 0) / 1_000_000 * self.config.price_input_per_mtok
            + usage.get("output_tokens", 0) / 1_000_000 * self.config.price_output_per_mtok
        )

    def estimate_cost(self, input_tokens: int, output_tokens: int) -> float:
        return (
            input_tokens / 1_000_000 * self.config.price_input_per_mtok
            + output_tokens / 1_000_000 * self.config.price_output_per_mtok
        )

    def _request(
        self, system: str, content: list[dict[str, Any]], schema: dict, max_tokens: int | None = None
    ) -> dict:
        client = self._ensure_client()
        try:
            response = client.messages.create(
                model=self.config.model,
                max_tokens=max_tokens or self.config.max_tokens,
                system=[{"type": "text", "text": system}],
                messages=[{"role": "user", "content": content}],
                output_config={
                    "effort": self.config.effort,
                    "format": {"type": "json_schema", "schema": schema},
                },
            )
        except anthropic.RateLimitError as exc:  # pragma: no cover - وابسته به شبکه
            raise RuntimeError(f"محدودیت نرخ Anthropic: {exc}") from exc
        except anthropic.APIStatusError as exc:  # pragma: no cover
            raise RuntimeError(f"خطای Anthropic ({exc.status_code}): {exc}") from exc
        except anthropic.APIConnectionError as exc:  # pragma: no cover
            raise RuntimeError(f"خطای اتصال به Anthropic: {exc}") from exc

        usage = {
            "input_tokens": getattr(response.usage, "input_tokens", 0),
            "output_tokens": getattr(response.usage, "output_tokens", 0),
        }
        if response.stop_reason == "refusal":  # pragma: no cover - نادر
            return {"data": None, "usage": usage, "refused": True}

        text = ""
        for block in response.content:
            if getattr(block, "type", "") == "text":
                text = block.text
                break
        try:
            data = json.loads(text) if text else None
        except json.JSONDecodeError:  # pragma: no cover - با structured outputs نباید رخ دهد
            data = None
        return {"data": data, "usage": usage, "refused": False}

    def _cached_request(
        self, endpoint: str, params: dict, system: str, content: list[dict], schema: dict
    ) -> dict:
        estimate = self.estimate_cost(4000, 800)
        if self.caller is None:
            return self._request(system, content, schema)
        return self.caller.call(
            provider=self.provider,
            endpoint=endpoint,
            params={"model": self.config.model, **params},
            fn=lambda: self._request(system, content, schema),
            cost_unit=estimate,
            cost_of=self._cost_of,
        )

    # -- فاز ۲ ---------------------------------------------------------------
    def judge_merges(self, pairs: Sequence[dict]) -> dict[str, dict]:
        """داوری چند جفت در یک کال. ورودی هر جفت:

        ``{"pair_id", "title_a", "title_b", "tokens_a", "tokens_b"}``
        خروجی: نگاشت ``pair_id`` به ``{"decision", "confidence", "reason"}``.
        """
        if not pairs:
            return {}
        lines = []
        for pair in pairs:
            lines.append(
                json.dumps(
                    {
                        "pair_id": pair["pair_id"],
                        "عنوان_الف": pair["title_a"],
                        "توکن_تمایز_الف": pair.get("tokens_a", []),
                        "عنوان_ب": pair["title_b"],
                        "توکن_تمایز_ب": pair.get("tokens_b", []),
                    },
                    ensure_ascii=False,
                )
            )
        prompt = "برای هر جفت زیر تصمیم بگیر:\n" + "\n".join(lines)
        payload = self._cached_request(
            endpoint="judge_merges",
            params={"pairs": [p["pair_id"] for p in pairs], "hash": _digest(prompt)},
            system=MERGE_SYSTEM,
            content=[{"type": "text", "text": prompt}],
            schema=MERGE_SCHEMA,
        )
        data = (payload or {}).get("data") or {}
        out: dict[str, dict] = {}
        for item in data.get("decisions", []):
            out[str(item.get("pair_id"))] = {
                "decision": item.get("decision", "unsure"),
                "confidence": float(item.get("confidence", 0.0)),
                "reason": item.get("reason", ""),
            }
        return out

    # -- فاز ۴ ---------------------------------------------------------------
    def score_candidates(self, title: str, candidates: Sequence[dict]) -> dict[str, dict]:
        """ارزیابی کیفی. هر کاندیدا: ``{"candidate_id", "url", "text", "metrics"}``."""
        if not candidates:
            return {}
        blocks = []
        for candidate in candidates:
            excerpt = (candidate.get("text") or "")[:6000]
            blocks.append(
                f"### candidate_id: {candidate['candidate_id']}\n"
                f"URL: {candidate.get('url', '')}\n"
                f"معیارهای کمّی: {json.dumps(candidate.get('metrics', {}), ensure_ascii=False)}\n"
                f"متن:\n{excerpt}"
            )
        prompt = f"عنوان هدف: {title}\n\n" + "\n\n".join(blocks)
        payload = self._cached_request(
            endpoint="score_candidates",
            params={"title": title, "hash": _digest(prompt)},
            system=BENCHMARK_SYSTEM,
            content=[{"type": "text", "text": prompt}],
            schema=BENCHMARK_SCHEMA,
        )
        data = (payload or {}).get("data") or {}
        out: dict[str, dict] = {}
        for item in data.get("scores", []):
            out[str(item.get("candidate_id"))] = {
                "score": float(item.get("score", 0.0)),
                "reason": item.get("reason", ""),
            }
        return out

    # -- فاز ۵ ---------------------------------------------------------------
    def has_watermark(self, image_bytes: bytes, media_type: str = "image/jpeg") -> bool | None:
        """``True`` یعنی واترمارک/لوگو دارد. ``None`` یعنی تشخیص ممکن نشد."""
        if not image_bytes:
            return None
        encoded = base64.standard_b64encode(image_bytes).decode("ascii")
        payload = self._cached_request(
            endpoint="has_watermark",
            params={"sha256": hashlib.sha256(image_bytes).hexdigest()},
            system=IMAGE_SYSTEM,
            content=[
                {
                    "type": "image",
                    "source": {"type": "base64", "media_type": media_type, "data": encoded},
                },
                {
                    "type": "text",
                    "text": "آیا روی این تصویر لوگو، آدرس سایت، متن تبلیغاتی یا واترمارک هست؟",
                },
            ],
            schema=IMAGE_SCHEMA,
        )
        data = (payload or {}).get("data") or {}
        if "has_watermark" not in data:
            return None
        return bool(data["has_watermark"])


def _digest(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()[:16]


def llm_from_config(config: Any, caller: Any | None = None) -> LLMClient:
    return LLMClient(
        LLMConfig(
            model=config.get("llm.model", "claude-sonnet-5"),
            api_key=config.get("llm.api_key", ""),
            max_tokens=int(config.get("llm.max_tokens", 8000)),
            effort=config.get("llm.effort", "low"),
            price_input_per_mtok=float(config.get("llm.price_input_per_mtok", 3.0)),
            price_output_per_mtok=float(config.get("llm.price_output_per_mtok", 15.0)),
        ),
        caller=caller,
    )
