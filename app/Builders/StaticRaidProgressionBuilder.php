<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StaticRaidProgressionBuilder extends Builder
{
    private const DIFFICULTY_RANK = [
        'LFR' => 1,
        'N'   => 2,
        'H'   => 3,
        'M'   => 4,
    ];

    public function forStatic(int $staticGroupId): self
    {
        return $this->where('static_group_id', $staticGroupId);
    }

    public function forInstance(string $instanceName): self
    {
        return $this->where('instance_name', $instanceName);
    }

    /**
     * Insert a boss kill record if this difficulty is not yet recorded.
     * Only inserts — never downgrades. Returns true if a new record was created.
     */
    public function recordBossKill(
        int    $staticGroupId,
        string $instanceName,
        string $bossName,
        string $difficulty,
        Carbon $achievedAt
    ): bool {
        $exists = $this->newQuery()
            ->where('static_group_id', $staticGroupId)
            ->where('instance_name', $instanceName)
            ->where('boss_name', $bossName)
            ->where('difficulty', $difficulty)
            ->exists();

        if ($exists) {
            return false;
        }

        $this->newQuery()->create([
            'static_group_id' => $staticGroupId,
            'instance_name'   => $instanceName,
            'boss_name'       => $bossName,
            'difficulty'      => $difficulty,
            'achieved_at'     => $achievedAt,
        ]);

        return true;
    }

    /**
     * Get the highest recorded difficulty for a specific boss.
     */
    public function highestDifficultyForBoss(
        int    $staticGroupId,
        string $instanceName,
        string $bossName
    ): ?string {
        $records = $this->newQuery()
            ->where('static_group_id', $staticGroupId)
            ->where('instance_name', $instanceName)
            ->where('boss_name', $bossName)
            ->pluck('difficulty');

        if ($records->isEmpty()) {
            return null;
        }

        return $records->sortByDesc(fn(string $d) => self::DIFFICULTY_RANK[$d] ?? 0)->first();
    }
}
