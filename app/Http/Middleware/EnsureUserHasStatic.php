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
            $user = auth()->user();
            $staticsCount = $user->statics()->count();

            // 1. If 0 statics, force onboarding
            if ($staticsCount === 0) {
                if (!$request->is('onboarding*') && !$request->is('join/*')) {
                    return redirect()->route('onboarding.index');
                }
            } else {
                // 2. If has static, check if any character is attached to any static they belong to
                // According to task: "Has a Static AND Has a Character attached to that specific Static."
                $hasCharacterAttached = \DB::table('character_static')
                    ->whereIn('static_id', $user->statics()->pluck('statics.id'))
                    ->whereIn('character_id', $user->characters()->pluck('id'))
                    ->exists();

                if (!$hasCharacterAttached) {
                    if (!$request->is('characters*') && !$request->is('roster*') && !$request->is('onboarding*') && !$request->is('join/*') && !$request->is('logout')) {
                        return redirect()->route('characters.index')->with('warning', 'Please select a character to participate in your static.');
                    }
                }
            }
        }

        return $next($request);
    }
}
