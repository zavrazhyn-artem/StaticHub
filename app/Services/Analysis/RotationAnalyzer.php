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

            $playerCasts = $castsByPlayer[$name] ?? [];
            if (empty($playerCasts)) continue;

            $playerDuration = $participationSeconds[$name] ?? 0;
            if ($playerDuration < 60) continue; // too little data for this player

            $encountersParticipated = count(array_filter(
                $encounters,
                fn($e) => isset($e['player_stats']['casts_summary'][$name])
            ));
            $totalEncounters = count($encounters);

            // Delegate evaluation (theoretical + empirical-only) to the shared
            // entry point. This keeps the per-pull and raid-wide paths
            // consistent and surfaces empirical-only abilities (e.g. Devourer's
            // Devour/Consume) in the raid-wide aggregate too.
            $analysis = $this->evaluatePlayerRotation(
                $entry['class'] ?? null,
                $entry['spec'] ?? null,
                $playerCasts,
                $playerDuration
            );
            if (empty($analysis)) continue;

            $issues = [];
            foreach ($analysis as $row) {
                if (($row['status'] ?? 'passing') === 'passing') continue;
                $issues[] = [
                    'ability'  => $row['ability'],
                    'issue'    => $row['summary'],
                    'severity' => $row['status'],
                ];
            }

            $entry['rotation_analysis'] = [
                'source'                   => $baseline['source'] ?? 'WoWAnalyzer-midnight',
                'participated_seconds'     => $playerDuration,
                'total_raid_seconds'       => $totalDurationSeconds,
                'encounters_participated'  => $encountersParticipated,
                'total_encounters'         => $totalEncounters,
                'checks'                   => $analysis,
            ];
            if (!empty($issues)) {
                $entry['rotation_issues'] = $issues;
            }
        }
    }

    /**
     * Evaluate a player's full rotation for a single fight (or any duration).
     * Public entry point used by FightBreakdownBuilder for per-pull analysis;
     * returns the same row shape as the per-encounter `player_rotation` data
     * produced by apply(). Returns [] when no baseline exists for the spec or
     * the player did nothing in the window.
     */
    public function evaluatePlayerRotation(?string $class, ?string $spec, array $playerCasts, int $durationSec): array
    {
        if ($durationSec < 30 || empty($playerCasts)) return [];
        $baseline = $this->loader->load($class, $spec);
        $checks = $baseline['rotation_checks'] ?? [];

        // Empirical p95 thresholds keyed by ability_id (top WCL parsers).
        // Falls back to theoretical when sample is too thin.
        $empirical = [];
        foreach (($baseline['empirical']['abilities'] ?? []) as $abilityId => $entry) {
            if (!is_array($entry)) continue;
            $sample = (int) ($entry['sample'] ?? 0);
            if ($sample < 5) continue;
            $empirical[(int) $abilityId] = $entry;
        }

        $rows = [];
        $checkedAbilityIds = [];
        foreach ($checks as $check) {
            $row = $this->evaluateCheck($check, $playerCasts, $durationSec, $empirical);
            if ($row !== null) {
                $rows[] = $row;
                $checkedAbilityIds[(int) ($check['ability_id'] ?? 0)] = true;
            }
        }

        // Surface empirical-only abilities — those that top WCL parsers spam
        // heavily but icy-veins rotation_checks don't list. Without this, brand
        // new specs (e.g. Devourer, where rotation_checks and empirical share
        // ZERO ability IDs) get no empirical signal at all.
        foreach ($empirical as $abilityId => $entry) {
            if (isset($checkedAbilityIds[$abilityId])) continue;
            $row = $this->evaluateEmpiricalCpm((int) $abilityId, $entry, $playerCasts, $durationSec);
            if ($row !== null) $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Evaluate a top-parser-empirical ability that has no rotation_check entry.
     * Compares the player's CPM (casts per minute) against the empirical p95
     * and median from real WCL top parses. Returns null if the player never
     * cast the ability (likely not talented or different build).
     */
    private function evaluateEmpiricalCpm(int $abilityId, array $entry, array $playerCasts, int $durationSec): ?array
    {
        $name        = $entry['name'] ?? null;
        $cpmP95      = (float) ($entry['cpm_p95'] ?? 0);
        $cpmMedian   = (float) ($entry['cpm_median'] ?? 0);
        if (!$name || $cpmP95 <= 0 || $durationSec < 30) return null;

        $actual = (int) ($playerCasts[$name] ?? 0);
        if ($actual === 0) return null;

        $actualCpm = ($actual * 60.0) / $durationSec;

        // Status thresholds — empirical-relative, not absolute.
        // p95 is the 95th-percentile target (top parsers); median is the typical
        // top parser. Below median × 0.5 = noticeably under-casting, below
        // median = mild under-casting, at-or-above median = passing.
        if ($actualCpm >= $cpmMedian) {
            $status = 'passing';
        } elseif ($actualCpm < ($cpmMedian * 0.5)) {
            $status = 'major';
        } else {
            $status = 'minor';
        }

        $actualCpmRounded = round($actualCpm, 1);
        $p95Rounded       = round($cpmP95, 1);
        $medianRounded    = round($cpmMedian, 1);
        $summary = "{$name}: {$actualCpmRounded} CPM vs top-parser median {$medianRounded} / p95 {$p95Rounded}.";

        return [
            'ability'              => $name,
            'ability_id'           => $abilityId,
            'check_type'           => 'cpm_comparison',
            'actual_casts'         => $actual,
            'actual_cpm'           => $actualCpmRounded,
            'empirical_cpm_p95'    => $p95Rounded,
            'empirical_cpm_median' => $medianRounded,
            'status'               => $status,
            'threshold_source'     => 'wcl-empirical-only',
            'summary'              => $summary,
        ];
    }

    /**
     * Evaluate a single check against a (player_casts, duration_seconds) pair.
     * Returns null if the ability isn't cast at all (likely not talented).
     */
    private function evaluateCheck(array $check, array $playerCasts, int $durationSec, array $empirical = []): ?array
    {
        if (($check['check'] ?? null) !== 'cast_efficiency') return null;

        $ability     = $check['ability'] ?? null;
        $abilityId   = (int) ($check['ability_id'] ?? 0);
        $cooldown    = $check['cooldown_seconds'] ?? null;
        $recommended = (float) ($check['recommended_efficiency'] ?? 0.80);
        $severityCfg = $check['severity'] ?? 'minor';

        if (!$ability || !is_numeric($cooldown) || $cooldown <= 0) return null;

        $maxCasts = $durationSec / (float) $cooldown;
        if ($maxCasts < 1) return null;

        $actual = (int) ($playerCasts[$ability] ?? 0);
        if ($actual === 0) return null; // not talented / not in rotation this encounter

        $efficiency = $actual / $maxCasts;

        // Empirical override: if top WCL parsers achieve a different threshold
        // than icy-veins theory says, prefer the empirical p95 floor.
        // Formula: empirical_target = cpm_p95 * cooldown_seconds / 60 (capped 0.99).
        // E.g. cpm_p95 of 0.95 on a 60s CD → 95% expected efficiency.
        $empiricalSource = null;
        $empiricalCpmP95 = null;
        $empiricalCpmMedian = null;
        if ($abilityId > 0 && isset($empirical[$abilityId])) {
            $empiricalCpmP95 = (float) ($empirical[$abilityId]['cpm_p95'] ?? 0);
            $empiricalCpmMedian = (float) ($empirical[$abilityId]['cpm_median'] ?? 0);
            if ($empiricalCpmP95 > 0) {
                $empiricalTarget = min(0.99, ($empiricalCpmP95 * (float) $cooldown) / 60);
                if ($empiricalTarget > 0.4) {
                    $recommended = $empiricalTarget;
                    $empiricalSource = 'wcl-p95';
                }
            }
        }

        $minorThreshold = $recommended - self::MINOR_DOWNSTEP;
        $majorThreshold = $recommended - self::MAJOR_DOWNSTEP;

        if ($efficiency >= $minorThreshold) {
            $status = 'passing';
        } elseif ($efficiency < $majorThreshold) {
            $status = 'major';
        } else {
            $status = 'minor';
        }

        // Cap status at the baseline severity. A utility ability flagged as
        // `severity: minor` in the baseline (e.g. Vengeful Retreat) should
        // never escalate to 'major' just because it's far below recommended —
        // baseline already told us it's not a top-priority button. Without
        // this cap, the AI sees `severity: major` and escalates the prose
        // to lecture the player about a non-rotational ability.
        if ($status === 'major' && $severityCfg !== 'major') {
            $status = $severityCfg === 'critical' ? 'major' : 'minor';
        }

        $pct = (int) round($efficiency * 100);
        $recommendedPct = (int) round($recommended * 100);
        $maxInt = (int) floor($maxCasts);

        $summary = "{$ability} cast efficiency {$pct}% (target {$recommendedPct}%+) — {$actual} casts of a possible {$maxInt}.";

        $row = [
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

        // Surface the source of the threshold + raw empirical numbers so the
        // AI can cite "top parsers run X CPM" instead of vague qualitative
        // language. Only present when empirical data was used.
        if ($empiricalSource !== null) {
            $row['threshold_source']  = $empiricalSource;
            $row['empirical_cpm_p95']    = $empiricalCpmP95;
            $row['empirical_cpm_median'] = $empiricalCpmMedian;
        }

        return $row;
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
