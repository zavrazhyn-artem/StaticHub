<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class UpdateRosterStatusAction
{
    /**
     * Update the roster status for a member of the static group.
     */
    public function execute(StaticGroup $static, User $user, string $rosterStatus): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'roster_status' => $rosterStatus,
        ]);
    }
}
