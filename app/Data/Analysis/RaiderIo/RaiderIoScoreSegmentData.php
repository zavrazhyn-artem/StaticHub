<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoScoreSegmentData extends Data
{
    public function __construct(
        public float $score,
        public string $color,
    ) {
    }
}
