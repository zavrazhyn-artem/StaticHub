<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class UpdateAccessRoleAction
{
    /**
     * Update the access role for a member of the static group.
     */
    public function execute(StaticGroup $static, User $user, string $accessRole): void
    {
        $static->members()->updateExistingPivot($user->id, [
            'access_role' => $accessRole,
        ]);
    }
}
