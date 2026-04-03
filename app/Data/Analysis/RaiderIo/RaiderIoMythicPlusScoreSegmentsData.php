<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoMythicPlusScoreSegmentsData extends Data
{
    public function __construct(
        public RaiderIoScoreSegmentData $all,
        public RaiderIoScoreSegmentData $dps,
        public RaiderIoScoreSegmentData $healer,
        public RaiderIoScoreSegmentData $tank,
        public ?RaiderIoScoreSegmentData $spec_0 = null,
        public ?RaiderIoScoreSegmentData $spec_1 = null,
        public ?RaiderIoScoreSegmentData $spec_2 = null,
        public ?RaiderIoScoreSegmentData $spec_3 = null,
    ) {
    }
}
