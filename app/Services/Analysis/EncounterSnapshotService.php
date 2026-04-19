<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\EncounterSnapshot;
use App\Models\TacticalReport;
use Carbon\Carbon;

/**
 * Persists per-encounter snapshots after analysis and provides retrieval for
 * cross-raid trend analysis (premium-tier feature).
 */
class EncounterSnapshotService
{
    /**
     * Save one snapshot per encounter from the preprocessed analyzer output.
     * Idempotent: deletes existing snapshots for this report before re-inserting.
     */
    public function saveFromPreprocessed(TacticalReport $report, array $preprocessed): void
    {
        $encounters = $preprocessed['encounters'] ?? [];
        $perPlayerData = $preprocessed['per_player_data'] ?? [];
        if (empty($encounters)) return;

        $raidDate = $report->created_at ?? Carbon::now();

        // Wipe stale snapshots for this report
        EncounterSnapshot::query()->where('tactical_report_id', $report->id)->delete();

        foreach ($encounters as $enc) {
            $bossName = $enc['boss'] ?? null;
            if (!$bossName) continue;

            $playerMetrics = $this->extractPerPlayerMetrics($enc, $perPlayerData);
            $encounterSummary = $this->extractEncounterSummary($enc);

            $totalDeaths = 0;
            foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                foreach ($mf['players'] ?? [] as $p) {
                    // Just to know if this encounter had death-causing fails
                }
            }
            // Total deaths: sum from phase_deaths or per_player_data death_details on this boss
            foreach ($enc['phase_deaths'] ?? [] as $pd) {
                $totalDeaths += (int) ($pd['deaths'] ?? 0);
            }

            EncounterSnapshot::create([
                'static_id'          => $report->static_id,
                'tactical_report_id' => $report->id,
                'boss_name'          => $bossName,
                'wcl_encounter_id'   => $enc['wcl_encounter_id'] ?? null,
                'difficulty'         => $enc['difficulty'] ?? null,
                'raid_date'          => $raidDate,
                'duration_seconds'   => (int) ($enc['duration_seconds'] ?? 0),
                'killed'             => ($enc['kills'] ?? 0) > 0,
                'best_wipe_pct'      => isset($enc['best_wipe_pct']) ? (int) round($enc['best_wipe_pct']) : null,
                'attempts'           => (int) ($enc['tries'] ?? 0),
                'total_deaths'       => $totalDeaths,
                'player_metrics'     => $playerMetrics,
                'encounter_summary'  => $encounterSummary,
            ]);
        }
    }

    /**
     * For each player who participated in this encounter, capture a compact snapshot
     * of their key metrics relevant to trend analysis.
     *
     * @return array<int, array>
     */
    private function extractPerPlayerMetrics(array $encounter, array $perPlayerData): array
    {
        $rows = [];
        $castsSummary = $encounter['player_stats']['casts_summary'] ?? [];
        $playerRotation = $encounter['player_rotation'] ?? [];
        $damageBreakdown = $encounter['player_stats']['damage_done_breakdown'] ?? [];

        foreach ($castsSummary as $playerName => $abilities) {
            if (!is_array($abilities) || empty($abilities)) continue;

            $perPlayer = $perPlayerData[$playerName] ?? [];

            // This-encounter rotation efficiency (avg of checks where status != null)
            $encChecks = $playerRotation[$playerName] ?? [];
            $avgEff = null;
            $passingCount = 0;
            if (!empty($encChecks)) {
                $effs = array_column($encChecks, 'efficiency_pct');
                $avgEff = (int) round(array_sum($effs) / count($effs));
                $passingCount = count(array_filter($encChecks, fn($c) => ($c['status'] ?? '') === 'passing'));
            }

            $topAbility = null;
            if (!empty($damageBreakdown[$playerName])) {
                $topAbility = $damageBreakdown[$playerName][0]['ability'] ?? null;
            }

            $rows[] = [
                'name'                  => $playerName,
                'class'                 => $perPlayer['class'] ?? null,
                'spec'                  => $perPlayer['spec'] ?? null,
                'role'                  => $perPlayer['role'] ?? null,
                'parse_pct'             => $perPlayer['parse_today_pct'] ?? $perPlayer['parse_pct'] ?? null,
                'rotation_eff_avg'      => $avgEff,
                'rotation_passing'      => $passingCount,
                'rotation_checked'      => count($encChecks),
                'add_damage_pct'        => $perPlayer['add_damage_pct'] ?? null,
                'top_ability'           => $topAbility,
            ];
        }

        return $rows;
    }

    /**
     * Compact encounter-wide summary — top mechanic failures, phase deaths breakdown.
     */
    private function extractEncounterSummary(array $encounter): array
    {
        $topFailures = [];
        foreach (array_slice($encounter['mechanic_failures'] ?? [], 0, 3) as $mf) {
            $totalCount = 0;
            foreach ($mf['players'] ?? [] as $p) {
                $totalCount += (int) ($p['failure_count'] ?? $p['count'] ?? 0);
            }
            $topFailures[] = [
                'mechanic' => $mf['mechanic'] ?? $mf['name'] ?? '?',
                'severity' => $mf['severity'] ?? 'minor',
                'count'    => $totalCount,
            ];
        }

        return [
            'top_mechanic_failures' => $topFailures,
            'phase_deaths'          => $encounter['phase_deaths'] ?? [],
        ];
    }

    /**
     * Get historical snapshots for a boss from this static, newest first, EXCLUDING the
     * snapshot tied to the current report being generated.
     */
    public function recentForBoss(int $staticId, string $bossName, int $limit = 5, ?int $excludeReportId = null)
    {
        return EncounterSnapshot::query()->recentForBoss($staticId, $bossName, $limit, $excludeReportId);
    }

    /**
     * Get all recent snapshots across bosses for the static — used to compute player-wide trends.
     */
    public function recentForStatic(int $staticId, int $limit = 100, ?int $excludeReportId = null)
    {
        return EncounterSnapshot::query()->recentForStatic($staticId, $limit, $excludeReportId);
    }
}
