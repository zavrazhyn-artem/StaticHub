<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AiTesterAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiTesterAuthController extends Controller
{
    public function __construct(private readonly AiTesterAuthService $service) {}

    public function login(Request $request): RedirectResponse
    {
        // Defense in depth: never in production, never without a configured key.
        abort_if(app()->isProduction(), 404);
        abort_unless(filled(config('ai_access.key')), 404);

        $provided = $request->header('X-AI-Access') ?? $request->query('key');
        abort_unless(
            $this->service->verifyKey(is_string($provided) ? $provided : null),
            404
        );

        $as = $request->query('as');
        $user = $this->service->resolveUser(is_string($as) ? $as : null);
        abort_if($user === null, 404);

        Auth::login($user);
        $request->session()->put('ai_tester', true);

        $redirect = $request->query('redirect');
        if (
            is_string($redirect)
            && str_starts_with($redirect, '/')
            && ! str_starts_with($redirect, '//')
            && ! str_starts_with($redirect, '/\\')
        ) {
            return redirect($redirect);
        }

        return redirect()->route('dashboard');
    }
}
