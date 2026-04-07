<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;

use App\Models\StaticGroup;
use App\Models\Transaction;
use App\Helpers\CurrencyHelper;
use App\Services\StaticGroup\TreasuryService;
use App\Services\StaticGroup\ConsumableService;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TreasuryController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService,
        protected ConsumableService $consumableService
    ) {}

    public function index(StaticGroup $static): View
    {
        return view('treasury.index', $this->treasuryService->buildTreasuryIndexPayload($static));
    }

    public function history(Request $request, StaticGroup $static): View
    {
        $userId = $request->query('member') ? (int) $request->query('member') : null;

        return view('treasury.history', $this->treasuryService->buildTransactionHistoryPayload($static, $userId));
    }

    public function store(StoreTransactionRequest $request, StaticGroup $static): RedirectResponse
    {
        Gate::authorize('canManageTreasury', $static);

        $validated = $request->validated();
        $amount = CurrencyHelper::goldToCopper($validated['amount']);
        $type = $validated['type'] ?? 'deposit';

        if ($type === 'deposit') {
            $this->treasuryService->createDeposit(
                $static,
                (int) $validated['user_id'],
                $amount,
                $validated['description'] ?? null,
            );
        } else {
            $this->treasuryService->createWithdrawal(
                $static,
                (int) $validated['user_id'],
                $amount,
                $validated['description'] ?? null,
            );
        }

        return redirect()->back()->with('success', 'Transaction recorded successfully.');
    }

    public function update(Request $request, StaticGroup $static, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('canManageTreasury', $static);

        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $this->treasuryService->updateTransactionComment($transaction, $validated['description'] ?? null);

        return redirect()->back()->with('success', 'Transaction description updated.');
    }

    public function updateSettings(Request $request, StaticGroup $static): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('canManageTreasury', $static);

        $validated = $request->validate([
            'weekly_tax_per_player' => 'required|integer|min:0',
        ]);

        $fixedTax = CurrencyHelper::goldToCopper($validated['weekly_tax_per_player']);
        $static->update(['weekly_tax_per_player' => $fixedTax]);

        $economics = $this->consumableService->buildConsumablesPayload($static);
        $realCostPerPlayer = (int) ceil(($economics['grand_total_weekly_cost'] ?? 0) / 20);

        return response()->json(array_merge(
            ['success' => true, 'targetTax' => $fixedTax],
            $this->treasuryService->computeTaxWarning($fixedTax, $realCostPerPlayer),
        ));
    }

    public function updateConsumables(Request $request, StaticGroup $static): \Illuminate\Http\JsonResponse
    {
        Gate::authorize('canManageTreasury', $static);

        $validated = $request->validate([
            'quantities'   => 'required|array',
            'quantities.*' => 'integer|min:0|max:9',
        ]);

        $this->consumableService->updateSettings($static, $validated['quantities']);

        $economics = $this->consumableService->buildConsumablesPayload($static);
        $totalCost    = (int) ($economics['grand_total_weekly_cost'] ?? 0);
        $taxPerRaider = (int) ($economics['guild_tax_per_raider'] ?? 0);
        $fixedTax     = (int) ($static->weekly_tax_per_player ?? 0);

        return response()->json(array_merge(
            ['success' => true, 'taxPerRaider' => $taxPerRaider, 'totalCost' => $totalCost],
            $this->treasuryService->computeTaxWarning($fixedTax, $taxPerRaider),
        ));
    }
}
