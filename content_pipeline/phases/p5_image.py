"""فاز ۵ — تصویر تمیز.

1. جستجوی تصویر از SERP API با فیلتر نسبت ابعاد مربعی
2. برای ۵ کاندیدای برتر: دانلود، چک نسبت (۰.۹ تا ۱.۱) و حداقل ۵۰۰×۵۰۰
3. ارسال به مدل Vision: «آیا لوگو/آدرس سایت/متن تبلیغاتی/واترمارک دارد؟»
4. اولین تصویری که «خیر» گرفت ذخیره می‌شود
5. اگر هیچ‌کدام پاس نشد، فیلد خالی می‌ماند
"""

from __future__ import annotations

import io
import sqlite3
import struct
from dataclasses import dataclass
from typing import Any

from ..core import db
from ..core.config import Config
from ..core.http import Fetcher


@dataclass
class ImageStats:
    products: int = 0
    searched: int = 0
    downloaded: int = 0
    rejected_size: int = 0
    rejected_watermark: int = 0
    accepted: int = 0
    failures: int = 0

    def render(self) -> str:
        return (
            f"فاز ۵ — محصولات: {self.products}، جستجو: {self.searched}، "
            f"دانلود: {self.downloaded}، ردشده (ابعاد): {self.rejected_size}، "
            f"ردشده (واترمارک): {self.rejected_watermark}، پذیرفته: {self.accepted}"
        )


# ---------------------------------------------------------------------------
# اندازه‌ی تصویر
# ---------------------------------------------------------------------------


def image_size(data: bytes) -> tuple[int, int, str]:
    """``(width, height, media_type)``. با Pillow اگر باشد، وگرنه از هدر فایل."""
    try:  # pragma: no cover - وابسته به محیط
        from PIL import Image  # type: ignore

        with Image.open(io.BytesIO(data)) as image:
            fmt = (image.format or "").lower()
            media = {"jpeg": "image/jpeg", "png": "image/png", "webp": "image/webp",
                     "gif": "image/gif"}.get(fmt, "image/jpeg")
            return image.width, image.height, media
    except ImportError:
        pass
    except Exception:
        return 0, 0, ""
    return _header_size(data)


def _header_size(data: bytes) -> tuple[int, int, str]:
    """خواندن ابعاد از هدر PNG/GIF/JPEG/WebP بدون وابستگی خارجی."""
    if len(data) < 24:
        return 0, 0, ""
    if data[:8] == b"\x89PNG\r\n\x1a\n":
        width, height = struct.unpack(">II", data[16:24])
        return width, height, "image/png"
    if data[:6] in (b"GIF87a", b"GIF89a"):
        width, height = struct.unpack("<HH", data[6:10])
        return width, height, "image/gif"
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        chunk = data[12:16]
        try:
            if chunk == b"VP8X":
                width = int.from_bytes(data[24:27], "little") + 1
                height = int.from_bytes(data[27:30], "little") + 1
                return width, height, "image/webp"
            if chunk == b"VP8 ":
                width = struct.unpack("<H", data[26:28])[0] & 0x3FFF
                height = struct.unpack("<H", data[28:30])[0] & 0x3FFF
                return width, height, "image/webp"
            if chunk == b"VP8L":
                bits = int.from_bytes(data[21:25], "little")
                return (bits & 0x3FFF) + 1, ((bits >> 14) & 0x3FFF) + 1, "image/webp"
        except (struct.error, IndexError):
            return 0, 0, "image/webp"
    if data[:2] == b"\xff\xd8":
        index = 2
        while index < len(data) - 9:
            if data[index] != 0xFF:
                index += 1
                continue
            marker = data[index + 1]
            if 0xC0 <= marker <= 0xCF and marker not in (0xC4, 0xC8, 0xCC):
                height, width = struct.unpack(">HH", data[index + 5 : index + 9])
                return width, height, "image/jpeg"
            try:
                length = struct.unpack(">H", data[index + 2 : index + 4])[0]
            except struct.error:
                break
            index += 2 + length
        return 0, 0, "image/jpeg"
    return 0, 0, ""


def acceptable_dimensions(
    width: int, height: int, min_size: int = 500, tolerance: float = 0.1
) -> bool:
    if width < min_size or height < min_size:
        return False
    ratio = width / height if height else 0.0
    return (1 - tolerance) <= ratio <= (1 + tolerance)


# ---------------------------------------------------------------------------
# اجرای فاز
# ---------------------------------------------------------------------------


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    serp: Any,
    fetcher: Fetcher,
    llm_client: Any | None = None,
    verbose: bool = True,
) -> ImageStats:
    stats = ImageStats()
    min_size = int(config.get("image.min_size", 500))
    tolerance = float(config.get("image.aspect_tolerance", 0.1))
    max_candidates = int(config.get("image.candidates", 5))

    rows = db.canonical_products(conn, run_id)
    stats.products = len(rows)
    for row in rows:
        if row["image_url"]:
            continue
        title = row["canonical_title"] or ""
        try:
            candidates = serp.image_search(title, num=max_candidates * 2, square_only=True)
            stats.searched += 1
        except Exception as exc:
            stats.failures += 1
            if verbose:
                print(f"  هشدار: جستجوی تصویر «{title}» ناموفق بود: {exc}")
            continue

        chosen = ""
        for candidate in candidates[:max_candidates]:
            status, data = fetcher.fetch_bytes(candidate.image_url)
            if status != 200 or not data:
                continue
            stats.downloaded += 1
            width, height, media_type = image_size(data)
            if not acceptable_dimensions(width, height, min_size, tolerance):
                stats.rejected_size += 1
                continue
            if llm_client is None or not getattr(llm_client, "available", False):
                # بدون بررسی Vision نمی‌توان واترمارک را رد کرد؛ محافظه‌کارانه رد می‌شود.
                stats.rejected_watermark += 1
                continue
            try:
                has_watermark = llm_client.has_watermark(data, media_type or "image/jpeg")
            except Exception as exc:  # pragma: no cover - وابسته به شبکه
                if verbose:
                    print(f"  هشدار: بررسی واترمارک ناموفق بود: {exc}")
                continue
            if has_watermark is False:
                chosen = candidate.image_url
                break
            stats.rejected_watermark += 1

        if chosen:
            with db.transaction(conn):
                db.update_canonical(conn, int(row["id"]), image_url=chosen)
            stats.accepted += 1

    return stats
