<?php

declare(strict_types=1);

namespace App\Data\Analysis;

use Spatie\LaravelData\Data;

class RaiderIoData extends Data
{
    public function __construct(
        public string $name,
        public string $realm,
        public string $region,
        public string $class,
        public string $active_spec_name,
        public string $profile_url,
        public float $mythic_plus_score,
        public int $item_level_equipped,
    ) {
    }

    public static function fromApi(array $data): self
    {
        return new self(
            name: $data['name'],
            realm: $data['realm'],
            region: $data['region'],
            class: $data['class'],
            active_spec_name: $data['active_spec_name'],
            profile_url: $data['profile_url'],
            mythic_plus_score: (float) ($data['mythic_plus_scores_by_season'][0]['scores']['all'] ?? 0),
            item_level_equipped: (int) ($data['gear']['item_level_equipped'] ?? 0),
        );
    }
}
