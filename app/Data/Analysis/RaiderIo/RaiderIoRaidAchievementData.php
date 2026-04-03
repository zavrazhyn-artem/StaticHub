<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoRaidAchievementData extends Data
{
    public function __construct(
        public string $raid,
        public ?string $aotc = null,
        public ?string $cutting_edge = null,
    ) {
    }
}
