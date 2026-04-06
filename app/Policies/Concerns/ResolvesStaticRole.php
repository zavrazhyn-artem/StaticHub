<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\StaticGroup\Role;
use App\Models\StaticGroup;
use App\Models\User;

/**
 * Single source of truth for resolving a user's role inside a static group.
 * Used by StaticGroupPermissionPolicy.
 */
trait ResolvesStaticRole
{
    protected function getUserRoleInStatic(User $user, StaticGroup $static): ?Role
    {
        // Static owner is always treated as leader regardless of pivot value.
        if ((int) $static->owner_id === $user->id) {
            return Role::Leader;
        }

        $membership = $user->statics()
            ->where('static_id', $static->id)
            ->first();

        if (! $membership) {
            return null;
        }

        return Role::tryFrom($membership->pivot->access_role);
    }
}
