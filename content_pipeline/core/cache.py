"""کش کال‌های خارجی.

اصل ۶ معماری: هر کال خارجی باید کش شود. کلید کش ``sha256`` از
``provider + endpoint + params`` است تا اجرای دوباره پول و زمان هدر ندهد.
"""

from __future__ import annotations

import hashlib
import json
import sqlite3
from datetime import datetime, timedelta, timezone
from typing import Any, Callable

from . import db


def cache_key(provider: str, endpoint: str, params: dict[str, Any]) -> str:
    payload = json.dumps(
        {"provider": provider, "endpoint": endpoint, "params": params},
        sort_keys=True,
        ensure_ascii=False,
        separators=(",", ":"),
    )
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def get(
    conn: sqlite3.Connection, key: str, ttl_days: int | None = None
) -> Any | None:
    row = conn.execute("SELECT * FROM api_cache WHERE cache_key=?", (key,)).fetchone()
    if row is None:
        return None
    if ttl_days:
        try:
            created = datetime.fromisoformat(row["created_at"])
        except (TypeError, ValueError):
            created = None
        if created is not None:
            if created.tzinfo is None:
                created = created.replace(tzinfo=timezone.utc)
            if datetime.now(timezone.utc) - created > timedelta(days=ttl_days):
                return None
    try:
        return json.loads(row["response"])
    except json.JSONDecodeError:
        return None


def put(conn: sqlite3.Connection, key: str, provider: str, value: Any) -> None:
    with db.transaction(conn):
        conn.execute(
            """INSERT OR REPLACE INTO api_cache (cache_key, provider, response, created_at)
               VALUES (?,?,?,?)""",
            (key, provider, json.dumps(value, ensure_ascii=False), db.utcnow()),
        )


class CachedCaller:
    """پوشش کش + لاگ هزینه دور یک کال خارجی.

    اگر پاسخ در کش باشد هزینه‌ای ثبت نمی‌شود و ``cost_guard`` مصرف نمی‌شود.
    """

    def __init__(
        self,
        conn: sqlite3.Connection,
        run_id: str,
        ttl_days: int | None = 14,
        cost_guard: Any | None = None,
    ) -> None:
        self.conn = conn
        self.run_id = run_id
        self.ttl_days = ttl_days
        self.cost_guard = cost_guard
        self.hits = 0
        self.misses = 0

    def call(
        self,
        provider: str,
        endpoint: str,
        params: dict[str, Any],
        fn: Callable[[], Any],
        cost_unit: float = 0.0,
        cost_of: Callable[[Any], float] | None = None,
    ) -> Any:
        """اجرای کال با کش.

        ``cost_unit`` تخمینی است که پیش از کال برای سقف هزینه رزرو می‌شود؛
        ``cost_of`` (اگر داده شود) هزینه‌ی واقعی را از پاسخ محاسبه می‌کند —
        برای LLM که هزینه‌اش به تعداد توکن بستگی دارد.
        """
        key = cache_key(provider, endpoint, params)
        cached = get(self.conn, key, self.ttl_days)
        if cached is not None:
            self.hits += 1
            return cached
        self.misses += 1
        if self.cost_guard is not None:
            self.cost_guard.reserve(cost_unit)
        value = fn()
        put(self.conn, key, provider, value)
        actual = cost_unit
        if cost_of is not None:
            try:
                actual = float(cost_of(value))
            except Exception:
                actual = cost_unit
        with db.transaction(self.conn):
            db.record_usage(self.conn, self.run_id, provider, endpoint, actual)
        return value

    def peek(self, provider: str, endpoint: str, params: dict[str, Any]) -> bool:
        """آیا این کال از کش پاسخ می‌گیرد؟ (برای تخمین هزینه در dry-run)"""
        return get(self.conn, cache_key(provider, endpoint, params), self.ttl_days) is not None
