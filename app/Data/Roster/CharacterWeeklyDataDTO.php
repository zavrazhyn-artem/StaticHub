<?php

declare(strict_types=1);

namespace App\Data\Roster;

/**
 * Weekly character data — resets on weekly maintenance.
 * Stored in characters.character_weekly_data and archived to character_weekly_snapshots.
 */
final readonly class CharacterWeeklyDataDTO
{
    public function __construct(
        // M+ weekly
        public int     $weekly_runs_count,
        public int     $week_regular_mythic,
        // Raids — weekly kills per boss per difficulty
        public ?array  $raids,
        // Vault
        public ?array  $vault_weekly_runs,
        public ?array  $vault_world_runs,
        public ?array  $vault_raid_slots,
        // Quests & Delves weekly
        public array   $prey_weekly,
        public array   $weekly_quests,
        public bool    $weekly_event_done,
        public int     $week_delves,
    ) {}
}
