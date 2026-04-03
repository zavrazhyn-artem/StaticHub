<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoAffixData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $icon,
        public string $icon_url,
        public string $wowhead_url,
    ) {
    }
}
