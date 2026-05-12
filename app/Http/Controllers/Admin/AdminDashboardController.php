<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use App\Models\ApiUsageLog;
use App\Models\Character;
use App\Models\Event;
use App\Models\InviteCode;
use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $metrics = [
            'users' => User::count(),
            'statics' => StaticGroup::withoutGlobalScopes()->count(),
            'characters' => Character::count(),
            'events' => Event::count(),
            'invite_codes_available' => InviteCode::query()->unused()->count(),
            'ai_requests_today' => AiRequestLog::query()->where('created_at', '>=', today())->count(),
            'api_requests_today' => ApiUsageLog::query()->where('created_at', '>=', today())->count(),
            'api_errors_today' => ApiUsageLog::query()->withErrors()->where('created_at', '>=', today())->count(),
        ];

        $toolLinks = [
            ['name' => 'Laravel Pulse', 'url' => '/pulse', 'description' => 'Real-time performance metrics', 'icon' => 'monitor_heart'],
            ['name' => 'Laravel Horizon', 'url' => '/horizon', 'description' => 'Queue monitoring dashboard', 'icon' => 'queue'],
            ['name' => 'GlitchTip', 'url' => 'https://obs.blastr.pro', 'description' => 'Error tracking & alerts', 'icon' => 'bug_report'],
            ['name' => 'Grafana / Logs', 'url' => 'https://obs.blastr.pro/grafana/', 'description' => 'Pod logs via Loki', 'icon' => 'area_chart'],
        ];

        return view('admin.dashboard', compact('metrics', 'toolLinks'));
    }
}
