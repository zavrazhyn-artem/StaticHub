<?php

namespace App\Builders;

use App\Models\Character;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    public function atMaxLevel(): self
    {
        return $this->where('level', '>=', config('wow_season.max_player_level'));
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

    public function findForRsvp(int $characterId, int $userId, int $staticId): ?Character
    {
        return $this->where('id', $characterId)
            ->belongingToUserInStatic($userId, $staticId)
            ->first();
    }

    /**
     * Find user's main character for a specific static.
     */
    public function findMainInStatic(int $userId, int $staticId): ?Character
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
    public function findUserCharacterInReport(int $userId, int $staticId, int $reportId): ?Character
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
     * @param array $apiData
     * @param int $userId
     * @param int $realmId
     * @param string|null $avatarUrl
     * @return Character
     */
    public function syncFromBlizzard(array $apiData, int $userId, int $realmId, ?string $avatarUrl): Character
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

    /**
     * Bulk upsert characters from Blizzard API in a single query — replaces N*(SELECT+INSERT)
     * with one INSERT ... ON DUPLICATE KEY UPDATE. Every row must include every NOT NULL
     * column (user_id, realm_id, name, …) so MySQL's strict mode is satisfied on the INSERT path.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return \Illuminate\Database\Eloquent\Collection<int, Character>
     */
    public function upsertFromBlizzard(array $rows): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($rows)) {
            return Character::query()->whereRaw('0 = 1')->get();
        }

        $this->upsert(
            $rows,
            ['id'],
            ['user_id', 'realm_id', 'name', 'playable_class', 'playable_race', 'level', 'avatar_url']
        );

        return Character::query()
            ->whereIn('id', array_column($rows, 'id'))
            ->get();
    }

    public function findById(int $id): ?Character
    {
        return $this->find($id);
    }

    /**
     * Return each given user's main character in the static, keyed by user_id.
     */
    public function mainsForUsersInStatic(array $userIds, int $staticId): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        return $this->whereIn('characters.user_id', $userIds)
            ->join('character_static', 'characters.id', '=', 'character_static.character_id')
            ->where('character_static.static_id', $staticId)
            ->where('character_static.role', 'main')
            ->select('characters.*')
            ->get()
            ->keyBy('user_id');
    }
}
