<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class ServiceRawDataBuilder extends Builder
{
    public function upsertForCharacter(int $characterId, array $data): object
    {
        return $this->updateOrCreate(
            ['character_id' => $characterId],
            $data
        );
    }

    public function forCharacter(int $characterId): self
    {
        return $this->where('character_id', $characterId);
    }
}
