<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\BattleNetAuthService;
use Illuminate\Http\RedirectResponse;
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
     * @return SymfonyRedirectResponse
     */
    public function redirect(): SymfonyRedirectResponse
    {
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

        Auth::login($user);

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

        if (User::query()->hasMainCharacter($user->id)) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('onboarding.index');
    }
}
