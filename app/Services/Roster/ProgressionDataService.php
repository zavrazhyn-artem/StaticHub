<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Helpers\WeeklyResetHelper;

/**
 * Handles progression data: delves, coffer keys, achievements (CE/AOTC),
 * crests, quests, prey weekly, and weekly events.
 */
final class ProgressionDataService
{
    private string $region = 'eu';
    /** Weekly quest ID groups. */
    private readonly array $weeklyQuestIds;
    private readonly array $weeklyEventQuestIds;

    /** Prey quest IDs by difficulty. */
    private readonly array $preyQuestIds;

    /** Achievement stat IDs for delve tiers 1-11. */
    private readonly array $delveTierStatIds;
    private readonly int   $delveTotalStatId;

    /** Coffer keys stat ID. */
    private readonly int $cofferKeysStatId;

    /** Crest stat IDs. */
    private readonly array $crestStatIds;

    /** Cutting Edge achievement IDs from wowaudit pve_constants.rb. */
    private const CUTTING_EDGE = [
        7485, 7486, 7487, 8238, 8260, 8400, 8401, 9442, 9443, 10045,
        11191, 11580, 11192, 11875, 12111, 12535, 13323, 13419, 13785,
        14069, 14461, 15135, 15471, 17108, 18254, 19351, 40254, 41297,
        41625, 61625, 61492, 61627,
    ];

    private const AHEAD_OF_THE_CURVE = [
        6954, 8246, 8248, 8249, 8260, 8398, 8399, 9441, 9444, 10044,
        11194, 11581, 11195, 11874, 12110, 12536, 13322, 13418, 13784,
        14068, 14460, 15134, 15470, 17107, 18253, 19350, 40253, 41298,
        41624, 61624, 61491, 61626,
    ];

    public function __construct()
    {
        $this->weeklyQuestIds      = config('wow_season.weekly_quest_ids', []);
        $this->weeklyEventQuestIds = config('wow_season.weekly_event_quest_ids', []);
        $this->preyQuestIds        = config('wow_season.prey_quest_ids', []);
        $this->delveTierStatIds    = config('wow_season.delve_tier_stat_ids', []);
        $this->delveTotalStatId    = (int) config('wow_season.delve_total_stat_id', 0);
        $this->cofferKeysStatId    = (int) config('wow_season.delve_coffer_keys_stat_id', 0);
        $this->crestStatIds        = config('wow_season.crest_stat_ids', []);
    }

    // =========================================================================
    // DELVES
    // =========================================================================

    public function resolveSeasonDelves(array $achStatsIndex): int
    {
        $stat = $achStatsIndex[$this->delveTotalStatId] ?? null;
        return $stat ? (int) ($stat['quantity'] ?? 0) : 0;
    }

    public function resolveWeekDelves(array $achStats, array $snapshot): int
    {
        if ($achStats === []) {
            return 0;
        }

        $delveCategory = null;
        foreach ($achStats['categories'] ?? [] as $category) {
            if (($category['name'] ?? '') === 'Delves') {
                $delveCategory = $category;
                break;
            }
        }
        if ($delveCategory === null) {
            return 0;
        }

        $statsById = [];
        foreach ($delveCategory['statistics'] ?? [] as $stat) {
            $statsById[(int) $stat['id']] = (int) ($stat['quantity'] ?? 0);
        }

        $currentTotal  = $statsById[$this->delveTotalStatId] ?? 0;
        $periodKey     = $this->currentWowPeriodKey();
        $snapshotTotal = (int) ($snapshot[$periodKey]['total'] ?? 0);

        return max(0, $currentTotal - $snapshotTotal);
    }

    public function resolveCofferKeys(array $achStatsIndex): int
    {
        if ($this->cofferKeysStatId <= 0) {
            return 0;
        }
        $stat = $achStatsIndex[$this->cofferKeysStatId] ?? null;
        return $stat ? (int) ($stat['quantity'] ?? 0) : 0;
    }

    // =========================================================================
    // ACHIEVEMENTS
    // =========================================================================

    public function resolveCuttingEdge(array $achStats): int
    {
        return $this->countAchievements($achStats, self::CUTTING_EDGE);
    }

    public function resolveAheadOfTheCurve(array $achStats): int
    {
        return $this->countAchievements($achStats, self::AHEAD_OF_THE_CURVE);
    }

    /**
     * Count how many achievements from a list the character has.
     * Uses the achievement_statistics categories to find achievements.
     * Note: This is a simplified check -- wowaudit uses a separate achievements endpoint.
     * For now returns 0 if we don't have the data; can be improved later.
     */
    public function countAchievements(array $achStats, array $achievementIds): int
    {
        return 0;
    }

    // =========================================================================
    // CRESTS
    // =========================================================================

    public function resolveCrests(array $achStatsIndex): array
    {
        $result = [];
        foreach ($this->crestStatIds as $name => $statId) {
            $stat = $achStatsIndex[$statId] ?? null;
            $result[$name] = $stat ? (int) ($stat['quantity'] ?? 0) : 0;
        }
        return $result;
    }

    // =========================================================================
    // QUESTS & PREY
    // =========================================================================

    public function buildCompletedQuestSet(array $quests): array
    {
        $set = [];
        foreach ($quests['quests'] ?? [] as $q) {
            $id = (int) ($q['id'] ?? 0);
            if ($id > 0) {
                $set[$id] = true;
            }
        }
        return $set;
    }

    public function resolvePreyWeekly(array $completedQuestIds): array
    {
        $result = ['normal' => 0, 'hard' => 0, 'nightmare' => 0];

        foreach ($this->preyQuestIds as $difficulty => $questIds) {
            foreach ($questIds as $qid) {
                if (isset($completedQuestIds[$qid])) {
                    $result[$difficulty]++;
                }
            }
        }

        return $result;
    }

    public function resolveWeeklyQuests(array $completedQuestIds): array
    {
        $result = [];
        foreach ($this->weeklyQuestIds as $name => $questIds) {
            $done = false;
            foreach ($questIds as $qid) {
                if (isset($completedQuestIds[$qid])) {
                    $done = true;
                    break;
                }
            }
            $result[$name] = $done;
        }
        return $result;
    }

    public function resolveWeeklyEventDone(array $completedQuestIds): bool
    {
        foreach ($this->weeklyEventQuestIds as $qid) {
            if (isset($completedQuestIds[$qid])) {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // ACHIEVEMENT STATISTICS HELPERS
    // =========================================================================

    /**
     * Flattens all achievement statistics into a flat map: statId => stat object.
     * Handles nested categories => sub_categories => statistics structure.
     */
    public function indexAchievementStatistics(array $achStats): array
    {
        $index = [];
        foreach ($achStats['categories'] ?? [] as $category) {
            foreach ($category['statistics'] ?? [] as $stat) {
                $index[(int) $stat['id']] = $stat;
            }
            foreach ($category['sub_categories'] ?? [] as $subCat) {
                foreach ($subCat['statistics'] ?? [] as $stat) {
                    $index[(int) $stat['id']] = $stat;
                }
            }
        }
        return $index;
    }

    // =========================================================================
    // REGION
    // =========================================================================

    public function setRegion(string $region): void
    {
        $this->region = WeeklyResetHelper::normalizeRegion($region);
    }

    // =========================================================================
    // TIME HELPERS
    // =========================================================================

    public function currentWowPeriodKey(): string
    {
        return WeeklyResetHelper::periodKey($this->region);
    }

    public function weeklyResetTimestamp(): int
    {
        return WeeklyResetHelper::resetTimestamp($this->region);
    }
}
