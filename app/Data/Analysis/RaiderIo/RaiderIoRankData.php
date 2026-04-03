<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoRankData extends Data
{
    public function __construct(
        public ?RaiderIoRankGroupData $overall = null,
        public ?RaiderIoRankGroupData $class = null,
        public ?RaiderIoRankGroupData $tank = null,
        public ?RaiderIoRankGroupData $healer = null,
        public ?RaiderIoRankGroupData $dps = null,
        public ?RaiderIoRankGroupData $class_tank = null,
        public ?RaiderIoRankGroupData $class_healer = null,
        public ?RaiderIoRankGroupData $class_dps = null,
        public ?RaiderIoRankGroupData $spec_0 = null,
        public ?RaiderIoRankGroupData $spec_1 = null,
        public ?RaiderIoRankGroupData $spec_2 = null,
        public ?RaiderIoRankGroupData $spec_3 = null,
    ) {
    }
}
