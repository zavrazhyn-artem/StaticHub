<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Cast-efficiency-based rotation analysis. Mirrors WoWAnalyzer's model:
 *
 *   max_possible_casts = duration_seconds / cooldown_seconds
 *   efficiency         = actual_casts / max_possible_casts
 *
 * Thresholds (matching WoWAnalyzer defaults):
 *   average_threshold = recommended - 0.05
 *   major_threshold   = recommended - 0.15
 *
 * Flagged as severity = 'major' if efficiency < major_threshold,
 * else 'minor' if < average_threshold, otherwise the ability is passing and
 * no rotation_issue is emitted.
 *
 * Aggregates casts raid-wide for stability (per-encounter view can come later).
 */
class RotationAnalyzer
{
    private const MINOR_DOWNSTEP = 0.05;
    private const MAJOR_DOWNSTEP = 0.15;

    public function __construct(private readonly SpecBaselineLoader $loader) {}

    /**
     * Populate per_player_data[player]['rotation_issues'] in-place.
     *
     * @param array<string, array> $perPlayerData
     * @param array $encounters              Full encounters[] with player_stats.casts_summary
     * @param int   $totalDurationSeconds    Full raid duration (all fights)
     */
    public function apply(array &$perPlayerData, array $encounters, int $totalDurationSeconds): void
    {
        if ($totalDurationSeconds < 60) return;

        $castsByPlayer = $this->aggregateCasts($encounters);

        foreach ($perPlayerData as $name => &$entry) {
            $baseline = $this->loader->load($entry['class'] ?? null, $entry['spec'] ?? null);
            if (!$baseline) continue;

            $checks = $baseline['rotation_checks'] ?? [];
            if (!is_array($checks) || empty($checks)) continue;

            $playerCasts = $castsByPlayer[$name] ?? [];
            if (empty($playerCasts)) continue;

            $analysis = [];
            $issues = [];
            foreach ($checks as $check) {
                $row = $this->evaluateCheck($check, $playerCasts, $totalDurationSeconds);
                if ($row === null) continue;
                $analysis[] = $row;
                if ($row['status'] !== 'passing') {
                    $issues[] = [
                        'ability'  => $row['ability'],
                        'issue'    => $row['summary'],
                        'severity' => $row['status'],
                    ];
                }
            }

            if (!empty($analysis)) {
                $entry['rotation_analysis'] = [
                    'source'           => $baseline['source'] ?? 'WoWAnalyzer-midnight',
                    'duration_seconds' => $totalDurationSeconds,
                    'checks'           => $analysis,
                ];
            }
            if (!empty($issues)) {
                $entry['rotation_issues'] = $issues;
            }
        }
    }

    /**
     * Evaluate a single check. Returns null if the ability isn't talented/used at all,
     * otherwise returns a full row (including 'passing' entries — the chat needs to see
     * what a player did RIGHT too, not just what they missed).
     */
    private function evaluateCheck(array $check, array $playerCasts, int $durationSec): ?array
    {
        if (($check['check'] ?? null) !== 'cast_efficiency') return null;

        $ability     = $check['ability'] ?? null;
        $cooldown    = $check['cooldown_seconds'] ?? null;
        $recommended = (float) ($check['recommended_efficiency'] ?? 0.80);
        $severityCfg = $check['severity'] ?? 'minor';

        if (!$ability || !is_numeric($cooldown) || $cooldown <= 0) return null;

        $maxCasts = $durationSec / (float) $cooldown;
        if ($maxCasts < 1) return null; // fight too short to expect even 1 cast

        $actual = (int) ($playerCasts[$ability] ?? 0);

        // Zero casts → most likely the ability isn't talented (Midnight rotations are heavily
        // talent-gated). Skip to avoid false positives on abilities the player can't cast.
        if ($actual === 0) return null;

        $efficiency = $actual / $maxCasts;
        $minorThreshold = $recommended - self::MINOR_DOWNSTEP;
        $majorThreshold = $recommended - self::MAJOR_DOWNSTEP;

        if ($efficiency >= $minorThreshold) {
            $status = 'passing';
        } elseif ($efficiency < $majorThreshold) {
            $status = 'major';
        } else {
            $status = ($severityCfg === 'minor') ? 'minor' : 'minor';
        }

        $pct = (int) round($efficiency * 100);
        $recommendedPct = (int) round($recommended * 100);
        $maxInt = (int) floor($maxCasts);

        $summary = "{$ability} cast efficiency {$pct}% (target {$recommendedPct}%+) — {$actual} casts of a possible {$maxInt}.";

        return [
            'ability'         => $ability,
            'ability_id'      => $check['ability_id'] ?? null,
            'cooldown_seconds' => (float) $cooldown,
            'actual_casts'    => $actual,
            'max_possible'    => $maxInt,
            'efficiency_pct'  => $pct,
            'recommended_pct' => $recommendedPct,
            'status'          => $status,
            'summary'         => $summary,
        ];
    }

    /**
     * @return array<string, array<string, int>>  [playerName => [abilityName => totalCasts]]
     */
    private function aggregateCasts(array $encounters): array
    {
        $out = [];
        foreach ($encounters as $enc) {
            $casts = $enc['player_stats']['casts_summary'] ?? [];
            foreach ($casts as $player => $abilities) {
                if (!is_array($abilities)) continue;
                foreach ($abilities as $ability => $count) {
                    $out[$player][$ability] = ($out[$player][$ability] ?? 0) + (int) $count;
                }
            }
        }
        return $out;
    }
}
