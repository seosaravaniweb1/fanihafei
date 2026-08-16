"""تست کش API و کنترل هزینه."""

from __future__ import annotations

import pytest

from content_pipeline.core import cache, db
from content_pipeline.core.cost_guard import (
    CostGuard,
    CostLimitExceeded,
    DryRunAbort,
    PhaseEstimate,
)


@pytest.fixture
def conn(tmp_path):
    connection = db.connect(tmp_path / "runs.db")
    yield connection
    connection.close()


@pytest.fixture
def run_id(conn):
    return db.create_run(conn, "رمان", run_id="r1")


def test_cache_key_is_order_independent():
    a = cache.cache_key("serp", "search", {"q": "الف", "num": 10})
    b = cache.cache_key("serp", "search", {"num": 10, "q": "الف"})
    assert a == b
    assert a != cache.cache_key("serp", "search", {"q": "ب", "num": 10})


def test_second_call_is_served_from_cache(conn, run_id):
    caller = cache.CachedCaller(conn, run_id, ttl_days=None)
    calls = {"n": 0}

    def fetch():
        calls["n"] += 1
        return {"value": 42}

    first = caller.call("serp", "search", {"q": "الف"}, fetch, cost_unit=0.01)
    second = caller.call("serp", "search", {"q": "الف"}, fetch, cost_unit=0.01)

    assert first == second == {"value": 42}
    assert calls["n"] == 1
    assert caller.hits == 1 and caller.misses == 1
    # فقط یک بار هزینه ثبت شده است
    assert round(db.total_cost(conn, run_id), 4) == 0.01


def test_cost_of_overrides_estimate(conn, run_id):
    caller = cache.CachedCaller(conn, run_id, ttl_days=None)
    caller.call(
        "anthropic",
        "judge",
        {"x": 1},
        lambda: {"usage": {"input_tokens": 1_000_000, "output_tokens": 0}},
        cost_unit=0.5,
        cost_of=lambda payload: payload["usage"]["input_tokens"] / 1_000_000 * 3.0,
    )
    assert round(db.total_cost(conn, run_id), 4) == 3.0


def test_peek_reports_cache_state(conn, run_id):
    caller = cache.CachedCaller(conn, run_id, ttl_days=None)
    assert caller.peek("serp", "search", {"q": "الف"}) is False
    caller.call("serp", "search", {"q": "الف"}, lambda: ["x"], cost_unit=0.0)
    assert caller.peek("serp", "search", {"q": "الف"}) is True


def test_dry_run_blocks_every_external_call(conn, run_id):
    guard = CostGuard(conn, run_id, dry_run=True)
    caller = cache.CachedCaller(conn, run_id, ttl_days=None, cost_guard=guard)
    with pytest.raises(DryRunAbort):
        caller.call("serp", "search", {"q": "الف"}, lambda: ["x"], cost_unit=0.01)
    assert db.total_cost(conn, run_id) == 0.0


def test_max_cost_stops_the_run(conn, run_id):
    guard = CostGuard(conn, run_id, max_cost=0.02)
    caller = cache.CachedCaller(conn, run_id, ttl_days=None, cost_guard=guard)
    caller.call("serp", "search", {"q": "۱"}, lambda: [1], cost_unit=0.01)
    caller.call("serp", "search", {"q": "۲"}, lambda: [2], cost_unit=0.01)
    with pytest.raises(CostLimitExceeded):
        caller.call("serp", "search", {"q": "۳"}, lambda: [3], cost_unit=0.01)


def test_cached_calls_do_not_consume_budget(conn, run_id):
    guard = CostGuard(conn, run_id, max_cost=0.01)
    caller = cache.CachedCaller(conn, run_id, ttl_days=None, cost_guard=guard)
    caller.call("serp", "search", {"q": "الف"}, lambda: [1], cost_unit=0.01)
    # همان کال دوباره: از کش می‌آید، پس سقف را رد نمی‌کند
    assert caller.call("serp", "search", {"q": "الف"}, lambda: [2], cost_unit=0.01) == [1]


def test_confirm_rejects_estimate_above_remaining_budget(conn, run_id):
    guard = CostGuard(conn, run_id, max_cost=1.0, assume_yes=True)
    with pytest.raises(CostLimitExceeded):
        guard.confirm(PhaseEstimate("۴", 500, 0.01), interactive=False)


def test_confirm_passes_within_budget(conn, run_id):
    guard = CostGuard(conn, run_id, max_cost=10.0, assume_yes=True)
    assert guard.confirm(PhaseEstimate("۳", 10, 0.01), interactive=False) is True


def test_estimate_rendering_mentions_calls_and_cost():
    estimate = PhaseEstimate("۳", 40, 0.01, "یک کوئری per محصول")
    assert "40" in estimate.render() and "0.40" in estimate.render()
