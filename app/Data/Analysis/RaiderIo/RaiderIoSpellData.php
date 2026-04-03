<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoSpellData extends Data
{
    public function __construct(
        public int $id,
        public int $school,
        public string $icon,
        public string $name,
        public ?string $rank = null,
    ) {
    }
}
