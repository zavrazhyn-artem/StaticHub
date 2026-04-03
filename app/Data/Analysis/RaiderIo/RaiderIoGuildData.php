<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoGuildData extends Data
{
    public function __construct(
        public string $name,
        public string $realm,
    ) {
    }
}
