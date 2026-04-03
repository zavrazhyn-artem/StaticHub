<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Data;

class RaiderIoRaidProgressionData extends Data
{
    public function __construct(
        public string $summary,
        public int $total_bosses,
        public int $normal_bosses_killed,
        public int $heroic_bosses_killed,
        public int $mythic_bosses_killed,
        public ?int $expansion_id = null,
    ) {
    }
}
