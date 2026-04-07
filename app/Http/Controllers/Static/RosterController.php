<?php

declare(strict_types=1);

namespace App\Http\Controllers\Static;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateRosterRequest;
use App\Models\StaticGroup;
use App\Services\Character\CharacterService;
use App\Services\StaticGroup\ConsumableService;
use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(
        protected RosterService     $rosterService,
        protected ConsumableService $consumableService,
        protected TreasuryService   $treasuryService,
        protected CharacterService  $characterService,
    ) {}

    public function index(StaticGroup $static): View
    {
        $rosterData = $this->rosterService->buildRosterIndexPayload($static);

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
        Log::info('Updating roster participation for user', [
            'user_id' => Auth::id(),
            'static_group_id' => $static->id,
            'validated' => $validated,
        ]);

        $mainCharId = isset($validated['main_character_id']) ? (int) $validated['main_character_id'] : null;
        $raidingCharIds = isset($validated['raiding_characters']) ? array_map('intval', $validated['raiding_characters']) : [];

        $this->rosterService->updateUserParticipation(
            Auth::user(),
            $static,
            $mainCharId,
            $raidingCharIds
        );

        if ($request->wantsJson()) {
            $characterSpecs = $this->characterService->buildCharacterSpecs($raidingCharIds, $static->id);

            return response()->json([
                'success'        => true,
                'characterSpecs' => $characterSpecs,
            ]);
        }

        if ($request->has('onboarding')) {
            return redirect()->route('dashboard')->with('success', 'Roster updated successfully!');
        }

        return redirect()->back()->with('success', 'Roster updated successfully!');
    }
}
