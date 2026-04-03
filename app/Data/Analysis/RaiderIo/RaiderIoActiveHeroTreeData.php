<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoActiveHeroTreeData extends Data
{
    public function __construct(
        public int $id,
        public int $traitTreeId,
        public string $name,
        public string $slug,
        public string $description,
        public string $iconUrl,
    ) {
    }
}
