<?php

namespace App\Services\Auth;

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class BattleNetAuthService
{
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
