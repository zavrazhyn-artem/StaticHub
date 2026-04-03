<?php

declare(strict_types=1);

namespace App\Data\Analysis\RaiderIo;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class RaiderIoProfileData extends Data
{
    public function __construct(
        public string $name,
        public string $race,
        public string $class,
        public string $active_spec_name,
        public string $active_spec_role,
        public string $gender,
        public ?string $faction,
        public int $achievement_points,
        public string $thumbnail_url,
        public string $region,
        public string $realm,
        public string $last_crawled_at,
        public string $profile_url,
        public string $profile_banner,
        public ?array $covenant = null,

        #[DataCollectionOf(RaiderIoMythicPlusSeasonData::class)]
        public DataCollection $mythic_plus_scores_by_season,

        public ?RaiderIoRankData $mythic_plus_ranks = null,
        public ?RaiderIoRankData $previous_mythic_plus_ranks = null,

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_recent_runs = [],

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_best_runs = [],

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_alternate_runs = [],

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_highest_level_runs = [],

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_weekly_highest_level_runs = [],

        /** @var array<RaiderIoWeeklyRunData> */
        #[DataCollectionOf(RaiderIoWeeklyRunData::class)]
        public array $mythic_plus_previous_weekly_highest_level_runs = [],

        public ?RaiderIoGearData $gear = null,

        #[MapInputName('talentLoadout')]
        public ?RaiderIoTalentsData $talents = null,

        /** @var array<string, RaiderIoRaidProgressionData> */
        public array $raid_progression = [],

        /** @var array<RaiderIoRaidAchievementData> */
        #[DataCollectionOf(RaiderIoRaidAchievementData::class)]
        public array $raid_achievement_curve = [],

        public ?RaiderIoGuildData $guild = null,
    ) {
    }
}
