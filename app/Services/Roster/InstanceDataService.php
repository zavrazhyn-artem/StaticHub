<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Helpers\WeeklyResetHelper;

/**
 * Handles raid instance data, mythic+ rating, and dungeon run tracking.
 */
final class InstanceDataService
{
    /** Instance name => ordered boss name list from config. */
    private readonly array $currentRaidInstances;

    /** Expansion dungeon stat IDs (heroic/mythic). */
    private readonly array $expansionDungeons;

    /** Boss name => achievement stat IDs per difficulty. */
    private readonly array $raidBossAchievementIds;

    private string $region = 'eu';

    public function __construct()
    {
        $this->currentRaidInstances  = config('wow_season.current_raid_instances', []);
        $this->expansionDungeons     = config('wow_season.expansion_dungeons', []);
        $this->raidBossAchievementIds = config('wow_season.raid_boss_achievement_ids', []);
    }

    // =========================================================================
    // REGION
    // =========================================================================

    public function setRegion(string $region): void
    {
        $this->region = WeeklyResetHelper::normalizeRegion($region);
    }

    // =========================================================================
    // MYTHIC+
    // =========================================================================

    public function resolveMythicRating(array $mplus): ?float
    {
        $rating = $mplus['current_mythic_rating']['rating']
            ?? $mplus['mythic_rating']['rating']
            ?? null;
        return $rating !== null ? (float) $rating : null;
    }

    public function resolveWeeklyRunsCount(array $mplus, array $rio = [], int $minLevel = 10): int
    {
        $runs = $rio['mythic_plus_weekly_highest_level_runs']
            ?? $mplus['weekly_best_runs']
            ?? $mplus['current_period_best_runs']
            ?? [];

        if (!is_array($runs)) {
            return 0;
        }

        $count = 0;
        foreach ($runs as $run) {
            $level = (int) ($run['mythic_level'] ?? 0);
            if ($level >= $minLevel) {
                $count++;
            }
        }
        return $count;
    }

    public function resolveSeasonHeroicDungeons(array $achStatsIndex): int
    {
        $total = 0;
        foreach ($this->expansionDungeons as $dungeon) {
            $stat = $achStatsIndex[$dungeon['heroic_id']] ?? null;
            if ($stat) {
                $total += (int) ($stat['quantity'] ?? 0);
            }
        }
        return $total;
    }

    // =========================================================================
    // RAIDS — WEEKLY KILLS (via achievement statistics timestamps)
    // =========================================================================

    /**
     * Determine which bosses were killed THIS week, per difficulty.
     * Uses achievement_statistics last_updated_timestamp > weekly reset timestamp.
     *
     * Returns: { instance_name: [ { name, LFR: bool, N: bool, H: bool, M: bool } ] }
     */
    public function resolveWeeklyRaidKills(array $achStatsIndex): ?array
    {
        if ($achStatsIndex === [] || $this->raidBossAchievementIds === []) {
            return null;
        }

        $resetTs     = WeeklyResetHelper::resetTimestamp($this->region);
        $diffMap     = ['raid_finder' => 'LFR', 'normal' => 'N', 'heroic' => 'H', 'mythic' => 'M'];
        $result      = [];

        foreach ($this->currentRaidInstances as $instanceName => $configBosses) {
            $bosses = [];

            foreach ($configBosses as $bossName) {
                $bossStatIds = $this->raidBossAchievementIds[$bossName] ?? [];
                $kills       = ['name' => $bossName, 'LFR' => false, 'N' => false, 'H' => false, 'M' => false];

                foreach ($diffMap as $diffKey => $label) {
                    $statIds = $bossStatIds[$diffKey] ?? [];
                    foreach ($statIds as $statId) {
                        $stat = $achStatsIndex[$statId] ?? null;
                        if ($stat && (($stat['last_updated_timestamp'] ?? 0) / 1000) > $resetTs) {
                            $kills[$label] = true;
                            break;
                        }
                    }
                }

                $bosses[] = $kills;
            }

            $result[$instanceName] = $bosses;
        }

        return $result;
    }

    // =========================================================================
    // RAIDS — CUMULATIVE (kept for reference, no longer used in compiled output)
    // =========================================================================

    public function resolveRaids(array $raid): ?array
    {
        if ($raid === []) {
            return null;
        }

        $instanceModes = $this->indexRaidInstanceModes($raid);
        $result        = [];

        foreach ($this->currentRaidInstances as $instanceName => $configBosses) {
            $modes                  = $instanceModes[$instanceName] ?? [];
            $result[$instanceName]  = $this->compileInstanceBosses($configBosses, $modes);
        }

        return $result;
    }

    public function compileInstanceBosses(array $configBosses, array $modes): array
    {
        static $diffLabel = ['MYTHIC' => 'M', 'HEROIC' => 'H', 'NORMAL' => 'N', 'LFR' => 'LFR'];

        $kills = [];
        foreach ($modes as $mode) {
            $diffType = strtoupper((string) ($mode['difficulty']['type'] ?? ''));
            $label    = $diffLabel[$diffType] ?? null;
            if ($label === null) {
                continue;
            }
            foreach ($mode['progress']['encounters'] ?? [] as $enc) {
                $name   = (string) ($enc['encounter']['name'] ?? '');
                $killed = ((int) ($enc['completed_count'] ?? 0)) > 0;
                if ($name !== '' && $killed) {
                    $kills[$name][$label] = true;
                }
            }
        }

        $bosses = [];
        foreach ($configBosses as $bossName) {
            $k        = $kills[$bossName] ?? [];
            $bosses[] = [
                'name' => $bossName,
                'LFR'  => $k['LFR'] ?? false,
                'N'    => $k['N']   ?? false,
                'H'    => $k['H']   ?? false,
                'M'    => $k['M']   ?? false,
            ];
        }

        return $bosses;
    }

    public function indexRaidInstanceModes(array $raid): array
    {
        $map = [];
        foreach ($raid['expansions'] ?? [] as $expansion) {
            foreach ($expansion['instances'] ?? [] as $instanceData) {
                $name = (string) ($instanceData['instance']['name'] ?? '');
                if ($name !== '') {
                    $map[$name] = $instanceData['modes'] ?? [];
                }
            }
        }
        return $map;
    }
}
