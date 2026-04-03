<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateConsumablesRequest;
use App\Models\StaticGroup;
use App\Services\ConsumableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConsumablesController extends Controller
{
    /**
     * @param ConsumableService $consumableService
     */
    public function __construct(
        protected ConsumableService $consumableService
    ) {}

    /**
     * Display a listing of the raid consumables.
     *
     * @return View
     */
    public function index(): View
    {
        $static = StaticGroup::query()->firstForUser(Auth::id());

        $data = $this->consumableService->buildConsumablesPayload($static);

        return view('consumables.index', array_merge($data, ['static' => $static]));
    }

    /**
     * Store consumable settings for a static group.
     *
     * @param UpdateConsumablesRequest $request
     * @return RedirectResponse
     */
    public function store(UpdateConsumablesRequest $request): RedirectResponse
    {
        $static = StaticGroup::query()->firstForUser(Auth::id());

        if (!$static) {
            return back()->with('error', 'No static group found.');
        }

        $this->consumableService->updateSettings($static, $request->validated('quantities'));

        return back()->with('success', 'Consumables configuration saved.');
    }
}
