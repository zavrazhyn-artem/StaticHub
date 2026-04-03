<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoGearData extends Data
{
    public function __construct(
        public string $updated_at,
        public float $item_level_equipped,
        public float $item_level_total,
        public ?RaiderIoGearCorruptionData $corruption = null,
        public ?RaiderIoGearItemsData $items = null,
    ) {
    }
}
