<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Admin\UserActivityLogService;
use App\Services\Ghost\GhostModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogUserActivity
{
    public function __construct(
        private readonly UserActivityLogService $logger,
        private readonly GhostModeService $ghost,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! Auth::check()) {
                return;
            }

            if ($this->ghost->isActive()) {
                return;
            }

            if (! $request->isMethod('GET')) {
                return;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return;
            }

            if (str_starts_with($request->path(), 'api/')) {
                return;
            }

            $static = $request->route()?->parameter('static');
            if (! $static || ! is_object($static) || ! isset($static->id)) {
                return;
            }

            $this->logger->record([
                'user_id' => Auth::id(),
                'static_id' => $static->id,
                'route_name' => $request->route()?->getName(),
                'url' => substr($request->fullUrl(), 0, 512),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'created_at' => now()->toDateTimeString(),
            ]);
        } catch (Throwable $e) {
            Log::warning('LogUserActivity middleware failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
