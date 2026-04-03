<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoAzeritePowerData extends Data
{
    public function __construct(
        public ?int $id = null,
        public ?RaiderIoSpellData $spell = null,
        public ?int $tier = null,
    ) {
    }
}
