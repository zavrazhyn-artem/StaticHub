<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\LootDistribution;
use Illuminate\Database\Eloquent\Builder;

class LootDistributionBuilder extends Builder
{
    public function findByEventUuid(string $uuid): ?LootDistribution
    {
        return $this->where('event_uuid', $uuid)->first();
    }

    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function existingUuids(array $uuids): array
    {
        return $this->whereIn('event_uuid', $uuids)->pluck('event_uuid')->all();
    }
}
