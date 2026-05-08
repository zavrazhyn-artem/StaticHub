<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\LootDistribution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class LootDistributionBuilder extends Builder
{
    public function findByExternalId(string $externalId): ?LootDistribution
    {
        return $this->where('external_id', $externalId)->first();
    }

    public function existingExternalIds(array $externalIds): array
    {
        return $this->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->all();
    }

    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function forCharacterId(int $characterId): self
    {
        return $this->where('recipient_character_id', $characterId);
    }

    public function awardedBetween(Carbon $from, Carbon $to): self
    {
        return $this->whereBetween('awarded_at', [$from, $to]);
    }

    public function realAwards(): self
    {
        return $this->where('is_award_reason', false);
    }

    public function withDifficulty(string $difficulty): self
    {
        return $this->where('raid_difficulty', $difficulty);
    }

    public function withSlot(string $slot): self
    {
        return $this->where('item_slot', $slot);
    }

    public function recent(int $limit = 100): Collection
    {
        return $this->orderByDesc('awarded_at')->limit($limit)->get();
    }
}
