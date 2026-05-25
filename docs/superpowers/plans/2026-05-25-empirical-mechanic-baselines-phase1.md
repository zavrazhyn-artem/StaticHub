# Empirical Mechanic Baselines — Phase 1 (scaffold + tank_swap) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hardcoded `tank_swap` magic numbers (`stack_swap_threshold ?? 7`, `max > threshold → failure`) with thresholds learned from top WCL kills, via a shared normalizer — and build the scaffold (stats helper, normalizer, baseline loader, harvest command) that later mechanic types and the movement module reuse.

**Architecture:** Approach C from the spec. A `MechanicMetricsNormalizer` is the single source of truth for *what we measure*. The harvest command (`refresh:mechanic-baselines`) runs the existing `getLogSummary → analyze` extraction over top-kill logs, feeds each log's debuff data through the normalizer, aggregates to median/p5/p95, and merges an `empirical:` block into the boss-timeline YAML. Live detection in `applyTypeSpecificEvidence` calls the *same* normalizer + the learned baseline and flags a tank only when out of envelope.

**Tech Stack:** Laravel 11, PHP 8.2, PHPUnit, Symfony YAML, Warcraft Logs GraphQL.

**Spec:** `docs/superpowers/specs/2026-05-25-empirical-mechanic-baselines-design.md`

**Out of scope (follow-on plans):** other discrete mechanic types (interrupt, dispel, absorb, soak, positioning); the movement module (RDP + grouping). Each reuses this scaffold.

---

## File Structure

- **Create** `app/Helpers/StatsHelper.php` — pure `percentile()` + `summarize()`. Reused by harvest.
- **Modify** `app/Helpers/WclReportParserHelper.php` — `calculateSwapTiming()` also returns a per-swap `swaps` list `{outgoing_player, stacks_at_swap, gap_ms}` (new extraction needed for `swap_at_stacks`).
- **Create** `app/Services/Analysis/MechanicMetricsNormalizer.php` — `tankSwap()` (per-log vector), `aggregateTankSwap()` (vectors → empirical entry), `evaluateTankSwap()` (flag rule), `debuffMatchesMechanic()` (shared matcher).
- **Create** `app/Services/Analysis/MechanicBaselineLoader.php` — mirrors `BossTimelineLoader`; reads the `empirical:` block from the boss-timeline YAML.
- **Create** `app/Console/Commands/RefreshMechanicBaselinesCommand.php` — `refresh:mechanic-baselines`; harvest → normalize → aggregate → `mergeYaml`.
- **Modify** `app/Services/Analysis/TacticalDataAnalyzer.php` — constructor-inject the normalizer + loader; load the baseline once; thread it into `detectMechanicFailures()` → `applyTypeSpecificEvidence()`; rewrite the `tank_swap` branch.

Tests under `tests/Unit/...` and `tests/Feature/...` mirroring those paths.

---

## Task 1: StatsHelper (percentile + summarize)

**Files:**
- Create: `app/Helpers/StatsHelper.php`
- Test: `tests/Unit/Helpers/StatsHelperTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\StatsHelper;
use PHPUnit\Framework\TestCase;

class StatsHelperTest extends TestCase
{
    public function test_percentile_interpolates(): void
    {
        $values = [10, 20, 30, 40];
        $this->assertSame(10.0, StatsHelper::percentile($values, 0));
        $this->assertSame(40.0, StatsHelper::percentile($values, 100));
        $this->assertSame(25.0, StatsHelper::percentile($values, 50));
    }

    public function test_percentile_empty_returns_zero(): void
    {
        $this->assertSame(0.0, StatsHelper::percentile([], 50));
    }

    public function test_summarize_returns_envelope(): void
    {
        $out = StatsHelper::summarize([11, 12, 13, 13, 14, 15]);
        $this->assertSame(6, $out['sample']);
        $this->assertEqualsWithDelta(13.0, $out['median'], 0.01);
        $this->assertGreaterThanOrEqual($out['p5'], $out['median']);
        $this->assertGreaterThanOrEqual($out['median'], $out['p95']);
    }

    public function test_summarize_empty_returns_null(): void
    {
        $this->assertNull(StatsHelper::summarize([]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StatsHelperTest`
Expected: FAIL — `Class "App\Helpers\StatsHelper" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Pure statistical helpers for empirical baselines. No state, no I/O.
 */
class StatsHelper
{
    /**
     * Linear-interpolated percentile. p in [0,100].
     *
     * @param array<int|float> $values
     */
    public static function percentile(array $values, float $p): float
    {
        if (empty($values)) {
            return 0.0;
        }
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lower = (int) floor($idx);
        $upper = (int) ceil($idx);
        if ($lower === $upper) {
            return (float) $values[$lower];
        }
        $frac = $idx - $lower;
        return $values[$lower] * (1 - $frac) + $values[$upper] * $frac;
    }

    /**
     * Reduce a list of observations to a baseline envelope.
     *
     * @param array<int|float> $values
     * @return array{median: float, p5: float, p95: float, sample: int}|null
     */
    public static function summarize(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        return [
            'median' => round(self::percentile($values, 50), 2),
            'p5'     => round(self::percentile($values, 5), 2),
            'p95'    => round(self::percentile($values, 95), 2),
            'sample' => count($values),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=StatsHelperTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Helpers/StatsHelper.php tests/Unit/Helpers/StatsHelperTest.php
git commit -m "feat(ai): StatsHelper percentile + summarize for empirical baselines"
```

---

## Task 2: Per-swap detail in calculateSwapTiming

The normalizer needs the stack count on the outgoing tank at each swap. `calculateSwapTiming()` currently emits only gap aggregates. Add a `swaps` list `[{outgoing_player, stacks_at_swap, gap_ms}]` by tracking the running stack count the same way `parseDebuffStacks()` does (applydebuff=1, applydebuffstack=event['stack'], removedebuff=read-then-zero).

**Files:**
- Modify: `app/Helpers/WclReportParserHelper.php` — `calculateSwapTiming()` (~lines 1080-1132).
- Test: `tests/Unit/Analysis/SwapTimingDetailTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Helpers\WclReportParserHelper;
use PHPUnit\Framework\TestCase;

class SwapTimingDetailTest extends TestCase
{
    public function test_swap_timing_reports_outgoing_player_and_stacks(): void
    {
        $actorMap = [1 => 'TankA', 2 => 'TankB'];
        // TankA stacks to 3 then swaps out at t=3000; TankB taunts at t=3500.
        $events = [
            ['type' => 'applydebuff',      'targetID' => 1, 'fight' => 7, 'timestamp' => 0],
            ['type' => 'applydebuffstack', 'targetID' => 1, 'fight' => 7, 'timestamp' => 1000, 'stack' => 2],
            ['type' => 'applydebuffstack', 'targetID' => 1, 'fight' => 7, 'timestamp' => 2000, 'stack' => 3],
            ['type' => 'removedebuff',     'targetID' => 1, 'fight' => 7, 'timestamp' => 3000],
            ['type' => 'applydebuff',      'targetID' => 2, 'fight' => 7, 'timestamp' => 3500],
        ];

        $parsed = WclReportParserHelper::parseDebuffStacks($events, $actorMap);
        $swaps = $parsed['swap_timing']['swaps'] ?? null;

        $this->assertIsArray($swaps);
        $this->assertCount(1, $swaps);
        $this->assertSame('TankA', $swaps[0]['outgoing_player']);
        $this->assertSame(3, $swaps[0]['stacks_at_swap']);
        $this->assertSame(500, $swaps[0]['gap_ms']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SwapTimingDetailTest`
Expected: FAIL — `$swaps` is null (`swaps` key not yet produced).

- [ ] **Step 3: Modify `calculateSwapTiming` to track stacks and emit `swaps`**

Replace the body of `calculateSwapTiming` (currently ~lines 1080-1132) with:

```php
    private static function calculateSwapTiming(array $events, array $actorMap): array
    {
        // Group events by fight
        $byFight = [];
        foreach ($events as $e) {
            $fightId = $e['fight'] ?? null;
            if (!$fightId) continue;
            $byFight[$fightId][] = $e;
        }

        $gaps = [];
        $swaps = []; // [{outgoing_player, stacks_at_swap, gap_ms}]

        foreach ($byFight as $fightId => $fightEvents) {
            usort($fightEvents, fn($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

            $currentStacks = []; // [targetID => running stack]
            $lastRemoval = null; // ['target' => id, 'time' => ts, 'stacks' => int]
            foreach ($fightEvents as $event) {
                $type = $event['type'] ?? '';
                $targetId = $event['targetID'] ?? null;
                $ts = $event['timestamp'] ?? 0;

                if ($type === 'applydebuff') {
                    $currentStacks[$targetId] = 1;
                } elseif ($type === 'applydebuffstack') {
                    $currentStacks[$targetId] = $event['stack'] ?? 1;
                } elseif ($type === 'removedebuff') {
                    $lastRemoval = [
                        'target' => $targetId,
                        'time'   => $ts,
                        'stacks' => (int) ($currentStacks[$targetId] ?? 0),
                    ];
                    $currentStacks[$targetId] = 0;
                    continue;
                }

                if ($type === 'applydebuff' && $lastRemoval !== null && $targetId !== $lastRemoval['target']) {
                    $gap = $ts - $lastRemoval['time'];
                    if ($gap >= 0 && $gap < 30000) { // sanity cap at 30s
                        $gaps[] = $gap;
                        $swaps[] = [
                            'outgoing_player' => $actorMap[$lastRemoval['target']] ?? 'Unknown',
                            'stacks_at_swap'  => $lastRemoval['stacks'],
                            'gap_ms'          => $gap,
                        ];
                    }
                    $lastRemoval = null;
                }
            }
        }

        if (empty($gaps)) {
            return [
                'avg_gap_ms' => null,
                'min_gap_ms' => null,
                'max_gap_ms' => null,
                'late_swaps' => 0,
                'total_swaps' => 0,
                'swaps' => [],
            ];
        }

        return [
            'avg_gap_ms'  => (int) round(array_sum($gaps) / count($gaps)),
            'min_gap_ms'  => min($gaps),
            'max_gap_ms'  => max($gaps),
            'late_swaps'  => count(array_filter($gaps, fn($g) => $g > 3000)),
            'total_swaps' => count($gaps),
            'swaps'       => $swaps,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SwapTimingDetailTest`
Expected: PASS.

- [ ] **Step 5: Verify no regression in existing debuff-stack consumers**

Run: `php artisan test --filter=Analysis`
Expected: PASS (existing `avg_gap_ms`/`late_swaps`/`total_swaps` keys unchanged; only an additive `swaps` key).

- [ ] **Step 6: Commit**

```bash
git add app/Helpers/WclReportParserHelper.php tests/Unit/Analysis/SwapTimingDetailTest.php
git commit -m "feat(ai): emit per-swap outgoing_player + stacks_at_swap in swap timing"
```

---

## Task 3: MechanicMetricsNormalizer::tankSwap (per-log vector)

**Files:**
- Create: `app/Services/Analysis/MechanicMetricsNormalizer.php`
- Test: `tests/Unit/Analysis/MechanicMetricsNormalizerTankSwapTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerTankSwapTest extends TestCase
{
    private function debuffEntry(): array
    {
        return [
            'max_stacks_per_player' => ['TankA' => 14, 'TankB' => 13],
            'stacks_at_death_per_player' => [
                'TankB' => [['fight_id' => 7, 'max_stacks' => 17, 'killing_blow' => 'Vaelwing']],
            ],
            'swap_timing' => [
                'swaps' => [
                    ['outgoing_player' => 'TankA', 'stacks_at_swap' => 12, 'gap_ms' => 800],
                    ['outgoing_player' => 'TankB', 'stacks_at_swap' => 13, 'gap_ms' => 900],
                    ['outgoing_player' => 'TankA', 'stacks_at_swap' => 13, 'gap_ms' => 750],
                ],
            ],
        ];
    }

    public function test_tank_swap_builds_observation_vectors(): void
    {
        $n = new MechanicMetricsNormalizer();
        $v = $n->tankSwap($this->debuffEntry());

        $this->assertSame([12, 13, 13], $v['observations']['swap_at_stacks']);
        $this->assertSame([800, 900, 750], $v['observations']['swap_gap_ms']);
        $this->assertSame([14, 13], array_values($v['observations']['max_stacks_held']));
        $this->assertSame([17], $v['death_stacks']);
    }

    public function test_tank_swap_by_player_attribution(): void
    {
        $n = new MechanicMetricsNormalizer();
        $v = $n->tankSwap($this->debuffEntry());

        $this->assertSame([12, 13], $v['by_player']['TankA']['swap_at_stacks']);
        $this->assertSame(14, $v['by_player']['TankA']['max_held']);
        $this->assertSame([], $v['by_player']['TankA']['death_stacks']);
        $this->assertSame([17], $v['by_player']['TankB']['death_stacks']);
    }

    public function test_debuff_matcher_token_based(): void
    {
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Vaelwing', 'Vaelwing', []));
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Vaelwing Stacks', 'Vaelwing', []));
        $this->assertTrue(MechanicMetricsNormalizer::debuffMatchesMechanic('Rakfang Mark', 'Something', ['Rakfang']));
        $this->assertFalse(MechanicMetricsNormalizer::debuffMatchesMechanic('Nullzone', 'Vaelwing', []));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MechanicMetricsNormalizerTankSwapTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the normalizer with `tankSwap` + `debuffMatchesMechanic`**

```php
<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Single source of truth for WHAT we measure per mechanic type. The same
 * methods run during the top-kill harvest (refresh:mechanic-baselines) and
 * during live detection (TacticalDataAnalyzer), so the learned baseline and
 * the judged value are always the same measurement — no drift.
 *
 * Per type there are three methods:
 *   - <type>()           : one log's debuff/event data → observation vectors
 *   - aggregate<Type>()  : list of per-log vectors → empirical YAML entry
 *   - evaluate<Type>()   : a live vector + a baseline entry → flagged players
 */
class MechanicMetricsNormalizer
{
    /**
     * One log's tank-swap debuff entry → observation vectors + per-player
     * attribution. Input is a single entry from parseDebuffStacks().
     *
     * @param array $debuffEntry  { max_stacks_per_player, stacks_at_death_per_player, swap_timing:{swaps:[...]} }
     * @return array{observations: array, death_stacks: array<int>, by_player: array}
     */
    public function tankSwap(array $debuffEntry): array
    {
        $swaps = $debuffEntry['swap_timing']['swaps'] ?? [];
        $maxHeld = $debuffEntry['max_stacks_per_player'] ?? [];
        $deathsByPlayer = $debuffEntry['stacks_at_death_per_player'] ?? [];

        $swapAtStacks = [];
        $swapGapMs = [];
        $byPlayer = [];

        foreach ($swaps as $s) {
            $p = $s['outgoing_player'] ?? 'Unknown';
            $stacks = (int) ($s['stacks_at_swap'] ?? 0);
            $gap = (int) ($s['gap_ms'] ?? 0);
            $swapAtStacks[] = $stacks;
            $swapGapMs[] = $gap;
            $byPlayer[$p]['swap_at_stacks'][] = $stacks;
        }

        $deathStacks = [];
        foreach ($deathsByPlayer as $player => $deaths) {
            foreach ($deaths as $d) {
                $stacks = (int) ($d['max_stacks'] ?? 0);
                if ($stacks > 0) {
                    $deathStacks[] = $stacks;
                    $byPlayer[$player]['death_stacks'][] = $stacks;
                }
            }
        }

        // Fill per-player held + defaults so every referenced player is complete.
        foreach (array_keys($byPlayer + $maxHeld) as $player) {
            $byPlayer[$player]['swap_at_stacks'] = $byPlayer[$player]['swap_at_stacks'] ?? [];
            $byPlayer[$player]['death_stacks'] = $byPlayer[$player]['death_stacks'] ?? [];
            $byPlayer[$player]['max_held'] = (int) ($maxHeld[$player] ?? 0);
        }

        return [
            'observations' => [
                'swap_at_stacks'  => $swapAtStacks,
                'swap_gap_ms'     => $swapGapMs,
                'max_stacks_held' => array_values(array_map('intval', $maxHeld)),
            ],
            'death_stacks' => $deathStacks,
            'by_player'    => $byPlayer,
        ];
    }

    /**
     * Token-based match of a debuff name to a mechanic (name + aliases),
     * mirroring the existing nameContainsAnyToken logic in TacticalDataAnalyzer.
     */
    public static function debuffMatchesMechanic(string $debuffName, string $mechanicName, array $aliases = []): bool
    {
        $tokens = self::tokens($mechanicName);
        foreach ($aliases as $alias) {
            $tokens = array_merge($tokens, self::tokens((string) $alias));
        }
        $hay = mb_strtolower($debuffName);
        foreach (array_unique($tokens) as $t) {
            if ($t !== '' && str_contains($hay, $t)) {
                return true;
            }
        }
        return false;
    }

    /** Split a name into lowercase word tokens of length >= 4 (skip short noise). */
    private static function tokens(string $name): array
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', mb_strtolower($name)) ?: [];
        return array_values(array_filter($parts, fn($p) => mb_strlen($p) >= 4));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MechanicMetricsNormalizerTankSwapTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/MechanicMetricsNormalizer.php tests/Unit/Analysis/MechanicMetricsNormalizerTankSwapTest.php
git commit -m "feat(ai): MechanicMetricsNormalizer tankSwap per-log vector + debuff matcher"
```

---

## Task 4: aggregateTankSwap (vectors → empirical entry)

**Files:**
- Modify: `app/Services/Analysis/MechanicMetricsNormalizer.php`
- Test: `tests/Unit/Analysis/MechanicMetricsNormalizerAggregateTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerAggregateTest extends TestCase
{
    public function test_aggregate_builds_empirical_entry(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vectors = [
            ['observations' => ['swap_at_stacks' => [12, 13], 'swap_gap_ms' => [800], 'max_stacks_held' => [14]], 'death_stacks' => [18]],
            ['observations' => ['swap_at_stacks' => [13, 14], 'swap_gap_ms' => [900], 'max_stacks_held' => [15]], 'death_stacks' => [17]],
        ];

        $entry = $n->aggregateTankSwap($vectors);

        $this->assertSame('tank_swap', $entry['type']);
        $this->assertSame(2, $entry['sample']);
        $this->assertSame(17, $entry['lethal_stacks']); // min death stack observed
        $this->assertArrayHasKey('swap_at_stacks', $entry['metrics']);
        $this->assertSame(4, $entry['metrics']['swap_at_stacks']['sample']); // 12,13,13,14
        $this->assertEqualsWithDelta(13.0, $entry['metrics']['swap_at_stacks']['median'], 0.5);
    }

    public function test_aggregate_no_deaths_null_lethal(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vectors = [
            ['observations' => ['swap_at_stacks' => [12], 'swap_gap_ms' => [800], 'max_stacks_held' => [13]], 'death_stacks' => []],
        ];
        $entry = $n->aggregateTankSwap($vectors);
        $this->assertNull($entry['lethal_stacks']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MechanicMetricsNormalizerAggregateTest`
Expected: FAIL — `aggregateTankSwap` not defined.

- [ ] **Step 3: Add `aggregateTankSwap`**

Add `use App\Helpers\StatsHelper;` at the top of the file, then add this method to the class:

```php
    /**
     * Aggregate per-log tank-swap vectors into a baseline YAML entry.
     *
     * @param array<int,array> $vectors  output of tankSwap() per top-kill log
     * @return array{type: string, sample: int, lethal_stacks: int|null, metrics: array}
     */
    public function aggregateTankSwap(array $vectors): array
    {
        $swapAt = [];
        $gaps = [];
        $held = [];
        $deathStacks = [];

        foreach ($vectors as $v) {
            $obs = $v['observations'] ?? [];
            $swapAt = array_merge($swapAt, $obs['swap_at_stacks'] ?? []);
            $gaps = array_merge($gaps, $obs['swap_gap_ms'] ?? []);
            $held = array_merge($held, $obs['max_stacks_held'] ?? []);
            $deathStacks = array_merge($deathStacks, $v['death_stacks'] ?? []);
        }

        $metrics = array_filter([
            'swap_at_stacks'  => StatsHelper::summarize($swapAt),
            'swap_gap_ms'     => StatsHelper::summarize($gaps),
            'max_stacks_held' => StatsHelper::summarize($held),
        ]);

        return [
            'type'          => 'tank_swap',
            'sample'        => count($vectors),
            'lethal_stacks' => empty($deathStacks) ? null : (int) min($deathStacks),
            'metrics'       => $metrics,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MechanicMetricsNormalizerAggregateTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/MechanicMetricsNormalizer.php tests/Unit/Analysis/MechanicMetricsNormalizerAggregateTest.php
git commit -m "feat(ai): aggregateTankSwap builds empirical envelope from top-kill vectors"
```

---

## Task 5: evaluateTankSwap (the flag rule) — includes the bug regression

This is the rule that fixes the reported bug. A tank is flagged ONLY when they swap later than the learned p95 **or** die at/above the learned lethal stacks. Merely reaching a high max stack is NOT a failure.

**Files:**
- Modify: `app/Services/Analysis/MechanicMetricsNormalizer.php`
- Test: `tests/Unit/Analysis/MechanicMetricsNormalizerEvaluateTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use App\Services\Analysis\MechanicMetricsNormalizer;
use PHPUnit\Framework\TestCase;

class MechanicMetricsNormalizerEvaluateTest extends TestCase
{
    private function baseline(): array
    {
        return [
            'type' => 'tank_swap',
            'sample' => 12,
            'lethal_stacks' => 17,
            'metrics' => [
                'swap_at_stacks' => ['median' => 13.0, 'p5' => 11.0, 'p95' => 15.0, 'sample' => 24],
            ],
        ];
    }

    public function test_on_time_swap_at_high_stacks_is_not_flagged(): void
    {
        // The reported bug: swapping at 13 (normal) must NOT be flagged just for exceeding an old "7".
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankA' => ['swap_at_stacks' => [12, 13], 'max_held' => 14, 'death_stacks' => []],
            'TankB' => ['swap_at_stacks' => [13, 13], 'max_held' => 13, 'death_stacks' => []],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertSame([], $eval['flagged']);
    }

    public function test_late_swap_beyond_p95_is_flagged(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankA' => ['swap_at_stacks' => [13, 19], 'max_held' => 19, 'death_stacks' => []],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertArrayHasKey('TankA', $eval['flagged']);
        $this->assertStringContainsString('19', $eval['flagged']['TankA']);
    }

    public function test_death_at_lethal_stacks_is_flagged(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => [
            'TankB' => ['swap_at_stacks' => [13], 'max_held' => 17, 'death_stacks' => [17]],
        ]];

        $eval = $n->evaluateTankSwap($vector, $this->baseline());
        $this->assertArrayHasKey('TankB', $eval['flagged']);
    }

    public function test_missing_baseline_metrics_flags_nobody(): void
    {
        $n = new MechanicMetricsNormalizer();
        $vector = ['by_player' => ['TankA' => ['swap_at_stacks' => [99], 'max_held' => 99, 'death_stacks' => []]]];
        $eval = $n->evaluateTankSwap($vector, ['metrics' => []]);
        $this->assertSame([], $eval['flagged']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MechanicMetricsNormalizerEvaluateTest`
Expected: FAIL — `evaluateTankSwap` not defined.

- [ ] **Step 3: Add `evaluateTankSwap`**

```php
    /**
     * Flag rule for tank swaps. A player is flagged ONLY when they swap later
     * than p95 of top kills, or die at/above the learned lethal stack count.
     * Reaching a high max stack on its own is NOT a failure.
     *
     * @param array $liveVector    output of tankSwap() for this raid (needs by_player)
     * @param array $baselineEntry empirical entry (aggregateTankSwap output)
     * @return array{flagged: array<string,string>, note: string|null}
     */
    public function evaluateTankSwap(array $liveVector, array $baselineEntry): array
    {
        $p95 = $baselineEntry['metrics']['swap_at_stacks']['p95'] ?? null;
        $median = $baselineEntry['metrics']['swap_at_stacks']['median'] ?? null;
        $lethal = $baselineEntry['lethal_stacks'] ?? null;

        if ($p95 === null) {
            return ['flagged' => [], 'note' => null];
        }

        $flagged = [];
        foreach ($liveVector['by_player'] ?? [] as $player => $pdata) {
            $swaps = $pdata['swap_at_stacks'] ?? [];
            if (!empty($swaps)) {
                $worst = max($swaps);
                if ($worst > $p95) {
                    $flagged[$player] = sprintf(
                        'swapped at %d stacks — top kills swap by %d (p95 %d)',
                        $worst, (int) round($median), (int) round($p95)
                    );
                }
            }
            $deaths = $pdata['death_stacks'] ?? [];
            if ($lethal !== null && !empty($deaths) && max($deaths) >= $lethal) {
                $flagged[$player] = sprintf('died at %d stacks (top-kill lethal point %d)', max($deaths), $lethal);
            }
        }

        $note = sprintf('Top kills swap by %d stacks (p95 %d).', (int) round($median), (int) round($p95));

        return ['flagged' => $flagged, 'note' => $note];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MechanicMetricsNormalizerEvaluateTest`
Expected: PASS (4 tests). The first test is the explicit regression for the reported bug.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/MechanicMetricsNormalizer.php tests/Unit/Analysis/MechanicMetricsNormalizerEvaluateTest.php
git commit -m "feat(ai): evaluateTankSwap flag rule (out-of-envelope only) + bug regression test"
```

---

## Task 6: MechanicBaselineLoader

Reads the `empirical:` block from the boss-timeline YAML, mirroring `BossTimelineLoader` (same path scheme, in-memory cache, graceful null).

**Files:**
- Create: `app/Services/Analysis/MechanicBaselineLoader.php`
- Test: `tests/Feature/Analysis/MechanicBaselineLoaderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Services\Analysis\MechanicBaselineLoader;
use Tests\TestCase;

class MechanicBaselineLoaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = resource_path('boss-timelines/test-season/mythic');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->dir . '/test-boss.yml', <<<YAML
            boss: 'Test Boss'
            mechanics:
              - name: Vaelwing
                type: tank_swap
            empirical:
              difficulty: mythic
              mechanics:
                Vaelwing:
                  type: tank_swap
                  lethal_stacks: 17
                  metrics:
                    swap_at_stacks: { median: 13, p5: 11, p95: 15, sample: 24 }
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/test-boss.yml');
        parent::tearDown();
    }

    public function test_loads_empirical_block(): void
    {
        $loader = new MechanicBaselineLoader();
        $baseline = $loader->load('test-season', 'test-boss', 'mythic');

        $this->assertNotNull($baseline);
        $this->assertSame(17, $baseline['mechanics']['Vaelwing']['lethal_stacks']);
        $this->assertSame(15, $baseline['mechanics']['Vaelwing']['metrics']['swap_at_stacks']['p95']);
    }

    public function test_missing_file_returns_null(): void
    {
        $loader = new MechanicBaselineLoader();
        $this->assertNull($loader->load('test-season', 'no-such-boss', 'mythic'));
    }

    public function test_file_without_empirical_returns_null(): void
    {
        file_put_contents($this->dir . '/bare-boss.yml', "boss: 'Bare'\nmechanics: []\n");
        $loader = new MechanicBaselineLoader();
        $this->assertNull($loader->load('test-season', 'bare-boss', 'mythic'));
        @unlink($this->dir . '/bare-boss.yml');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MechanicBaselineLoaderTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the loader**

```php
<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads the `empirical:` block (top-kill mechanic baselines) from a
 * boss-timeline YAML. Same file + path scheme as BossTimelineLoader:
 *   resources/boss-timelines/{season}/{difficulty}/{slug}.yml
 *
 * Returns the raw empirical block, or null when the file or block is absent
 * (callers then run informational-only — they flag nobody).
 */
class MechanicBaselineLoader
{
    /** @var array<string, array|null> */
    private array $cache = [];

    public function load(?string $season, ?string $slug, ?string $difficulty): ?array
    {
        if (!$season || !$slug || !$difficulty) {
            return null;
        }

        $key = "{$season}/{$difficulty}/{$slug}";
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $path = resource_path("boss-timelines/{$season}/{$difficulty}/{$slug}.yml");
        if (!is_file($path)) {
            return $this->cache[$key] = null;
        }

        try {
            $raw = Yaml::parseFile($path);
            $empirical = is_array($raw) ? ($raw['empirical'] ?? null) : null;
            return $this->cache[$key] = (is_array($empirical) && !empty($empirical)) ? $empirical : null;
        } catch (\Throwable) {
            return $this->cache[$key] = null;
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=MechanicBaselineLoaderTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Analysis/MechanicBaselineLoader.php tests/Feature/Analysis/MechanicBaselineLoaderTest.php
git commit -m "feat(ai): MechanicBaselineLoader reads empirical block from boss-timeline YAML"
```

---

## Task 7: RefreshMechanicBaselinesCommand (harvest → aggregate → mergeYaml)

Harvests top-kill logs for a boss, runs the existing `getLogSummary` extraction, feeds each log's `tank_swap` debuffs through `tankSwap()`, aggregates with `aggregateTankSwap()`, and merges an `empirical:` block. Caching + pacing mirror `RefreshSpecBaselinesCommand`.

**Files:**
- Create: `app/Console/Commands/RefreshMechanicBaselinesCommand.php`
- Test: `tests/Feature/Analysis/RefreshMechanicBaselinesCommandTest.php`

- [ ] **Step 1: Write the failing test** (mocks WclService — no real WCL)

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Analysis;

use App\Services\Analysis\WclService;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class RefreshMechanicBaselinesCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = resource_path('boss-timelines/test-season/mythic');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
        file_put_contents($this->dir . '/test-boss.yml', <<<YAML
            boss: 'Test Boss'
            wcl_encounter_id: 9999
            mechanics:
              - name: Vaelwing
                type: tank_swap
            YAML);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/test-boss.yml');
        parent::tearDown();
    }

    public function test_harvest_writes_empirical_tank_swap_block(): void
    {
        $logData = ['debuff_stacks' => ['Vaelwing' => [
            'max_stacks_per_player' => ['TankA' => 14, 'TankB' => 13],
            'stacks_at_death_per_player' => [],
            'swap_timing' => ['swaps' => [
                ['outgoing_player' => 'TankA', 'stacks_at_swap' => 12, 'gap_ms' => 800],
                ['outgoing_player' => 'TankB', 'stacks_at_swap' => 13, 'gap_ms' => 900],
            ]],
        ]]];

        $mock = $this->createMock(WclService::class);
        $mock->method('fetchCharacterRankings')->willReturn([
            ['name' => 'X', 'report' => ['code' => 'AAA', 'fightID' => 1]],
            ['name' => 'Y', 'report' => ['code' => 'AAA', 'fightID' => 1]], // dup report → deduped
            ['name' => 'Z', 'report' => ['code' => 'BBB', 'fightID' => 2]],
        ]);
        $mock->method('getLogSummary')->willReturn($logData);
        $this->app->instance(WclService::class, $mock);

        $this->artisan('refresh:mechanic-baselines', [
            '--bosses' => 'test-boss',
            '--season' => 'test-season',
            '--difficulty' => 5,
            '--kills' => 5,
            '--pace' => 0,
            '--force' => true,
        ])->assertExitCode(0);

        $yaml = Yaml::parseFile($this->dir . '/test-boss.yml');
        $entry = $yaml['empirical']['mechanics']['Vaelwing'];
        $this->assertSame('tank_swap', $entry['type']);
        $this->assertSame(2, $entry['sample']);            // 2 unique kill logs (AAA, BBB)
        $this->assertArrayHasKey('swap_at_stacks', $entry['metrics']);
        // authored mechanics preserved
        $this->assertSame('Vaelwing', $yaml['mechanics'][0]['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RefreshMechanicBaselinesCommandTest`
Expected: FAIL — command `refresh:mechanic-baselines` not defined.

- [ ] **Step 3: Create the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\MechanicMetricsNormalizer;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Refresh per-boss mechanic baselines from top WCL kills. Mirrors
 * refresh:spec-baselines but for boss mechanics: harvests top-kill logs,
 * runs the existing getLogSummary extraction, normalises each log's
 * mechanics through MechanicMetricsNormalizer, aggregates to median/p5/p95,
 * and merges an `empirical:` block into the boss-timeline YAML (authored
 * `mechanics:` preserved).
 *
 * Usage:
 *   php artisan refresh:mechanic-baselines --bosses=vaelgor-ezzorak --kills=5 --dry-run
 *   php artisan refresh:mechanic-baselines               # all bosses in the season
 */
class RefreshMechanicBaselinesCommand extends Command
{
    protected $signature = 'refresh:mechanic-baselines
                            {--bosses= : comma-separated boss slugs (default: all in season/difficulty)}
                            {--kills=10 : top kills to sample per boss}
                            {--difficulty=5 : 3=Normal 4=Heroic 5=Mythic}
                            {--season= : season slug (default: config wow_season.current_season)}
                            {--dry-run : print summary without writing YAML}
                            {--force : ignore cached per-report extraction}
                            {--pace=6 : seconds between WCL calls}';

    protected $description = 'Refresh boss mechanic baselines from empirical top-kill WCL data.';

    private const CACHE_DIR = 'mechanic-baseline-cache';
    private const CACHE_TTL_DAYS = 7;

    private const DIFFICULTY_NAME = [3 => 'normal', 4 => 'heroic', 5 => 'mythic'];

    public function __construct(
        private readonly WclService $wcl,
        private readonly MechanicMetricsNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $season = (string) ($this->option('season') ?: config('wow_season.current_season'));
        $difficulty = (int) $this->option('difficulty');
        $diffName = self::DIFFICULTY_NAME[$difficulty] ?? 'mythic';
        $kills = (int) $this->option('kills');
        $pace = (int) $this->option('pace');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $dir = resource_path("boss-timelines/{$season}/{$diffName}");
        $slugs = $this->resolveBosses((string) $this->option('bosses'), $dir);

        if (empty($slugs)) {
            $this->error("No boss-timeline YAMLs found in {$dir}.");
            return self::FAILURE;
        }

        foreach ($slugs as $slug) {
            $path = "{$dir}/{$slug}.yml";
            $yaml = Yaml::parseFile($path) ?: [];
            $encounterId = (int) ($yaml['wcl_encounter_id'] ?? 0);
            $mechanics = $yaml['mechanics'] ?? [];
            if ($encounterId === 0 || empty($mechanics)) {
                $this->warn("  {$slug}: missing wcl_encounter_id or mechanics — skipped.");
                continue;
            }

            $reports = $this->topKillReports($encounterId, $difficulty, $kills, $pace);
            if (empty($reports)) {
                $this->warn("  {$slug}: no top-kill reports returned — skipped.");
                continue;
            }

            // type → mechanic name → list of per-log vectors
            $vectorsByMechanic = [];
            foreach ($reports as [$reportCode, $fightId]) {
                $logData = $this->getOrFetchLog($slug, $reportCode, $fightId, $difficulty, $force, $pace);
                $debuffStacks = $logData['debuff_stacks'] ?? [];

                foreach ($mechanics as $mech) {
                    if (($mech['type'] ?? '') !== 'tank_swap') {
                        continue; // Phase 1 models tank_swap only
                    }
                    $name = (string) ($mech['name'] ?? '');
                    $aliases = $mech['aliases'] ?? [];
                    foreach ($debuffStacks as $debuffName => $entry) {
                        if (!MechanicMetricsNormalizer::debuffMatchesMechanic($debuffName, $name, $aliases)) {
                            continue;
                        }
                        $vectorsByMechanic[$name][] = $this->normalizer->tankSwap($entry);
                    }
                }
            }

            $empiricalMechanics = [];
            foreach ($vectorsByMechanic as $name => $vectors) {
                $empiricalMechanics[$name] = $this->normalizer->aggregateTankSwap($vectors);
            }

            if (empty($empiricalMechanics)) {
                $this->warn("  {$slug}: no tank_swap data extracted — nothing written.");
                continue;
            }

            $empirical = [
                'difficulty'    => $diffName,
                'kills_sampled' => count($reports),
                'fetched_at'    => now()->toDateString(),
                'mechanics'     => $empiricalMechanics,
            ];

            $this->line("  {$slug}: " . count($empiricalMechanics) . " mechanic(s), {$empirical['kills_sampled']} kills sampled.");
            foreach ($empiricalMechanics as $name => $e) {
                $sa = $e['metrics']['swap_at_stacks'] ?? null;
                if ($sa) {
                    $this->line(sprintf('      %s: swap_at_stacks median=%.1f p95=%.1f lethal=%s', $name, $sa['median'], $sa['p95'], $e['lethal_stacks'] ?? '—'));
                }
            }

            if (!$dryRun) {
                $yaml['empirical'] = $empirical;
                $out = Yaml::dump($yaml, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                $tmp = $path . '.tmp';
                file_put_contents($tmp, $out);
                rename($tmp, $path);
                $this->info("  {$slug}: empirical block written.");
            }
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function resolveBosses(string $arg, string $dir): array
    {
        if ($arg !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $arg))));
        }
        if (!is_dir($dir)) {
            return [];
        }
        return array_values(array_map(
            fn($p) => basename($p, '.yml'),
            glob("{$dir}/*.yml") ?: []
        ));
    }

    /**
     * Top-kill (report, fightID) pairs for an encounter, deduped to unique
     * report codes. Reuses fetchCharacterRankings (kill reports are what top
     * parses live in); dedupe collapses multiple top parsers in one kill log.
     *
     * @return list<array{0:string,1:int}>
     */
    private function topKillReports(int $encounterId, int $difficulty, int $kills, int $pace): array
    {
        // A single reference query (any high-representation spec) returns the
        // top kill reports; we only need the report codes, deduped.
        $rankings = $this->wcl->fetchCharacterRankings($encounterId, 'DeathKnight', 'Blood', 'playerscore', $difficulty, 1);
        if ($pace > 0) {
            sleep($pace);
        }

        $seen = [];
        $out = [];
        foreach ($rankings as $rank) {
            $code = $rank['report']['code'] ?? null;
            $fightId = (int) ($rank['report']['fightID'] ?? 0);
            if (!$code || !$fightId || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = [$code, $fightId];
            if (count($out) >= $kills) {
                break;
            }
        }
        return $out;
    }

    private function getOrFetchLog(string $slug, string $reportCode, int $fightId, int $difficulty, bool $force, int $pace): array
    {
        $cachePath = storage_path(sprintf('app/%s/%s/%s-f%d-d%d.json', self::CACHE_DIR, $slug, $reportCode, $fightId, $difficulty));

        if (!$force && file_exists($cachePath)) {
            $age = time() - filemtime($cachePath);
            if ($age < self::CACHE_TTL_DAYS * 86400) {
                $cached = json_decode((string) file_get_contents($cachePath), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        @mkdir(dirname($cachePath), 0775, true);
        $logData = $this->wcl->getLogSummary($reportCode, []);
        if ($pace > 0) {
            sleep($pace);
        }
        file_put_contents($cachePath, json_encode($logData, JSON_UNESCAPED_UNICODE));
        return $logData;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RefreshMechanicBaselinesCommandTest`
Expected: PASS. The dedupe (AAA twice → one log) yields `sample == 2`, and the authored `mechanics:` survives.

- [ ] **Step 5: Verify the command registers**

Run: `php artisan list | grep refresh:mechanic-baselines`
Expected: the command appears.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/RefreshMechanicBaselinesCommand.php tests/Feature/Analysis/RefreshMechanicBaselinesCommandTest.php
git commit -m "feat(ai): refresh:mechanic-baselines harvests tank_swap baselines from top kills"
```

---

## Task 8: Wire baseline-driven detection into the tank_swap branch

Replace the magic-number `tank_swap` branch in `applyTypeSpecificEvidence` with the normalizer + baseline. Inject `MechanicMetricsNormalizer` + `MechanicBaselineLoader`; load the baseline once in `analyze()` (mirroring `loadBossTimelineForEncounter`) and thread it through `detectMechanicFailures()`.

**Files:**
- Modify: `app/Services/Analysis/TacticalDataAnalyzer.php`
  - constructor (add two injected deps)
  - `analyze()` ~line 318: load baseline, pass into `detectMechanicFailures`
  - `detectMechanicFailures()` signature ~line 574 (add `array $mechanicBaseline = []`)
  - `applyTypeSpecificEvidence()` signature ~line 784 (add `array $mechanicBaseline = []`) and its call site ~line 639
  - `tank_swap` case ~lines 792-817 (rewrite)
- Test: covered by the Task 5 unit tests (the rule) + Task 9 validation (integration). Add a no-regression suite run here.

- [ ] **Step 1: Inject the two dependencies into the constructor**

Find the existing constructor (it already injects `private readonly IntentCutoffResolver $intentCutoffResolver` and `FightBreakdownBuilder`). Add:

```php
        private readonly MechanicMetricsNormalizer $mechanicMetrics,
        private readonly MechanicBaselineLoader $mechanicBaselineLoader,
```

(Both are in `App\Services\Analysis`, same namespace — no `use` needed.)

- [ ] **Step 2: Load the baseline in `analyze()` and pass it into detection**

Immediately before the `$mechanicFailures = $this->detectMechanicFailures(` call (~line 318), add:

```php
        // Top-kill empirical baseline for this boss/difficulty (same YAML file as
        // the boss timeline). Null/empty when not harvested yet → detection runs
        // informational-only and flags nobody.
        $mechanicBaseline = $this->loadMechanicBaselineForEncounter($tactics, $difficulty) ?? [];
```

Then add `$mechanicBaseline` as the final argument to the `detectMechanicFailures(...)` call.

Add this private method near `loadBossTimelineForEncounter` (read that method for the exact slug/season resolution it uses, and mirror it):

```php
    /**
     * Load the empirical mechanic-baseline block for this encounter from the
     * same boss-timeline YAML the timeline comes from. Mirrors the slug/season/
     * difficulty resolution used by loadBossTimelineForEncounter().
     */
    private function loadMechanicBaselineForEncounter(array $tactics, string $difficulty): ?array
    {
        $season = (string) config('wow_season.current_season');
        $slug = $this->bossTimelineSlug($tactics); // same slug helper loadBossTimelineForEncounter uses
        return $this->mechanicBaselineLoader->load($season, $slug, $difficulty);
    }
```

NOTE: `loadBossTimelineForEncounter` already derives the slug + difficulty directory. Reuse its exact mechanism (call the same private slug helper, or replicate the two lines it uses). If it inlines the slug, extract a tiny `bossTimelineSlug(array $tactics): string` helper and have both call it (DRY). Confirm `$difficulty` here is the directory name ('mythic'); if `loadBossTimelineForEncounter` maps it, apply the same mapping.

- [ ] **Step 3: Thread the baseline through the two private signatures**

`detectMechanicFailures(...)` — add a trailing parameter:

```php
    private function detectMechanicFailures(array $tactics, array $deaths, array $majorDamageTaken, array $debuffStacks, array $orbStaggering, array $shieldedCasts, string $difficulty, array $targetedDamage = [], array $enemyBuffUptimes = [], array $addsDamage = [], array $mechanicBaseline = []): array
```

Inside it, where it calls `$this->applyTypeSpecificEvidence($entry, $mechanic, ...)` (~line 639), pass `$mechanicBaseline` as the final argument.

`applyTypeSpecificEvidence(...)` — add a trailing parameter:

```php
    private function applyTypeSpecificEvidence(array &$entry, array $mechanic, array $debuffStacks, array $orbStaggering, array $shieldedCasts, array $enemyBuffUptimes = [], array $addsDamage = [], array $mechanicBaseline = []): void
```

- [ ] **Step 4: Rewrite the `tank_swap` case**

Replace the current `case 'tank_swap':` block (~lines 792-817) with:

```php
            case 'tank_swap':
                $baseEntry = $mechanicBaseline['mechanics'][$mechName] ?? null;
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!MechanicMetricsNormalizer::debuffMatchesMechanic($debuffName, $mechName, $mechanic['aliases'] ?? [])) {
                        continue;
                    }

                    $vector = $this->mechanicMetrics->tankSwap($data);

                    // No baseline yet → informational only, flag nobody.
                    if (!$baseEntry || empty($baseEntry['metrics']['swap_at_stacks'])) {
                        $held = $vector['observations']['max_stacks_held'] ?? [];
                        if (!empty($held)) {
                            $entry['notes'][] = 'Max stacks reached: ' . max($held)
                                . ' (no top-kill baseline harvested yet — not counted as a failure).';
                        }
                        continue;
                    }

                    $eval = $this->mechanicMetrics->evaluateTankSwap($vector, $baseEntry);
                    if ($eval['note']) {
                        $entry['evidence'][] = 'tank_swap_baseline';
                        $entry['notes'][] = $eval['note'];
                    }
                    foreach ($eval['flagged'] as $player => $reason) {
                        $entry['players'][$player] = ($entry['players'][$player] ?? 0) + 1;
                        $entry['notes'][] = "{$player}: {$reason}";
                    }

                    // Keep the legitimate late-swap aggregate note.
                    if (($data['swap_timing']['late_swaps'] ?? 0) > 0) {
                        $st = $data['swap_timing'];
                        $entry['notes'][] = "Late swaps: {$st['late_swaps']}/{$st['total_swaps']} (avg gap {$st['avg_gap_ms']}ms, max {$st['max_gap_ms']}ms)";
                    }
                }
                break;
```

- [ ] **Step 5: Run the full analysis test suite for regressions**

Run: `php artisan test --filter=Analysis`
Expected: PASS. (Confirm no other suite relied on the old `stack_swap_threshold`/`max > threshold` behavior. The 2 pre-existing unrelated `RosterTest` failures noted in memory are not in this filter.)

- [ ] **Step 6: Commit**

```bash
git add app/Services/Analysis/TacticalDataAnalyzer.php
git commit -m "feat(ai): tank_swap detection uses learned baseline, drops the ?? 7 magic number"
```

---

## Task 9: Validation (manual — operator runs the full harvest separately)

No code. Verify the slice end-to-end on a small sample. The full multi-hour harvest is run later by the operator.

Host-env note (from RC1): `.env` uses docker service names; prefix local runs with `CACHE_STORE=array REDIS_HOST=127.0.0.1 LOG_CHANNEL=null` and run with the Bash sandbox disabled (WCL egress). Faster inside the docker container.

- [ ] **Step 1: Small-sample dry-run** (eyeball learned numbers)

Run (pick 2 real bosses, e.g. `vaelgor-ezzorak`):
```bash
php artisan refresh:mechanic-baselines --bosses=vaelgor-ezzorak --kills=3 --difficulty=5 --dry-run
```
Expected: prints `swap_at_stacks median=… p95=… lethal=…` with sane numbers (e.g. median ~12-14, not ~7), no YAML written.

- [ ] **Step 2: Write the small sample** (no `--dry-run`)

```bash
php artisan refresh:mechanic-baselines --bosses=vaelgor-ezzorak --kills=3 --difficulty=5
```
Then confirm an `empirical:` block exists and the authored `mechanics:` list is intact:
```bash
git diff resources/boss-timelines/midnight-s1/mythic/vaelgor-ezzorak.yml
```

- [ ] **Step 3: Re-run the deterministic analyzer on the Thursday report**

```bash
php artisan ai:diagnose-data mFAKX4d6PnVbrkgp
```
Then inspect the regenerated `..._vaelgor-and-ezzorak_preprocessed.json` `mechanic_failures` for the tank_swap mechanic. Expected: tanks are NO LONGER flagged merely for reaching 12-13 stacks; notes cite the learned baseline ("top kills swap by 13 (p95 15)") instead of "5-7"; only genuinely-late swaps or lethal-stack deaths are flagged.

- [ ] **Step 4: Report to the user** what the learned numbers are and that the false tank-swap flags are gone.

---

## Self-Review Notes

- **Spec coverage (Phase 1 portion):** shared normalizer (Tasks 3-5), `empirical:` YAML schema + loader (Task 6), harvest command with `--kills`/`--difficulty`/`--season`/`--dry-run`/`--force`/`--pace` + cache (Task 7), detection rewrite removing magic numbers + "participation = failure" for tank_swap + graceful degradation (Task 8), validation (Task 9). Other discrete types + movement are explicitly out of scope (follow-on plans), matching the agreed phasing.
- **Type consistency:** `tankSwap()`, `aggregateTankSwap()`, `evaluateTankSwap()`, `debuffMatchesMechanic()`, `StatsHelper::percentile/summarize`, `MechanicBaselineLoader::load($season,$slug,$difficulty)` are used identically across tasks.
- **Known follow-up flagged in Task 8:** `loadMechanicBaselineForEncounter` must reuse the exact slug/difficulty resolution from the existing `loadBossTimelineForEncounter` — the implementer reads that method first and mirrors it (extract a shared `bossTimelineSlug()` if needed).
