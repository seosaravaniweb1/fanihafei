"""فاز ۲ — Entity Resolution (حساس‌ترین فاز).

سه مرحله، به ترتیب داکیومنت:

* **A — بلاک‌بندی:** به‌جای O(n²)، عنوان‌ها بر اساس نادرترین توکن‌هایشان
  گروه‌بندی می‌شوند و مقایسه فقط داخل گروه انجام می‌شود.
* **B — استخراج توکن تمایزدهنده:** :mod:`core.entities` (قانون‌محور، بدون هزینه).
* **C — قانون ادغام:**

  .. code-block:: text

     اگر similarity(base_title) < 0.80  →  ادغام نکن
     اگر similarity >= 0.80:
         entity_tokens یکسان                  →  ادغام (confidence: high)
         یکی از دو طرف entity_tokens خالی      →  داوری LLM
         entity_tokens متفاوت و هر دو پر       →  ادغام نکن (خط قرمز)
         LLM گفت «مطمئن نیستم»                 →  needs_review، ادغام نکن

**قانون طلایی:** در حالت شک ادغام نکن. یک merge اشتباه، داده را برای همیشه
خراب می‌کند.
"""

from __future__ import annotations

import json
import math
import sqlite3
from collections import Counter, defaultdict
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Sequence

from ..core import db, normalizer, similarity
from ..core.config import Config
from ..core.entities import EntityExtractor, EntityResult

HIGH_CONFIDENCE = 0.95
LLM_MERGE_CONFIDENCE = 0.75
REVIEW_BELOW = 0.80


@dataclass
class Candidate:
    """یک عنوان خام آماده‌ی مقایسه."""

    raw_id: int
    raw_title: str
    display_title: str
    normalized_title: str
    entity: EntityResult

    @property
    def base_title(self) -> str:
        return self.entity.base_title or self.normalized_title

    @property
    def tokens(self) -> list[str]:
        return self.entity.base_tokens or self.normalized_title.split()


@dataclass
class Decision:
    a: int
    b: int
    score: float
    verdict: str  # merge | no_merge | unsure
    confidence: float
    reason: str


@dataclass
class ResolveStats:
    raw_count: int = 0
    pairs_compared: int = 0
    merged_pairs: int = 0
    red_lines: int = 0
    llm_pairs: int = 0
    unsure: int = 0
    canonical_count: int = 0
    needs_review: int = 0
    decisions: list[Decision] = field(default_factory=list)

    def render(self) -> str:
        return (
            f"فاز ۲ — ورودی: {self.raw_count}، مقایسه: {self.pairs_compared}، "
            f"ادغام: {self.merged_pairs}، خط قرمز: {self.red_lines}، "
            f"داوری LLM: {self.llm_pairs}، مبهم: {self.unsure}، "
            f"محصول نهایی: {self.canonical_count} (بازبینی: {self.needs_review})"
        )


# ---------------------------------------------------------------------------
# مرحله A — بلاک‌بندی
# ---------------------------------------------------------------------------


def build_blocks(candidates: Sequence[Candidate], top_tokens: int = 2) -> dict[str, list[int]]:
    """گروه‌بندی بر اساس نادرترین توکن‌های هر عنوان (کلید بلاک)."""
    document_freq: Counter[str] = Counter()
    for candidate in candidates:
        document_freq.update(set(candidate.tokens))

    total = max(len(candidates), 1)
    blocks: dict[str, list[int]] = defaultdict(list)
    for index, candidate in enumerate(candidates):
        tokens = sorted(
            set(candidate.tokens),
            key=lambda t: (math.log(total / (1 + document_freq[t])), t),
            reverse=True,
        )
        if not tokens:
            blocks[f"__empty__{index}"].append(index)
            continue
        for token in tokens[: max(1, top_tokens)]:
            blocks[token].append(index)
    return blocks


def candidate_pairs(
    candidates: Sequence[Candidate], top_tokens: int = 2, max_block_size: int = 400
) -> list[tuple[int, int]]:
    pairs: set[tuple[int, int]] = set()
    for members in build_blocks(candidates, top_tokens).values():
        if len(members) < 2 or len(members) > max_block_size:
            continue
        for i in range(len(members)):
            for j in range(i + 1, len(members)):
                a, b = sorted((members[i], members[j]))
                pairs.add((a, b))
    return sorted(pairs)


# ---------------------------------------------------------------------------
# مرحله C — قانون ادغام
# ---------------------------------------------------------------------------


def classify_pair(a: Candidate, b: Candidate, threshold: float = 0.80) -> tuple[str, float, float, str]:
    """خروجی: ``(verdict, score, confidence, reason)`` — بدون کال LLM.

    ``verdict`` یکی از ``merge`` / ``no_merge`` / ``ask_llm`` است.
    """
    score = similarity.title_similarity(a.base_title, b.base_title)
    if score < threshold:
        return "no_merge", score, 0.0, "شباهت عنوان پایه کمتر از آستانه"

    key_a, key_b = a.entity.key, b.entity.key
    if key_a == key_b:
        return "merge", score, HIGH_CONFIDENCE, "توکن‌های تمایزدهنده یکسان"
    if not key_a or not key_b:
        return "ask_llm", score, 0.0, "یکی از دو طرف توکن تمایزدهنده ندارد"
    return "no_merge", score, 0.0, "خط قرمز: توکن‌های تمایزدهنده متفاوت"


# ---------------------------------------------------------------------------
# خوشه‌بندی با رعایت خط قرمز
# ---------------------------------------------------------------------------


class SafeClusters:
    """اتحاد حریصانه‌ای که هرگز دو عضو «خط قرمز» را در یک خوشه نمی‌گذارد."""

    def __init__(self, size: int) -> None:
        self.members: list[set[int]] = [{i} for i in range(size)]
        self.owner: list[int] = list(range(size))
        self.forbidden: set[tuple[int, int]] = set()

    def forbid(self, a: int, b: int) -> None:
        self.forbidden.add(tuple(sorted((a, b))))  # type: ignore[arg-type]

    def _blocked(self, group_a: set[int], group_b: set[int]) -> bool:
        for x in group_a:
            for y in group_b:
                if tuple(sorted((x, y))) in self.forbidden:
                    return True
        return False

    def union(self, a: int, b: int) -> bool:
        root_a, root_b = self.owner[a], self.owner[b]
        if root_a == root_b:
            return True
        group_a, group_b = self.members[root_a], self.members[root_b]
        if self._blocked(group_a, group_b):
            return False
        group_a |= group_b
        for member in group_b:
            self.owner[member] = root_a
        self.members[root_b] = set()
        return True

    def clusters(self) -> list[list[int]]:
        return [sorted(group) for group in self.members if group]


# ---------------------------------------------------------------------------
# اجرای فاز
# ---------------------------------------------------------------------------


def load_candidates(
    conn: sqlite3.Connection, run_id: str, extractor: EntityExtractor
) -> list[Candidate]:
    rows = db.relevant_raw_products(conn, run_id)
    candidates: list[Candidate] = []
    with db.transaction(conn):
        for row in rows:
            entity = extractor.extract(row["raw_title"] or "")
            db.set_raw_entity_tokens(conn, int(row["id"]), entity.tokens)
            candidates.append(
                Candidate(
                    raw_id=int(row["id"]),
                    raw_title=row["raw_title"] or "",
                    display_title=row["display_title"] or row["raw_title"] or "",
                    normalized_title=row["normalized_title"] or "",
                    entity=entity,
                )
            )
    return candidates


def resolve(
    candidates: Sequence[Candidate],
    threshold: float = 0.80,
    top_tokens: int = 2,
    llm_client: Any | None = None,
    max_llm_pairs: int = 200,
    verbose: bool = True,
) -> tuple[list[list[int]], dict[int, float], set[int], ResolveStats]:
    """هسته‌ی خالص فاز ۲ — بدون دیتابیس، تا مستقیم قابل تست باشد."""
    stats = ResolveStats(raw_count=len(candidates))
    clusters = SafeClusters(len(candidates))
    pairs = candidate_pairs(candidates, top_tokens)
    stats.pairs_compared = len(pairs)

    accepted: list[tuple[int, int, float]] = []
    pending_llm: list[tuple[int, int, float]] = []
    review: set[int] = set()

    for a, b in pairs:
        verdict, score, confidence, reason = classify_pair(candidates[a], candidates[b], threshold)
        if verdict == "merge":
            accepted.append((a, b, confidence))
            stats.decisions.append(Decision(a, b, score, "merge", confidence, reason))
        elif verdict == "ask_llm":
            pending_llm.append((a, b, score))
        else:
            if reason.startswith("خط قرمز"):
                clusters.forbid(a, b)
                stats.red_lines += 1
            stats.decisions.append(Decision(a, b, score, "no_merge", 0.0, reason))

    # داوری LLM فقط برای حالت «یک طرف خالی»
    if pending_llm:
        pending_llm.sort(key=lambda item: item[2], reverse=True)
        selected = pending_llm[:max_llm_pairs]
        skipped = pending_llm[max_llm_pairs:]
        for a, b, score in skipped:
            review.update({a, b})
            stats.decisions.append(
                Decision(a, b, score, "no_merge", 0.0, "سقف داوری LLM پر شد — بازبینی دستی")
            )
        verdicts = _arbitrate(candidates, selected, llm_client, verbose=verbose)
        stats.llm_pairs = len(selected) if llm_client is not None else 0
        for (a, b, score), outcome in zip(selected, verdicts):
            decision = outcome.get("decision", "unsure")
            confidence = float(outcome.get("confidence", 0.0))
            reason = outcome.get("reason", "")
            if decision == "merge" and confidence >= 0.5:
                accepted.append((a, b, min(confidence, LLM_MERGE_CONFIDENCE)))
                stats.decisions.append(
                    Decision(a, b, score, "merge", min(confidence, LLM_MERGE_CONFIDENCE), f"LLM: {reason}")
                )
            elif decision == "no_merge":
                stats.decisions.append(Decision(a, b, score, "no_merge", confidence, f"LLM: {reason}"))
            else:
                stats.unsure += 1
                review.update({a, b})
                stats.decisions.append(
                    Decision(a, b, score, "unsure", confidence, f"LLM مطمئن نیست: {reason}")
                )

    # اتحاد حریصانه از قوی‌ترین به ضعیف‌ترین
    accepted.sort(key=lambda item: item[2], reverse=True)
    edge_confidence: dict[int, float] = {}
    for a, b, confidence in accepted:
        if clusters.union(a, b):
            stats.merged_pairs += 1
            for node in (a, b):
                edge_confidence[node] = min(edge_confidence.get(node, 1.0), confidence)
        else:
            review.update({a, b})

    groups = clusters.clusters()
    stats.canonical_count = len(groups)
    return groups, edge_confidence, review, stats


def _arbitrate(
    candidates: Sequence[Candidate],
    pairs: Sequence[tuple[int, int, float]],
    llm_client: Any | None,
    batch_size: int = 15,
    verbose: bool = True,
) -> list[dict]:
    """داوری LLM. بدون کلاینت، همه‌چیز «unsure» است (یعنی ادغام نمی‌شود)."""
    if not pairs:
        return []
    if llm_client is None or not getattr(llm_client, "available", False):
        return [
            {"decision": "unsure", "confidence": 0.0, "reason": "LLM در دسترس نیست"}
            for _ in pairs
        ]

    outcomes: list[dict] = []
    for start in range(0, len(pairs), batch_size):
        chunk = pairs[start : start + batch_size]
        payload = [
            {
                "pair_id": f"{a}-{b}",
                "title_a": candidates[a].display_title,
                "title_b": candidates[b].display_title,
                "tokens_a": candidates[a].entity.tokens,
                "tokens_b": candidates[b].entity.tokens,
            }
            for a, b, _ in chunk
        ]
        try:
            answers = llm_client.judge_merges(payload)
        except Exception as exc:  # pragma: no cover - وابسته به شبکه
            if verbose:
                print(f"  هشدار: داوری LLM ناموفق بود ({exc}) — این جفت‌ها بازبینی دستی می‌شوند.")
            answers = {}
        for item in payload:
            outcomes.append(
                answers.get(
                    item["pair_id"],
                    {"decision": "unsure", "confidence": 0.0, "reason": "پاسخی دریافت نشد"},
                )
            )
    return outcomes


def pick_canonical_title(
    members: Sequence[Candidate], norm_config: normalizer.NormalizerConfig
) -> str:
    """کامل‌ترین عنوان خوشه: بیشترین توکن معنادار، سپس بلندترین، سپس الفبایی."""
    def sort_key(candidate: Candidate) -> tuple[int, int, str]:
        meaningful = normalizer.meaningful_tokens(candidate.raw_title, norm_config)
        return (len(meaningful), len(candidate.display_title), candidate.display_title)

    best = max(members, key=sort_key)
    return best.display_title.strip() or best.raw_title.strip()


def run(
    conn: sqlite3.Connection,
    run_id: str,
    config: Config,
    llm_client: Any | None = None,
    verbose: bool = True,
) -> ResolveStats:
    norm_config = normalizer.config_from_mapping(config.normalizer)
    extractor = EntityExtractor(
        norm_config, extra_names=frozenset(config.get("normalizer.extra_names", []) or [])
    )
    candidates = load_candidates(conn, run_id, extractor)
    if not candidates:
        raise ValueError(
            "هیچ عنوان مرتبطی برای فاز ۲ وجود ندارد. اول فاز ۱ را اجرا کنید."
        )

    groups, edge_confidence, review, stats = resolve(
        candidates,
        threshold=float(config.get("resolve.similarity_threshold", 0.80)),
        top_tokens=int(config.get("resolve.blocking_top_tokens", 2)),
        llm_client=llm_client if config.get("resolve.llm_arbitration", True) else None,
        max_llm_pairs=int(config.get("resolve.max_llm_pairs", 200)),
        verbose=verbose,
    )

    used_titles: set[str] = set()
    with db.transaction(conn):
        for group in groups:
            members = [candidates[i] for i in group]
            title = _unique_title(pick_canonical_title(members, norm_config), members, used_titles)
            used_titles.add(title)

            tokens: list[str] = []
            for member in members:
                for token in member.entity.tokens:
                    if token not in tokens:
                        tokens.append(token)

            confidence = 1.0 if len(group) == 1 else min(
                edge_confidence.get(i, HIGH_CONFIDENCE) for i in group
            )
            needs_review = bool(review & set(group)) or (
                len(group) > 1 and confidence < REVIEW_BELOW
            )
            if needs_review:
                stats.needs_review += 1

            canonical_id = db.insert_canonical(
                conn, run_id, title, tokens, round(confidence, 4), needs_review
            )
            for member in members:
                db.map_raw_to_canonical(conn, member.raw_id, canonical_id)

    # لاگ تصمیم‌ها برای ممیزی دستی — ادغام اشتباه باید قابل ردیابی باشد.
    log_dir = Path(config.db_path).parent / "review"
    log_dir.mkdir(parents=True, exist_ok=True)
    path = dump_decisions(stats, candidates, str(log_dir / f"{run_id}_merge_decisions.json"))
    if verbose:
        print(f"  لاگ تصمیم‌های ادغام: {path}")
    return stats


def _unique_title(title: str, members: Sequence[Candidate], used: set[str]) -> str:
    """جلوگیری از تخطی ``UNIQUE(run_id, canonical_title)`` به‌شکل قابل‌پیش‌بینی."""
    if title not in used:
        return title
    tokens = " ".join(sorted({t for m in members for t in m.entity.tokens}))
    if tokens and f"{title} {tokens}" not in used:
        return f"{title} {tokens}"
    index = 2
    while f"{title} #{index}" in used:
        index += 1
    return f"{title} #{index}"


def dump_decisions(stats: ResolveStats, candidates: Sequence[Candidate], path: str) -> str:
    """ثبت تصمیم‌های فاز ۲ برای ممیزی دستی."""
    payload = [
        {
            "a": candidates[d.a].raw_title,
            "b": candidates[d.b].raw_title,
            "score": d.score,
            "verdict": d.verdict,
            "confidence": d.confidence,
            "reason": d.reason,
        }
        for d in stats.decisions
    ]
    with open(path, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, ensure_ascii=False, indent=2)
    return path
