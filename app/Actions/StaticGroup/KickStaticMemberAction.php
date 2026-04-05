<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class KickStaticMemberAction
{
    /**
     * Remove a member from the static group and detach all their characters.
     */
    public function execute(StaticGroup $static, User $user): void
    {
        $characterIds = $user->characters()->pluck('id');

        if ($characterIds->isNotEmpty()) {
            $static->characters()->detach($characterIds);
        }

        $static->members()->detach($user->id);
    }
}
