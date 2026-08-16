"""نقطه ورود CLI ابزار استخراج و تحلیل خوراک محتوایی.

نمونه‌ها::

    python -m content_pipeline.run init-db -c config.yaml
    python -m content_pipeline.run start   -c config.yaml
    python -m content_pipeline.run start   -c config.yaml --resume-from 3
    python -m content_pipeline.run status  -c config.yaml
    python -m content_pipeline.run normalize "دانلود جزوه ریاضی ۱۴۰۴ PDF"

هزینه‌ی API این ابزار صفر است؛ هیچ سرویس پولی استفاده نمی‌شود.
"""

from __future__ import annotations

import sqlite3
import sys
from dataclasses import dataclass
from typing import Optional

import typer

from .core import db, normalizer
from .core.cache import QueryCache
from .core.config import Config, ConfigError, load_config
from .core.embeddings import TopicMatcher, get_encoder
from .core.http import fetcher_from_config
from .core.suggest import suggest_from_config
from .output import exporter
from .phases import p1_crawl, p2_resolve, p3_suggest

app = typer.Typer(
    add_completion=False,
    help="پایپ‌لاین موضوع‌محور استخراج و تحلیل خوراک محتوایی (بدون هزینه‌ی API)",
)

PHASE_NAMES = {
    1: "کراول و تشخیص موضوعی",
    2: "Entity Resolution",
    3: "گوگل ساجست",
    4: "خروجی",
}
LAST_PHASE = 4


@dataclass
class Context:
    config: Config
    conn: sqlite3.Connection
    run_id: str

    def close(self) -> None:
        self.conn.close()


def _fail(message: str) -> None:
    typer.secho(message, fg=typer.colors.RED, err=True)
    raise typer.Exit(code=1)


def _open(
    config_path: Optional[str],
    topic: Optional[str] = None,
    run_id: Optional[str] = None,
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
        if db.get_run(conn, run_id) is None:
            _fail(f"run_id پیدا نشد: {run_id}")
        resolved = run_id
    elif create:
        if not target_topic:
            _fail(
                "موضوع هدف مشخص نیست: --topic بدهید یا run.target_topic را در config پر کنید."
            )
        resolved = db.create_run(conn, target_topic)
    else:
        row = db.latest_run(conn, target_topic or None)
        if row is None:
            _fail("هیچ اجرای قبلی پیدا نشد. اول `start` را اجرا کنید یا --run-id بدهید.")
        resolved = row["run_id"]
    return Context(config=config, conn=conn, run_id=resolved)


def _topic_matcher(config: Config) -> TopicMatcher:
    encoder = get_encoder(config.get("embeddings.model"))
    typer.echo(f"  انکودر: {encoder.name}")
    if not config.target_topic:
        raise ValueError("run.target_topic در config خالی است.")
    return TopicMatcher.build(encoder, config.target_topic, config.topic_examples)


# ---------------------------------------------------------------------------
# فازها
# ---------------------------------------------------------------------------


def _run_phase(context: Context, phase: int) -> None:
    config, conn, run_id = context.config, context.conn, context.run_id
    typer.secho(f"\n▶ فاز {phase} — {PHASE_NAMES[phase]}", fg=typer.colors.CYAN, bold=True)

    if phase == 1:
        matcher = _topic_matcher(config)
        with fetcher_from_config(config) as fetcher:
            typer.echo(f"  backend HTTP: {fetcher.backend}")
            if fetcher.backend != "curl_cffi":
                typer.secho(
                    "  ⚠ curl_cffi نصب نیست؛ اثرانگشت TLS ممکن است شناسایی شود. "
                    "نصب کنید: pip install curl_cffi",
                    fg=typer.colors.YELLOW,
                )
            stats = p1_crawl.run(conn, run_id, config, fetcher, matcher)
        typer.echo(stats.render())

    elif phase == 2:
        stats = p2_resolve.run(conn, run_id, config)
        typer.echo(stats.render())

    elif phase == 3:
        cache = QueryCache(conn, ttl_days=int(config.get("suggest.cache_ttl_days", 30)))
        client = suggest_from_config(config, cache)
        pending = len(p3_suggest.pending_products(conn, run_id))
        typer.echo(
            f"  {pending} محصول در صف؛ سقف این نشست: {client.config.max_per_session} کوئری، "
            f"تأخیر {client.config.delay_min}–{client.config.delay_max} ثانیه"
        )
        stats = p3_suggest.run(conn, run_id, config, client)
        typer.echo(stats.render())
        typer.echo(f"  کش: {cache.hits} hit / {cache.misses} miss")

    elif phase == 4:
        stats = exporter.run(conn, run_id, config)
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
    resume_from: int = typer.Option(1, "--resume-from", min=1, max=LAST_PHASE),
    until: int = typer.Option(LAST_PHASE, "--until", min=1, max=LAST_PHASE),
    phases: Optional[str] = typer.Option(None, "--phases", help="فهرست دلخواه، مثل 1,2"),
) -> None:
    """اجرای پایپ‌لاین. فازها مستقل‌اند و از طریق دیتابیس با هم حرف می‌زنند."""
    context = _open(
        config, topic=topic, run_id=run_id, create=resume_from == 1 and run_id is None
    )
    if phases:
        try:
            selected = [int(p) for p in phases.replace(" ", "").split(",") if p]
        except ValueError:
            _fail("قالب --phases نامعتبر است. نمونه: --phases 1,2")
            return
    else:
        selected = list(range(resume_from, until + 1))

    typer.secho(f"run_id: {context.run_id}", fg=typer.colors.BRIGHT_BLACK)
    try:
        for phase in selected:
            _run_phase(context, phase)
    except Exception as exc:
        db.set_run_status(context.conn, context.run_id, db.FAILED)
        typer.secho(f"\nاجرا متوقف شد: {exc}", fg=typer.colors.RED, err=True)
        typer.echo("داده‌های فازهای قبلی در دیتابیس محفوظ است؛ با --resume-from ادامه دهید.")
        context.close()
        raise typer.Exit(code=1)

    if LAST_PHASE in selected:
        db.set_run_status(context.conn, context.run_id, db.COMPLETED, LAST_PHASE)
    context.close()


@app.command("phase")
def phase_command(
    number: int = typer.Argument(..., min=1, max=LAST_PHASE),
    config: Optional[str] = typer.Option(None, "--config", "-c"),
    run_id: Optional[str] = typer.Option(None, "--run-id"),
) -> None:
    """اجرای فقط یک فاز روی یک اجرای موجود."""
    context = _open(config, run_id=run_id, create=False)
    typer.secho(f"run_id: {context.run_id}", fg=typer.colors.BRIGHT_BLACK)
    try:
        _run_phase(context, number)
    finally:
        context.close()


@app.command("export")
def export_command(
    config: Optional[str] = typer.Option(None, "--config", "-c"),
    run_id: Optional[str] = typer.Option(None, "--run-id"),
    no_sheets: bool = typer.Option(False, "--no-sheets", help="فقط فایل محلی"),
) -> None:
    """ساخت خروجی نهایی (سه شیت)."""
    context = _open(config, run_id=run_id, create=False)
    stats = exporter.run(
        context.conn, context.run_id, context.config, push_to_sheets=not no_sheets
    )
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
    typer.echo(
        f"موضوع: {row['target_topic']}  |  وضعیت: {row['status']}  |  فاز: {row['current_phase']}"
    )

    counts = {
        "عنوان خام": "SELECT COUNT(*) c FROM raw_products WHERE run_id=?",
        "مرتبط": "SELECT COUNT(*) c FROM raw_products WHERE run_id=? AND is_relevant=1",
        "مرزی (شیت C)": "SELECT COUNT(*) c FROM raw_products WHERE run_id=? AND is_relevant IS NULL",
        "محصول یکپارچه": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=?",
        "نیازمند بازبینی": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND needs_review=1",
        "has_suggest": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND suggest_status='has_suggest'",
        "no_suggest": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND suggest_status='no_suggest'",
        "در صف ساجست": "SELECT COUNT(*) c FROM canonical_products WHERE run_id=? AND suggest_status IS NULL",
    }
    for label, query in counts.items():
        typer.echo(f"  {label}: {conn.execute(query, (rid,)).fetchone()['c']}")
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
            f"{row['run_id']}  |  {row['target_topic']}  |  {row['status']}"
            f"  |  فاز {row['current_phase']}"
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
