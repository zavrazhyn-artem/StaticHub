<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\StatsHelper;

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

    /**
     * Token-based match of a debuff name to a mechanic (name + aliases),
     * mirroring the existing nameContainsAnyToken logic in TacticalDataAnalyzer.
     *
     * @param string        $debuffName   debuff name from the log data
     * @param string        $mechanicName authored mechanic name (tokenized)
     * @param array<string> $aliases      optional extra mechanic aliases
     * @return bool                       true if any token is a substring of the debuff name
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
