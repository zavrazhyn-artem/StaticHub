# Intent-Cutoff Layer — Design

**Date:** 2026-05-24
**Status:** Approved design, pending implementation plan
**Scope:** RC1 of the AI raid-analysis feedback backlog — eliminate intentional-death contamination.

---

## 1. Problem & Context

Two rounds of user feedback on the Gemini raid-analysis reports surfaced four root causes (RC1–RC4). This spec covers **RC1 only**.

**RC1 — Intentional-death contamination.** When the raid leader calls a wipe, players stop trying, release, or walk into a mechanic (e.g. Darkwell on Midnight Falls). Those deaths are not mistakes, yet they get counted in death attribution, mechanic-failure counts, recurring-failure severity, and they also poison the *tail* of every other metric (rotation CPM, buff/debuff uptime, damage-taken) because people stop pressing buttons. The user's words: this makes the analysis *"inherently incorrect."*

### What already exists (and why it fails)

- **`WipeDetector`** (`app/Services/Analysis/WipeDetector.php`) tags deaths `wipe_called` / `tank_loss_cascade` / `mechanic_oneshot` / `normal`. It detects a **tight cluster of ≥5 deaths within 3s** (`CLUSTER_WINDOW_MS = 3000`). The Darkwell case fails structurally: post-wipe deaths *trickle* over 10–20s, so no 3s cluster forms and nothing is tagged. It also only flags individual deaths — it never establishes a **cutoff time**.
- **`AiDeathSuppressor`** (`suppress($preprocessed, $cutoff)`, runs *after* `analyze()`, AI-payload only) does a crude token-saving trim: keep the first-N (N=1–5) deaths chronologically per pull and drop pulls under 20s. It is **not** intent-aware — it keeps the first N regardless of whether a wipe was called, and trims the tail by count, not by a cutoff time.
- **The actual bug:** `DeathAttributionBuilder` computes an `attribution` for **every** death in `encounters[].fights[].deaths[]`, and the prompt mirrors them all 1:1 into `key_deaths` (gemini_main_report.txt line 332). The existing `death_tag_distribution` separation only feeds **one** Raid-Overview paragraph (line 319) — it never removes wipe-call deaths from the per-pull attribution or `key_deaths`. So intentional deaths still get coached as mistakes.

### Decisions locked during brainstorming

| Decision | Choice |
|---|---|
| Source of truth | **Phased: anchor-heuristic now → addon live-marker later.** Consumption side is shared, so phase 2 does not rework phase 1. |
| Cutoff scope | **Whole tail of the pull** — deaths + rotation + uptime + damage-taken, all truncated at the cutoff. |
| Transparency | **Separate bucket + cutoff time shown** — excluded deaths are visible (collapsed, labelled), not silently dropped, so the RL can verify the heuristic. |

---

## 2. Goals & Non-Goals

**Goals**
- A single, source-agnostic per-pull cutoff abstraction that all death/metric analysis respects.
- Phase 1: zero-toil heuristic (anchor + loosened terminal-cascade) that fixes the Darkwell-class bug.
- Phase 2: true ground-truth signal via the existing BlastR addon, plus a manual override/review surface.
- The AI never frames an excluded death as a mistake.

**Non-Goals (this spec)**
- RC2 (mechanic grounding), RC3 (hallucination/obsolete advice), RC4 (explanation quality). Tracked separately.
- Individual mid-fight intentional deaths (e.g. a single player suiciding to reset a debuff). The dominant case is the *mass* wipe-call → one cutoff timestamp per pull. Individual intentional deaths are out of scope.
- The player movement-map / spatial initiative (next design session; builds on this layer).

**Build sequencing.** Phase 1a (death partition + excluded bucket + transparency + prompt rule — pure in-memory, shippable, this plan) → Phase 1b (metric-tail truncation via truncated WCL re-fetch — own plan) → Phase 2 (addon marker + alignment + review/override UI — own plan).

---

## 3. Core Abstraction — `intent_cutoff_ms` + Resolver

Every **wiped** pull resolves to a single `cutoff_ms` (milliseconds relative to fight start). After that point the whole pull is truncated. **Kills never receive a cutoff.**

A new `IntentCutoffResolver` (Service, `app/Services/Analysis/`) returns per fight:

```
{ cutoff_ms: int|null, source: string, confidence: 'high'|'medium'|'low'|null }
```

**Source priority (first non-null wins):**

```
manual_override  >  addon_marker  >  anchor+cascade  >  cascade_only  >  none
```

- Phase 1 implements `anchor+cascade` and `cascade_only`.
- Phase 2 adds `addon_marker` and `manual_override`.
- The **consumption side is written once** against `cutoff_ms`; new sources slot into the resolver without touching it.

---

## 4. Phase 1 — Anchor + Loosened Terminal-Cascade

**Config:** per-static `intent_anchor_character_ids` (1–2 characters), stored in static settings. Default = the static leader's main character.

**Per wiped pull** (boss HP above the progression threshold; near-kills are left untouched):

- **Signal A — anchor death:** the timestamp of the anchor player's death in this pull (earliest if multiple anchors).
- **Signal B — terminal-abandon run:** the last contiguous run of deaths leading to fight end, where consecutive deaths are separated by less than `G` seconds. The **start** of that run is the candidate. This is the Darkwell fix — the window is wider than 3s and anchored to the end of the fight rather than to a dense spike.
- **Candidate cutoff:** `min(A, B)` among whichever signals are present.
- **Corroboration guard (required):** apply the cutoff only if, after the candidate, the fight ends within `T` seconds **and** boss HP does not drop further (the raid did not recover). If the anchor died early but the fight continued and HP kept dropping, the deaths were real → **no cutoff**.
- **Confidence:**
  - `high` — A and B coincide.
  - `medium` — B only (cascade without anchor).
  - `low` — A only, no corroborating cascade → **not auto-applied**; flagged for manual review (phase 2 surface).

**Thresholds** (`G`, `T`, minimum run length, progression HP threshold) are **tuned against real guild logs**, not guessed up front. `WipeDetector`'s existing constants (`BOSS_HP_THRESHOLD_PCT = 30`) are a starting reference.

`WipeDetector` is extended with the terminal-abandon detector (loosened cascade) and feeds its output to `IntentCutoffResolver`. Its existing tight-cluster classification stays for `mechanic_oneshot` / `tank_loss_cascade`.

---

## 5. Consumption — Truncating the Whole Tail

**Death partition (Phase 1a) — pure in-memory, no extra WCL call.** Deaths are already fully fetched. `WipeDetector::tag()` runs in `TacticalDataAnalyzer::analyze()` (line 63) on the *grouped* `$logData['deaths'][boss][try_N]` **before** the encounter loop (line 71) and before `applyDeathAttribution()` (line 137). So we insert one step right after tagging:

- `applyIntentCutoff(&$logData, $anchorNames)` — for each **wiped** fight, resolve `cutoff_ms`, then **move** post-cutoff deaths out of `$logData['deaths'][boss][try_N]` into `$logData['excluded_deaths'][boss][try_N]`, and record `$logData['intent_cutoffs'][fight_id] = {cutoff_ms, source, confidence, excluded_count}`.
- Because every downstream consumer (`analyzeEncounter`, `FightBreakdownBuilder`, mechanic-failures, `InsightsBuilder`, `DeathAttributionBuilder`, `buildDeathTagDistribution`) reads the partitioned `$logData['deaths']`, **none of them needs to change** — they automatically see only the kept deaths.
- The excluded bucket + cutoff metadata are surfaced into each fight (`fights[].excluded_deaths`, `fights[].intent_cutoff`) via `FightBreakdownBuilder` so the AI/UI can render the transparent section.

**Metric-tail truncation (Phase 1b — separate plan).** Truncating rotation/CPM, buff/debuff uptime and damage-taken needs the truncated WCL table data. Confirmed against the WCL v2 schema: the `table` field **does** accept `startTime`/`endTime` (Float, report-relative ms; the two must differ) alongside `fightIDs` — the existing `getPerFightCastsSummary()` alias-batching pattern is the template. For cutoff pulls we re-fetch their tables with `endTime = fightStart + cutoff_ms` and recompute per-player metrics. This touches `WclService` fetch orchestration + `RotationAnalyzer` recompute, so it is carved into its own plan after Phase 1a's death partition ships and is validated. (WCL's native `wipeCutoff: Int` arg was considered and rejected — it truncates at the Nth death regardless of *intent*, which is exactly the conflation we are removing.)

---

## 6. Transparency & AI Prompt Rule

- `pull_breakdown` blocks gain `intent_cutoff` (time + `source` + `confidence`) and `excluded_deaths[]`.
- The report renders a **collapsed, labelled** section per affected pull, e.g. *"Після команди вайп (4:32) — 5 смертей, не враховано"*, including the source/confidence so the RL can spot a misfire.
- **Prompt rule** (added to `gemini_main_report.txt` and `gemini_player_report.txt`): `excluded_deaths` are **never** mistakes — they must not appear in attribution, `recurring_failures`, or coaching narrative. They may only be summarised in the dedicated excluded section.

---

## 7. Phase 2 — Addon Marker + Alignment + Review UI

**Addon.** A keybind/macro registers a wipe-mark capturing `(encounterID, pullStart_serverTime, offset_seconds)`. Shipped to the site through the addon's existing data channel (no AceComm; go:embed bundle).

**Alignment (addon → WCL).** Match the marked pull to a WCL fight by `encounterID` + nearest `startTime`; `cutoff_ms = offset_seconds * 1000`. The pull-ordinal ↔ WCL-fight mapping is the main technical risk — resolved via encounter ID + start-time proximity.

**Persistence.** New table `pull_intent_markers`:

```
id, tactical_report_id (FK), wcl_fight_id, cutoff_ms,
source ('addon' | 'manual'), created_by (nullable user id),
created_at, updated_at
```

Wired to a model via a new `PullIntentMarkerBuilder` (per CLAUDE.md builder convention). A Service ingests addon markers and persists manual edits (Controller → Service → Builder).

**Review UI.** A small panel on the report showing per-pull cutoff + source + confidence, with controls to adjust or clear a cutoff. A manual edit writes a `manual` marker = highest resolver priority. This panel is also the fallback when the phase-1 heuristic returns `low` confidence.

---

## 8. Code Placement (per CLAUDE.md)

| Concern | Location |
|---|---|
| Resolve per-pull cutoff from all sources | `app/Services/Analysis/IntentCutoffResolver.php` (new Service) |
| Terminal-abandon (loosened cascade) detection | extend `app/Services/Analysis/WipeDetector.php` |
| Resolve + truncate + build `excluded_deaths` | `app/Services/Analysis/TacticalDataAnalyzer.php` |
| Truncated WCL table fetch (`endTime`) | `app/Services/Analysis/WclService.php` + `app/Helpers/WclQueryBuilder.php` |
| Anchor config | static settings (StaticGroup domain) |
| Marker persistence (phase 2) | migration `pull_intent_markers` + `app/Builders/PullIntentMarkerBuilder.php` + model |
| Marker ingest + manual edits (phase 2) | Service in `Services/Analysis/` + Controller (Controller → Service → Builder) |
| Review panel (phase 2) | Vue component under `resources/js/Components/` (reuse UI primitives) |
| Prompt rule | `resources/prompts/gemini_main_report.txt`, `gemini_player_report.txt` |

All new/changed files respect the 350-line limit; split by sub-concern if exceeded.

---

## 9. Success Criteria

- The Darkwell pull (Midnight Falls, the reported case) no longer counts post-wipe deaths; they appear only in the excluded bucket with a visible cutoff time.
- Rotation/uptime/damage-taken for cutoff pulls reflect only the pre-cutoff window.
- No excluded death appears in attribution, recurring-failures, or coaching text.
- Phase 2: a single in-game keybind produces an accurate cutoff with no post-raid effort; the RL can override any cutoff from the report.
- Adding the addon/manual sources requires **no change** to the consumption side.

---

## 10. Open Questions / To Tune During Implementation

- Exact thresholds: `G` (inter-death gap for the terminal run), `T` (fight-end window for corroboration), minimum run length, progression HP threshold — calibrated on real guild logs.
- Whether `cascade_only` (medium confidence, no anchor) auto-applies or also routes to manual review until trust is established.
- Server-time vs `GetTime()` basis for the addon offset, and clock-skew tolerance in alignment.

---

## 11. Testing

- Unit: `IntentCutoffResolver` source-priority resolution; corroboration guard (early-anchor-death-but-recovered → no cutoff); terminal-run detection on trickle deaths.
- Unit: `WipeDetector` terminal-abandon detector against a synthetic Darkwell-style death sequence.
- Integration: `TacticalDataAnalyzer` truncation produces `excluded_deaths` and excludes them from attribution/recurring-failures; truncated table fetch clips metrics.
- Fixture: a real anonymised WCL pull with a known wipe-call, asserting the cutoff lands at the right second.
