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
                if (!$request->is('onboarding*') && !$request->is('join/*')) {
                    return redirect()->route('onboarding.index');
                }
            } else {
                // 2. If has static, check if any character is attached to any static they belong to
                // According to task: "Has a Static AND Has a Character attached to that specific Static."
                if (!$userQuery->hasCharacterInAnyStatic($userId)) {
                    if (!$request->is('characters*') && !$request->is('roster*') && !$request->is('onboarding*') && !$request->is('join/*') && !$request->is('logout')) {
                        return redirect()->route('characters.index')->with('warning', 'Please select a character to participate in your static.');
                    }
                }
            }
        }

        return $next($request);
    }
}
