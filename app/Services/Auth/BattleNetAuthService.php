<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\CharacterSyncService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BattleNetAuthService
{
    public function __construct(
        protected CharacterSyncService $characterSyncService
    ) {}

    /**
     * Get the redirect response for Battle.net authentication.
     *
     * @return RedirectResponse
     */
    public function getRedirectResponse(): RedirectResponse
    {
        return Socialite::driver('battlenet')
            ->scopes(['wow.profile'])
            ->redirect();
    }

    /**
     * Handle the callback from Battle.net.
     *
     * @return User
     */
    public function handleCallback(): User
    {
        $socialUser = Socialite::driver('battlenet')->user();

        $user = $this->findOrCreateUser($socialUser);

        // Store token in session for API calls
        session(['battlenet_token' => $socialUser->token]);

        Auth::login($user);

        // Sync characters automatically after login
        try {
            $this->characterSyncService->syncUserCharacters($socialUser->token, $user->id);
        } catch (\Exception $e) {
            // Log error but continue to redirect
            Log::error('Failed to sync characters on login: ' . $e->getMessage());
        }

        return $user;
    }

    /**
     * Find or create a user based on Battle.net social user data.
     *
     * @param  SocialiteUser  $socialUser
     * @return User
     */
    public function findOrCreateUser(SocialiteUser $socialUser): User
    {
        return User::updateOrCreate([
            'battlenet_id' => $socialUser->getId(),
        ], [
            'name' => $socialUser->getName() ?? $socialUser->getNickname(),
            'battletag' => $socialUser->getNickname(),
            'avatar' => $socialUser->getAvatar(),
            'email' => $socialUser->getEmail(),
        ]);
    }
}
