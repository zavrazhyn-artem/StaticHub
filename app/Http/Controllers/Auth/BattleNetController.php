<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\BattleNetAuthService;
use App\Services\CharacterSyncService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class BattleNetController extends Controller
{
    /**
     * @var BattleNetAuthService
     */
    protected $authService;

    /**
     * @var CharacterSyncService
     */
    protected $characterSyncService;

    /**
     * Create a new controller instance.
     *
     * @param  BattleNetAuthService  $authService
     * @param  CharacterSyncService  $characterSyncService
     * @return void
     */
    public function __construct(
        BattleNetAuthService $authService,
        CharacterSyncService $characterSyncService
    ) {
        $this->authService = $authService;
        $this->characterSyncService = $characterSyncService;
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

        // Sync characters automatically after login
        try {
            $this->characterSyncService->syncUserCharacters($socialUser->token, $user->id);
        } catch (\Exception $e) {
            // Log error but continue to redirect
            \Log::error('Failed to sync characters on login: ' . $e->getMessage());
        }

        // Check if user has a main character in any static
        $hasMainCharacter = $user->characters()->whereHas('statics', function ($query) {
            $query->where('role', 'main');
        })->exists();

        if ($hasMainCharacter) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('characters.index');
    }
}
