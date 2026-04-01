<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRosterRequest;
use App\Models\StaticGroup;
use App\Models\User;
use App\Services\ConsumableService;
use App\Services\RosterService;
use App\Services\TreasuryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(
        protected RosterService $rosterService,
        protected ConsumableService $consumableService,
        protected TreasuryService $treasuryService
    ) {}

    public function showFirst(): RedirectResponse
    {
        $static = User::query()->firstStaticForUser(Auth::id());

        if (!$static) {
            return redirect()->route('statics.setup');
        }

        return redirect()->route('statics.roster', $static->id);
    }

    public function index(StaticGroup $static): View
    {
        $consumablesData = $this->consumableService->getRaidConsumablesData($static);
        $targetTax = $consumablesData['guild_tax_per_raider'] ?? 0;

        $groupedRoster = $this->rosterService->getGroupedRoster($static->id);
        $weeklyTaxStatus = $this->treasuryService->getWeeklyStatus($static, $targetTax)->keyBy('user_id');

        return view('roster.index', [
            'static' => $static,
            'groupedRoster' => $groupedRoster,
            'weeklyTaxStatus' => $weeklyTaxStatus,
            'targetTax' => $targetTax,
        ]);
    }

    public function overview(StaticGroup $static): View
    {
        $mains = $this->rosterService->getRosterOverview($static);

        return view('roster.overview', [
            'static' => $static,
            'characters' => $mains,
        ]);
    }

    public function updateParticipation(UpdateRosterRequest $request, StaticGroup $static): RedirectResponse
    {
        $validated = $request->validated();

        $this->rosterService->updateUserParticipation(
            Auth::user(),
            $static,
            $validated['main_character_id'] ?? null,
            $validated['raiding_characters'] ?? [],
            $validated['combat_roles']
        );

        if ($request->has('onboarding')) {
            return redirect()->route('dashboard')->with('success', 'Roster updated successfully!');
        }

        return redirect()->back()->with('success', 'Roster updated successfully!');
    }
}
