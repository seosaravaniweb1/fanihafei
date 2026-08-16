"""کنترل هزینه: تخمین قبل از هر فاز، سقف هزینه و حالت dry-run."""

from __future__ import annotations

import sqlite3
from dataclasses import dataclass, field

from . import db


class CostLimitExceeded(RuntimeError):
    """سقف ``--max-cost`` رد شده است."""


class DryRunAbort(RuntimeError):
    """در حالت dry-run هیچ کالی زده نمی‌شود."""


@dataclass
class PhaseEstimate:
    phase: str
    calls: int
    unit_cost: float
    note: str = ""

    @property
    def total(self) -> float:
        return self.calls * self.unit_cost

    def render(self) -> str:
        note = f" — {self.note}" if self.note else ""
        return (
            f"فاز {self.phase}: حدود {self.calls} کال "
            f"و ${self.total:.2f} هزینه{note}"
        )


@dataclass
class CostGuard:
    conn: sqlite3.Connection
    run_id: str
    max_cost: float | None = None
    dry_run: bool = False
    assume_yes: bool = False
    #: هزینه‌ی رزروشده در این اجرا (قبل از commit شدن در api_usage)
    _reserved: float = field(default=0.0, init=False)

    # -- وضعیت ---------------------------------------------------------------
    @property
    def spent(self) -> float:
        return db.total_cost(self.conn, self.run_id)

    @property
    def remaining(self) -> float | None:
        if self.max_cost is None:
            return None
        return self.max_cost - self.spent

    # -- گیت‌ها --------------------------------------------------------------
    def reserve(self, cost_unit: float) -> None:
        """قبل از زدن یک کال واقعی صدا زده می‌شود."""
        if self.dry_run:
            raise DryRunAbort("dry-run فعال است؛ هیچ کال خارجی زده نمی‌شود.")
        if self.max_cost is not None and self.spent + cost_unit > self.max_cost:
            raise CostLimitExceeded(
                f"سقف هزینه ${self.max_cost:.2f} پر شده است "
                f"(مصرف‌شده: ${self.spent:.2f}). اجرا متوقف شد."
            )

    def confirm(self, estimate: PhaseEstimate, interactive: bool = True) -> bool:
        """نمایش تخمین و گرفتن تأیید کاربر قبل از شروع فاز."""
        print(estimate.render())
        if self.max_cost is not None:
            print(f"  مصرف تاکنون: ${self.spent:.2f} از سقف ${self.max_cost:.2f}")
        if self.dry_run:
            print("  [dry-run] اجرا نمی‌شود.")
            return False
        if self.max_cost is not None and self.spent + estimate.total > self.max_cost:
            raise CostLimitExceeded(
                f"تخمین این فاز (${estimate.total:.2f}) از سقف باقی‌مانده "
                f"(${self.max_cost - self.spent:.2f}) بیشتر است."
            )
        if self.assume_yes or not interactive or estimate.total <= 0:
            return True
        answer = input("  ادامه؟ [y/N] ").strip().lower()
        return answer in {"y", "yes", "بله"}

    def report(self) -> str:
        lines = [f"هزینه‌ی کل این اجرا: ${self.spent:.2f}"]
        for row in db.usage_breakdown(self.conn, self.run_id):
            lines.append(
                f"  {row['provider']}/{row['endpoint']}: "
                f"{row['calls']} کال، ${(row['cost'] or 0):.2f}"
            )
        return "\n".join(lines)
