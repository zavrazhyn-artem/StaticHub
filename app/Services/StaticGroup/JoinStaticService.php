<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\User;

class JoinStaticService
{
    public function __construct(
        private readonly RosterService $rosterService,
    ) {}

    /**
     * Get public preview data for the join landing page (no auth required).
     */
    public function buildPublicPreview(string $token): array
    {
        $static = $this->fetchStaticByToken($token);
        $static->loadCount('members');
        $static->load('owner:id,battletag,avatar');

        return [
            'staticName' => $static->name,
            'region' => strtoupper($static->region),
            'memberCount' => $static->members_count,
            'ownerName' => $static->owner->battletag ?? $static->owner->name,
            'ownerAvatar' => $static->owner->avatar,
            'raidDays' => $static->getRaidDaysArray(),
            'token' => $token,
        ];
    }

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
    public function executeJoin(string $token, int $userId): StaticGroup
    {
        $static = $this->fetchStaticByToken($token);

        $this->assignUserToStatic($static, $userId);
        $this->autoSetSpecsForNewMember($userId, $static->id);

        return $static;
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

    /**
     * Auto-set main spec for the new member's existing characters in this static.
     */
    private function autoSetSpecsForNewMember(int $userId, int $staticId): void
    {
        $characters = Character::query()
            ->where('user_id', $userId)
            ->whereNotNull('active_spec')
            ->get();

        foreach ($characters as $character) {
            $this->rosterService->autoSetMainSpecIfMissing($character, $staticId);
        }
    }
}
