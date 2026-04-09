<?php

declare(strict_types=1);

namespace App\Data\Roster;

/**
 * Persistent character data that does NOT reset each week.
 * Stored in characters.character_data.
 */
final readonly class CharacterDataDTO
{
    public function __construct(
        // Profile
        public ?string $avatar_url,
        public ?string $class,
        public ?string $combat_role,
        public ?float  $equipped_ilvl,
        public ?float  $highest_ilvl_ever,
        // M+ cumulative
        public ?float  $mythic_rating,
        public int     $season_heroic_dungeons,
        // Gear audit
        public array   $missing_enchants_slots,
        public array   $low_quality_enchants_slots,
        public int     $empty_sockets_count,
        public int     $upgrades_missing,
        public int     $sparks_equipped,
        public array   $tier_pieces,
        public ?array  $tier_ilvls,
        // Equipment
        public ?array  $equipment,
        // Cumulative delves & keys
        public int     $season_delves,
        public int     $coffer_keys,
        // Achievements
        public int     $cutting_edge,
        public int     $ahead_of_the_curve,
        // Collections
        public int     $achievement_points,
        public array   $crests,
        public int     $mounts_count,
        public int     $unique_pets,
        public int     $lvl_25_pets,
        public int     $titles_count,
        // PvP
        public int     $honor_level,
        public int     $honorable_kills,
        public array   $pvp_brackets,
        // Reputation
        public array   $renown,
        // Crafting extras
        public ?array  $embellished_items,
        public ?array  $spark_gear,
    ) {}
}
