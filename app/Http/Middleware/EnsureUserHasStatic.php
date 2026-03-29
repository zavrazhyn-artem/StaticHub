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
        if (auth()->check() && auth()->user()->statics()->count() === 0) {
            if (!$request->is('onboarding*') && !$request->is('join/*')) {
                return redirect()->route('onboarding.index');
            }
        }

        return $next($request);
    }
}
