<?php

declare(strict_types=1);

namespace App\Services\Roster;

/**
 * Handles Great Vault data: weekly M+ runs, world (delve) runs, and raid vault slots.
 */
final class VaultDataService
{
    /** Great Vault ilvl reward tables: raid/dungeon/delve => ilvl+track. */
    private readonly array $vaultIlvlTable;

    /** Boss name => achievement stat IDs per difficulty. */
    private readonly array $raidBossAchievementIds;

    /** Bosses that count for vault. */
    private readonly array $vaultRaidBosses;

    /** Keystone dungeon config. */
    private readonly array $keystoneDungeons;

    /** Expansion dungeon stat IDs (heroic/mythic). */
    private readonly array $expansionDungeons;

    /** Achievement stat IDs for delve tiers 1-11. */
    private readonly array $delveTierStatIds;
    private readonly int   $delveTotalStatId;

    public function __construct()
    {
        $this->vaultIlvlTable         = config('wow_season.great_vault', []);
        $this->raidBossAchievementIds = config('wow_season.raid_boss_achievement_ids', []);
        $this->vaultRaidBosses        = config('wow_season.vault_raid_bosses', []);
        $this->keystoneDungeons       = config('wow_season.keystone_dungeons', []);
        $this->expansionDungeons      = config('wow_season.expansion_dungeons', []);
        $this->delveTierStatIds       = config('wow_season.delve_tier_stat_ids', []);
        $this->delveTotalStatId       = (int) config('wow_season.delve_total_stat_id', 0);
    }

    // =========================================================================
    // VAULT — M+ RUNS
    // =========================================================================

    /**
     * Vault M+ runs: includes keystone runs + regular mythic (level 1) + heroic (level 0).
     * Source: wowaudit external_data.rb add_leaderboard_data.
     */
    public function resolveVaultWeeklyRuns(array $rio, int $weekRegularMythic): ?array
    {
        $runs = $rio['mythic_plus_weekly_highest_level_runs'] ?? null;
        $dungeonTable = $this->vaultIlvlTable['dungeon'] ?? [];

        $levels = [];

        // Add keystone runs from raider.io
        if (is_array($runs)) {
            foreach ($runs as $run) {
                $level  = (int) ($run['mythic_level'] ?? 0);
                $capped = min($level, 10);
                $entry  = $dungeonTable[$capped] ?? ['ilvl' => null, 'track' => null];
                $levels[] = [
                    'mythic_level' => $level,
                    'ilvl'         => $entry['ilvl'] ?? null,
                    'track'        => $entry['track'] ?? null,
                ];
            }
        }

        // Add regular mythic dungeons (counted as level 1) -- wowaudit logic
        for ($i = 0; $i < $weekRegularMythic; $i++) {
            $entry = $dungeonTable[1] ?? ['ilvl' => null, 'track' => null];
            $levels[] = [
                'mythic_level' => 1,
                'ilvl'         => $entry['ilvl'] ?? null,
                'track'        => $entry['track'] ?? null,
            ];
        }

        if ($levels === []) {
            return null;
        }

        usort($levels, fn (array $a, array $b) => $b['mythic_level'] - $a['mythic_level']);
        return $levels;
    }

    /**
     * Count regular mythic dungeon completions this week from achievement stats.
     * Uses last_updated_timestamp > weekly reset.
     */
    public function resolveWeekRegularMythicDungeons(array $achStatsIndex): int
    {
        $resetTs  = $this->weeklyResetTimestamp();
        $count    = 0;
        $allMythicIds = [];

        foreach ($this->expansionDungeons as $dungeon) {
            $allMythicIds[] = $dungeon['mythic_id'];
        }
        foreach ($this->keystoneDungeons as $dungeon) {
            if ($dungeon['mythic_id'] > 0) {
                $allMythicIds[] = $dungeon['mythic_id'];
            }
        }

        $allMythicIds = array_unique($allMythicIds);

        foreach ($allMythicIds as $statId) {
            $stat = $achStatsIndex[$statId] ?? null;
            if ($stat && (($stat['last_updated_timestamp'] ?? 0) / 1000) > $resetTs) {
                $count++;
            }
        }

        // Wowaudit: "We can't track Pit of Saron, if the user completed all other dungeons we assume they did that one too."
        if ($count === 7) {
            $count = 8;
        }

        return $count;
    }

    // =========================================================================
    // VAULT — WORLD (DELVES)
    // =========================================================================

    /**
     * World vault runs: delve delta + weekly quest bonuses + Prey runs.
     * Source: wowaudit delve_data.rb.
     */
    public function resolveVaultWorldRuns(
        array $achStats,
        array $snapshot,
        array $weeklyQuests,
        array $preyWeekly,
    ): ?array {
        if ($achStats === [] || $this->delveTierStatIds === []) {
            return null;
        }

        $delveCategory = null;
        foreach ($achStats['categories'] ?? [] as $category) {
            if (($category['name'] ?? '') === 'Delves') {
                $delveCategory = $category;
                break;
            }
        }
        if ($delveCategory === null) {
            return null;
        }

        $statsById = [];
        foreach ($delveCategory['statistics'] ?? [] as $stat) {
            $statsById[(int) $stat['id']] = (int) ($stat['quantity'] ?? 0);
        }
        if ($statsById === []) {
            return null;
        }

        $periodKey      = $this->currentWowPeriodKey();
        $periodSnapshot = $snapshot[$periodKey] ?? [];

        // Calculate delve tier runs this week (delta from snapshot)
        $delvesThisWeek = [];

        foreach ($this->delveTierStatIds as $tier => $statId) {
            $currentCount  = $statsById[$statId] ?? 0;
            $snapshotCount = (int) ($periodSnapshot["tier_{$tier}"] ?? 0);
            $weeklyCount   = max(0, $currentCount - $snapshotCount);

            for ($i = 0; $i < $weeklyCount; $i++) {
                $delvesThisWeek[] = (int) $tier;
            }
        }

        // Add weekly quest bonuses (each counts as tier 1 delve)
        foreach (['haranir', 'saltheril', 'abundance', 'stormarion', 'unity'] as $quest) {
            if ($weeklyQuests[$quest] ?? false) {
                $delvesThisWeek[] = 1;
            }
        }

        // Add Prey runs: normal=tier1, hard=tier5, nightmare=tier8
        for ($i = 0; $i < ($preyWeekly['normal'] ?? 0); $i++) {
            $delvesThisWeek[] = 1;
        }
        for ($i = 0; $i < ($preyWeekly['hard'] ?? 0); $i++) {
            $delvesThisWeek[] = 5;
        }
        for ($i = 0; $i < ($preyWeekly['nightmare'] ?? 0); $i++) {
            $delvesThisWeek[] = 8;
        }

        if ($delvesThisWeek === []) {
            return null;
        }

        rsort($delvesThisWeek);

        $delveTable = $this->vaultIlvlTable['delve'] ?? [];
        $result = [];
        foreach ($delvesThisWeek as $tier) {
            $capped = min($tier, 11);
            $entry  = $delveTable[$capped] ?? ['ilvl' => null, 'track' => null];
            $result[] = [
                'tier'  => $tier,
                'ilvl'  => $entry['ilvl'] ?? null,
                'track' => $entry['track'] ?? null,
            ];
        }

        return $result;
    }

    // =========================================================================
    // VAULT — RAID
    // =========================================================================

    /**
     * Compute vault raid slots from weekly boss kills via achievement statistics.
     * Source: wowaudit instance_data.rb add_great_vault_data.
     *
     * Returns 3 vault slots: index 0=slot1 (2 kills), 1=slot2 (4 kills), 2=slot3 (6 kills).
     * Each slot has ilvl/track/difficulty from the N-th best killed boss.
     */
    public function resolveVaultRaidSlots(array $achStatsIndex): ?array
    {
        if ($achStatsIndex === [] || $this->raidBossAchievementIds === []) {
            return null;
        }

        $resetTs      = $this->weeklyResetTimestamp();
        $diffPriority = ['mythic', 'heroic', 'normal', 'raid_finder'];
        $raidTable    = $this->vaultIlvlTable['raid'] ?? [];

        // For each vault-eligible boss, find the best difficulty killed this week
        $weeklyKillDifficulties = [];

        foreach ($this->vaultRaidBosses as $bossName) {
            $bossStatIds = $this->raidBossAchievementIds[$bossName] ?? [];
            $bestDiff    = null;

            foreach ($diffPriority as $diff) {
                $statIds = $bossStatIds[$diff] ?? [];
                foreach ($statIds as $statId) {
                    $stat = $achStatsIndex[$statId] ?? null;
                    if ($stat && (($stat['last_updated_timestamp'] ?? 0) / 1000) > $resetTs) {
                        $bestDiff = $diff;
                        break 2;
                    }
                }
            }

            if ($bestDiff !== null) {
                $weeklyKillDifficulties[] = $bestDiff;
            }
        }

        if ($weeklyKillDifficulties === []) {
            return null;
        }

        // Sort by difficulty priority (mythic first)
        usort($weeklyKillDifficulties, function (string $a, string $b) use ($diffPriority): int {
            return array_search($a, $diffPriority) - array_search($b, $diffPriority);
        });

        // Vault slots need 2/4/6 boss kills
        $neededKills = [2, 4, 6];
        $totalKills  = count($weeklyKillDifficulties);
        $slots       = [];

        foreach ($neededKills as $needed) {
            if ($totalKills >= $needed) {
                $diff  = $weeklyKillDifficulties[$needed - 1];
                $entry = $raidTable[$diff] ?? ['ilvl' => null, 'track' => null];
                $slots[] = [
                    'ilvl'       => $entry['ilvl'] ?? null,
                    'track'      => $entry['track'] ?? null,
                    'difficulty' => $diff,
                ];
            } else {
                $slots[] = null;
            }
        }

        return $slots;
    }

    // =========================================================================
    // TIME HELPERS
    // =========================================================================

    private function currentWowPeriodKey(): string
    {
        $resetTimestamp = $this->weeklyResetTimestamp();
        return gmdate('o-\WW', $resetTimestamp);
    }

    private function weeklyResetTimestamp(): int
    {
        $now = time();
        $resetTimestamp = strtotime('last wednesday 04:00 UTC', $now);
        if ($resetTimestamp > $now) {
            $resetTimestamp = strtotime('-7 days', $resetTimestamp);
        }
        return $resetTimestamp;
    }
}
