<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Models\Character;
use App\Models\PersonalTacticalReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CharacterService
{
    /**
     * Build the payload for the character index page.
     *
     * @param int $userId
     * @return array
     */
    public function buildIndexPayload(int $userId): array
    {
        $user = $this->fetchUserWithStatics($userId);
        $statics = $user?->statics ?? new Collection();
        $static = $statics->first();

        $characters = $this->fetchCharactersForUser($userId, $static?->id);

        return [
            'characters' => $characters,
            'statics' => $statics,
            'static' => $static,
        ];
    }

    /**
     * Get personal tactical reports for a user.
     *
     * @param User $user
     * @return Collection
     */
    public function getPersonalReports(User $user): Collection
    {
        return PersonalTacticalReport::query()->forCharacters($user->getCharacterIds());
    }

    /**
     * Fetch the user with their associated static groups.
     *
     * @param int $userId
     * @return User|null
     */
    private function fetchUserWithStatics(int $userId): ?User
    {
        return User::query()->where('id', $userId)->withStatics()->first();
    }

    /**
     * Fetch characters for the user, optionally scoped to a static group's role order.
     *
     * @param int $userId
     * @param int|null $staticId
     * @return Collection
     */
    private function fetchCharactersForUser(int $userId, ?int $staticId = null): Collection
    {
        $query = Character::query()->belongingTo($userId)->atMaxLevel()->withStatics();

        if ($staticId) {
            $query->withStaticRole($staticId)->orderedByStaticRole();
        } else {
            $query->defaultOrder();
        }

        return $query->get();
    }
}
