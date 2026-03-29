<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\BattleNetAuthService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class BattleNetController extends Controller
{
    /**
     * @var BattleNetAuthService
     */
    protected $authService;

    /**
     * Create a new controller instance.
     *
     * @param  BattleNetAuthService  $authService
     * @return void
     */
    public function __construct(BattleNetAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Redirect the user to the Battle.net authentication page.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect()
    {
        return Socialite::driver('battlenet')
            ->scopes(['wow.profile'])
            ->redirect();
    }

    /**
     * Obtain the user information from Battle.net.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback()
    {
        $socialUser = Socialite::driver('battlenet')->user();

        $user = $this->authService->findOrCreateUser($socialUser);

        // Store token in session for API calls
        session(['battlenet_token' => $socialUser->token]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
