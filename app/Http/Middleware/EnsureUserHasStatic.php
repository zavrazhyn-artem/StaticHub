<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasStatic
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $userId = auth()->id();
            $userQuery = \App\Models\User::query();

            // 1. If 0 statics, force onboarding
            if (!$userQuery->hasAnyStatic($userId)) {
                // Capture join token from /join/{token} URL before redirecting to onboarding
                if ($request->is('join/*')) {
                    $token = $request->segment(2);
                    session(['pending_join_token' => $token]);
                    return redirect()->route('onboarding.index');
                }

                if (!$request->is('onboarding*') && !$request->is('api/onboarding/*')) {
                    return redirect()->route('onboarding.index');
                }
            } else {
                // 2. If has static, check if any character is attached to any static they belong to
                if (!$userQuery->hasCharacterInAnyStatic($userId)) {
                    if (!$request->is('characters*') && !$request->is('roster*') && !$request->is('onboarding*') && !$request->is('api/onboarding/*') && !$request->is('join/*') && !$request->is('logout')) {
                        return redirect()->route('onboarding.index');
                    }
                }
            }
        }

        return $next($request);
    }
}
