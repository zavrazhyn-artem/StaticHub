<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Character;
use App\Models\User;

class AssignCharacterRoleTask
{
    /**
     * Assign a character to a static with a specific role and handle auto-downgrade.
     *
     * @param int $characterId
     * @param int $staticId
     * @param string $role
     * @param string $combatRole
     * @param int $userId
     * @return void
     */
    public function run(int $characterId, int $staticId, string $role, string $combatRole, int $userId): void
    {
        $character = Character::findOrFail($characterId);

        // Check if user belongs to the static (or owns it)
        $user = User::findOrFail($userId);
        if (!$user->statics()->where('statics.id', $staticId)->exists()) {
            throw new \Exception('You are not a member of this static.');
        }

        // Enforce "One Main per User per Static" Rule (Auto-downgrade logic)
        if ($role === 'main') {
            Character::downgradeMainToAlt($userId, $staticId);
        }

        $character->statics()->syncWithoutDetaching([
            $staticId => [
                'role' => $role,
                'combat_role' => $combatRole,
            ]
        ]);
    }
}
