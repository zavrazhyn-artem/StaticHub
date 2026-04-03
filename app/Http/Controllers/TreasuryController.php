<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Helpers\CurrencyHelper;
use App\Services\StaticGroup\TreasuryService;
use App\Http\Requests\StoreTransactionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TreasuryController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService
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
}
