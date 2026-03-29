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
}
