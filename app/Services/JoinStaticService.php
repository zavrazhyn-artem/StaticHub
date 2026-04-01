<?php

namespace App\Services;

use App\Models\StaticGroup;
use App\Models\User;

class JoinStaticService
{
    /**
     * Get the data required for the join page.
     *
     * @param string $token
     * @param int $userId
     * @return array
     */
    public function getJoinPageData(string $token, int $userId): array
    {
        $static = StaticGroup::query()->findByInviteToken($token);
        $user = User::findOrFail($userId);

        return [
            'static' => $static,
            'userCharacters' => $user->characters,
        ];
    }

    /**
     * Process a user joining a static group.
     *
     * @param string $token
     * @param int $userId
     * @return void
     */
    public function join(string $token, int $userId): void
    {
        $static = StaticGroup::query()->findByInviteToken($token);

        if (!$static->members()->where('user_id', $userId)->exists()) {
            $static->members()->attach($userId, ['role' => 'member']);
        }
    }
}
