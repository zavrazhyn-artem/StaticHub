<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Helpers\CurrencyHelper;
use App\Services\TreasuryService;
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
        return view('treasury.index', $this->treasuryService->getIndexData($static));
    }

    public function store(StoreTransactionRequest $request, StaticGroup $static): RedirectResponse
    {
        $validated = $request->validated();

        // Convert gold from input to copper for database storage
        $validated['amount'] = CurrencyHelper::goldToCopper($validated['amount']);

        $this->treasuryService->addTransaction($static, $validated);

        return redirect()->back()->with('success', 'Transaction recorded successfully.');
    }
}
