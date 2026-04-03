<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class KickStaticMemberAction
{
    /**
     * Remove a member from the static group.
     */
    public function execute(StaticGroup $static, User $user): void
    {
        $static->members()->detach($user->id);
    }
}
