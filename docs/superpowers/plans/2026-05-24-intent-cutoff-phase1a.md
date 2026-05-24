# Intent-Cutoff Layer — Phase 1a Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stop intentional (post-wipe-call) deaths from being counted as mistakes in AI raid analysis — partition each wiped pull's deaths at a resolved intent cutoff *before* attribution runs, and surface the excluded deaths in a transparent, labelled bucket.

**Architecture:** A pure `IntentCutoffResolver` resolves a per-pull `cutoff_ms` from already-fetched WCL deaths (anchor death corroborated by a loosened terminal death-run, else terminal-run-only; low-confidence anchor-only is recorded but not auto-applied in 1a). `TacticalDataAnalyzer` calls the resolver's `partition()` right after `WipeDetector::tag()` — moving post-cutoff deaths out of the grouped `$logData['deaths']` into `$logData['excluded_deaths']`. Because every downstream consumer reads the partitioned structure, none of them changes. `FightBreakdownBuilder` surfaces `excluded_deaths` + `intent_cutoff` per fight; the prompt renders a transparent note and never coaches excluded deaths.

**Tech Stack:** PHP 8.3 / Laravel 11, PHPUnit 12 (pure unit tests extend `PHPUnit\Framework\TestCase`; integration tests extend `Tests\TestCase`). Run a single test with `php artisan test --filter=ClassName`.

**Out of scope (separate plans):** Phase 1b = metric-tail truncation (rotation/uptime/damage-taken via truncated WCL re-fetch). Phase 2 = addon live-marker + alignment + review/override UI + `pull_intent_markers` table.

---

## File Structure

| File | Responsibility | Action |
|---|---|---|
| `app/Services/Analysis/WipeDetector.php` | Add `detectTerminalAbandon()` — pure loosened-cascade detector | Modify |
| `app/Services/Analysis/IntentCutoffResolver.php` | Resolve per-fight cutoff + partition grouped deaths | Create |
| `app/Services/Analysis/TacticalDataAnalyzer.php` | Inject resolver; call `partition()` after tagging; new `$anchorNames` param | Modify |
| `app/Services/Analysis/FightBreakdownBuilder.php` | Carry `excluded_deaths` + `intent_cutoff` onto each fight | Modify |
| `app/Models/StaticGroup.php` | `intentAnchorCharacterIds()` accessor | Modify |
| `app/Jobs/Analysis/ProcessRaidAnalysisJob.php` | Resolve anchor names, pass to `analyze()` | Modify |
| `app/Services/Analysis/BlockSchema.php` | Allow `excluded_note` on pull_breakdown pulls | Modify |
| `resources/prompts/gemini_main_report.txt` | Rule: render `intent_cutoff`/`excluded_deaths`; never attribute | Modify |
| `resources/prompts/gemini_player_report.txt` | Rule: never coach excluded deaths | Modify |
| `tests/Unit/Analysis/WipeDetectorTerminalAbandonTest.php` | Unit tests for detector | Create |
| `tests/Unit/Analysis/IntentCutoffResolverTest.php` | Unit tests for resolve + partition | Create |
| `tests/Feature/Analysis/FightBreakdownExcludedDeathsTest.php` | Fight surfaces excluded bucket | Create |
| `tests/Feature/Analysis/StaticGroupAnchorTest.php` | Anchor accessor | Create |
| `tests/Unit/Analysis/BlockSchemaExcludedNoteTest.php` | Schema keeps `excluded_note` | Create |

---

## Task 1: `WipeDetector::detectTerminalAbandon()`

A pure detector for the "everyone trickled in after the wipe call" pattern that the existing 3s-cluster logic misses. Returns the `time_ms_relative` of the start of the terminal death-run, or `null`.

**Files:**
- Modify: `app/Services/Analysis/WipeDetector.php`
- Test: `tests/Unit/Analysis/WipeDetectorTerminalAbandonTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Analysis/WipeDetectorTerminalAbandonTest.php`:

```php
<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\WipeDetector;
use PHPUnit\Framework\TestCase;

class WipeDetectorTerminalAbandonTest extends TestCase
{
    private function deaths(array $timesMs): array
    {
        return array_map(fn ($t) => ['player' => 'P', 'time_ms_relative' => $t], $timesMs);
    }

    public function test_trickle_run_to_end_returns_run_start(): void
    {
        $d = $this->deaths([200000, 203000, 206000, 210000, 214000]);
        // bossPct high (55%), fight ends at 216000 — last death within 8s of end.
        $this->assertSame(200000, (new WipeDetector())->detectTerminalAbandon($d, 55.0, 216000));
    }

    public function test_keeps_early_real_deaths_before_terminal_run(): void
    {
        $d = $this->deaths([30000, 35000, 200000, 204000, 208000]);
        $this->assertSame(200000, (new WipeDetector())->detectTerminalAbandon($d, 60.0, 210000));
    }

    public function test_progression_wipe_near_kill_returns_null(): void
    {
        $d = $this->deaths([200000, 203000, 206000, 210000, 214000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 12.0, 216000));
    }

    public function test_sparse_deaths_return_null(): void
    {
        $d = $this->deaths([30000, 90000, 150000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 160000));
    }

    public function test_run_not_reaching_fight_end_returns_null(): void
    {
        // tight run but ends 30s before fight end → not an abandon
        $d = $this->deaths([100000, 103000, 106000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 160000));
    }

    public function test_fewer_than_min_run_returns_null(): void
    {
        $d = $this->deaths([210000, 214000]);
        $this->assertNull((new WipeDetector())->detectTerminalAbandon($d, 70.0, 216000));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WipeDetectorTerminalAbandonTest`
Expected: FAIL — `Error: Call to undefined method App\Services\Analysis\WipeDetector::detectTerminalAbandon()`

- [ ] **Step 3: Implement the method**

In `app/Services/Analysis/WipeDetector.php`, add these constants next to the existing ones (after line 41, `TANK_LOSS_LOOKBACK_MS`):

```php
    // Loosened "terminal abandon" detector — for post-wipe-call trickle deaths
    // that the 3s cluster logic misses. Tuned later against real guild logs.
    private const TERMINAL_GAP_MS = 5000;        // max gap between consecutive deaths in the run
    private const TERMINAL_MIN_RUN = 3;          // min deaths to call it an abandon
    private const TERMINAL_END_WINDOW_MS = 8000; // run's last death must land within this of fight end
```

Add this public method (e.g. after the `tag()` method, before `tagFight()`):

```php
    /**
     * Detect a post-wipe-call "everyone trickled into a mechanic" abandon run.
     * Unlike the 3s cluster used for tagging, this allows wider gaps and anchors
     * to the END of the fight. Returns the time_ms_relative of the run START, or
     * null when no abandon pattern is present.
     *
     * @param array<int,array> $deaths   deaths in one fight (each may carry time_ms_relative)
     */
    public function detectTerminalAbandon(array $deaths, ?float $bossPct, int $fightDurationMs): ?int
    {
        // Boss nearly dead → legit progression wipe, never an intentional abandon.
        if ($bossPct !== null && $bossPct <= self::BOSS_HP_THRESHOLD_PCT) {
            return null;
        }

        $times = [];
        foreach ($deaths as $d) {
            $t = $d['time_ms_relative'] ?? null;
            if ($t !== null) $times[] = (int) $t;
        }
        if (count($times) < self::TERMINAL_MIN_RUN) {
            return null;
        }
        sort($times);

        $last = $times[count($times) - 1];
        // The run must reach (near) the end of the fight.
        if ($fightDurationMs > 0 && ($fightDurationMs - $last) > self::TERMINAL_END_WINDOW_MS) {
            return null;
        }

        // Walk backward from the last death while consecutive gaps stay tight.
        $runStart = $last;
        $runSize = 1;
        for ($i = count($times) - 2; $i >= 0; $i--) {
            if (($times[$i + 1] - $times[$i]) <= self::TERMINAL_GAP_MS) {
                $runStart = $times[$i];
                $runSize++;
            } else {
                break;
            }
        }

        return $runSize >= self::TERMINAL_MIN_RUN ? $runStart : null;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=WipeDetectorTerminalAbandonTest`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/WipeDetector.php tests/Unit/Analysis/WipeDetectorTerminalAbandonTest.php
git commit -m "feat(ai): WipeDetector terminal-abandon detector for trickle wipe-call deaths"
```

---

## Task 2: `IntentCutoffResolver::resolve()`

Per-fight resolution: returns `{cutoff_ms, source, confidence}` or `null`.

**Files:**
- Create: `app/Services/Analysis/IntentCutoffResolver.php`
- Test: `tests/Unit/Analysis/IntentCutoffResolverTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Analysis/IntentCutoffResolverTest.php`:

```php
<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\IntentCutoffResolver;
use App\Services\Analysis\WipeDetector;
use PHPUnit\Framework\TestCase;

class IntentCutoffResolverTest extends TestCase
{
    private function resolver(): IntentCutoffResolver
    {
        return new IntentCutoffResolver(new WipeDetector());
    }

    private function deaths(array $rows): array
    {
        // $rows = [[player, timeMs], ...]
        return array_map(fn ($r) => ['player' => $r[0], 'time_ms_relative' => $r[1], 'fight_id' => 7], $rows);
    }

    public function test_kill_is_never_cut(): void
    {
        $d = $this->deaths([['P', 200000], ['P', 203000], ['P', 206000]]);
        $meta = ['outcome' => 'kill', 'boss_pct' => 0.0, 'duration_s' => 210, 'fight_id' => 7];
        $this->assertNull($this->resolver()->resolve($d, $meta, []));
    }

    public function test_cascade_only_is_medium(): void
    {
        $d = $this->deaths([['A', 200000], ['B', 203000], ['C', 206000], ['D', 210000], ['E', 214000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 216, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, []); // no anchor
        $this->assertSame(['cutoff_ms' => 200000, 'source' => 'cascade_only', 'confidence' => 'medium'], $res);
    }

    public function test_anchor_within_cascade_is_high(): void
    {
        $d = $this->deaths([['A', 200000], ['RL', 201000], ['C', 206000], ['D', 210000], ['E', 214000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 216, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, ['RL']);
        $this->assertSame(['cutoff_ms' => 200000, 'source' => 'anchor+cascade', 'confidence' => 'high'], $res);
    }

    public function test_anchor_only_no_cascade_is_low(): void
    {
        // Anchor dies early, only sparse deaths after → no terminal run.
        $d = $this->deaths([['RL', 40000], ['B', 90000], ['C', 150000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160, 'fight_id' => 7];
        $res = $this->resolver()->resolve($d, $meta, ['RL']);
        $this->assertSame(['cutoff_ms' => 40000, 'source' => 'anchor_only', 'confidence' => 'low'], $res);
    }

    public function test_no_signal_returns_null(): void
    {
        $d = $this->deaths([['B', 90000], ['C', 150000]]);
        $meta = ['outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160, 'fight_id' => 7];
        $this->assertNull($this->resolver()->resolve($d, $meta, []));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IntentCutoffResolverTest`
Expected: FAIL — `Error: Class "App\Services\Analysis\IntentCutoffResolver" not found`.

- [ ] **Step 3: Implement `resolve()` (and helper)**

Create `app/Services/Analysis/IntentCutoffResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Resolves a per-pull "intent cutoff" — the moment a wipe was called, after
 * which deaths are not mistakes. Source-agnostic by design: Phase 1a feeds it
 * from the anchor death + WipeDetector terminal-abandon; Phase 2 will add addon
 * markers and manual overrides at higher priority.
 *
 * Source priority (Phase 1a subset): anchor+cascade > cascade_only > anchor_only(low).
 * Kills are never cut.
 */
class IntentCutoffResolver
{
    private const ANCHOR_NEAR_MS = 5000;
    private const AUTO_APPLY_CONFIDENCE = ['high', 'medium'];

    public function __construct(private readonly WipeDetector $wipeDetector) {}

    /**
     * @param array<int,array> $deaths     deaths for ONE fight (each has player, time_ms_relative)
     * @param array            $fightMeta  ['outcome','boss_pct','duration_s','fight_id']
     * @param list<string>     $anchorNames
     * @return array{cutoff_ms:int,source:string,confidence:string}|null
     */
    public function resolve(array $deaths, array $fightMeta, array $anchorNames): ?array
    {
        if (($fightMeta['outcome'] ?? 'wipe') === 'kill') {
            return null;
        }

        $durationMs = (int) (($fightMeta['duration_s'] ?? 0) * 1000);
        $bossPct = isset($fightMeta['boss_pct']) ? (float) $fightMeta['boss_pct'] : null;

        $terminal = $this->wipeDetector->detectTerminalAbandon($deaths, $bossPct, $durationMs);
        $anchorDeath = $this->earliestAnchorDeath($deaths, $anchorNames);

        if ($terminal !== null) {
            $near = $anchorDeath !== null && $anchorDeath >= ($terminal - self::ANCHOR_NEAR_MS);
            return [
                'cutoff_ms'  => $terminal,
                'source'     => $near ? 'anchor+cascade' : 'cascade_only',
                'confidence' => $near ? 'high' : 'medium',
            ];
        }

        if ($anchorDeath !== null) {
            // Anchor died but no corroborating cascade — low confidence.
            // Recorded for Phase 2 review; NOT auto-applied in Phase 1a.
            return [
                'cutoff_ms'  => $anchorDeath,
                'source'     => 'anchor_only',
                'confidence' => 'low',
            ];
        }

        return null;
    }

    private function earliestAnchorDeath(array $deaths, array $anchorNames): ?int
    {
        if (empty($anchorNames)) return null;
        $set = array_flip($anchorNames);
        $earliest = null;
        foreach ($deaths as $d) {
            if (!isset($set[$d['player'] ?? ''])) continue;
            $t = $d['time_ms_relative'] ?? null;
            if ($t === null) continue;
            $t = (int) $t;
            if ($earliest === null || $t < $earliest) $earliest = $t;
        }
        return $earliest;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=IntentCutoffResolverTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/IntentCutoffResolver.php tests/Unit/Analysis/IntentCutoffResolverTest.php
git commit -m "feat(ai): IntentCutoffResolver per-fight cutoff resolution"
```

---

## Task 3: `IntentCutoffResolver::partition()`

Partition the grouped deaths structure: move post-cutoff deaths into a parallel excluded structure and record per-fight cutoff metadata. Low-confidence (`anchor_only`) results are NOT applied in 1a.

**Files:**
- Modify: `app/Services/Analysis/IntentCutoffResolver.php`
- Test: `tests/Unit/Analysis/IntentCutoffResolverTest.php` (add to existing)

- [ ] **Step 1: Write the failing test**

Append these methods to `IntentCutoffResolverTest`:

```php
    public function test_partition_moves_post_cutoff_deaths_and_records_metadata(): void
    {
        $deathsGrouped = [
            'Midnight Falls' => [
                'try_1' => [
                    ['player' => 'A', 'time_ms_relative' => 30000, 'fight_id' => 7],
                    ['player' => 'B', 'time_ms_relative' => 200000, 'fight_id' => 7],
                    ['player' => 'C', 'time_ms_relative' => 203000, 'fight_id' => 7],
                    ['player' => 'D', 'time_ms_relative' => 206000, 'fight_id' => 7],
                    ['player' => 'E', 'time_ms_relative' => 210000, 'fight_id' => 7],
                ],
            ],
        ];
        $phaseSummary = [
            'Midnight Falls' => [
                ['fight_id' => 7, 'outcome' => 'wipe', 'boss_pct' => 55.0, 'duration_s' => 212],
            ],
        ];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, []);

        // Kept: the early real death only.
        $this->assertCount(1, $out['deaths']['Midnight Falls']['try_1']);
        $this->assertSame('A', $out['deaths']['Midnight Falls']['try_1'][0]['player']);
        // Excluded: the 4-death terminal run.
        $this->assertCount(4, $out['excluded_deaths']['Midnight Falls']['try_1']);
        // Metadata keyed by fight_id.
        $this->assertSame(200000, $out['intent_cutoffs'][7]['cutoff_ms']);
        $this->assertSame('cascade_only', $out['intent_cutoffs'][7]['source']);
        $this->assertSame(4, $out['intent_cutoffs'][7]['excluded_count']);
    }

    public function test_partition_leaves_low_confidence_untouched(): void
    {
        $deathsGrouped = [
            'Boss' => [
                'try_1' => [
                    ['player' => 'RL', 'time_ms_relative' => 40000, 'fight_id' => 9],
                    ['player' => 'B', 'time_ms_relative' => 90000, 'fight_id' => 9],
                    ['player' => 'C', 'time_ms_relative' => 150000, 'fight_id' => 9],
                ],
            ],
        ];
        $phaseSummary = ['Boss' => [['fight_id' => 9, 'outcome' => 'wipe', 'boss_pct' => 70.0, 'duration_s' => 160]]];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, ['RL']);

        $this->assertCount(3, $out['deaths']['Boss']['try_1']);       // unchanged
        $this->assertArrayNotHasKey('Boss', $out['excluded_deaths']); // nothing excluded
        $this->assertArrayNotHasKey(9, $out['intent_cutoffs']);       // not recorded in 1a
    }

    public function test_partition_skips_kills(): void
    {
        $deathsGrouped = ['Boss' => ['try_1' => [
            ['player' => 'A', 'time_ms_relative' => 200000, 'fight_id' => 1],
            ['player' => 'B', 'time_ms_relative' => 203000, 'fight_id' => 1],
            ['player' => 'C', 'time_ms_relative' => 206000, 'fight_id' => 1],
        ]]];
        $phaseSummary = ['Boss' => [['fight_id' => 1, 'outcome' => 'kill', 'boss_pct' => 0.0, 'duration_s' => 210]]];

        $out = $this->resolver()->partition($deathsGrouped, $phaseSummary, []);
        $this->assertCount(3, $out['deaths']['Boss']['try_1']);
        $this->assertSame([], $out['excluded_deaths']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=IntentCutoffResolverTest`
Expected: FAIL — `Error: Call to undefined method ...::partition()`.

- [ ] **Step 3: Implement `partition()`**

Add to `IntentCutoffResolver` (after `resolve()`):

```php
    /**
     * Partition grouped deaths at each wiped fight's intent cutoff. Post-cutoff
     * deaths move from 'deaths' into 'excluded_deaths'. Kills and low-confidence
     * (anchor_only) resolutions are left untouched in Phase 1a.
     *
     * @param array $deathsGrouped  [bossName][try_N][] => death (already WipeDetector-tagged)
     * @param array $phaseSummary   [bossName][] => ['fight_id','outcome','boss_pct','duration_s', ...]
     * @param list<string> $anchorNames
     * @return array{deaths:array, excluded_deaths:array, intent_cutoffs:array}
     */
    public function partition(array $deathsGrouped, array $phaseSummary, array $anchorNames): array
    {
        $metaByFight = [];
        foreach ($phaseSummary as $bossTries) {
            if (!is_array($bossTries)) continue;
            foreach ($bossTries as $t) {
                if (isset($t['fight_id'])) $metaByFight[$t['fight_id']] = $t;
            }
        }

        $excluded = [];
        $cutoffs = [];

        foreach ($deathsGrouped as $bossName => $byTry) {
            foreach ($byTry as $tryKey => $deaths) {
                if (empty($deaths)) continue;

                // All deaths in a fight share fight_id (see WipeDetector::tagFight).
                $fightId = $deaths[0]['fight_id'] ?? null;
                $meta = ($fightId !== null) ? ($metaByFight[$fightId] ?? []) : [];

                $res = $this->resolve($deaths, $meta, $anchorNames);
                if ($res === null || !in_array($res['confidence'], self::AUTO_APPLY_CONFIDENCE, true)) {
                    continue;
                }

                $cutoffMs = $res['cutoff_ms'];
                $kept = [];
                $cut = [];
                foreach ($deaths as $d) {
                    $t = $d['time_ms_relative'] ?? null;
                    if ($t !== null && (int) $t >= $cutoffMs) {
                        $cut[] = $d;
                    } else {
                        $kept[] = $d;
                    }
                }

                if (empty($cut)) continue;

                $deathsGrouped[$bossName][$tryKey] = $kept;
                $excluded[$bossName][$tryKey] = $cut;
                if ($fightId !== null) {
                    $cutoffs[$fightId] = [
                        'cutoff_ms'      => $cutoffMs,
                        'source'         => $res['source'],
                        'confidence'     => $res['confidence'],
                        'excluded_count' => count($cut),
                    ];
                }
            }
        }

        return [
            'deaths'          => $deathsGrouped,
            'excluded_deaths' => $excluded,
            'intent_cutoffs'  => $cutoffs,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=IntentCutoffResolverTest`
Expected: PASS (8 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/IntentCutoffResolver.php tests/Unit/Analysis/IntentCutoffResolverTest.php
git commit -m "feat(ai): IntentCutoffResolver grouped-deaths partition"
```

---

## Task 4: Wire `partition()` into `TacticalDataAnalyzer::analyze()`

Inject the resolver, add an `$anchorNames` parameter, and partition deaths immediately after tagging — before encounters are built and before attribution.

**Files:**
- Modify: `app/Services/Analysis/TacticalDataAnalyzer.php:23-35` (constructor), `:44` (signature), `:61-68` (tag block)

- [ ] **Step 1: Inject the resolver**

In the constructor (`app/Services/Analysis/TacticalDataAnalyzer.php`, after line 33 `private readonly DeathAttributionBuilder $deathAttributionBuilder,`), add:

```php
        private readonly IntentCutoffResolver $intentCutoffResolver,
```

(The trailing comma is valid PHP 8 in the last constructor-promotion slot; keep it consistent with the existing list.)

- [ ] **Step 2: Add the `$anchorNames` parameter to `analyze()`**

Change line 44 from:

```php
    public function analyze(array $logData, array $localization = [], array $rosterNames = [], ?string $reportId = null): array
```

to:

```php
    public function analyze(array $logData, array $localization = [], array $rosterNames = [], ?string $reportId = null, array $anchorNames = []): array
```

- [ ] **Step 3: Call `partition()` after tagging**

Replace the tag block (lines 61-68) with:

```php
        if (isset($logData['deaths'])) {
            $tankNames = $this->extractTankNames($logData['player_details'] ?? []);
            $this->wipeDetector->tag(
                $logData['deaths'],
                $logData['phase_summary'] ?? [],
                $tankNames
            );

            // Intent-cutoff: move post-wipe-call deaths out of the grouped death
            // structure BEFORE encounters/insights/attribution read it, so they
            // are never counted as mistakes. Excluded deaths + cutoff metadata
            // travel alongside for the transparent "after wipe call" section.
            $partition = $this->intentCutoffResolver->partition(
                $logData['deaths'],
                $logData['phase_summary'] ?? [],
                $anchorNames
            );
            $logData['deaths']          = $partition['deaths'];
            $logData['excluded_deaths'] = $partition['excluded_deaths'];
            $logData['intent_cutoffs']  = $partition['intent_cutoffs'];
        }
```

- [ ] **Step 4: Run the full suite to verify no regression**

Run: `php artisan test`
Expected: PASS — same green result as before this task (the new injection resolves via the container; partition is a no-op when no wiped trickle pulls exist). If a test constructs `TacticalDataAnalyzer` manually, update it to pass `new IntentCutoffResolver(new WipeDetector())`.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/TacticalDataAnalyzer.php
git commit -m "feat(ai): partition intentional deaths before attribution in TacticalDataAnalyzer"
```

---

## Task 5: Surface `excluded_deaths` + `intent_cutoff` on each fight

`FightBreakdownBuilder` already maps `try_N` → fight. Pass it the excluded structure and cutoff metadata so each fight carries the bucket for the AI/UI.

**Files:**
- Modify: `app/Services/Analysis/FightBreakdownBuilder.php` (`build()` signature + `$out[]`)
- Modify: `app/Services/Analysis/TacticalDataAnalyzer.php:349-355` (call site)
- Test: `tests/Feature/Analysis/FightBreakdownExcludedDeathsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Analysis/FightBreakdownExcludedDeathsTest.php`:

```php
<?php

namespace Tests\Feature\Analysis;

use App\Services\Analysis\FightBreakdownBuilder;
use Tests\TestCase;

class FightBreakdownExcludedDeathsTest extends TestCase
{
    public function test_fight_carries_excluded_deaths_and_intent_cutoff(): void
    {
        $builder = app(FightBreakdownBuilder::class);

        $tries = [
            ['fight_id' => 7, 'outcome' => 'wipe', 'duration_s' => 212, 'boss_pct' => 55.0],
        ];
        $deathsByTry = [
            'try_1' => [['player' => 'A', 'time_ms_relative' => 30000, 'fight_id' => 7]],
        ];
        $excludedByTry = [
            'try_1' => [
                ['player' => 'B', 'time_ms_relative' => 200000, 'fight_id' => 7],
                ['player' => 'C', 'time_ms_relative' => 203000, 'fight_id' => 7],
            ],
        ];
        $intentCutoffs = [
            7 => ['cutoff_ms' => 200000, 'source' => 'cascade_only', 'confidence' => 'medium', 'excluded_count' => 2],
        ];

        $fights = $builder->build($tries, $deathsByTry, [], [], $excludedByTry, $intentCutoffs);

        $this->assertCount(2, $fights[0]['excluded_deaths']);
        $this->assertSame(200000, $fights[0]['intent_cutoff']['cutoff_ms']);
        $this->assertSame('cascade_only', $fights[0]['intent_cutoff']['source']);
    }

    public function test_fight_without_cutoff_has_empty_bucket_and_null_cutoff(): void
    {
        $builder = app(FightBreakdownBuilder::class);
        $tries = [['fight_id' => 1, 'outcome' => 'kill', 'duration_s' => 210, 'boss_pct' => 0.0]];
        $deathsByTry = ['try_1' => []];

        $fights = $builder->build($tries, $deathsByTry, [], []);

        $this->assertSame([], $fights[0]['excluded_deaths']);
        $this->assertNull($fights[0]['intent_cutoff']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FightBreakdownExcludedDeathsTest`
Expected: FAIL — too few arguments / undefined array key `excluded_deaths`.

- [ ] **Step 3: Extend `build()`**

In `app/Services/Analysis/FightBreakdownBuilder.php`, change the `build()` signature to add two optional params:

```php
    public function build(
        array $tries,
        array $deathsByTry,
        array $perFightCasts,
        array $perPlayerData,
        array $excludedByTry = [],
        array $intentCutoffs = []
    ): array {
```

Inside the loop, after `$tryKey = "try_{$attempt}";` and where `$fightId` is computed, add the two fields to the `$out[]` array (alongside `'deaths' => $deaths,`):

```php
            'excluded_deaths'     => $excludedByTry[$tryKey] ?? [],
            'intent_cutoff'       => ($fightId !== null ? ($intentCutoffs[$fightId] ?? null) : null),
```

- [ ] **Step 4: Update the call site**

In `app/Services/Analysis/TacticalDataAnalyzer.php`, change the `fightBreakdownBuilder->build(...)` call (lines 349-355) to pass the excluded structure and cutoffs:

```php
        $fights = $this->fightBreakdownBuilder->build(
            $tries,
            $logData['deaths'][$bossName] ?? [],
            $logData['per_fight_casts'] ?? [],
            $this->playerSpecLookup($playerNames, $logData),
            $logData['excluded_deaths'][$bossName] ?? [],
            $logData['intent_cutoffs'] ?? []
        );
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=FightBreakdownExcludedDeathsTest`
Expected: PASS (2 tests). Then `php artisan test --filter=TacticalData` and the full suite to confirm no regression.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Analysis/FightBreakdownBuilder.php app/Services/Analysis/TacticalDataAnalyzer.php tests/Feature/Analysis/FightBreakdownExcludedDeathsTest.php
git commit -m "feat(ai): surface excluded_deaths + intent_cutoff on per-fight breakdown"
```

---

## Task 6: Anchor config + Job wiring

Add the `intentAnchorCharacterIds()` accessor and resolve anchor names in the production job.

**Files:**
- Modify: `app/Models/StaticGroup.php`
- Modify: `app/Jobs/Analysis/ProcessRaidAnalysisJob.php:75-91`
- Test: `tests/Feature/Analysis/StaticGroupAnchorTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Analysis/StaticGroupAnchorTest.php`:

```php
<?php

namespace Tests\Feature\Analysis;

use App\Models\StaticGroup;
use Tests\TestCase;

class StaticGroupAnchorTest extends TestCase
{
    public function test_returns_configured_anchor_ids_as_ints(): void
    {
        $s = new StaticGroup();
        $s->automation_settings = ['intent_anchor_character_ids' => ['5', 9]];
        $this->assertSame([5, 9], $s->intentAnchorCharacterIds());
    }

    public function test_returns_empty_when_unset(): void
    {
        $s = new StaticGroup();
        $s->automation_settings = [];
        $this->assertSame([], $s->intentAnchorCharacterIds());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StaticGroupAnchorTest`
Expected: FAIL — `Call to undefined method App\Models\StaticGroup::intentAnchorCharacterIds()`.

- [ ] **Step 3: Implement the accessor**

In `app/Models/StaticGroup.php`, add (near the other settings accessors, e.g. after `getConsumableQuantity()`):

```php
    /**
     * Character IDs whose death marks the wipe-call cutoff for intent filtering.
     * Stored in automation_settings; empty array when unconfigured.
     *
     * @return list<int>
     */
    public function intentAnchorCharacterIds(): array
    {
        return array_values(array_map(
            'intval',
            $this->automation_settings['intent_anchor_character_ids'] ?? []
        ));
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StaticGroupAnchorTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Wire anchor names into the job**

In `app/Jobs/Analysis/ProcessRaidAnalysisJob.php`, inside `handle()`. The static + characters are already loaded at lines 75-77. After line 77 (`$rosterNames = $static->characters->pluck('name')->toArray();`), add:

```php
            $anchorIds = $static->intentAnchorCharacterIds();
            $anchorNames = $anchorIds
                ? $static->characters->whereIn('id', $anchorIds)->pluck('name')->values()->all()
                : [];
```

Then change the `analyze()` call (lines 86-91) to pass anchor names:

```php
            $preprocessed = $analyzer->analyze(
                $logData,
                $localization,
                $rosterNames,
                $this->report->wcl_report_id,
                $anchorNames
            );
```

- [ ] **Step 6: Verify the job compiles and the suite is green**

Run: `php artisan test` and `php artisan route:list >/dev/null && echo OK`
Expected: tests PASS; `OK` printed (no fatal in autoloaded job).

- [ ] **Step 7: Commit**

```bash
git add app/Models/StaticGroup.php app/Jobs/Analysis/ProcessRaidAnalysisJob.php tests/Feature/Analysis/StaticGroupAnchorTest.php
git commit -m "feat(ai): static anchor config + wire anchor names into raid analysis job"
```

---

## Task 7: Transparency — prompt rule + BlockSchema

Let the AI render a transparent "after wipe call" note and forbid coaching excluded deaths.

**Files:**
- Modify: `app/Services/Analysis/BlockSchema.php:72-75` (PULL_ALLOWED_FIELDS)
- Modify: `resources/prompts/gemini_main_report.txt`
- Modify: `resources/prompts/gemini_player_report.txt`
- Test: `tests/Unit/Analysis/BlockSchemaExcludedNoteTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Analysis/BlockSchemaExcludedNoteTest.php`:

```php
<?php

namespace Tests\Unit\Analysis;

use App\Services\Analysis\BlockSchema;
use ReflectionClass;
use Tests\TestCase;

class BlockSchemaExcludedNoteTest extends TestCase
{
    public function test_pull_allowed_fields_includes_excluded_note(): void
    {
        $const = (new ReflectionClass(BlockSchema::class))->getConstant('PULL_ALLOWED_FIELDS');
        $this->assertContains('excluded_note', $const);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=BlockSchemaExcludedNoteTest`
Expected: FAIL — `excluded_note` not in `PULL_ALLOWED_FIELDS`.

- [ ] **Step 3: Add the field to the whitelist**

In `app/Services/Analysis/BlockSchema.php`, the `PULL_ALLOWED_FIELDS` constant (around line 72-75) — add `'excluded_note'`:

```php
    private const PULL_ALLOWED_FIELDS = [
        'pull_number', 'outcome', 'wipe_pct', 'duration_s', 'phase',
        'summary', 'key_deaths', 'mechanic_failures', 'pattern_note',
        'excluded_note',
    ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=BlockSchemaExcludedNoteTest`
Expected: PASS.

- [ ] **Step 5: Add the main-report prompt rule**

In `resources/prompts/gemini_main_report.txt`, inside the CROSS-PLAYER ATTRIBUTION section, after line 106 (`This rule applies to every section that names a death cause: ...`), insert:

```
============================================================
INTENT-CUTOFF (HARD RULE — excluded deaths are NOT mistakes)
============================================================

Each fight may carry `fights[i].intent_cutoff` ({ cutoff_ms, source, confidence, excluded_count }) and `fights[i].excluded_deaths[]`. These are deaths AFTER the raid leader called the wipe — players released or walked into a mechanic on purpose. They are NOT mistakes.

- NEVER attribute, coach, or count `excluded_deaths` anywhere: not in `key_deaths`, not in `recurring_failures`, not in Coaching Plan, not in mechanic-failure narrative. `key_deaths` mirrors `fights[i].deaths` ONLY (already excludes them).
- When `intent_cutoff` is present, add a brief, neutral `excluded_note` on that pull entry in the raid_leader locale: e.g. "Після команди вайп (3:20) — 3 смерті не враховано." Use M:SS from `cutoff_ms` (SS ∈ 0–59). State the count from `excluded_count`. Do not editorialize.
- In the Raid Overview, you may fold the total excluded count into the intentional-vs-mechanical separation already required by `death_tag_distribution`.
```

- [ ] **Step 6: Add the player-report prompt rule**

In `resources/prompts/gemini_player_report.txt`, add a short rule in its death-handling section (near the existing death-attribution guidance):

```
INTENT-CUTOFF: Deaths in `excluded_deaths` (after the raid leader's wipe call) are NEVER this player's mistake. Do not mention, coach, or count them. Only the deaths in the player's regular death list are real.
```

- [ ] **Step 7: Verify prompts read correctly**

Run: `php artisan test` (full suite green) and manually re-read both prompt insertions.
Expected: suite PASS; both rules present and grammatical.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Analysis/BlockSchema.php resources/prompts/gemini_main_report.txt resources/prompts/gemini_player_report.txt tests/Unit/Analysis/BlockSchemaExcludedNoteTest.php
git commit -m "feat(ai): transparent excluded-death note + never-coach-intentional prompt rules"
```

---

## Task 8: Aggregate excluded count into `death_tag_distribution` (optional polish)

Surfaces the intent-excluded total so the Raid Overview separation is exact.

**Files:**
- Modify: `app/Services/Analysis/TacticalDataAnalyzer.php` (`buildDeathTagDistribution` + its call at line 359)

- [ ] **Step 1: Pass excluded into the distribution builder**

At line 359, change:

```php
        $deathTagDistribution = $this->buildDeathTagDistribution($logData['deaths'][$bossName] ?? []);
```

to also pass the excluded structure:

```php
        $deathTagDistribution = $this->buildDeathTagDistribution(
            $logData['deaths'][$bossName] ?? [],
            $logData['excluded_deaths'][$bossName] ?? []
        );
```

- [ ] **Step 2: Add the excluded count in `buildDeathTagDistribution`**

Add `array $excludedByTry = []` as the second parameter of `buildDeathTagDistribution`, and add an `intent_excluded` key to its returned array equal to the total count across `$excludedByTry`:

```php
        $intentExcluded = 0;
        foreach ($excludedByTry as $deaths) {
            $intentExcluded += count($deaths);
        }
        // ... existing distribution build ...
        $distribution['intent_excluded'] = $intentExcluded;
```

(Place the `$distribution['intent_excluded'] = $intentExcluded;` line just before the method's `return`.)

- [ ] **Step 3: Verify**

Run: `php artisan test`
Expected: full suite PASS (no regression; new key is additive).

- [ ] **Step 4: Commit**

```bash
git add app/Services/Analysis/TacticalDataAnalyzer.php
git commit -m "feat(ai): report intent_excluded count in death_tag_distribution"
```

---

## Final Verification

- [ ] `php artisan test` — full suite green.
- [ ] `npm run build` — frontend compiles (no FE changes here, but verify per CLAUDE.md).
- [ ] Smoke (if a real report is available locally): run the existing `php artisan` analysis command on a known wipe-call log and confirm the affected pull shows `excluded_deaths` populated, `key_deaths` excludes them, and an `excluded_note` renders.

---

## Self-Review

**Spec coverage:**
- Core abstraction (`intent_cutoff_ms` + resolver, source priority) → Tasks 2-3. ✓
- Phase-1 anchor + loosened terminal-cascade + corroboration + confidence → Tasks 1-2. ✓ (Implementation refines spec's `min(A,B)` to "cutoff = terminal-run start; anchor modulates confidence" — avoids cutting genuine early deaths when the anchor died long before the abandon; consistent with the spec's corroboration guard.)
- Death partition before attribution; consumers unchanged → Task 4. ✓
- Transparent excluded bucket + cutoff shown → Tasks 5, 7. ✓
- Prompt rule "excluded ≠ mistake" → Task 7. ✓
- Anchor config in static settings → Task 6. ✓
- Metric-tail truncation → explicitly Phase 1b (out of scope here). ✓
- Addon marker / review UI / `pull_intent_markers` → Phase 2 (out of scope). ✓

**Placeholder scan:** No TBD/TODO; every code step shows complete code; every test step shows the command + expected result.

**Type consistency:** `detectTerminalAbandon(array,?float,int):?int` used identically in Tasks 1-2. `resolve(array,array,array):?array` returns `{cutoff_ms,source,confidence}` consumed identically in Task 3. `partition(...)` returns `{deaths,excluded_deaths,intent_cutoffs}`, consumed in Task 4 with matching keys. `intent_cutoffs` keyed by `fight_id`; `excluded_deaths`/`deaths` keyed `[bossName][try_N]` — consumed by `FightBreakdownBuilder` (Task 5) via `$tryKey` and `$fightId`, matching its existing `try_{attempt}` convention.

**Tuning note for executor:** `TERMINAL_GAP_MS`, `TERMINAL_MIN_RUN`, `TERMINAL_END_WINDOW_MS`, `ANCHOR_NEAR_MS` are initial values; calibrate against real guild logs (see spec §10) once the smoke test runs on actual wipe-call pulls.
