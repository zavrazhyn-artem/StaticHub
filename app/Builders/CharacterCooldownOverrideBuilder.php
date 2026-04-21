<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CharacterCooldownOverrideBuilder extends Builder
{
    public function forCharacter(int $characterId): self
    {
        return $this->where('character_id', $characterId);
    }

    public function forCharacters(array $characterIds): self
    {
        return $this->whereIn('character_id', $characterIds);
    }

    public function disabledForCharacters(array $characterIds): Collection
    {
        return $this->whereIn('character_id', $characterIds)
            ->where('enabled', false)
            ->get(['character_id', 'spell_id']);
    }
}
