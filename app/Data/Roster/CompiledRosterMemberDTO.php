<?php

declare(strict_types=1);

namespace App\Data\Roster;

/**
 * Flat, immutable output DTO produced by RosterCompilerService.
 * Consumed directly by the Vue frontend roster table.
 *
 * All scalar fields are nullable to reflect that the underlying
 * ServiceRawData columns may be absent for a given character.
 */
final readonly class CompiledRosterMemberDTO
{
    /**
     * @param  ?string              $avatar_url             Character avatar image URL (Blizzard media API).
     * @param  ?string              $class                  Character class name, e.g. "Druid".
     * @param  ?string              $combat_role            Active spec role: TANK | HEALER | DPS.
     * @param  ?float               $equipped_ilvl          Currently equipped average item level.
     * @param  ?float               $mythic_rating          Current season Mythic+ rating (Blizzard score).
     * @param  int                  $weekly_runs_count      Mythic+ keys timed this weekly reset.
     * @param  string[]             $missing_enchants_slots Slot type keys lacking a permanent enchant, e.g. ["CHEST","FEET"].
     * @param  int                  $empty_sockets_count    Gem sockets across all gear containing no gem.
     * @param  array<string,string> $tier_pieces            Tier abbreviation → difficulty label map.
     *                                                       Shape is always: ['H'=>'…','S'=>'…','C'=>'…','G'=>'…','L'=>'…'].
     *                                                       Value is one of 'M','H','N','F', or '-' when the slot is
     *                                                       not occupied by a tier piece.
     * @param  array<string, array<int, array{
     *             name: string,
     *             LFR:  bool,
     *             N:    bool,
     *             H:    bool,
     *             M:    bool,
     *         }>>|null $raids Per-instance boss progress keyed by instance name.
     *                         Null when bnet_raid has never been synced.
     *                         Every boss from the config is always present; boolean flags are
     *                         false when the boss has not been killed on that difficulty.
     * @param  array<int, array{
     *             slot:         string,
     *             id:           int,
     *             name:         string,
     *             ilvl:         int,
     *             quality:      string,
     *             enchant_id:   int|null,
     *             gem_ids:      int[],
     *             socket_count: int,
     *         }>|null $equipment Per-slot compiled items. Null when bnet_equipment is absent.
     *                            Icons are not included — Wowhead tooltip injection handles
     *                            icon display using the item id via data-wowhead attributes.
     */
    public function __construct(
        public ?string $avatar_url,
        public ?string $class,
        public ?string $combat_role,
        public ?float  $equipped_ilvl,
        public ?float  $mythic_rating,
        public int     $weekly_runs_count,
        public array   $missing_enchants_slots,
        public int     $empty_sockets_count,
        public array   $tier_pieces,
        public ?array  $raids,
        public ?array  $equipment,
    ) {}
}
