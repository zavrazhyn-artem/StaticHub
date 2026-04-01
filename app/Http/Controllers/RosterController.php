<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\User;
use App\Services\ConsumableService;
use App\Services\RosterService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RosterController extends Controller
{
    protected RosterService $rosterService;
    protected ConsumableService $consumableService;
    protected TreasuryService $treasuryService;

    public function __construct(
        RosterService $rosterService,
        ConsumableService $consumableService,
        TreasuryService $treasuryService
    ) {
        $this->rosterService = $rosterService;
        $this->consumableService = $consumableService;
        $this->treasuryService = $treasuryService;
    }

    public function showFirst()
    {
        $static = User::firstStaticForUser(Auth::id());
        if (!$static) {
            return redirect()->route('statics.setup');
        }
        return redirect()->route('statics.roster', $static->id);
    }

    public function index(StaticGroup $static)
    {
        $consumablesData = $this->consumableService->getRaidConsumablesData($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
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

    public function overview(StaticGroup $static)
    {
        $allCharacters = $static->characters()->get();

        $mains = $allCharacters->where(function ($char) {
            return strtolower($char->pivot->role) === 'main';
        })->values();

        foreach ($mains as $main) {
            $main->alts = $allCharacters->where('user_id', $main->user_id)
                ->where('id', '!=', $main->id)
                ->values();
        }

        return view('roster.overview', [
            'static' => $static,
            'characters' => $mains, // Передаємо мейнів, всередині яких тепер є масив ->alts
        ]);
    }

    public function updateParticipation(Request $request, StaticGroup $static)
    {
        $validated = $request->validate([
            'main_character_id' => 'nullable|integer|exists:characters,id',
            'raiding_characters' => 'nullable|array',
            'raiding_characters.*' => 'integer|exists:characters,id',
            'combat_roles' => 'required|array',
        ]);

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
