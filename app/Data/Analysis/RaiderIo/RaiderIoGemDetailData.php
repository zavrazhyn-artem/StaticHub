<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoGemDetailData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $icon,
        public ?int $item_level = null,
        public ?int $item_quality = null,
    ) {
    }
}
