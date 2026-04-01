<?php

namespace App\Services;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Support\Collection;

class RosterService
{
    /**
     * Get all members (users) of a static with their characters, grouped by role.
     */
    public function getGroupedRoster(int $staticId): Collection
    {
        $users = $this->getStaticMembers($staticId);

        return $users->groupBy(function ($user) use ($staticId) {
            return $user->mainCharacter ? $user->mainCharacter->statics->firstWhere('id', $staticId)->pivot->combat_role : 'unknown';
        });
    }

    /**
     * Get all members (users) of a static with their characters.
     */
    public function getStaticMembers(int $staticId): Collection
    {
        $users = User::inStatic($staticId)
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                })
                ->with(['statics' => function ($q) use ($staticId) {
                    $q->where('statics.id', $staticId);
                }]);
            }])
            ->get();

        // Data Prep: For each user in the collection, separate their "Main" character and their "Alts"
        foreach ($users as $user) {
            $user->mainCharacter = $user->characters->first(function ($char) use ($staticId) {
                return $char->statics->firstWhere('id', $staticId)->pivot->role === 'main';
            });

            $user->altCharacters = $user->characters->filter(function ($char) use ($staticId) {
                return $char->statics->firstWhere('id', $staticId)->pivot->role === 'alt';
            });
        }

        return $users;
    }

    /**
     * Get role counts for a static roster.
     *
     * @param int $staticId
     * @return array
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
            if ($user->mainCharacter) {
                $role = $user->mainCharacter->statics->firstWhere('id', $staticId)->pivot->combat_role;
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
    public function assignCharacterToStatic(int $characterId, int $staticId, string $role, string $combatRole, int $userId): void
    {
        $character = Character::findOrFail($characterId);

        // Check if character belongs to user
        if ($character->user_id !== $userId) {
            throw new \Exception('Unauthorized', 403);
        }

        // Check if user belongs to the static (or owns it)
        $user = User::findOrFail($userId);
        if (!$user->statics()->where('statics.id', $staticId)->exists()) {
            throw new \Exception('You are not a member of this static.');
        }

        // Enforce "One Main per User per Static" Rule (Auto-downgrade logic)
        if ($role === 'main') {
            Character::belongingTo($userId)
                ->withRoleMain($staticId)
                ->each(function ($mainCharacter) use ($staticId) {
                    $mainCharacter->statics()->updateExistingPivot($staticId, ['role' => 'alt']);
                });
        }

        $character->statics()->syncWithoutDetaching([
            $staticId => [
                'role' => $role,
                'combat_role' => $combatRole,
            ]
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
     * Update user participation for a static.
     */
    public function updateUserParticipation(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds, array $combatRoles): void
    {
        // Step 1: Reset. Find all character IDs belonging to this $user.
        $userCharacterIds = Character::belongingTo($user->id)->pluck('id');

        // Detach ALL of them from the given $static in the character_static pivot table.
        $static->characters()->detach($userCharacterIds);

        // Step 2: Process Raiding Characters (Alts).
        foreach ($raidingCharIds as $charId) {
            // If the ID is NOT the $mainCharId, attach it to the static with role => 'alt'
            if ($charId != $mainCharId) {
                $static->characters()->attach($charId, [
                    'role' => 'alt',
                    'combat_role' => $combatRoles[$charId] ?? 'rdps',
                ]);
            }
        }

        // Step 3: Process the Main Character.
        if ($mainCharId) {
            $static->characters()->attach($mainCharId, [
                'role' => 'main',
                'combat_role' => $combatRoles[$mainCharId] ?? 'rdps',
            ]);
        }
    }
}
