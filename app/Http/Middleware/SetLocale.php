<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en';

        if (Auth::check()) {
            $locale = Auth::user()->locale;
        } elseif (session()->has('locale')) {
            $locale = session('locale');
        }

        if (in_array($locale, ['en', 'uk'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
