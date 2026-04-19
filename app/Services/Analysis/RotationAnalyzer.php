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
 * CRITICAL — duration is **participated_seconds**, not total raid duration. A player
 * benched for half the night should not be penalized as if they were there. We determine
 * participation per-encounter by checking whether they appear in the encounter's casts_summary.
 *
 * Outputs:
 *   per_player_data[player].rotation_analysis  — raid-wide aggregate, normalized to participation
 *   per_player_data[player].rotation_issues    — compact list of non-passing checks
 *   encounters[i].player_rotation[player]      — per-encounter breakdown (so the AI sees attendance)
 */
class RotationAnalyzer
{
    private const MINOR_DOWNSTEP = 0.05;
    private const MAJOR_DOWNSTEP = 0.15;

    public function __construct(private readonly SpecBaselineLoader $loader) {}

    /**
     * Populate per_player_data[player]['rotation_analysis' + 'rotation_issues'] in-place,
     * AND attach encounters[i]['player_rotation'] per-encounter breakdown.
     *
     * @param array<string, array> $perPlayerData
     * @param array $encounters   Full encounters[] with duration_seconds + player_stats.casts_summary
     * @param int   $totalDurationSeconds  Total raid duration (fallback)
     */
    public function apply(array &$perPlayerData, array &$encounters, int $totalDurationSeconds): void
    {
        if ($totalDurationSeconds < 60) return;

        // Step 1: for each encounter, compute per-player rotation checks (requires a baseline
        // for that player's class+spec). Also determine participation per (player, encounter).
        $participationSeconds = [];
        foreach ($encounters as &$enc) {
            $encDuration = (int) ($enc['duration_seconds'] ?? 0);
            if ($encDuration < 30) continue; // skip incomplete / empty encounters

            $perEncRotation = [];
            foreach ($enc['player_stats']['casts_summary'] ?? [] as $player => $abilities) {
                if (!is_array($abilities) || empty($abilities)) continue;

                // Participated if they cast anything this encounter
                $participationSeconds[$player] = ($participationSeconds[$player] ?? 0) + $encDuration;

                $baseline = $this->loader->load(
                    $perPlayerData[$player]['class'] ?? null,
                    $perPlayerData[$player]['spec'] ?? null
                );
                $checks = $baseline['rotation_checks'] ?? [];
                if (empty($checks)) continue;

                $rows = [];
                foreach ($checks as $check) {
                    $row = $this->evaluateCheck($check, $abilities, $encDuration);
                    if ($row !== null) $rows[] = $row;
                }
                if (!empty($rows)) {
                    $perEncRotation[$player] = $rows;
                }
            }

            if (!empty($perEncRotation)) {
                $enc['player_rotation'] = $perEncRotation;
            }
        }
        unset($enc);

        // Step 2: aggregate raid-wide using PARTICIPATED duration per player.
        $castsByPlayer = $this->aggregateCasts($encounters);

        foreach ($perPlayerData as $name => &$entry) {
            $baseline = $this->loader->load($entry['class'] ?? null, $entry['spec'] ?? null);
            if (!$baseline) continue;

            $checks = $baseline['rotation_checks'] ?? [];
            if (!is_array($checks) || empty($checks)) continue;

            $playerCasts = $castsByPlayer[$name] ?? [];
            if (empty($playerCasts)) continue;

            $playerDuration = $participationSeconds[$name] ?? 0;
            if ($playerDuration < 60) continue; // too little data for this player

            $encountersParticipated = count(array_filter(
                $encounters,
                fn($e) => isset($e['player_stats']['casts_summary'][$name])
            ));
            $totalEncounters = count($encounters);

            $analysis = [];
            $issues = [];
            foreach ($checks as $check) {
                $row = $this->evaluateCheck($check, $playerCasts, $playerDuration);
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
                    'source'                   => $baseline['source'] ?? 'WoWAnalyzer-midnight',
                    'participated_seconds'     => $playerDuration,
                    'total_raid_seconds'       => $totalDurationSeconds,
                    'encounters_participated'  => $encountersParticipated,
                    'total_encounters'         => $totalEncounters,
                    'checks'                   => $analysis,
                ];
            }
            if (!empty($issues)) {
                $entry['rotation_issues'] = $issues;
            }
        }
    }

    /**
     * Evaluate a single check against a (player_casts, duration_seconds) pair.
     * Returns null if the ability isn't cast at all (likely not talented).
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
        if ($maxCasts < 1) return null;

        $actual = (int) ($playerCasts[$ability] ?? 0);
        if ($actual === 0) return null; // not talented / not in rotation this encounter

        $efficiency = $actual / $maxCasts;
        $minorThreshold = $recommended - self::MINOR_DOWNSTEP;
        $majorThreshold = $recommended - self::MAJOR_DOWNSTEP;

        if ($efficiency >= $minorThreshold) {
            $status = 'passing';
        } elseif ($efficiency < $majorThreshold) {
            $status = 'major';
        } else {
            $status = 'minor';
            if ($severityCfg === 'major' && $efficiency < $recommended - 0.10) {
                // slightly stricter for abilities the baseline marks as major-priority
                $status = 'minor';
            }
        }

        $pct = (int) round($efficiency * 100);
        $recommendedPct = (int) round($recommended * 100);
        $maxInt = (int) floor($maxCasts);

        $summary = "{$ability} cast efficiency {$pct}% (target {$recommendedPct}%+) — {$actual} casts of a possible {$maxInt}.";

        return [
            'ability'          => $ability,
            'ability_id'       => $check['ability_id'] ?? null,
            'cooldown_seconds' => (float) $cooldown,
            'actual_casts'     => $actual,
            'max_possible'     => $maxInt,
            'efficiency_pct'   => $pct,
            'recommended_pct'  => $recommendedPct,
            'status'           => $status,
            'summary'          => $summary,
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
