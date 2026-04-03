<?php

namespace App\Http\Controllers;

use App\Actions\StaticGroup\UpdateAccessRoleAction;
use App\Actions\StaticGroup\UpdateRosterStatusAction;
use App\Actions\StaticGroup\KickStaticMemberAction;
use App\Http\Requests\UpdateAccessRoleRequest;
use App\Http\Requests\UpdateRosterStatusRequest;
use App\Http\Resources\StaticRosterMemberResource;
use App\Models\StaticGroup;
use App\Models\User;
use App\Policies\StaticGroupRosterPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StaticGroupRosterController extends Controller
{
    /**
     * Update access_role.
     */
    public function updateAccessRole(UpdateAccessRoleRequest $request, StaticGroup $static, User $user, UpdateAccessRoleAction $action): JsonResponse
    {
        Gate::authorize('updateAccessRole', [StaticGroupRosterPolicy::class, $static, $user]);

        $action->execute($static, $user, $request->validated('access_role'));

        return response()->json(['message' => 'Access role updated successfully.']);
    }

    /**
     * Update roster_status.
     */
    public function updateRosterStatus(UpdateRosterStatusRequest $request, StaticGroup $static, User $user, UpdateRosterStatusAction $action): JsonResponse
    {
        Gate::authorize('updateRosterStatus', [StaticGroupRosterPolicy::class, $static, $user]);

        $action->execute($static, $user, $request->validated('roster_status'));

        return response()->json(['message' => 'Roster status updated successfully.']);
    }

    /**
     * Kick member from static.
     */
    public function kick(StaticGroup $static, User $user, KickStaticMemberAction $action): JsonResponse
    {
        Gate::authorize('kick', [StaticGroupRosterPolicy::class, $static, $user]);

        $action->execute($static, $user);

        return response()->json(['message' => 'Member kicked successfully.']);
    }
}
