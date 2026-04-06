<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CharacterStaticSpecBuilder extends Builder
{
    public function forCharacterInStatic(int $characterId, int $staticId): self
    {
        return $this->where('character_id', $characterId)->where('static_id', $staticId);
    }

    public function deleteForCharacterInStatic(int $characterId, int $staticId): int
    {
        return $this->forCharacterInStatic($characterId, $staticId)->delete();
    }

    public function mainSpecsForCharacters(array $characterIds, int $staticId): Collection
    {
        return $this->whereIn('character_id', $characterIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->get();
    }

    public function existsForCharacterInStatic(int $characterId, int $staticId): bool
    {
        return $this->forCharacterInStatic($characterId, $staticId)->exists();
    }
}
