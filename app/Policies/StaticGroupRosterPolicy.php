<?php

namespace App\Policies;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StaticGroupRosterPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the access role of a member.
     */
    public function updateAccessRole(User $user, StaticGroup $static, User $targetUser): bool
    {
        $currentUserRole = $this->getUserRoleInStatic($user, $static);

        // Only leader can change access roles
        return $currentUserRole === 'leader';
    }

    /**
     * Determine whether the user can update the roster status of a member.
     */
    public function updateRosterStatus(User $user, StaticGroup $static, User $targetUser): bool
    {
        $currentUserRole = $this->getUserRoleInStatic($user, $static);

        // Leader and officer can change roster status
        return in_array($currentUserRole, ['leader', 'officer']);
    }

    /**
     * Determine whether the user can kick a member.
     */
    public function kick(User $user, StaticGroup $static, User $targetUser): bool
    {
        // Cannot kick yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        $currentUserRole = $this->getUserRoleInStatic($user, $static);
        $targetUserRole = $this->getUserRoleInStatic($targetUser, $static);

        if (!$currentUserRole || !$targetUserRole) {
            return false;
        }

        // Leader can kick anyone (except themselves, handled above)
        if ($currentUserRole === 'leader') {
            return true;
        }

        // Officers can only kick members
        if ($currentUserRole === 'officer' && $targetUserRole === 'member') {
            return true;
        }

        return false;
    }

    /**
     * Get the user's role in the static group.
     */
    protected function getUserRoleInStatic(User $user, StaticGroup $static): ?string
    {
        $membership = $user->statics()
            ->where('static_id', $static->id)
            ->first();

        return $membership ? $membership->pivot->access_role : null;
    }
}
