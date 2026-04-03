<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class RaiderIoWeeklyRunData extends Data
{
    public function __construct(
        public string $dungeon,
        public string $short_name,
        public int $mythic_level,
        public string $completed_at,
        public int $clear_time_ms,
        public int $keystone_run_id,
        public int $par_time_ms,
        public int $num_keystone_upgrades,
        public int $map_challenge_mode_id,
        public int $zone_id,
        public int $score,
        /** @var array<RaiderIoAffixData> */
        #[DataCollectionOf(RaiderIoAffixData::class)]
        public array $affixes,
        public string $url,
        public ?RaiderIoSpecData $spec = null,
        public ?string $role = null,
    ) {
    }
}
