<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class RaiderIoTalentsData extends Data
{
    public function __construct(
        public int $loadout_spec_id,
        public string $loadout_text,
        /** @var array<RaiderIoTalentData>|null */
        #[DataCollectionOf(RaiderIoTalentData::class)]
        #[MapInputName('class_talents')]
        public ?array $class_talents = null,
        /** @var array<RaiderIoTalentData>|null */
        #[DataCollectionOf(RaiderIoTalentData::class)]
        #[MapInputName('spec_talents')]
        public ?array $spec_talents = null,
        /** @var array<RaiderIoTalentData>|null */
        #[DataCollectionOf(RaiderIoTalentData::class)]
        #[MapInputName('hero_talents')]
        public ?array $hero_talents = null,
        public ?RaiderIoActiveHeroTreeData $active_hero_tree = null,
    ) {
    }
}
