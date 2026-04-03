<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class RaiderIoGearItemData extends Data
{
    public function __construct(
        public int $item_id,
        public int $item_level,
        public ?int $enchant = null,
        public string $icon,
        public string $name,
        public int $item_quality,
        public bool $is_legendary,
        public bool $is_azerite_armor,
        /** @var array<RaiderIoAzeritePowerData|null> */
        #[DataCollectionOf(RaiderIoAzeritePowerData::class)]
        public array $azerite_powers,
        public ?RaiderIoItemCorruptionData $corruption = null,
        public array $domination_shards,
        public ?string $tier = null,
        /** @var int[] */
        public array $gems,
        /** @var array<RaiderIoGemDetailData> */
        #[DataCollectionOf(RaiderIoGemDetailData::class)]
        public array $gems_detail,
        /** @var int[] */
        public array $enchants,
        /** @var array<RaiderIoEnchantDetailData> */
        #[DataCollectionOf(RaiderIoEnchantDetailData::class)]
        public array $enchants_detail,
        /** @var int[] */
        public array $bonuses,
    ) {
    }
}
