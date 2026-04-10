<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Models\StaticRaidProgression;
use App\Services\Roster\InstanceDataService;
use Illuminate\Support\Carbon;

/**
 * Recalculates and persists static group raid progression.
 *
 * A boss counts as killed at a given difficulty when >= 60% of the roster
 * has killed it (cumulative, from Blizzard bnet_raid data). New kills are
 * recorded with a timestamp — existing records are never downgraded.
 */
class StaticProgressionService
{
    private const THRESHOLD_PERCENT = 0.6;

    private const DIFFICULTIES = ['LFR', 'N', 'H', 'M'];

    public function __construct(
        protected InstanceDataService $instanceDataService
    ) {}

    /**
     * Recalculate progression for a static group and persist any new achievements.
     *
     * @return int Number of new achievements recorded.
     */
    public function recalculate(StaticGroup $static): int
    {
        $characters = $static->characters()
            ->wherePivot('role', 'main')
            ->with(['serviceRawData' => function ($q) {
                $q->select('id', 'character_id', 'bnet_raid');
            }])->get();

        $rosterSize = $characters->count();

        if ($rosterSize === 0) {
            return 0;
        }

        $threshold = (int) ceil($rosterSize * self::THRESHOLD_PERCENT);

        $this->instanceDataService->setRegion($static->region ?? 'eu');

        $counters = $this->aggregateRosterKills($characters);
        $instances = config('wow_season.current_raid_instances', []);

        $newAchievements = 0;
        $now = Carbon::now();

        foreach ($instances as $instanceName => $configBosses) {
            foreach ($configBosses as $bossName) {
                foreach (self::DIFFICULTIES as $diff) {
                    $count = $counters[$instanceName][$bossName][$diff] ?? 0;

                    if ($count < $threshold) {
                        continue;
                    }

                    $recorded = StaticRaidProgression::query()
                        ->recordBossKill(
                            $static->id,
                            $instanceName,
                            $bossName,
                            $diff,
                            $now
                        );

                    if ($recorded) {
                        $newAchievements++;
                    }
                }
            }
        }

        return $newAchievements;
    }

    /**
     * Aggregate boss kill counts across the roster.
     *
     * @return array<string, array<string, array<string, int>>> [instance][boss][difficulty] => count
     */
    private function aggregateRosterKills($characters): array
    {
        $counters = [];

        foreach ($characters as $character) {
            $raidData = $character->serviceRawData?->bnet_raid ?? [];

            if ($raidData === []) {
                continue;
            }

            $charProgression = $this->instanceDataService->resolveRaids($raidData);

            if ($charProgression === null) {
                continue;
            }

            foreach ($charProgression as $instance => $bosses) {
                foreach ($bosses as $boss) {
                    foreach (self::DIFFICULTIES as $diff) {
                        if ($boss[$diff] ?? false) {
                            $counters[$instance][$boss['name']][$diff]
                                = ($counters[$instance][$boss['name']][$diff] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        return $counters;
    }

    /**
     * Get persisted progression for a static, formatted for the dashboard.
     *
     * @return array<int, array{instance: string, bosses: array}>
     */
    public function getProgression(int $staticGroupId): array
    {
        $records = StaticRaidProgression::query()
            ->forStatic($staticGroupId)
            ->get()
            ->groupBy('instance_name');

        $instances = config('wow_season.current_raid_instances', []);
        $diffRank  = ['LFR' => 1, 'N' => 2, 'H' => 3, 'M' => 4];

        $result = [];

        foreach ($instances as $instanceName => $configBosses) {
            $bossRecords = $records->get($instanceName, collect())->groupBy('boss_name');

            $bosses = [];
            foreach ($configBosses as $bossName) {
                $kills = $bossRecords->get($bossName, collect());

                $best = null;
                $bestAchievedAt = null;
                $history = [];

                foreach ($kills as $kill) {
                    $history[] = [
                        'difficulty'  => $kill->difficulty,
                        'achieved_at' => $kill->achieved_at->toIso8601String(),
                    ];

                    if ($best === null || ($diffRank[$kill->difficulty] ?? 0) > ($diffRank[$best] ?? 0)) {
                        $best = $kill->difficulty;
                        $bestAchievedAt = $kill->achieved_at->toIso8601String();
                    }
                }

                $bosses[] = [
                    'name'        => $bossName,
                    'difficulty'  => $best,
                    'achieved_at' => $bestAchievedAt,
                    'history'     => $history,
                ];
            }

            $result[] = [
                'instance' => $instanceName,
                'bosses'   => $bosses,
            ];
        }

        return $result;
    }
}
