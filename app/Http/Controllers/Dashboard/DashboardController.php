<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;

use App\Models\StaticGroup;
use App\Services\StaticGroup\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function show(StaticGroup $static): View
    {
        $dashboardData = $this->dashboardService->buildDashboardViewPayload($static);

        return view('dashboard.show', [
            'static'        => $static,
            'dashboardData' => $dashboardData,
        ]);
    }
}
