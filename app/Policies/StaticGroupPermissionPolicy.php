<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StaticGroup\Role;
use App\Models\StaticGroup;
use App\Models\User;
use App\Policies\Concerns\ResolvesStaticRole;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Unified policy for all StaticGroup permission checks.
 *
 * Replaces StaticGroupPolicy, TreasuryPolicy and StaticGroupRosterPolicy.
 *
 * Naming convention:
 *   - canAccess*   → read access to a section
 *   - canManage*   → write / mutating access
 *   - manage       → alias kept for @can('manage', $static) in blade layouts
 */
class StaticGroupPermissionPolicy
{
    use HandlesAuthorization, ResolvesStaticRole;

    /**
     * Ghost mode: allow a small whitelist of read abilities on safe HTTP
     * methods (GET/HEAD/OPTIONS) so settings tabs, full report view, and
     * manager-gated UI render correctly when peeking. Every other ability
     * is denied — writes (PATCH/POST/DELETE) always fall through to false.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! app(\App\Services\Ghost\GhostModeService::class)->isActive()) {
            return null;
        }

        $readOnlyAbilities = [
            'manage',
            'canAccessSettings',
            'canViewGlobalReport',
        ];

        if (in_array($ability, $readOnlyAbilities, true) && request()->isMethodSafe()) {
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Settings
    // -------------------------------------------------------------------------

    /**
     * Blade alias: @can('manage', $static) — used in the sidebar and calendar.
     */
    public function manage(User $user, StaticGroup $static): bool
    {
        return $this->canAccessSettings($user, $static);
    }

    /**
     * Full access to the Settings section (all sub-pages).
     * Required role: leader or officer.
     */
    public function canAccessSettings(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    // -------------------------------------------------------------------------
    // Schedule
    // -------------------------------------------------------------------------

    /**
     * Create, edit and delete raid events.
     * Required role: leader or officer.
     */
    public function canManageSchedule(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    /**
     * Post a raid event announcement to Discord.
     * Required role: leader or officer.
     */
    public function canAnnounceToDiscord(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    // -------------------------------------------------------------------------
    // Logs / Reports
    // -------------------------------------------------------------------------

    /**
     * View the full global (guild-wide) report and AI analysis.
     * Members can only see their own personal report.
     * Required role: leader or officer.
     */
    public function canViewGlobalReport(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    /**
     * Activate the AI chat for a tactical report (one-shot, 30-min window).
     * Required role: leader or officer. Members consume the activated session
     * but cannot start it.
     */
    public function canActivateReportChat(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    // -------------------------------------------------------------------------
    // Treasury
    // -------------------------------------------------------------------------

    /**
     * Record deposits/withdrawals and change treasury settings.
     * Required role: leader or officer.
     */
    public function canManageTreasury(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    // -------------------------------------------------------------------------
    // Roster management
    // -------------------------------------------------------------------------

    /**
     * Change another member's access role (officer → member etc.).
     * Required role: leader or officer.
     */
    public function updateAccessRole(User $user, StaticGroup $static): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    /**
     * Change a member's roster status (core / bench).
     * Required role: leader or officer.
     */
    public function updateRosterStatus(User $user, StaticGroup $static, User $targetUser): bool
    {
        return $this->getUserRoleInStatic($user, $static)?->isManager() ?? false;
    }

    /**
     * Kick a member from the static.
     * - Leader can kick anyone except themselves.
     * - Officer can only kick members (not other officers or the leader).
     */
    public function kick(User $user, StaticGroup $static, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        $currentRole = $this->getUserRoleInStatic($user, $static);
        $targetRole  = $this->getUserRoleInStatic($targetUser, $static);

        if (! $currentRole || ! $targetRole) {
            return false;
        }

        if ($currentRole === Role::Leader) {
            return true;
        }

        if ($currentRole === Role::Officer && $targetRole === Role::Member) {
            return true;
        }

        return false;
    }
}
