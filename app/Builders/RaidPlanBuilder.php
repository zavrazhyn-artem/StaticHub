<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class RaidPlanBuilder extends Builder
{
    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function forEncounter(string $encounterSlug): self
    {
        return $this->where('encounter_slug', $encounterSlug);
    }

    public function forEvent(int $eventId): self
    {
        return $this->where('event_id', $eventId);
    }

    public function findForEncounter(int $staticId, string $encounterSlug, string $difficulty = 'mythic'): ?\App\Models\RaidPlan
    {
        return $this->where('static_id', $staticId)
            ->where('encounter_slug', $encounterSlug)
            ->where('difficulty', $difficulty)
            ->latest()
            ->first();
    }

    public function allForStatic(int $staticId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->where('static_id', $staticId)
            ->orderBy('encounter_slug')
            ->orderByDesc('updated_at')
            ->get();
    }
}
