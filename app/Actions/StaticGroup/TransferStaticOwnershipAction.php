<?php

namespace App\Actions\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class TransferStaticOwnershipAction
{
    public function execute(StaticGroup $static, User $currentOwner, User $newOwner): void
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
}
