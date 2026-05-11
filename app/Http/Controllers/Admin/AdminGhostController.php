<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticGroup;
use App\Services\Ghost\GhostModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AdminGhostController extends Controller
{
    public function __construct(private readonly GhostModeService $ghost) {}

    public function enter(int $static): RedirectResponse
    {
        $this->ensureGhostAuth();

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
        $this->ensureGhostAuth();

        $target = StaticGroup::withoutGlobalScopes()->findOrFail($static);

        $this->ghost->enter($target->id);

        return redirect("/logs/{$report}");
    }

    /**
     * Auto-login as the configured ghost user when an admin (already past
     * admin_auth middleware) hasn't completed a Bnet OAuth flow. Trust the
     * admin session — the admin_auth middleware already verified the request.
     */
    private function ensureGhostAuth(): void
    {
        $ghostUserId = (int) config('ghost.user_id');

        abort_if($ghostUserId <= 0, 403, 'Ghost mode requires GHOST_USER_ID to be configured.');

        if (! Auth::check()) {
            $loggedIn = Auth::loginUsingId($ghostUserId);
            abort_if($loggedIn === false, 403, "Ghost mode user (id={$ghostUserId}) does not exist in the database.");
        }
    }
}
