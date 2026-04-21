<?php

namespace App\Http\Middleware;

use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Ghost\GhostModeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentStatic
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $ghost = app(GhostModeService::class);

            if ($ghost->isActive()) {
                $static = StaticGroup::withoutGlobalScopes()->find($ghost->currentStaticId());
            } else {
                $static = User::query()->firstStaticForUser(Auth::id());
            }

            $route = $request->route();

            if ($static && $route) {
                // Laravel's controller dispatcher resolves typed args by position after
                // `array_values()` on route params. SubstituteBindings runs before this
                // middleware, so URL-derived params (e.g. {report}) are already present.
                // Re-insert them with `static` FIRST so positional matching aligns with
                // controllers that accept `(StaticGroup $static, OtherModel $x)`.
                $existing = $route->parameters();
                foreach (array_keys($existing) as $name) {
                    $route->forgetParameter($name);
                }
                $route->setParameter('static', $static);
                foreach ($existing as $name => $value) {
                    if ($name === 'static') continue;
                    $route->setParameter($name, $value);
                }
            }
        }

        return $next($request);
    }
}
