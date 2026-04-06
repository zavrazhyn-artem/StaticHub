<?php

declare(strict_types=1);

namespace App\Services\Roster;

/**
 * Handles raid instance data, mythic+ rating, and dungeon run tracking.
 */
final class InstanceDataService
{
    /** Instance name => ordered boss name list from config. */
    private readonly array $currentRaidInstances;

    /** Expansion dungeon stat IDs (heroic/mythic). */
    private readonly array $expansionDungeons;

    public function __construct()
    {
        $this->currentRaidInstances = config('wow_season.current_raid_instances', []);
        $this->expansionDungeons    = config('wow_season.expansion_dungeons', []);
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

    public function resolveWeeklyRunsCount(array $mplus, array $rio = []): int
    {
        if (isset($rio['mythic_plus_weekly_highest_level_runs']) && is_array($rio['mythic_plus_weekly_highest_level_runs'])) {
            return count($rio['mythic_plus_weekly_highest_level_runs']);
        }
        if (isset($mplus['weekly_best_runs']) && is_array($mplus['weekly_best_runs'])) {
            return count($mplus['weekly_best_runs']);
        }
        if (isset($mplus['current_period_best_runs']) && is_array($mplus['current_period_best_runs'])) {
            return count($mplus['current_period_best_runs']);
        }
        return 0;
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
    // RAIDS
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
