<?php

declare(strict_types=1);

namespace App\Enums\StaticGroup;

enum Role: string
{
    case Leader  = 'leader';
    case Officer = 'officer';
    case Member  = 'member';

    /**
     * Returns true for roles that can manage the static (leader or officer).
     */
    public function isManager(): bool
    {
        return match ($this) {
            self::Leader, self::Officer => true,
            self::Member                => false,
        };
    }
}
