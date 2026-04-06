<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\User;

class SyncUserParticipationTask
{
    public function __construct(
        private readonly AssignCharacterRoleTask $assignCharacterRoleTask
    ) {}

    /**
     * Update user participation for a static.
     */
    public function run(User $user, StaticGroup $static, ?int $mainCharId, array $raidingCharIds): void
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
                $this->assignCharacterRoleTask->autoSetMainSpecIfMissing($character, $static->id);
            }
        }

        if ($mainCharId) {
            $character = Character::find($mainCharId);
            if ($character) {
                $static->characters()->attach($mainCharId, ['role' => 'main']);
                $this->assignCharacterRoleTask->autoSetMainSpecIfMissing($character, $static->id);
            }
        }
    }
}
