<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\JsonResponse;

use App\Http\Middleware\HasStatic;
use App\Http\Middleware\EnsureUserHasStatic;
use App\Http\Middleware\LogUserActivity;
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

            // k8s readiness probe: verifies DB + Redis are reachable. Pod is
            // pulled from Service rotation on 503 until the next probe passes.
            // /up (built-in via health: option) remains the liveness probe —
            // it answers as long as PHP itself is alive, regardless of deps.
            Route::get('/healthz', function () {
                $checks = ['db' => false, 'redis' => false];
                try {
                    DB::connection()->getPdo();
                    $checks['db'] = true;
                } catch (\Throwable $e) {
                    $checks['db'] = $e->getMessage();
                }
                try {
                    Cache::store('redis')->put('_healthz', 1, 1);
                    $checks['redis'] = true;
                } catch (\Throwable $e) {
                    $checks['redis'] = $e->getMessage();
                }
                $ok = $checks['db'] === true && $checks['redis'] === true;
                return new JsonResponse(['ok' => $ok, 'checks' => $checks], $ok ? 200 : 503);
            });
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
            'log_user_activity' => LogUserActivity::class,
            'admin_auth' => AdminAuthenticate::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('battlenet.redirect'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
