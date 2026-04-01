<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class StaticGroupBuilder extends Builder
{
    /**
     * Scope a query to only include statics the user belongs to.
     */
    public function whereUserIsMember(int $userId): self
    {
        return $this->whereHas('members', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        });
    }
    /**
     * Get the first static group for a user.
     *
     * @param int $userId
     * @return \App\Models\StaticGroup|null
     */
    public function firstForUser(int $userId): ?\App\Models\StaticGroup
    {
        return $this->whereUserIsMember($userId)->first();
    }

    /**
     * Find a static group by invite token.
     *
     * @param string $token
     * @return \App\Models\StaticGroup
     */
    public function findByInviteToken(string $token): \App\Models\StaticGroup
    {
        return $this->withoutGlobalScope('member')
            ->where('invite_token', $token)
            ->where('invite_until', '>', now())
            ->firstOrFail();
    }
}
