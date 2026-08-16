"""نقطه ورود CLI ابزار استخراج و تحلیل خوراک محتوایی.

نمونه‌ها::

    python -m content_pipeline.run init-db --config config.yaml
    python -m content_pipeline.run start --config config.yaml --topic "رمان"
    python -m content_pipeline.run start --config config.yaml --resume-from 3
    python -m content_pipeline.run start --config config.yaml --dry-run
    python -m content_pipeline.run status --config config.yaml
    python -m content_pipeline.run normalize "دانلود رمان تاوان خیانت آوا PDF ۱۴۰۴"
"""

from __future__ import annotations

import sqlite3
import sys
from dataclasses import dataclass

from typing import Optional

import typer

from .clients.llm_client import llm_from_config
from .clients.serp_client import serp_from_config
from .core import db, normalizer
from .core.cache import CachedCaller
from .core.config import Config, ConfigError, load_config
from .core.cost_guard import CostGuard, CostLimitExceeded, DryRunAbort, PhaseEstimate
from .core.embeddings import TopicMatcher, get_encoder
from .core.http import fetcher_from_config
from .phases import p1_crawl, p2_resolve, p3_suggest, p4_benchmark, p5_image, p6_export

app = typer.Typer(
    add_completion=False,
    help="پایپ‌لاین موضوع‌محور استخراج و تحلیل خوراک محتوایی",
)

PHASE_NAMES = {
    1: "کراول و تشخیص موضوعی",
    2: "Entity Resolution",
    3: "گوگل ساجست",
    4: "بنچمارک محتوا",
    5: "تصویر تمیز",
    6: "خروجی",
}


@dataclass
class Context:
    config: Config
    conn: sqlite3.Connection
    run_id: str
    guard: CostGuard
    caller: CachedCaller

    def close(self) -> None:
        self.conn.close()


def _fail(message: str) -> None:
    typer.secho(message, fg=typer.colors.RED, err=True)
    raise typer.Exit(code=1)


def _open(
    config_path: Optional[str],
    topic: Optional[str] = None,
    run_id: Optional[str] = None,
    max_cost: Optional[float] = None,
    dry_run: bool = False,
    assume_yes: bool = False,
    create: bool = True,
) -> Context:
    try:
        config = load_config(config_path)
    except ConfigError as exc:
        _fail(str(exc))
        raise
    conn = db.connect(config.db_path)

    target_topic = topic or config.target_topic
    if run_id:
        # اجرای مشخص‌شده باید از قبل وجود داشته باشد
        if db.get_run(conn, run_id) is None:
            _fail(f"run_id پیدا نشد: {run_id}")
        resolved = run_id
    elif create:
        # شروع تازه
        if not target_topic:
            _fail("موضوع هدف مشخص نیست: --topic بدهید یا topic.target را در config پر کنید.")
        resolved = db.create_run(conn, target_topic, config=config.raw.get("topic"))
    else:
        # ادامه‌ی آخرین اجرای همین موضوع (resume / status / export)
        row = db.latest_run(conn, target_topic or None)
        if row is None:
            _fail("هیچ اجرای قبلی پیدا نشد. اول `start` را اجرا کنید یا --run-id بدهید.")
        resolved = row["run_id"]

    guard = CostGuard(
        conn=conn,
        run_id=resolved,
        max_cost=max_cost if max_cost is not None else config.get("cost.max_cost"),
        dry_run=dry_run,
        assume_yes=assume_yes,
    )
    caller = CachedCaller(
        conn, resolved, ttl_days=int(config.get("cache.ttl_days", 14)), cost_guard=guard
    )
    return Context(config=config, conn=conn, run_id=resolved, guard=guard, caller=caller)


def _topic_matcher(config: Config) -> TopicMatcher:
    encoder = get_encoder(config.get("embeddings.model"))
    typer.echo(f"  انکودر: {encoder.name}")
    topic = config.target_topic
    if not topic:
        raise ValueError("topic.target در config خالی است.")
    return TopicMatcher.build(encoder, topic, config.topic_examples)


# ---------------------------------------------------------------------------
# فازها
# ---------------------------------------------------------------------------


def _run_phase(context: Context, phase: int, interactive: bool = True) -> None:
    config, conn, run_id = context.config, context.conn, context.run_id
    typer.secho(f"\n▶ فاز {phase} — {PHASE_NAMES[phase]}", fg=typer.colors.CYAN, bold=True)

    if phase == 1:
        matcher = _topic_matcher(config)
        with fetcher_from_config(config) as fetcher:
            typer.echo(f"  backend HTTP: {fetcher.backend}")
            estimate = PhaseEstimate("۱", 0, 0.0, "کراول مستقیم است و هزینه‌ی API ندارد")
            if not context.guard.confirm(estimate, interactive):
                return
            stats = p1_crawl.run(conn, run_id, config, fetcher, matcher)
        typer.echo(stats.render())

    elif phase == 2:
        llm = llm_from_config(config, context.caller)
        pending = conn.execute(
            "SELECT COUNT(*) AS c FROM raw_products WHERE run_id=? AND is_relevant=1", (run_id,)
        ).fetchone()["c"]
        estimate = PhaseEstimate(
            "۲",
            max(1, pending // 30) if llm.available else 0,
            llm.estimate_cost(6000, 1500),
            "فقط برای جفت‌های مبهم (یک طرف بدون توکن تمایزدهنده)",
        )
        if not context.guard.confirm(estimate, interactive):
            return
        stats = p2_resolve.run(conn, run_id, config, llm_client=llm)
        typer.echo(stats.render())

    elif phase == 3:
        serp = serp_from_config(config, context.caller)
        calls = p3_suggest.estimate_calls(conn, run_id, serp) if serp.available else 0
        estimate = PhaseEstimate("۳", calls, serp.config.cost_per_autocomplete, "یک کوئری به ازای هر محصول")
        if not context.guard.confirm(estimate, interactive):
            return
        stats = p3_suggest.run(conn, run_id, config, serp)
        typer.echo(stats.render())

    elif phase == 4:
        serp = serp_from_config(config, context.caller)
        llm = llm_from_config(config, context.caller)
        count = len(p4_benchmark.targets(conn, run_id))
        estimate = PhaseEstimate(
            "۴",
            count,
            serp.config.cost_per_search + llm.estimate_cost(20000, 1500),
            "یک سرچ + ارزیابی کیفی فقط روی سه کاندیدا",
        )
        if not context.guard.confirm(estimate, interactive):
            return
        with fetcher_from_config(config) as fetcher:
            stats = p4_benchmark.run(conn, run_id, config, serp, fetcher, llm_client=llm)
        typer.echo(stats.render())

    elif phase == 5:
        serp = serp_from_config(config, context.caller)
        llm = llm_from_config(config, context.caller)
        count = len(db.canonical_products(conn, run_id))
        candidates = int(config.get("image.candidates", 5))
        estimate = PhaseEstimate(
            "۵",
            count,
            serp.config.cost_per_image_search + candidates * llm.estimate_cost(2500, 100),
            f"یک جستجوی تصویر + حداکثر {candidates} بررسی Vision",
        )
        if not context.guard.confirm(estimate, interactive):
            return
        with fetcher_from_config(config) as fetcher:
            stats = p5_image.run(conn, run_id, config, serp, fetcher, llm_client=llm)
        typer.echo(stats.render())

    elif phase == 6:
        if context.guard.dry_run:
            typer.echo("  [dry-run] خروجی نوشته نمی‌شود.")
            return
        stats = p6_export.run(conn, run_id, config)
        typer.echo(stats.render())

    else:  # pragma: no cover
        _fail(f"شماره فاز نامعتبر: {phase}")

    db.set_run_status(conn, run_id, db.RUNNING, phase)


# ---------------------------------------------------------------------------
# دستورها
# ---------------------------------------------------------------------------


@app.command("init-db")
def init_db(config: Optional[str] = typer.Option(None, "--config", "-c")) -> None:
    """ساخت دیتابیس و اسکیما."""
    configuration = load_config(config)
    conn = db.connect(configuration.db_path)
    conn.close()
    typer.secho(f"دیتابیس آماده است: {configuration.db_path}", fg=typer.colors.GREEN)


@app.command("start")
def start(
    config: Optional[str] = typer.Option(None, "--config", "-c", help="مسیر config.yaml"),
    topic: Optional[str] = typer.Option(None, "--topic", "-t", help="موضوع هدف"),
    run_id: Optional[str] = typer.Option(None, "--run-id", help="ادامه‌ی یک اجرای موجود"),
    resume_from: int = typer.Option(1, "--resume-from", min=1, max=6, help="شروع از این فاز"),
    until: int = typer.Option(6, "--until", min=1, max=6, help="تا این فاز"),
    phases: Optional[str] = typer.Option(None, "--phases", help="فهرست دلخواه، مثل 1,2,3"),
    max_cost: Optional[float] = typer.Option(None, "--max-cost", help="سقف هزینه به دلار"),
    dry_run: bool = typer.Option(False, "--dry-run", help="فقط تخمین، بدون هیچ کالی"),
    yes: bool = typer.Option(False, "--yes", "-y", help="بدون پرسش تأیید"),
) -> None:
    """اجرای پایپ‌لاین. فازها مستقل‌اند و از طریق دیتابیس با هم حرف می‌زنند."""
    context = _open(
        config,
        topic=topic,
        run_id=run_id,
        max_cost=max_cost,
        dry_run=dry_run,
        assume_yes=yes,
        create=resume_from == 1 and run_id is None,
    )
    if phases:
        try:
            selected = [int(p) for p in phases.replace(" ", "").split(",") if p]
        except ValueError:
            _fail("قالب --phases نامعتبر است. نمونه: --phases 1,2,3")
            return
    else:
        selected = list(range(resume_from, until + 1))

    typer.secho(f"run_id: {context.run_id}", fg=typer.colors.BRIGHT_BLACK)
    if dry_run:
        typer.secho("حالت dry-run: هیچ کال خارجی زده نمی‌شود.", fg=typer.colors.YELLOW)

    try:
        for phase in selected:
            _run_phase(context, phase, interactive=not yes)
    except DryRunAbort as exc:
        typer.secho(f"\n{exc}", fg=typer.colors.YELLOW)
    except CostLimitExceeded as exc:
        db.set_run_status(context.conn, context.run_id, db.FAILED)
        typer.secho(f"\n{exc}", fg=typer.colors.RED)
        typer.echo(context.guard.report())
        context.close()
        raise typer.Exit(code=2)
    except Exception as exc:
        db.set_run_status(context.conn, context.run_id, db.FAILED)
        typer.secho(f"\nاجرا متوقف شد: {exc}", fg=typer.colors.RED, err=True)
        typer.echo("داده‌های فازهای قبلی در دیتابیس محفوظ است؛ با --resume-from ادامه دهید.")
        context.close()
        raise typer.Exit(code=1)

    if not dry_run and 6 in selected:
        db.set_run_status(context.conn, context.run_id, db.COMPLETED, 6)
    typer.echo("\n" + context.guard.report())
    typer.echo(f"کش: {context.caller.hits} hit / {context.caller.misses} miss")
    context.close()


@app.command("phase")
def phase_command(
    number: int = typer.Argument(..., min=1, max=6),
    config: Optional[str] = typer.Option(None, "--config", "-c"),
    run_id: Optional[str] = typer.Option(None, "--run-id"),
    max_cost: Optional[float] = typer.Option(None, "--max-cost"),
    dry_run: bool = typer.Option(False, "--dry-run"),
    yes: bool = typer.Option(False, "--yes", "-y"),
) -> None:
    """اجرای فقط یک فاز روی یک run موجود."""
    context = _open(
        config, run_id=run_id, max_cost=max_cost, dry_run=dry_run, assume_yes=yes, create=False
    )
    typer.secho(f"run_id: {context.run_id}", fg=typer.colors.BRIGHT_BLACK)
    try:
        _run_phase(context, number, interactive=not yes)
    finally:
        typer.echo("\n" + context.guard.report())
        context.close()


@app.command("export")
def export_command(
    config: Optional[str] = typer.Option(None, "--config", "-c"),
    run_id: Optional[str] = typer.Option(None, "--run-id"),
    no_sheets: bool = typer.Option(False, "--no-sheets", help="فقط فایل محلی"),
) -> None:
    """ساخت خروجی نهایی (شیت + فایل محلی)."""
    context = _open(config, run_id=run_id, create=False)
    stats = p6_export.run(context.conn, context.run_id, context.config, push_to_sheets=not no_sheets)
    typer.echo(stats.render())
    context.close()


@app.command("status")
def status_command(
    config: Optional[str] = typer.Option(None, "--config", "-c"),
    run_id: Optional[str] = typer.Option(None, "--run-id"),
) -> None:
    """وضعیت یک اجرا."""
    context = _open(config, run_id=run_id, create=False)
    conn, rid = context.conn, context.run_id
    row = db.get_run(conn, rid)
    typer.secho(f"run_id: {rid}", bold=True)
    typer.echo(f"موضوع: {row['target_topic']}  |  وضعیت: {row['status']}  |  فاز: {row['current_phase']}")

    counts = {
        "عنوان خام": "SELECT COUNT(*) c FROM raw_products WHERE run_id=?",
        "مرتبط": "SELECT COUNT(*) c FROM raw_products WHERE run_id=? AND is_relevant=1",
        "مرزی": "SELECT COUNT(*) c FROM raw_products WHERE run_id=? AND is_relevant IS NULL",
        "محصول یکپارچه": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=?",
        "نیازمند بازبینی": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND needs_review=1",
        "has_suggest": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND suggest_status='has_suggest'",
        "no_suggest": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND suggest_status='no_suggest'",
        "دارای بنچمارک": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND benchmark_url IS NOT NULL",
        "دارای تصویر": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND image_url IS NOT NULL",
    }
    for label, query in counts.items():
        typer.echo(f"  {label}: {conn.execute(query, (rid,)).fetchone()['c']}")
    typer.echo(context.guard.report())
    context.close()


@app.command("runs")
def runs_command(config: Optional[str] = typer.Option(None, "--config", "-c")) -> None:
    """فهرست اجراهای قبلی."""
    configuration = load_config(config)
    conn = db.connect(configuration.db_path)
    rows = db.list_runs(conn)
    if not rows:
        typer.echo("هنوز هیچ اجرایی ثبت نشده است.")
    for row in rows:
        typer.echo(
            f"{row['run_id']}  |  {row['target_topic']}  |  {row['status']}  |  فاز {row['current_phase']}"
        )
    conn.close()


@app.command("normalize")
def normalize_command(
    text: str = typer.Argument(...),
    config: Optional[str] = typer.Option(None, "--config", "-c"),
) -> None:
    """ابزار کمکی برای دیدن خروجی فاز ۰ روی یک متن."""
    configuration = load_config(config)
    norm_config = normalizer.config_from_mapping(configuration.normalizer)
    typer.echo(f"ورودی      : {text}")
    typer.echo(f"نمایشی     : {normalizer.normalize_display(text, norm_config)}")
    typer.echo(f"تطبیق      : {normalizer.normalize(text, norm_config)}")
    typer.echo(f"توکن‌ها     : {normalizer.meaningful_tokens(text, norm_config)}")


def main() -> None:  # pragma: no cover
    try:
        app()
    except KeyboardInterrupt:
        typer.secho("\nمتوقف شد.", fg=typer.colors.YELLOW)
        sys.exit(130)


if __name__ == "__main__":  # pragma: no cover
    main()
