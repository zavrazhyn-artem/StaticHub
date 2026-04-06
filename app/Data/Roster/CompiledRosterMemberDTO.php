<?php

declare(strict_types=1);

namespace App\Data\Roster;

/**
 * Flat, immutable output DTO produced by RosterCompilerService.
 * Consumed directly by the Vue frontend roster table.
 *
 * All scalar fields are nullable to reflect that the underlying
 * ServiceRawData columns may be absent for a given character.
 *
 * Replicates the full data scope of wowaudit core (gear_data, instance_data,
 * external_data, delve_data, quest_data, collection_data, pvp_data, etc.).
 */
final readonly class CompiledRosterMemberDTO
{
    /**
     * @param  ?string  $avatar_url             Character avatar image URL.
     * @param  ?string  $class                  Character class name, e.g. "Druid".
     * @param  ?string  $combat_role            Active spec role: TANK | HEALER | DPS.
     * @param  ?float   $equipped_ilvl          Currently equipped average item level (Blizzard pre-computed).
     * @param  ?float   $highest_ilvl_ever      Max ilvl ever recorded for this character.
     * @param  ?float   $mythic_rating          Current season Mythic+ rating (Blizzard score).
     * @param  int      $weekly_runs_count      Mythic+ keys timed this weekly reset.
     * @param  int      $week_regular_mythic    Regular mythic (non-keystone) dungeon completions this week.
     * @param  int      $season_heroic_dungeons Total heroic dungeon completions this season.
     * @param  string[] $missing_enchants_slots Slot type keys lacking a permanent enchant.
     * @param  int      $empty_sockets_count    Gem sockets across all gear containing no gem.
     * @param  int      $upgrades_missing       Total number of missing upgrades across all equipped items.
     * @param  int      $sparks_equipped        Count of spark-crafted items currently equipped.
     *
     * @param  array<string,string> $tier_pieces  Shape: ['H'=>'…','S'=>'…','C'=>'…','G'=>'…','L'=>'…'].
     *                                             Value is track name (Myth/Hero/Champion/…) or '-'.
     * @param  array<string,int|string>|null $tier_ilvls  Per tier-slot ilvl: ['H'=>int,...].
     *
     * @param  array<string, array<int, array{
     *             name: string, LFR: bool, N: bool, H: bool, M: bool,
     *         }>>|null $raids Per-instance boss progress (cumulative).
     *
     * @param  array<int, array{
     *             slot: string, id: int, name: string, ilvl: int,
     *             quality: string, enchant_id: int|null, gem_ids: int[],
     *             bonus_ids: int[], upgrade: array|null, socket_count: int,
     *             icon: string|null,
     *         }>|null $equipment
     *
     * @param  array<int, array{mythic_level: int, ilvl: int|null, track: string|null}>|null $vault_weekly_runs
     * @param  array<int, array{tier: int, ilvl: int|null, track: string|null}>|null         $vault_world_runs
     * @param  array<int, array{ilvl: int|null, track: string|null, difficulty: string}>|null $vault_raid_slots
     *
     * @param  array{
     *             normal: int, hard: int, nightmare: int,
     *         }                       $prey_weekly        Prey completions this week by difficulty.
     * @param  array<string, bool>     $weekly_quests      Weekly quest statuses (haranir/saltheril/etc).
     * @param  bool                    $weekly_event_done  Weekly PvE event quest completed.
     *
     * @param  int      $season_delves          Total delves completed this season.
     * @param  int      $week_delves            Delves completed this week.
     * @param  int      $coffer_keys            Bountiful coffer keys earned.
     *
     * @param  int      $cutting_edge           Number of Cutting Edge achievements.
     * @param  int      $ahead_of_the_curve     Number of AotC achievements.
     *
     * @param  int      $achievement_points     Total achievement points.
     * @param  array<string,int> $crests        Crest counts: adventurer/veteran/champion/hero/myth.
     * @param  int      $mounts_count           Mounts collected.
     * @param  int      $unique_pets            Unique pets collected.
     * @param  int      $lvl_25_pets            Level 25 pets.
     * @param  int      $titles_count           Titles unlocked.
     *
     * @param  int      $honor_level            PvP honor level.
     * @param  int      $honorable_kills        Total honorable kills.
     * @param  array<string,array{rating:int,season_played:int,week_played:int}> $pvp_brackets
     *
     * @param  array<string,int> $renown        Reputation renown levels.
     *
     * @param  array<int,array{id:int,ilvl:int,name:string,spell_id:int|null,spell_name:string|null}>|null $embellished_items
     * @param  array<string,array{ilvl:int,id:int,name:string}>|null $spark_gear  Spark crafted items by slot.
     */
    public function __construct(
        // Profile
        public ?string $avatar_url,
        public ?string $class,
        public ?string $combat_role,
        public ?float  $equipped_ilvl,
        public ?float  $highest_ilvl_ever,
        // M+
        public ?float  $mythic_rating,
        public int     $weekly_runs_count,
        public int     $week_regular_mythic,
        public int     $season_heroic_dungeons,
        // Gear audit
        public array   $missing_enchants_slots,
        public int     $empty_sockets_count,
        public int     $upgrades_missing,
        public int     $sparks_equipped,
        public array   $tier_pieces,
        public ?array  $tier_ilvls,
        // Raids
        public ?array  $raids,
        // Equipment
        public ?array  $equipment,
        // Vault
        public ?array  $vault_weekly_runs,
        public ?array  $vault_world_runs,
        public ?array  $vault_raid_slots,
        // Quests & Delves
        public array   $prey_weekly,
        public array   $weekly_quests,
        public bool    $weekly_event_done,
        public int     $season_delves,
        public int     $week_delves,
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
