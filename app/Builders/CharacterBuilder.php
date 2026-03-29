<?php

namespace App\Builders;

use App\Models\Character;
use Illuminate\Database\Eloquent\Builder;

class CharacterBuilder extends Builder
{
    public function inStatic(int $staticId): self
    {
        return $this->whereHas('statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId);
        });
    }

    public function withRoleMain(int $staticId): self
    {
        return $this->whereHas('statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId)
                  ->where('role', 'main');
        });
    }

    public function withRoleAlt(int $staticId): self
    {
        return $this->whereHas('statics', function ($query) use ($staticId) {
            $query->where('statics.id', $staticId)
                  ->where('role', 'alt');
        });
    }

    public function belongingTo(int $userId): self
    {
        return $this->where('user_id', $userId);
    }

    public function withStatics(): self
    {
        return $this->with('statics');
    }

    /**
     * Update or create a character from Blizzard API data.
     */
    public function syncFromBlizzard(array $apiData, int $userId, int $realmId, ?string $avatarUrl): \App\Models\Character
    {
        return $this->updateOrCreate(
            ['id' => $apiData['id']],
            [
                'user_id' => $userId,
                'realm_id' => $realmId,
                'name' => $apiData['name'],
                'playable_class' => $apiData['playable_class'],
                'playable_race' => $apiData['playable_race'],
                'level' => $apiData['level'],
                'avatar_url' => $avatarUrl,
            ]
        );
    }
}
