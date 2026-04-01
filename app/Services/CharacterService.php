<?php

namespace App\Services;

use App\Models\Character;
use App\Models\PersonalTacticalReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class CharacterService
{
    /**
     * Get characters data for the index page.
     *
     * @param int $userId
     * @return array
     */
    public function getIndexData(int $userId): array
    {
        $user = User::query()->where('id', $userId)->withStatics()->first();
        $statics = $user ? $user->statics : new Collection();
        $static = $statics->first();

        $query = Character::query()->belongingTo($userId)->withStatics();

        if ($static) {
            $query->withStaticRole($static->id)->orderedByStaticRole();
        } else {
            $query->defaultOrder();
        }

        return [
            'characters' => $query->get(),
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
        $characterIds = $user->characters()->pluck('id')->toArray();
        return PersonalTacticalReport::query()->forCharacters($characterIds);
    }
}
