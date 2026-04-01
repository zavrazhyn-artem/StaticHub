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

    public function belongingToUserInStatic(int $userId, int $staticId): self
    {
        return $this->where('user_id', $userId)
            ->whereHas('statics', function ($query) use ($staticId) {
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

    public function withStaticRole(int $staticId): self
    {
        return $this->leftJoin('character_static', function ($join) use ($staticId) {
            $join->on('characters.id', '=', 'character_static.character_id')
                ->where('character_static.static_id', '=', $staticId);
        })
        ->select('characters.*', 'character_static.role as static_role');
    }

    public function orderedByStaticRole(): self
    {
        return $this->orderByRaw("CASE WHEN character_static.role = 'main' THEN 0 ELSE 1 END")
            ->orderBy('level', 'desc')
            ->orderBy('equipped_item_level', 'desc');
    }

    public function defaultOrder(): self
    {
        return $this->orderBy('level', 'desc')
            ->orderBy('equipped_item_level', 'desc');
    }

    public function findForRsvp(int $characterId, int $userId, int $staticId): ?\App\Models\Character
    {
        return $this->where('id', $characterId)
            ->belongingToUserInStatic($userId, $staticId)
            ->first();
    }

    /**
     * Find user's main character for a specific static.
     */
    public function findMainInStatic(int $userId, int $staticId): ?\App\Models\Character
    {
        return $this->where('user_id', $userId)
            ->whereHas('statics', function ($query) use ($staticId) {
                $query->where('statics.id', $staticId)
                      ->where('character_static.role', 'main');
            })
            ->first();
    }

    /**
     * Find user character in the context of a tactical report.
     */
    public function findUserCharacterInReport(int $userId, int $staticId, int $reportId): ?\App\Models\Character
    {
        return $this->where('user_id', $userId)
            ->join('character_static', 'characters.id', '=', 'character_static.character_id')
            ->where('character_static.static_id', $staticId)
            ->join('personal_tactical_reports', 'characters.id', '=', 'personal_tactical_reports.character_id')
            ->where('personal_tactical_reports.tactical_report_id', $reportId)
            ->select('characters.*')
            ->first();
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
