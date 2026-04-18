<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentStatic
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $static = User::query()->firstStaticForUser(Auth::id());

            if ($static) {
                $request->route()?->setParameter('static', $static);
            }
        }

        return $next($request);
    }
}
