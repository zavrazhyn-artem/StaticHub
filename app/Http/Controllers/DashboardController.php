<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\User;
use App\Models\RaidEvent;
use App\Helpers\CurrencyHelper;
use App\Services\ConsumableService;
use App\Services\RosterService;
use App\Services\TreasuryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
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
            return redirect()->route('onboarding.index');
        }
        return redirect()->route('statics.dashboard', $static->id);
    }

    public function show(StaticGroup $static)
    {
        // 1. Next Raid
        $nextRaid = RaidEvent::where('static_id', $static->id)
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->first();

        // 2. Roster Summary
        $users = $this->rosterService->getStaticMembers($static->id);

        $roleCounts = [
            'tank' => 0,
            'heal' => 0,
            'mdps' => 0,
            'rdps' => 0,
        ];

        foreach ($users as $user) {
            if ($user->mainCharacter) {
                $role = $user->mainCharacter->statics->firstWhere('id', $static->id)->pivot->combat_role;
                if (isset($roleCounts[$role])) {
                    $roleCounts[$role]++;
                }
            }
        }

        // 3. Consumables
        $consumables = $this->consumableService->getRaidConsumablesData($static);

        // 4. Weekly Schedule (Next 7 days)
        $weeklySchedule = RaidEvent::where('static_id', $static->id)
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addDays(7))
            ->withCount(['attendances as rsvp_count' => function($query) {
                $query->whereIn('status', ['present', 'late', 'tentative']);
            }])
            ->orderBy('start_time', 'asc')
            ->get();

        // 5. Treasury Data
        $reserves = $this->treasuryService->getTotalReserves($static);
        $weeklyCost = $consumables['grand_total_weekly_cost'] ?? 0;
        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);
        $targetTax = $consumables['guild_tax_per_raider'] ?? 0;
        $weeklyStatus = $this->treasuryService->getWeeklyStatus($static, $targetTax);

        // 6. Sync Data
        $syncData = [
            'bnet' => $static->bnet_last_synced_at ? $static->bnet_last_synced_at->toIso8601String() : null,
            'rio' => $static->rio_last_synced_at ? $static->rio_last_synced_at->toIso8601String() : null,
            'wcl' => $static->wcl_last_synced_at ? $static->wcl_last_synced_at->toIso8601String() : null,
        ];

        return view('dashboard.show', array_merge(
            compact('static', 'nextRaid', 'roleCounts', 'weeklySchedule', 'reserves', 'autonomy', 'weeklyCost', 'targetTax', 'weeklyStatus', 'syncData'),
            $consumables
        ));
    }
}
