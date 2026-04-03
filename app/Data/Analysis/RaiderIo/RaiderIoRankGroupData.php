<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoRankGroupData extends Data
{
    public function __construct(
        public int $world,
        public int $region,
        public int $realm,
    ) {
    }
}
