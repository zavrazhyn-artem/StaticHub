<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Helpers\CurrencyHelper;
use App\Services\TreasuryService;
use App\Services\ConsumableService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TreasuryController extends Controller
{
    protected $treasuryService;
    protected $consumableService;

    public function __construct(TreasuryService $treasuryService, ConsumableService $consumableService)
    {
        $this->treasuryService = $treasuryService;
        $this->consumableService = $consumableService;
    }

    public function index(StaticGroup $static)
    {
        $consumablesData = $this->consumableService->getRaidConsumablesData($static);
        $weeklyCost = $consumablesData['grand_total_weekly_cost'] ?? 0;
        $calculatedTax = $consumablesData['guild_tax_per_raider'] ?? 0;

        $reserves = $this->treasuryService->getTotalReserves($static);
        $recentTransactions = $this->treasuryService->getRecentTransactions($static);
        $weeklyStatus = $this->treasuryService->getWeeklyStatus($static, $calculatedTax);

        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);

        return view('treasury.index', [
            'static' => $static,
            'reserves' => $reserves,
            'recentTransactions' => $recentTransactions,
            'weeklyStatus' => $weeklyStatus,
            'weeklyCost' => $weeklyCost,
            'autonomy' => $autonomy,
            'targetTax' => $calculatedTax,
        ]);
    }

    public function store(Request $request, StaticGroup $static)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|integer|min:1',
            'type' => 'required|in:deposit,withdrawal',
            'description' => 'nullable|string|max:255',
        ]);

        // Convert gold from input to copper for database storage
        $validated['amount'] = CurrencyHelper::goldToCopper($validated['amount']);

        $this->treasuryService->addTransaction($static, $validated);

        return redirect()->back()->with('success', 'Transaction recorded successfully.');
    }
}
