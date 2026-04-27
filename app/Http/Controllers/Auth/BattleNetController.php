<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\BattleNetAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class BattleNetController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  BattleNetAuthService  $authService
     */
    public function __construct(
        protected BattleNetAuthService $authService
    ) {}

    /**
     * Redirect the user to the Battle.net authentication page.
     *
     * Accepts an optional `redirect_to` query parameter which is stored in the
     * session so that public-page visitors (e.g. /feedback) end up back where
     * they started instead of being dropped into onboarding.
     *
     * @return SymfonyRedirectResponse
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $redirectTo = $request->query('redirect_to');
        if (is_string($redirectTo) && $this->isSafePublicPath($redirectTo)) {
            $request->session()->put('feedback_return_to', $redirectTo);
        }

        return $this->authService->buildRedirectProvider();
    }

    /**
     * Obtain the user information from Battle.net.
     *
     * @return RedirectResponse
     */
    public function callback(): RedirectResponse
    {
        try {
            $data = $this->authService->executeCallbackProcessing();
        } catch (InvalidStateException) {
            return redirect('/');
        }

        $user = $data['user'];
        $token = $data['token'];

        // Store token in session for API calls
        session(['battlenet_token' => $token]);

        Auth::login($user, remember: true);

        // Sync locale from session if user doesn't have one
        if (session()->has('locale') && (!$user->locale || $user->locale === 'en')) {
            $user->locale = session('locale');
            $user->save();
        }

        // Check if user arrived via an invite link (stored in session or url.intended)
        if (!session('pending_join_token')) {
            $intended = session('url.intended', '');
            if (preg_match('#/join/([A-Za-z0-9]+)#', $intended, $matches)) {
                session(['pending_join_token' => $matches[1]]);
                session()->forget('url.intended');
            }
        }

        if (session('pending_join_token')) {
            return redirect()->route('onboarding.index');
        }

        // If the user started on a public page (feedback/roadmap/etc.), bounce
        // them back there — no forced onboarding for folks who just want to
        // vote or read the roadmap.
        if ($returnTo = session('feedback_return_to')) {
            session()->forget('feedback_return_to');
            if ($this->isSafePublicPath($returnTo)) {
                return redirect($returnTo);
            }
        }

        if (User::query()->hasMainCharacter($user->id)) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.index');
    }

    /**
     * Validate that a path is a same-origin public page we're willing to redirect to.
     */
    private function isSafePublicPath(string $path): bool
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }

        $pathOnly = parse_url($path, PHP_URL_PATH) ?? '';

        return (bool) preg_match('#^/(feedback|roadmap|changelog|help)(?:/|$)#', $pathOnly);
    }
}
