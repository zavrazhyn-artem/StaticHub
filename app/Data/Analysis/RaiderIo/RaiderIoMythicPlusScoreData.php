<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoMythicPlusScoreData extends Data
{
    public function __construct(
        public float $all,
        public float $dps,
        public float $healer,
        public float $tank,
        public ?float $spec_0 = null,
        public ?float $spec_1 = null,
        public ?float $spec_2 = null,
        public ?float $spec_3 = null,
    ) {
    }
}
