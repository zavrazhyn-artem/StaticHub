<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Enums\StaticGroup\SyncType;

class SyncIntervalHelper
{
    /**
     * Get the interval in minutes for a given subscription tier and sync type.
     *
     * @param string $tier The subscription tier (free, premium, pro)
     * @param SyncType $syncType The type of sync (bnet, rio, wcl)
     * @return int The interval in minutes
     */
    public static function getIntervalInMinutes(string $tier, SyncType $syncType): int
    {
        $tier = strtolower($tier);
        $syncTypeValue = $syncType->value;

//        $mapping = [
//            'free' => [
//                'bnet' => 1440, // 24h
//                'rio' => 720,   // 12h
//                'wcl' => 360,   // 6h
//            ],
//            'premium' => [
//                'bnet' => 360,  // 6h
//                'rio' => 180,  // 3h
//                'wcl' => 60,   // 1h
//            ],
//            'pro' => [
//                'bnet' => 60,   // 1h
//                'rio' => 30,
//                'wcl' => 15,
//            ],
//        ];

        $mapping = [
            'free' => [
                'bnet' => 1, // 24h
                'rio' => 1,   // 12h
                'wcl' => 1,   // 6h
            ],
            'premium' => [
                'bnet' => 360,  // 6h
                'rio' => 180,  // 3h
                'wcl' => 60,   // 1h
            ],
            'pro' => [
                'bnet' => 60,   // 1h
                'rio' => 30,
                'wcl' => 15,
            ],
        ];

        return $mapping[$tier][$syncTypeValue] ?? $mapping['free'][$syncTypeValue] ?? 1440;
    }
}
