# Empirical Mechanic Baselines — Design Spec

- **Date:** 2026-05-25
- **Status:** Approved (design), pending implementation plan
- **Author:** Artem Zavrazhyn + Claude
- **Related:** RC2 in `project_ai_feedback_backlog`; precedent = `RefreshSpecBaselinesCommand` / `RotationAnalyzer` (player rotations); follow-on to RC1 intent-cutoff layer.

## 1. Problem

The deterministic mechanic-failure detection in `TacticalDataAnalyzer::applyTypeSpecificEvidence()`
judges players against **hardcoded magic numbers** and a **"participation = failure"** anti-pattern:

- `tank_swap`: `$threshold = $mechanic['stack_swap_threshold'] ?? 7;` then `if ($max > $threshold) players[]+1`.
  Two tanks swapping at a normal 12–13 stacks both exceed 7 → both flagged **every pull**. The AI then
  scolds them to "swap at 5–7". This is the live, user-reported bug (the user is a tank).
- `absorb_shield`, `soak`, `tether`, etc.: "every player who received the debuff → `players[]+1`",
  regardless of whether anyone actually failed.
- `interrupt`: `miss_threshold_pct ?? 20`; `absorb`: `* 0.9` / `* 1.5` — all unanchored guesses.

The number `7` (and friends) was a placeholder nobody kept in sync with reality. The root issue is the
same as RC2 ("AI/our layer has no ground truth, fills gaps with guesses"): the *count* must stay
deterministic (LLMs hallucinate when they count), but its **thresholds must be learned from reality**.

### The precedent we are cloning

The team already solved exactly this for **player rotations**:

- `app/Console/Commands/RefreshSpecBaselinesCommand.php` (`refresh:spec-baselines`) harvests top WCL
  parses (98–100 percentile) via `WclService::fetchCharacterRankings()` + casts table, extracts per-ability
  CPM median/p95, and **merges an `empirical:` block** into `resources/spec-baselines/{class-spec}.yaml`,
  preserving authored `rotation_checks`. JSON cache in `storage/app/spec-baseline-cache/`, 7-day TTL,
  `--pace` between WCL calls, `--dry-run`/`--force`.
- `RotationAnalyzer` + `SpecBaselineLoader` compare each player against those empirical numbers.

This spec points the **same engine at boss mechanics**.

## 2. Goals / Non-goals

**Goals**
- Learn the real per-mechanic behavior of top kills (swap cadence, dispel latency, kick coverage,
  clear time, soakers-per-event, avoidable-hit baseline, movement/grouping) and store it per boss/difficulty.
- Rewrite detection to flag a player **only when this raid's behavior falls outside the learned envelope**.
- Make baseline-vs-detection drift **structurally impossible** (single shared normalizer).
- Kill the "participation = failure" anti-pattern globally; safe default = "not modeled → never flag".
- Add a movement model: per-player RDP-simplified tracks + stack/spread grouping timeline, with a
  learned canonical grouping pattern per phase.

**Non-goals**
- No continuous interpolated movement paths (RDP keeps only real sample points; gaps stay honest).
- No storage of strangers' individual top-kill tracks (only the comparable aggregate grouping pattern).
- No automatic/scheduled harvest in v1 — manual CLI command (full multi-hour run is operator-triggered).
- Not re-touching RC1 (intent-cutoff), RC3 (obsolete advice), RC4 (contradictions) here.

## 3. Approach — C: reuse extraction + shared `MechanicMetrics` normalizer

Extract once using the **existing** per-log extraction (same path `ai:diagnose-data` runs), then route
**both** the top-kill harvest **and** the live raid through one shared `MechanicMetricsNormalizer` that
emits a uniform per-mechanic-type metric envelope. Baselines = aggregate of normalizer output over N top
kills. Detection = compare live normalizer output vs the learned envelope. Because the same class defines
*what we measure* on both sides, the `?? 7` class of drift bug cannot return.

(Rejected: B — dedicated lightweight harvest extractors — because duplicate extraction logic is precisely
how the current drift bug arose.)

## 4. Architecture & data flow

**Five components:**

1. **`MechanicMetricsNormalizer`** (new, `app/Services/Analysis/`) — linchpin. Input: raw per-log data our
   extractors already produce (`swap_timing`, `debuff_stacks`, `interrupt_analysis`, soak/death events,
   coordinates). Output: uniform metric envelope per mechanic type for **one** log. Called by both harvest
   and live detection.
2. **`RefreshMechanicBaselinesCommand`** (`refresh:mechanic-baselines`) — clone of the spec-baselines
   command. Harvests top-kill logs, aggregates, merges `empirical:` block.
3. **`empirical:` block** in `resources/boss-timelines/{season}/{difficulty}/{slug}.yml`.
4. **Detection rewrite** in `applyTypeSpecificEvidence` + new **`MechanicBaselineLoader`**
   (clone of `SpecBaselineLoader`).
5. **Movement module**: `MovementTrackBuilder` (RDP) + `GroupingAnalyzer` (clustering), feeding the
   normalizer as a movement metric type.

**Data flow (both directions through the same normalizer):**

```
HARVEST:  WCL rankings → unique top-kill (report, fight)
          → existing WclService extraction → MechanicMetricsNormalizer
          (+ MovementTrackBuilder / GroupingAnalyzer)
          → aggregate median/p5/p95 across N kills → empirical: YAML block

LIVE:     our raid log → existing extraction → MechanicMetricsNormalizer
          → MechanicBaselineLoader compares vs envelope
          → flag ONLY out-of-envelope behavior
```

## 5. `MechanicMetricsNormalizer` contract

**Principle 1 — uniform shape.** Every metric stored as `{median, p5, p95, sample}`.

**Principle 2 — explicit flag rule per type** (direction + percentile boundary). If a type has no learned
metric → emit a descriptive note and **flag nobody**.

| Type | Learned from top kills | Flag rule (when a player/event is "at fault") |
|---|---|---|
| `tank_swap` | `swap_at_stacks`, `max_stacks_held`, `swap_gap_ms`; derived `lethal_stacks` | swap later than p95 **or** death at stacks ≥ lethal. NOT for merely reaching a high max |
| `interrupt` / kick | `coverage_pct`, `miss_rate_pct` | our coverage < p5 (or miss_rate > p95) |
| `dispel` | `dispel_latency_ms`, `dispel_coverage_pct` | latency > p95 or coverage < p5 |
| `absorb_shield` / debuff clear | `clear_time_ms` | our clear time > p95 |
| `soak` | `soakers_per_event` | flag the **event** (too few soakers) or the missing assigned soaker — not every participant |
| `positioning` / `dodge` | `avoidable_hits_per_player` | our value > p95. If top kills eat it just as often → not a positioning failure (fixes "unavoidable AOE labeled positioning") |
| `orb_management` | `overlap_rate` | our value > p95 |
| all other types | descriptive note | never flag (informational) until modeled |

**Small extraction additions required (point additions, not rewrites):**
- `tank_swap.swap_at_stacks` needs the stack count at the moment of swap (tank handoff). Add to the
  swap-timing extraction in `WclReportParserHelper` (currently emits gaps + late_swaps only).
- `dispel` needs a dispel-latency extractor (currently only interrupt is extracted).

## 6. `empirical:` YAML schema + consumption

Difficulty is already encoded in the file path, so the harvester appends a top-level `empirical:` block
keyed by mechanic name; the authored `mechanics:` list is never touched.

```yaml
boss: 'Vaelgor & Ezzorak'
mechanics:                       # authored — harvester never touches
  - name: Vaelwing
    type: tank_swap
    ability_ids: [1265131, 1265146]
    severity: critical
    description: '...'

empirical:                       # written by refresh:mechanic-baselines
  difficulty: mythic
  kills_sampled: 12
  fetched_at: '2026-05-25'
  mechanics:
    Vaelwing:
      type: tank_swap
      sample: 12
      lethal_stacks: 17          # derived: stacks at which top tanks died / would die
      metrics:
        swap_at_stacks:  { median: 13, p5: 11, p95: 15, sample: 24 }
        max_stacks_held: { median: 14, p5: 12, p95: 16, sample: 24 }
        swap_gap_ms:     { median: 800, p5: 400, p95: 1600, sample: 22 }
    "Heaven's Glaives":
      type: positioning
      sample: 12
      metrics:
        avoidable_hits_per_player: { median: 0, p5: 0, p95: 1, sample: 12 }
  movement:
    rdp_epsilon: 2.5
    cluster_radius_yd: 8
    phases:
      P1:
        dominant_state: stacked
        stacked_pct:      { median: 82, p5: 70, p95: 91, sample: 12 }
        spread_radius_yd: { median: 6,  p5: 4,  p95: 9,  sample: 12 }
      P2_naarus_lament:
        dominant_state: spread
        cluster_count:    { median: 5,  p5: 4,  p95: 6,  sample: 12 }
```

**Consumption:**
1. `MechanicBaselineLoader::load(bossSlug, difficulty)` returns the `empirical:` block.
2. In `applyTypeSpecificEvidence`, per mechanic: if an envelope exists → apply the type's flag rule against
   p5/p95; if absent → informational default, **no magic numbers, flag nobody**.
3. `players[]` receives only out-of-envelope offenders, plus a context note:
   *"top kills swap at 13 (p95 15); your tank swapped at 19 → late."*

**Graceful degradation:** a boss with no harvested baseline → detection is informational-only for it
(strictly better than today: zero false flags). Coverage grows boss-by-boss as harvest runs.

**AI payload:** the envelope numbers are also placed into the `mechanic_failures` payload so the AI states
"top kills swap at 13, you at 19" instead of inventing thresholds (kills the hallucinated-threshold half
of RC3 for mechanics).

## 7. Movement module (RDP + grouping)

**Point source:** WCL attaches x,y to cast events (+ resource events). Extend the existing cast-coordinate
fetch (`WclService`, already uses `includeResources`) to gather **all** friendly casts with coordinates per
fight → per-player time-ordered `{t, x, y}`.

**Step 1 — raw polyline:** sort each player's samples by time.

**Step 2 — RDP (`MovementTrackBuilder`):** Ramer-Douglas-Peucker with tolerance ε (yards, ~2.5, config)
compresses the polyline to key vertices `{t, x, y}`. RDP keeps only a **subset of real points** — never
invents. Timestamps retained on every vertex so long no-cast gaps remain visibly straight segments.

**Step 3 — grouping timeline (`GroupingAnalyzer`):** over sliding windows (~2–3 s, last-known position per
player), distance-based clustering with radius R (~8 yd, DBSCAN-like) labels each window
`stacked` / `spread` / `groups(k)`. Output: `{t_start, t_end, state, cluster_count, members}`.

**Step 4 — normalizer integration** (harvest + live, same pipeline), per phase (boss phase boundaries
already available): grouping-state proportions, raid spread radius, cluster counts.

**Store-vs-discard decision:** do **not** store strangers' individual top-kill tracks (not comparable,
heavy). Store the **aggregate per-phase grouping pattern** (comparable). RDP per-player tracks are computed
for OUR raid (compact movement description for the AI + our grouping timeline) and transiently on top kills
(to derive the canonical timeline, then discarded).

**Flagging is raid/positional, not "player is bad":** e.g. "raid failed to spread in P2 (cluster_count 1 vs
canonical 5)". Individual flag only for a clear outlier (the one not stacked when canonical + everyone else
says stack).

**Honest limits:** resolution bounded by cast frequency (healers/melee cast often → good; sparse windows →
straight segment with visible gap); WCL coordinate units converted to yards for human-readable thresholds.

**New components:** `MovementTrackBuilder` (RDP — pure, testable), `GroupingAnalyzer` (clustering — pure,
testable), + a focused coordinate-collection addition in `WclService`.

## 8. Harvest command

```
refresh:mechanic-baselines
  {--bosses=     : comma-separated boss slugs (default: all in season)}
  {--kills=10    : top kills to sample per boss}
  {--difficulty=5 : 3=Normal 4=Heroic 5=Mythic}
  {--season=midnight-s1}
  {--dry-run}    {--force}    {--pace=6}
```

**`handle()` flow:**
1. Resolve bosses from the boss-timeline directory for season/difficulty.
2. Per boss:
   - read `wcl_encounter_id` from the YAML → fetch top-kill `(report, fightID)` pairs via WCL rankings
     (reuse/extend `WclService::fetchCharacterRankings`; dedupe report codes to unique kill logs; take top N);
   - per unique kill log (paced; cached at `mechanic-baseline-cache/{boss}/{report}-d{diff}.json`, 7-day TTL):
     run the **existing** full extraction → `MechanicMetricsNormalizer` + movement → per-mechanic metric
     vectors for that log;
   - aggregate across N logs → median/p5/p95/sample per metric (reuse `percentile()` from spec-baselines).
3. `mergeYaml` writes the `empirical:` block into the matching-difficulty boss YAML, preserving authored
   `mechanics:`. `--dry-run` prints a diff.

**Cost:** each kill log = one heavy full-event fetch. Cache makes only the first run heavy; re-runs cheap.
`--pace` throttles WCL. A full all-bosses × 10-kills run is expected to take ~1–2 hours and is
**operator-triggered**, not part of CI or tests.

## 9. Detection rewrite (what changes in `applyTypeSpecificEvidence`)

- **Remove** every `?? <magic number>` threshold and every "received the debuff → `players[]+1`" line.
- **Replace** with: fetch `empirical.mechanics[name]` via `MechanicBaselineLoader`; run the type's flag rule
  against the envelope; populate `players[]` with out-of-envelope offenders only; attach a baseline-context
  note.
- **No baseline present** → informational note, flag nobody.
- Keep the legitimate signals already computed (`late_swaps`, `death_with_high_stacks`) — they fold into the
  new rules.

## 10. Testing strategy

**Pure unit tests** (PHPUnit\Framework\TestCase) — the core safety:
- `MechanicMetricsNormalizer` per type: synthetic input → assert envelope (swaps at 12/13/14 →
  `swap_at_stacks` median 13).
- **Flag-rule regression for the reported bug**: "max 13 stacks + on-time swap → NOT flagged"; "swap at 19
  vs p95 15 → flagged".
- `MovementTrackBuilder` (RDP): known polyline → keeps endpoints + corner, drops collinear midpoints;
  ε behavior.
- `GroupingAnalyzer`: 18 within 8 yd + 2 far → stacked + outlier; all dispersed → spread.
- `percentile()` (shared with spec-baselines).

**Feature / integration tests:**
- `MechanicBaselineLoader` reads `empirical:` from a fixture YAML; graceful when absent.
- `mergeYaml` preserves authored `mechanics:`, adds `empirical:` (temp YAML fixture).
- Detection end-to-end on a fixture: baseline present → out-of-envelope flagged; baseline absent →
  informational only, nobody flagged.
- Harvest command `--dry-run` against a **mocked** `WclService` (no real WCL in tests): assert aggregation
  math + YAML write.

Real WCL harvest is a manual CLI operation, never a test.

## 11. Validation plan (before the full run)

The operator will run the full multi-hour harvest separately. Implementation verification uses a **small
sample**:

1. `php artisan refresh:mechanic-baselines --bosses=<2 random bosses> --kills=3 --difficulty=5 --dry-run`
   → eyeball the learned numbers (sane swap stacks, coverage %, etc.).
2. Write the small sample for those bosses (no `--dry-run`), then re-run
   `ai:diagnose-data mFAKX4d6PnVbrkgp` on the Thursday report and confirm the tank-swap false flags are
   gone and notes now cite learned numbers.

(Host-env note for local runs, from RC1: `.env` uses docker service names; override
`CACHE_STORE=array REDIS_HOST=127.0.0.1 LOG_CHANNEL=null` and disable the Bash sandbox for WCL egress.)

## 12. File placement (per CLAUDE.md architecture)

- `app/Services/Analysis/MechanicMetricsNormalizer.php` — service.
- `app/Services/Analysis/MechanicBaselineLoader.php` — service (mirrors `SpecBaselineLoader`).
- `app/Services/Analysis/GroupingAnalyzer.php` — service.
- `app/Helpers/RamerDouglasPeucker.php` **or** `app/Services/Analysis/MovementTrackBuilder.php` — RDP is a
  pure stateless algorithm; if kept dependency-free it fits `app/Helpers/`, otherwise a service. Plan to
  decide.
- `app/Console/Commands/RefreshMechanicBaselinesCommand.php` — command.
- Extraction additions: `app/Helpers/WclReportParserHelper.php` (stacks-at-swap, dispel latency),
  `app/Services/Analysis/WclService.php` (all-friendly-cast coordinate collection). Watch the 350-line
  limit on `WclService`; extract a focused method/helper if it grows.

## 13. Open questions / to confirm during implementation

- Exact WCL query for "top kill report+fight pairs for an encounter": dedupe `characterRankings` (per a
  reference spec) vs an `encounterRankings`/`fightRankings` query. Confirm which returns clean kill logs.
- WCL coordinate→yard conversion factor (verify against a known arena size).
- ε (RDP tolerance) and R (cluster radius) starting values — set sane defaults, expose as config, tune on
  real harvested data.
- Phase keys for the movement block — reuse existing phase identifiers from the boss-timeline phase data.

## 14. Deferred (future specs)

- Automatic/scheduled harvest cadence.
- Per-difficulty baseline scaling beyond the guild's primary difficulty.
- RC3 (obsolete rotation/WeakAuras advice, class/spec ability whitelist), RC4 (explanation contradictions).
