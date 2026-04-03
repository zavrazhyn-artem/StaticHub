<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Character\CharacterSyncService;
use App\Tasks\Auth\UpdateOrCreateBattleNetUserTask;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BattleNetAuthService
{
    public function __construct(
        protected CharacterSyncService $characterSyncService,
        protected UpdateOrCreateBattleNetUserTask $updateOrCreateBattleNetUserTask
    ) {}

    /**
     * Get the redirect response for Battle.net authentication.
     *
     * @return RedirectResponse
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

        $user = $this->updateOrCreateBattleNetUserTask->run($socialUser);

        // Sync characters automatically after login
        try {
            $this->characterSyncService->syncUserCharacters($socialUser->token, $user->id);
        } catch (\Exception $e) {
            // Log error but continue
            Log::error('Failed to sync characters on login: ' . $e->getMessage());
        }

        return [
            'user' => $user,
            'token' => $socialUser->token,
        ];
    }
}
