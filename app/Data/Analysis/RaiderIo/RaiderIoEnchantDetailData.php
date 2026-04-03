<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoEnchantDetailData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $icon,
        public ?string $description = null,
    ) {
    }
}
