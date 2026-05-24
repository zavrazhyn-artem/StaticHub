<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\StaticGroup;
use App\Models\User;

class AiTesterAuthService
{
    /**
     * Timing-safe check of the provided secret against the configured key.
     * Returns false when the gate is unconfigured (empty key) so an empty
     * config can never be matched.
     */
    public function verifyKey(?string $provided): bool
    {
        $expected = (string) config('ai_access.key');

        if ($expected === '' || $provided === null) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Resolve the user the gate should authenticate as.
     * Numeric $as -> user id; non-numeric $as -> battletag; null/empty ->
     * owner of the first static group. Null when no match is found.
     */
    public function resolveUser(?string $as): ?User
    {
        if ($as === null || $as === '') {
            return StaticGroup::query()->firstWithOwner()?->owner;
        }

        // Cap at 18 digits so an oversized numeric string can't overflow the
        // int cast; longer strings fall through to the battletag lookup.
        if (ctype_digit($as) && strlen($as) <= 18) {
            return User::query()->findById((int) $as);
        }

        return User::query()->findByBattletag($as);
    }
}
