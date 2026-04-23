<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

use App\Http\Middleware\HasStatic;
use App\Http\Middleware\EnsureUserHasStatic;
use App\Http\Middleware\ResolveCurrentStatic;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            $adminDomain = config('admin.domain')
                ?: config('admin.subdomain', 'admin') . '.' . $host;

            Route::domain($adminDomain)
                ->middleware('web')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'has_static' => HasStatic::class,
            'ensure_has_static' => EnsureUserHasStatic::class,
            'resolve_current_static' => ResolveCurrentStatic::class,
            'admin_auth' => AdminAuthenticate::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('battlenet.redirect'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
