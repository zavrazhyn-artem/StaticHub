<?php

declare(strict_types=1);

namespace App\Services\Character;

use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\PersonalTacticalReport;
use App\Models\Specialization;
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

        // All specializations for the spec picker (empty if wow:sync-specializations hasn't run yet)
        $specializations = Specialization::orderBy('class_name')->orderBy('name')->get();

        // Current specs per character in this static (spec_ids + main_spec_id)
        $characterSpecs = [];
        if ($static) {
            $characterIds = $characters->pluck('id');
            $specRecords = CharacterStaticSpec::whereIn('character_id', $characterIds)
                ->where('static_id', $static->id)
                ->get()
                ->groupBy('character_id');

            foreach ($characters as $character) {
                $records = $specRecords->get($character->id, collect());
                $characterSpecs[$character->id] = [
                    'spec_ids'     => $records->pluck('spec_id')->map(fn ($id) => (int) $id)->toArray(),
                    'main_spec_id' => $records->firstWhere('is_main', true)?->spec_id,
                ];
            }
        }

        // Derive main / raiding initial state for the AJAX-driven UI
        $mainCharId    = null;
        $raidingCharIds = [];
        if ($static) {
            foreach ($characters as $character) {
                $role = $character->static_role
                    ?? $character->statics->firstWhere('id', $static->id)?->pivot?->role;
                if ($role === 'main') {
                    $mainCharId = $character->id;
                }
                if ($role === 'main' || $role === 'alt') {
                    $raidingCharIds[] = $character->id;
                }
            }
        }

        return [
            'characters'      => $characters,
            'statics'         => $statics,
            'static'          => $static,
            'specializations' => $specializations,
            'characterSpecs'  => $characterSpecs,
            'mainCharId'      => $mainCharId,
            'raidingCharIds'  => $raidingCharIds,
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
