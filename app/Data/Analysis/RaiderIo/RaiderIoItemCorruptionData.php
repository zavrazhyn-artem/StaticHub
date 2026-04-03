<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoItemCorruptionData extends Data
{
    public function __construct(
        public int $added,
        public int $resisted,
        public int $total,
    ) {
    }
}
