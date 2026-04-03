<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\User;

class SyncUserParticipationTask
{
    /**
     * Update user participation for a static.
     *
     * @param User $user
     * @param StaticGroup $static
     * @param int|null $mainCharId
     * @param array $raidingCharIds
     * @param array $combatRoles
     * @return void
     */
    public function run(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds, array $combatRoles): void
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
