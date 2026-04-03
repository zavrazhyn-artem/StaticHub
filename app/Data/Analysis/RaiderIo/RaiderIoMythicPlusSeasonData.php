<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoMythicPlusSeasonData extends Data
{
    public function __construct(
        public string $season,
        public RaiderIoMythicPlusScoreData $scores,
        public ?RaiderIoMythicPlusScoreSegmentsData $segments = null,
    ) {
    }
}
