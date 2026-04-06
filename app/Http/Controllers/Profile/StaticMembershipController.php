<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;

use App\Models\StaticGroup;
use App\Services\StaticGroup\RosterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class StaticMembershipController extends Controller
{
    public function __construct(
        protected RosterService $rosterService,
    ) {}

    /**
     * Transfer ownership of a static group to another member.
     */
    public function transferOwnership(Request $request, StaticGroup $static): RedirectResponse
    {
        $user = $request->user();

        if ($static->owner_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate(['new_owner_id' => 'required|integer']);

        $newOwner = $static->members()
            ->where('user_id', $validated['new_owner_id'])
            ->firstOrFail();

        $this->rosterService->transferOwnership($static, $user, $newOwner);

        return Redirect::route('profile.edit')->with('status', 'ownership-transferred');
    }

    /**
     * Leave the current static group.
     */
    public function leaveStatic(Request $request): RedirectResponse
    {
        $user = $request->user();
        $statics = $user->statics()->get();

        foreach ($statics as $static) {
            if ($static->owner_id === $user->id) {
                return Redirect::route('profile.edit')->with('error', 'leave-owner');
            }
            $this->rosterService->kickMember($static, $user);
        }

        return Redirect::route('onboarding.index')->with('status', 'left-static');
    }
}
