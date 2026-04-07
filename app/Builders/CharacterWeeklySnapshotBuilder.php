<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class CharacterWeeklySnapshotBuilder extends Builder
{
    public function forCharacter(int $characterId): self
    {
        return $this->where('character_id', $characterId);
    }

    public function forRegion(string $region): self
    {
        return $this->where('region', strtolower($region));
    }

    public function forPeriod(string $periodKey): self
    {
        return $this->where('period_key', $periodKey);
    }

    public function inSeason(string $firstPeriodKey): self
    {
        return $this->where('period_key', '>=', $firstPeriodKey);
    }
}
