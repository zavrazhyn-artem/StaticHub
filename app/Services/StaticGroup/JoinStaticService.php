<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\StaticGroup;
use App\Models\User;

class JoinStaticService
{
    /**
     * Get the data required for the join page.
     */
    public function buildJoinPayload(string $token, int $userId): array
    {
        $static = $this->fetchStaticByToken($token);
        $user = $this->fetchUser($userId);

        return [
            'static' => $static,
            'userCharacters' => $user->characters,
        ];
    }

    /**
     * Process a user joining a static group.
     */
    public function executeJoin(string $token, int $userId): void
    {
        $static = $this->fetchStaticByToken($token);

        $this->assignUserToStatic($static, $userId);
    }

    /**
     * Fetch a static group by its invite token.
     */
    private function fetchStaticByToken(string $token): StaticGroup
    {
        return StaticGroup::query()->findByInviteToken($token);
    }

    /**
     * Fetch a user by ID.
     */
    private function fetchUser(int $userId): User
    {
        return User::findOrFail($userId);
    }

    /**
     * Assign a user to a static group if they are not already a member.
     */
    private function assignUserToStatic(StaticGroup $static, int $userId): void
    {
        if (!$static->hasMember($userId)) {
            $static->addMember($userId);
        }
    }
}
