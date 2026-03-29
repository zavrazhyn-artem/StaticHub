<?php

namespace App\Http\Controllers;

use App\Services\ConsumableService;
use Illuminate\Http\Request;

class ConsumablesController extends Controller
{
    protected ConsumableService $consumableService;

    public function __construct(ConsumableService $consumableService)
    {
        $this->consumableService = $consumableService;
    }

    public function index(Request $request)
    {
        // Try to get current user's static if available
        $static = \App\Models\User::firstStaticForUser(\Illuminate\Support\Facades\Auth::id());

        $data = $this->consumableService->getRaidConsumablesData($static);

        return view('consumables.index', array_merge($data, ['static' => $static]));
    }

    public function store(Request $request)
    {
        $static = \App\Models\User::firstStaticForUser(\Illuminate\Support\Facades\Auth::id());

        if (!$static) {
            return back()->with('error', 'No static group found.');
        }

        $validated = $request->validate([
            'quantities' => 'required|array',
            'quantities.*' => 'required|integer|min:0',
        ]);

        $static->update([
            'consumable_settings' => [
                'quantities' => $validated['quantities'],
            ]
        ]);

        return back()->with('success', 'Consumables configuration saved.');
    }
}
