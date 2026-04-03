<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Models\StaticGroup;

class FetchUserDefaultStaticTask
{
    /**
     * Fetch the default static group for a user.
     *
     * @param int $userId
     * @return StaticGroup
     * @throws \RuntimeException
     */
    public function run(int $userId): StaticGroup
    {
        $static = StaticGroup::query()->firstForUser($userId);

        if (!$static) {
            throw new \RuntimeException('No static group found.');
        }

        return $static;
    }
}
