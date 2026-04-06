<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\Specialization;
use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class RosterService
{
    /**
     * Get all members (users) of a static with their characters, grouped by role.
     */
    public function getGroupedRoster(int $staticId): Collection
    {
        $users = $this->getStaticMembers($staticId);

        $users->each(function (User $user) use ($staticId) {
            $user->setRelation('mainCharacter', $user->getMainCharacterForStatic($staticId));
            $user->setRelation('altCharacters', $user->getAltCharactersForStatic($staticId));
        });

        return $users->groupBy(function (User $user) use ($staticId) {
            $main = $user->mainCharacter;
            return $main ? $main->getCombatRoleInStatic($staticId) : 'unknown';
        });
    }

    /**
     * Get all members (users) of a static with their characters.
     * Also attaches main_spec attribute on each character for frontend use.
     */
    public function getStaticMembers(int $staticId): Collection
    {
        $users = User::query()->inStatic($staticId)
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                })
                ->with(['statics' => function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                }]);
            }])
            ->get();

        // Pre-load main specs for all characters in this static in one query
        $allCharacterIds = $users->flatMap(fn ($u) => $u->characters->pluck('id'));

        $mainSpecRecords = CharacterStaticSpec::whereIn('character_id', $allCharacterIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        // Set main_spec attribute on each character
        $users->each(function ($user) use ($mainSpecRecords) {
            $user->characters->each(function ($char) use ($mainSpecRecords) {
                $specRecord = $mainSpecRecords->get($char->id);
                $spec = $specRecord?->specialization;

                $char->setAttribute('main_spec', $spec ? [
                    'id'       => $spec->id,
                    'name'     => $spec->name,
                    'role'     => $spec->role,
                    'icon_url' => $spec->icon_url,
                ] : null);
            });
        });

        return $users;
    }

    /**
     * Get role counts for a static roster.
     */
    public function getRoleCounts(int $staticId): array
    {
        $users = $this->getStaticMembers($staticId);

        $roleCounts = [
            'tank' => 0,
            'heal' => 0,
            'mdps' => 0,
            'rdps' => 0,
        ];

        foreach ($users as $user) {
            $main = $user->getMainCharacterForStatic($staticId);
            if ($main) {
                $role = $main->getCombatRoleInStatic($staticId);
                if (isset($roleCounts[$role])) {
                    $roleCounts[$role]++;
                }
            }
        }

        return $roleCounts;
    }

    /**
     * Assign a character to a static with a specific role and handle auto-downgrade.
     */
    public function assignCharacterToStatic(int $characterId, int $staticId, string $role, int $userId): void
    {
        $character = Character::findOrFail($characterId);

        $user = User::findOrFail($userId);
        if (!$user->statics()->where('statics.id', $staticId)->exists()) {
            throw new \Exception('You are not a member of this static.');
        }

        if ($role === 'main') {
            Character::downgradeMainToAlt($userId, $staticId);
        }

        $character->statics()->syncWithoutDetaching([
            $staticId => ['role' => $role],
        ]);

        $this->autoSetMainSpecIfMissing($character, $staticId);
    }

    /**
     * Auto-set main spec from active_spec on first assignment to a static.
     */
    public function autoSetMainSpecIfMissing(Character $character, int $staticId): void
    {
        $hasSpecs = CharacterStaticSpec::where('character_id', $character->id)
            ->where('static_id', $staticId)
            ->exists();

        if ($hasSpecs) {
            return;
        }

        if (!$character->active_spec || !$character->playable_class) {
            return;
        }

        $spec = Specialization::where('name', $character->active_spec)
            ->where('class_name', $character->playable_class)
            ->first();

        if (!$spec) {
            return;
        }

        CharacterStaticSpec::create([
            'character_id' => $character->id,
            'static_id'    => $staticId,
            'spec_id'      => $spec->id,
            'is_main'      => true,
        ]);
    }

    /**
     * Get the overview data for a static roster (mains with their alts).
     */
    public function getRosterOverview(StaticGroup $static): Collection
    {
        $allCharacters = $static->characters()->get();

        $mains = $allCharacters->where(function ($char) {
            return strtolower($char->pivot->role) === 'main';
        })->values();

        foreach ($mains as $main) {
            $main->alts = $allCharacters->where('user_id', $main->user_id)
                ->where('id', '!=', $main->id)
                ->values();
        }

        return $mains;
    }

    /**
     * Transfer ownership of a static group.
     */
    public function transferOwnership(StaticGroup $static, User $currentOwner, User $newOwner): void
    {
        $static->members()->updateExistingPivot($currentOwner->id, [
            'role'        => 'officer',
            'access_role' => 'officer',
        ]);

        $static->members()->updateExistingPivot($newOwner->id, [
            'role'        => 'owner',
            'access_role' => 'leader',
        ]);

        $static->update(['owner_id' => $newOwner->id]);
    }

    /**
     * Kick a member from the static group.
     */
    public function kickMember(StaticGroup $static, User $user): void
    {
        $characterIds = $user->characters()->pluck('id');

        if ($characterIds->isNotEmpty()) {
            $static->characters()->detach($characterIds);
        }

        $static->members()->detach($user->id);
    }

    /**
     * Get roster members with their characters.
     */
    public function getMembersWithCharacters(StaticGroup $static): EloquentCollection
    {
        return $static->members()
            ->with('characters')
            ->get();
    }

    /**
     * Update access role for a member.
     */
    public function updateAccessRole(StaticGroup $static, User $user, string $accessRole): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'access_role' => $accessRole,
        ]);
    }

    /**
     * Update roster status for a member.
     */
    public function updateRosterStatus(StaticGroup $static, User $user, string $rosterStatus): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'roster_status' => $rosterStatus,
        ]);
    }

    /**
     * Update user participation for a static.
     */
    public function updateUserParticipation(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds): void
    {
        $userCharacterIds = Character::belongingTo($user->id)->pluck('id');

        $static->characters()->detach($userCharacterIds);

        foreach ($raidingCharIds as $charId) {
            if ($charId != $mainCharId) {
                $character = Character::find($charId);
                if (!$character) {
                    continue;
                }
                $static->characters()->attach($charId, ['role' => 'alt']);
                $this->autoSetMainSpecIfMissing($character, $static->id);
            }
        }

        if ($mainCharId) {
            $character = Character::find($mainCharId);
            if ($character) {
                $static->characters()->attach($mainCharId, ['role' => 'main']);
                $this->autoSetMainSpecIfMissing($character, $static->id);
            }
        }
    }
}
