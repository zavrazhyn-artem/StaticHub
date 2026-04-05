<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\StaticGroup\SyncType;

class SyncIntervalHelper
{
    /**
     * Get the sync interval in minutes for a given subscription tier and sync type.
     * Values are driven by config/sync.php and can be overridden via .env.
     */
    public static function getIntervalInMinutes(string $tier, SyncType $syncType): int
    {
        $tier    = strtolower($tier);
        $service = $syncType->value;

        /** @var array<string, array<string, int>> $intervals */
        $intervals = config('sync.intervals');

        return $intervals[$tier][$service]
            ?? $intervals['free'][$service]
            ?? 360;
    }
}
