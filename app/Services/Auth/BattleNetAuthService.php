<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Character\CharacterSyncService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BattleNetAuthService
{
    private const TOKEN_RETRY_ATTEMPTS = 3;
    private const TOKEN_RETRY_DELAY_MS = 1000;

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
        $socialUser = $this->resolveUserWithRetry();

        $user = $this->updateOrCreateFromSocialite($socialUser);

        return [
            'user' => $user,
            'token' => $socialUser->token,
        ];
    }

    /**
     * Resolve the Socialite user with retry logic for Blizzard 429 rate limits.
     */
    private function resolveUserWithRetry(): \Laravel\Socialite\Two\User
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::TOKEN_RETRY_ATTEMPTS; $attempt++) {
            try {
                return Socialite::driver('battlenet')->user();
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() !== 429) {
                    throw $e;
                }

                $lastException = $e;

                Log::warning('Blizzard OAuth 429 rate limit hit, retrying.', [
                    'attempt' => $attempt,
                    'max_attempts' => self::TOKEN_RETRY_ATTEMPTS,
                ]);

                if ($attempt < self::TOKEN_RETRY_ATTEMPTS) {
                    usleep(self::TOKEN_RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException;
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
