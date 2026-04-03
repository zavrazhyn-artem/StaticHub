<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoGearItemsData extends Data
{
    public function __construct(
        public ?RaiderIoGearItemData $head = null,
        public ?RaiderIoGearItemData $neck = null,
        public ?RaiderIoGearItemData $shoulder = null,
        public ?RaiderIoGearItemData $back = null,
        public ?RaiderIoGearItemData $chest = null,
        public ?RaiderIoGearItemData $waist = null,
        public ?RaiderIoGearItemData $shirt = null,
        public ?RaiderIoGearItemData $wrist = null,
        public ?RaiderIoGearItemData $hands = null,
        public ?RaiderIoGearItemData $legs = null,
        public ?RaiderIoGearItemData $feet = null,
        public ?RaiderIoGearItemData $finger1 = null,
        public ?RaiderIoGearItemData $finger2 = null,
        public ?RaiderIoGearItemData $trinket1 = null,
        public ?RaiderIoGearItemData $trinket2 = null,
        public ?RaiderIoGearItemData $mainhand = null,
        public ?RaiderIoGearItemData $offhand = null,
    ) {
    }
}
