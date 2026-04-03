<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoSpecData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public int $class_id,
        public string $role,
        public bool $is_melee,
        public string $patch,
        public int $ordinal,
    ) {
    }
}
