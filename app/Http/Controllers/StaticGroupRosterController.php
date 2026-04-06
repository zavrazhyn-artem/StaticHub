<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccessRoleRequest;
use App\Http\Requests\UpdateRosterStatusRequest;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\StaticGroup\RosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StaticGroupRosterController extends Controller
{
    public function __construct(
        private readonly RosterService $rosterService
    ) {}

    /**
     * Update access_role.
     */
    public function updateAccessRole(UpdateAccessRoleRequest $request, StaticGroup $static, User $user): JsonResponse
    {
        Gate::authorize('updateAccessRole', $static);

        $this->rosterService->updateAccessRole($static, $user, $request->validated('access_role'));

        return response()->json(['message' => 'Access role updated successfully.']);
    }

    /**
     * Update roster_status.
     */
    public function updateRosterStatus(UpdateRosterStatusRequest $request, StaticGroup $static, User $user): JsonResponse
    {
        Gate::authorize('updateRosterStatus', [$static, $user]);

        $this->rosterService->updateRosterStatus($static, $user, $request->validated('roster_status'));

        return response()->json(['message' => 'Roster status updated successfully.']);
    }

    /**
     * Kick member from static.
     */
    public function kick(StaticGroup $static, User $user): JsonResponse
    {
        Gate::authorize('kick', [$static, $user]);

        $this->rosterService->kickMember($static, $user);

        return response()->json(['message' => 'Member kicked successfully.']);
    }
}
