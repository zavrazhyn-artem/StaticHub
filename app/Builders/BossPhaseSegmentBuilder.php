<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class BossPhaseSegmentBuilder extends Builder
{
    public function forSeason(string $season): self
    {
        return $this->where('season', $season);
    }

    public function forSeasonEncounterDifficulty(string $season, string $encounterSlug, string $difficulty): self
    {
        return $this->where('season', $season)
            ->where('encounter_slug', $encounterSlug)
            ->where('difficulty', $difficulty);
    }

    public function globalOrForStatic(?int $staticId): self
    {
        if ($staticId === null) {
            return $this->whereNull('static_id');
        }
        return $this->where(function ($q) use ($staticId) {
            $q->whereNull('static_id')->orWhere('static_id', $staticId);
        });
    }
}
