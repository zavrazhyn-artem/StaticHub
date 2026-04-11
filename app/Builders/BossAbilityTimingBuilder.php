<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BossAbilityTimingBuilder extends Builder
{
    public function forSeason(string $season): self
    {
        return $this->where('season', $season);
    }

    public function forEncounter(string $encounterSlug): self
    {
        return $this->where('encounter_slug', $encounterSlug);
    }

    public function forSeasonEncounter(string $season, string $encounterSlug): self
    {
        return $this->where('season', $season)->where('encounter_slug', $encounterSlug);
    }

    public function forSeasonEncounterDifficulty(string $season, string $encounterSlug, string $difficulty): self
    {
        return $this->where('season', $season)
            ->where('encounter_slug', $encounterSlug)
            ->where('difficulty', $difficulty);
    }

    /**
     * Filter to global rows OR rows owned by a specific static.
     * Used at read-time so per-static custom seeds can override the global default.
     */
    public function globalOrForStatic(?int $staticId): self
    {
        if ($staticId === null) {
            return $this->whereNull('static_id');
        }
        return $this->where(function ($q) use ($staticId) {
            $q->whereNull('static_id')->orWhere('static_id', $staticId);
        });
    }

    public function allForSeason(string $season): Collection
    {
        return $this->where('season', $season)
            ->orderBy('encounter_slug')
            ->orderBy('row_order')
            ->get();
    }

    public function allForSeasonEncounter(string $season, string $encounterSlug): Collection
    {
        return $this->forSeasonEncounter($season, $encounterSlug)
            ->orderBy('row_order')
            ->get();
    }
}
