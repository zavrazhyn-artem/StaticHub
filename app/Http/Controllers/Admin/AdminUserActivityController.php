<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\UserActivityDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserActivityController extends Controller
{
    public function __construct(private readonly UserActivityDashboardService $dashboard) {}

    public function index(): View
    {
        return view('admin.user-activity.index', $this->dashboard->buildDashboardPayload());
    }

    public function show(User $user, Request $request): View
    {
        return view('admin.user-activity.show', [
            'subject' => $user,
            ...$this->dashboard->buildUserDrilldownPayload($user, $request),
        ]);
    }
}
