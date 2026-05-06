<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Services\Ghost\GhostModeService;
use Illuminate\Http\RedirectResponse;

class AdminGhostController extends Controller
{
    public function __construct(private readonly GhostModeService $ghost) {}

    public function enter(int $static): RedirectResponse
    {
        abort_unless($this->ghost->canActivate(), 403, 'Ghost mode not available for this user.');

        $target = StaticGroup::withoutGlobalScopes()->findOrFail($static);

        $this->ghost->enter($target->id);

        return redirect()->route('dashboard');
    }

    public function exit(): RedirectResponse
    {
        $this->ghost->exit();

        return redirect()->route('admin.dashboard');
    }

    /**
     * Activate ghost for the report's static and land directly on the log
     * page. Posted in a target=_blank form from the admin feedback dashboard
     * so triage can happen without leaving the dashboard tab.
     */
    public function enterReport(int $static, int $report): RedirectResponse
    {
        abort_unless($this->ghost->canActivate(), 403, 'Ghost mode not available for this user.');

        $target = StaticGroup::withoutGlobalScopes()->findOrFail($static);

        $this->ghost->enter($target->id);

        return redirect("/logs/{$report}");
    }
}
