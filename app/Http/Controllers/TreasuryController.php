<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\Transaction;
use App\Helpers\CurrencyHelper;
use App\Services\StaticGroup\TreasuryService;
use App\Services\ConsumableService;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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

    public function store(StoreTransactionRequest $request, StaticGroup $static): RedirectResponse
    {
        $validated = $request->validated();

        // Convert gold from input to copper for database storage
        $validated['amount'] = CurrencyHelper::goldToCopper($validated['amount']);

        $this->treasuryService->executeTransactionCreation($static, $validated);

        return redirect()->back()->with('success', 'Transaction recorded successfully.');
    }

    public function update(Request $request, StaticGroup $static, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'description' => 'nullable|string|max:255',
        ]);

        $transaction->update($validated);

        return redirect()->back()->with('success', 'Transaction description updated.');
    }

    public function updateConsumables(Request $request, StaticGroup $static)
    {
        $validated = $request->validate([
            'quantities' => 'required|array',
            'quantities.*' => 'integer|min:0|max:9',
        ]);

        $this->consumableService->updateSettings($static, $validated['quantities']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('success', 'Consumable settings updated.');
    }
}
