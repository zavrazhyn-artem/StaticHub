<?php

declare(strict_types=1);

namespace App\Enums\StaticGroup;

enum SyncType: string
{
    case BNET = 'bnet';
    case RIO = 'rio';
    case WCL = 'wcl';

    /**
     * Get all sync types.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
