<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRosterRequest;
use App\Http\Resources\StaticRosterMemberResource;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\ConsumableService;
use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(
        protected RosterService    $rosterService,
        protected ConsumableService $consumableService,
        protected TreasuryService  $treasuryService,
    ) {}

    public function index(StaticGroup $static): View
    {
        // Load each member (User) with:
        //   - their characters, each having the statics relationship scoped to
        //     this static so the pivot role ('main'/'alt') is available in-memory.
        //   - compiled_data is a column on characters, loaded automatically.
        $members = $static->members()
            ->with([
                'characters' => fn ($q) => $q->with([
                    'statics' => fn ($sq) => $sq->where('statics.id', $static->id),
                ]),
            ])
            ->get();

        $currentUserId    = (int) Auth::id();
        $currentUserAccess = $members
            ->first(fn (User $m) => $m->id === $currentUserId)
            ?->pivot
            ?->access_role ?? 'member';

        $rosterData = [
            'roster' => $members
                ->map(fn (User $user) => (new StaticRosterMemberResource($user))
                    ->setStaticId($static->id)
                    ->resolve()
                )
                ->values(),
            'current_user_access' => $currentUserAccess,
        ];

        return view('roster.index', compact('static', 'rosterData'));
    }

    public function overview(StaticGroup $static): View
    {
        $mains = $this->rosterService->getRosterOverview($static);

        return view('roster.overview', [
            'static'     => $static,
            'characters' => $mains,
        ]);
    }

    public function updateParticipation(UpdateRosterRequest $request, StaticGroup $static): RedirectResponse
    {
        $validated = $request->validated();

        $mainCharId = isset($validated['main_character_id']) ? (int) $validated['main_character_id'] : null;
        $raidingCharIds = isset($validated['raiding_characters']) ? array_map('intval', $validated['raiding_characters']) : [];

        $this->rosterService->updateUserParticipation(
            Auth::user(),
            $static,
            $mainCharId,
            $raidingCharIds,
            $validated['combat_roles']
        );

        if ($request->has('onboarding')) {
            return redirect()->route('dashboard')->with('success', 'Roster updated successfully!');
        }

        return redirect()->back()->with('success', 'Roster updated successfully!');
    }
}
