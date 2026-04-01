<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Redirect to the first static dashboard or onboarding.
     *
     * @return RedirectResponse
     */
    public function showFirst(): RedirectResponse
    {
        $static = User::query()->firstStaticForUser(Auth::id());

        if (!$static) {
            return redirect()->route('onboarding.index');
        }

        return redirect()->route('statics.dashboard', $static->id);
    }

    /**
     * Show the dashboard for a specific static.
     *
     * @param StaticGroup $static
     * @return View
     */
    public function show(StaticGroup $static): View
    {
        $data = $this->dashboardService->getDashboardData($static);

        return view('dashboard.show', $data);
    }
}
