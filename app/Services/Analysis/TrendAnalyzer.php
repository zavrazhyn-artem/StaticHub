<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\EncounterSnapshot;
use Illuminate\Support\Collection;

/**
 * Builds cross-raid trend insights from previously stored EncounterSnapshots. Returned
 * structure is appended to the preprocessed payload so the AI can interpret growth /
 * regression / progression patterns.
 *
 * This is a PREMIUM-tier feature — only runs when the static has historical snapshots
 * available.
 */
class TrendAnalyzer
{
    public function __construct(private readonly EncounterSnapshotService $snapshots) {}

    /**
     * @param int   $staticId
     * @param int   $currentReportId  Excluded from trend window
     * @param array $encounters       Current report encounters (to know which bosses to compare)
     * @return array{enabled:bool, history_depth:int, per_boss:array, per_player:array}
     */
    public function buildTrends(int $staticId, int $currentReportId, array $encounters): array
    {
        $bossNames = array_unique(array_filter(array_column($encounters, 'boss')));
        if (empty($bossNames)) {
            return ['enabled' => false, 'history_depth' => 0, 'per_boss' => [], 'per_player' => []];
        }

        // Collect all historical snapshots across all bosses we care about
        $allHistorical = collect();
        foreach ($bossNames as $bossName) {
            $rows = $this->snapshots->recentForBoss($staticId, $bossName, 5, $currentReportId);
            $allHistorical = $allHistorical->concat($rows);
        }

        if ($allHistorical->isEmpty()) {
            return ['enabled' => false, 'history_depth' => 0, 'per_boss' => [], 'per_player' => []];
        }

        $perBoss = $this->buildPerBossTrends($bossNames, $staticId, $currentReportId);
        $perPlayer = $this->buildPerPlayerTrends($allHistorical);

        $depth = $allHistorical
            ->groupBy(fn($s) => $s->tactical_report_id
                ? "r:{$s->tactical_report_id}"
                : "d:{$s->raid_date->toDateString()}"
            )
            ->count();

        return [
            'enabled'       => true,
            'history_depth' => $depth,
            'per_boss'      => $perBoss,
            'per_player'    => $perPlayer,
        ];
    }

    /**
     * For each boss, build a progression history: attempts, best wipe %, killed.
     * Newest first.
     *
     * @return array<string, array>
     */
    private function buildPerBossTrends(array $bossNames, int $staticId, int $excludeReportId): array
    {
        $out = [];
        foreach ($bossNames as $bossName) {
            $history = $this->snapshots->recentForBoss($staticId, $bossName, 5, $excludeReportId);
            if ($history->isEmpty()) continue;

            $attemptsTrend  = $history->pluck('attempts')->all();
            $bestWipeTrend  = $history->pluck('best_wipe_pct')->all();
            $deathsTrend    = $history->pluck('total_deaths')->all();
            $killedHistory  = $history->pluck('killed')->map(fn($k) => (bool) $k)->all();

            $verdict = $this->bossVerdict($attemptsTrend, $bestWipeTrend, $killedHistory);

            $out[$bossName] = [
                'raids_with_data'     => $history->count(),
                'attempts_history'    => $attemptsTrend,           // newest first
                'best_wipe_history'   => $bestWipeTrend,
                'total_deaths_history'=> $deathsTrend,
                'killed_history'      => $killedHistory,
                'progression_verdict' => $verdict,
            ];
        }
        return $out;
    }

    /**
     * For each player who appears in historical snapshots, build their trend across raids.
     *
     * @return array<string, array>
     */
    private function buildPerPlayerTrends(Collection $allHistorical): array
    {
        // Group snapshots by raid (use report_id when present, otherwise the raid_date so historical
        // snapshots without an associated report still cluster correctly).
        $byReport = $allHistorical
            ->groupBy(fn($s) => $s->tactical_report_id
                ? "report:{$s->tactical_report_id}"
                : "date:{$s->raid_date->toDateString()}"
            )
            ->sortByDesc(fn($snapshots) => $snapshots->first()->raid_date)
            ->take(5);  // last 5 raids

        // For each player, collect their per-raid average across bosses
        $playerRaidStats = []; // [name => [raidIndex => ['parse'=>X, 'rot'=>Y, 'deaths'=>Z, 'attended_bosses'=>N]]]
        $raidIdx = 0;
        foreach ($byReport as $reportSnaps) {
            foreach ($reportSnaps as $snap) {
                foreach ($snap->player_metrics ?? [] as $pm) {
                    $name = $pm['name'] ?? null;
                    if (!$name) continue;

                    if (!isset($playerRaidStats[$name][$raidIdx])) {
                        $playerRaidStats[$name][$raidIdx] = [
                            'parse_sum'      => 0,
                            'parse_count'    => 0,
                            'rot_sum'        => 0,
                            'rot_count'      => 0,
                            'attended_bosses'=> 0,
                            'date'           => $snap->raid_date->toIso8601String(),
                        ];
                    }
                    $r = &$playerRaidStats[$name][$raidIdx];
                    if ($pm['parse_pct'] !== null) {
                        $r['parse_sum'] += $pm['parse_pct'];
                        $r['parse_count']++;
                    }
                    if (isset($pm['rotation_eff_avg']) && $pm['rotation_eff_avg'] !== null) {
                        $r['rot_sum'] += $pm['rotation_eff_avg'];
                        $r['rot_count']++;
                    }
                    $r['attended_bosses']++;
                    unset($r);
                }
            }
            $raidIdx++;
        }

        // Convert to clean trends (newest first), drop players with <2 raids of data
        $out = [];
        foreach ($playerRaidStats as $name => $perRaid) {
            if (count($perRaid) < 2) continue;

            ksort($perRaid); // raid 0 = newest, ascending = older
            $parseTrend = [];
            $rotTrend = [];
            $attendanceTrend = [];
            foreach ($perRaid as $r) {
                $parseTrend[]    = $r['parse_count'] > 0 ? (int) round($r['parse_sum'] / $r['parse_count']) : null;
                $rotTrend[]      = $r['rot_count']   > 0 ? (int) round($r['rot_sum']   / $r['rot_count'])   : null;
                $attendanceTrend[] = (int) $r['attended_bosses'];
            }

            $out[$name] = [
                'raids_compared'  => count($perRaid),
                'parse_trend'     => $parseTrend,        // newest first
                'rotation_trend'  => $rotTrend,
                'attendance_trend'=> $attendanceTrend,   // # bosses attended per raid
                'verdict'         => $this->playerVerdict($parseTrend, $rotTrend),
            ];
        }
        return $out;
    }

    /**
     * Verdict: 'improving' | 'plateau' | 'regressing' | 'mixed'
     */
    private function playerVerdict(array $parseTrend, array $rotTrend): string
    {
        // Treat "newest first" — to compare growth, reverse so chronological
        $parseChrono = array_reverse(array_filter($parseTrend, fn($v) => $v !== null));
        $rotChrono   = array_reverse(array_filter($rotTrend, fn($v) => $v !== null));

        $parseDelta = count($parseChrono) >= 2 ? end($parseChrono) - reset($parseChrono) : 0;
        $rotDelta   = count($rotChrono) >= 2 ? end($rotChrono) - reset($rotChrono) : 0;

        $improving = ($parseDelta >= 5) || ($rotDelta >= 5);
        $regressing = ($parseDelta <= -5) || ($rotDelta <= -5);

        if ($improving && !$regressing) return 'improving';
        if ($regressing && !$improving) return 'regressing';
        if ($improving && $regressing)  return 'mixed';
        return 'plateau';
    }

    /**
     * Boss verdict: 'kill_progressed' | 'wall_lowering' | 'plateau' | 'regressing' | 'killed'
     */
    private function bossVerdict(array $attemptsTrend, array $bestWipeTrend, array $killedHistory): string
    {
        // newest first
        $latestKill = $killedHistory[0] ?? false;
        if ($latestKill) return 'killed';

        // Compare wipe %: chronologically descending = best (lower) is good
        $wipes = array_filter($bestWipeTrend, fn($v) => $v !== null);
        if (count($wipes) >= 2) {
            $latest = reset($wipes);
            $oldest = end($wipes);
            if ($latest < $oldest - 5) return 'wall_lowering';     // %.s decreasing = closer to kill
            if ($latest > $oldest + 5) return 'regressing';
        }
        return 'plateau';
    }
}
