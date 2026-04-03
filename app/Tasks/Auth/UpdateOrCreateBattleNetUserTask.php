<?php

declare(strict_types=1);

namespace App\Tasks\Auth;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UpdateOrCreateBattleNetUserTask
{
    /**
     * Run the task to update or create a user based on Battle.net social user data.
     *
     * @param SocialiteUser $socialUser
     * @return User
     */
    public function run(SocialiteUser $socialUser): User
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
