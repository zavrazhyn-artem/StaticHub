<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Character\CharacterSyncService;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BattleNetAuthService
{
    public function __construct(
        protected CharacterSyncService $characterSyncService,
    ) {}

    /**
     * Get the redirect response for Battle.net authentication.
     */
    public function buildRedirectProvider(): RedirectResponse
    {
        return Socialite::driver('battlenet')
            ->scopes(['wow.profile'])
            ->redirect();
    }

    /**
     * Handle the callback from Battle.net.
     *
     * @return array{user: User, token: string}
     */
    public function executeCallbackProcessing(): array
    {
        /** @var \Laravel\Socialite\Two\User $socialUser */
        $socialUser = Socialite::driver('battlenet')->user();

        $user = $this->updateOrCreateFromSocialite($socialUser);

        try {
            $this->characterSyncService->syncUserCharacters($socialUser->token, $user->id);
        } catch (\Exception $e) {
            Log::error('Failed to sync characters on login: ' . $e->getMessage());
        }

        return [
            'user' => $user,
            'token' => $socialUser->token,
        ];
    }

    /**
     * Update or create a user based on Battle.net social user data.
     */
    public function updateOrCreateFromSocialite(SocialiteUser $socialUser): User
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
