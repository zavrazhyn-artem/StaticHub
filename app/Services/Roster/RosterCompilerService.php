<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Data\Roster\CompiledRosterMemberDTO;
use App\Models\ServiceRawData;

/**
 * Compiles a ServiceRawData record (raw Blizzard / Raider.io JSON columns)
 * into a single, frontend-ready DTO driven by the wow_season config.
 *
 * No network calls are made here — all data comes from previously fetched
 * and validated JSON stored in the database.
 */
final class RosterCompilerService
{
    /** @var string[] */
    private readonly array $enchantableSlots;

    /**
     * ilvl (int) => difficulty label (string), sorted highest-key-first.
     * @var array<int,string>
     */
    private readonly array $tierThresholds;

    /**
     * Instance name → strictly-ordered boss name list from config.
     * @var array<string, string[]>
     */
    private readonly array $currentRaidInstances;

    /**
     * Short abbreviation => Blizzard API slot.type, e.g. ['H' => 'HEAD'].
     * @var array<string,string>
     */
    private readonly array $tierSlots;

    /** Blank tier_pieces skeleton built from config keys, all defaulting to '-'. */
    private readonly array $emptyTierPieces;

    public function __construct()
    {
        $this->enchantableSlots     = config('wow_season.enchantable_slots', []);
        $this->currentRaidInstances = config('wow_season.current_raid_instances', []);

        /** @var array<string,string> $tierSlots */
        $tierSlots             = config('wow_season.tier_slots', []);
        $this->tierSlots       = $tierSlots;
        $this->emptyTierPieces = array_fill_keys(array_keys($tierSlots), '-');

        // Sort by ilvl key descending so the first match wins when walking the map.
        /** @var array<int,string> $thresholds */
        $thresholds = config('wow_season.tier_ilvl_thresholds', []);
        krsort($thresholds);
        $this->tierThresholds = $thresholds;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function compile(ServiceRawData $rawData): CompiledRosterMemberDTO
    {
        // Treat every column as potentially null — the sync action may have
        // skipped a route due to API failure or schema validation error.
        $profile   = $rawData->bnet_profile   ?? [];
        $equipment = $rawData->bnet_equipment ?? [];
        $media     = $rawData->bnet_media     ?? [];
        $mplus     = $rawData->bnet_mplus     ?? [];
        $raid      = $rawData->bnet_raid      ?? [];

        $equippedItems = $equipment['equipped_items'] ?? [];
        $rio           = $rawData->rio_profile ?? [];
        $rioItems      = $rio['gear']['items'] ?? [];

        return new CompiledRosterMemberDTO(
            avatar_url:             $this->resolveAvatarUrl($media),
            class:                  $this->resolveClass($profile),
            combat_role:            $this->resolveRole($profile),
            equipped_ilvl:          $this->resolveEquippedIlvl($profile),
            mythic_rating:          $this->resolveMythicRating($mplus),
            weekly_runs_count:      $this->resolveWeeklyRunsCount($mplus, $rio),
            missing_enchants_slots: $this->resolveMissingEnchants($equippedItems),
            empty_sockets_count:    $this->resolveEmptySockets($equippedItems),
            upgrades_missing:       $this->resolveTotalUpgradesMissing($equippedItems),
            tier_pieces:            $this->resolveTierPieces($equippedItems),
            raids:                  $this->resolveRaids($raid),
            equipment:              $this->resolveEquipment($equippedItems, $rioItems, $rawData),
        );
    }

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    private function resolveAvatarUrl(array $media): ?string
    {
        foreach ($media['assets'] ?? [] as $asset) {
            if (($asset['key'] ?? '') === 'avatar') {
                $url = (string) ($asset['value'] ?? '');
                return $url !== '' ? $url : null;
            }
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    private function resolveClass(array $profile): ?string
    {
        $name = (string) ($profile['character_class']['name'] ?? $profile['class'] ?? '');

        return $name !== '' ? $name : null;
    }

    private function resolveEquippedIlvl(array $profile): ?float
    {
        $raw = $profile['equipped_item_level'] ?? null;

        return $raw !== null ? (float) $raw : null;
    }

    private function resolveRole(array $profile): ?string
    {
        // Prefer an explicit role field present in enriched or merged profiles.
        $role = $profile['active_spec']['role']['type']
            ?? $profile['active_specialization']['role']['type']
            ?? $profile['specializations']['active_specialization']['role']['type']
            ?? null;

        if ($role !== null) {
            return strtoupper((string) $role);
        }

        // Derive role from the specialisation name as a fallback.
        $specName = strtolower(
            (string) ($profile['active_spec']['name']
                ?? $profile['active_specialization']['name']
                ?? '')
        );

        if ($specName === '') {
            return null;
        }

        return $this->deriveRoleFromSpecName($specName);
    }

    private function deriveRoleFromSpecName(string $specName): string
    {
        static $tanks   = ['protection', 'guardian', 'blood', 'brewmaster', 'vengeance'];
        static $healers = ['restoration', 'holy', 'discipline', 'mistweaver', 'preservation'];

        foreach ($tanks as $keyword) {
            if (str_contains($specName, $keyword)) {
                return 'TANK';
            }
        }

        foreach ($healers as $keyword) {
            if (str_contains($specName, $keyword)) {
                return 'HEALER';
            }
        }

        return 'DPS';
    }

    // -------------------------------------------------------------------------
    // Mythic+
    // -------------------------------------------------------------------------

    private function resolveMythicRating(array $mplus): ?float
    {
        $rating = $mplus['current_mythic_rating']['rating']
            ?? $mplus['mythic_rating']['rating']
            ?? null;

        return $rating !== null ? (float) $rating : null;
    }

    private function resolveWeeklyRunsCount(array $mplus, array $rio = []): int
    {
        // Primary: Raider.io weekly_highest_level_runs — tracks every run
        // completed this reset (including duplicates of the same dungeon),
        // unlike Blizzard's best_runs which de-duplicates by dungeon.
        if (isset($rio['mythic_plus_weekly_highest_level_runs']) && is_array($rio['mythic_plus_weekly_highest_level_runs'])) {
            return count($rio['mythic_plus_weekly_highest_level_runs']);
        }

        // Fallback: Blizzard current_period.best_runs (unique dungeons only,
        // so this under-counts when a dungeon is run more than once).
        if (isset($mplus['current_period']['best_runs']) && is_array($mplus['current_period']['best_runs'])) {
            return count($mplus['current_period']['best_runs']);
        }

        if (isset($mplus['weekly_best_runs']) && is_array($mplus['weekly_best_runs'])) {
            return count($mplus['weekly_best_runs']);
        }

        if (isset($mplus['current_period_best_runs']) && is_array($mplus['current_period_best_runs'])) {
            return count($mplus['current_period_best_runs']);
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // Equipment — enchants
    // -------------------------------------------------------------------------

    /**
     * Returns the list of enchantable slot type keys that are missing a
     * PERMANENT enchantment. Unequipped enchantable slots are excluded
     * (nothing to enchant if the slot is empty).
     *
     * @param  array<int,array<string,mixed>> $equippedItems
     * @return string[]
     */
    private function resolveMissingEnchants(array $equippedItems): array
    {
        $bySlot  = $this->indexBySlotType($equippedItems);
        $missing = [];

        foreach ($this->enchantableSlots as $slotType) {
            $item = $bySlot[$slotType] ?? null;

            if ($item === null) {
                continue; // Slot unequipped — skip rather than flag.
            }

            if (!$this->hasPermanentEnchant($item)) {
                $missing[] = $slotType;
            }
        }

        return $missing;
    }

    /** @param array<string,mixed> $item */
    private function hasPermanentEnchant(array $item): bool
    {
        foreach ($item['enchantments'] ?? [] as $enchantment) {
            $type = strtoupper((string) ($enchantment['enchantment_slot']['type'] ?? ''));

            if ($type === 'PERMANENT') {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Equipment — gems / sockets
    // -------------------------------------------------------------------------

    /**
     * @param  array<int,array<string,mixed>> $equippedItems
     */
    private function resolveEmptySockets(array $equippedItems): int
    {
        $empty = 0;

        foreach ($equippedItems as $item) {
            foreach ($item['sockets'] ?? [] as $socket) {
                // A socket is empty when it has a type but no inserted gem item.
                if (empty($socket['item'])) {
                    $empty++;
                }
            }
        }

        return $empty;
    }

    // -------------------------------------------------------------------------
    // Equipment — upgrades
    // -------------------------------------------------------------------------

    /**
     * Calculates the total number of missing upgrades for the character to reach 100% full BIS.
     *
     * @param  array<int,array<string,mixed>> $equippedItems
     */
    private function resolveTotalUpgradesMissing(array $equippedItems): int
    {
        $totalMissing = 0;

        foreach ($equippedItems as $item) {
            $bonuses = $item['bonus_list'] ?? [];
            $upgrade = $this->resolveUpgradeTrack($bonuses);

            if ($upgrade) {
                $totalMissing += ($upgrade['max'] - $upgrade['level']);
            }
        }

        return $totalMissing;
    }

    // -------------------------------------------------------------------------
    // Equipment — tier pieces
    // -------------------------------------------------------------------------

    /**
     * Returns the tier_pieces map with shape ['H'=>'…','S'=>'…','C'=>'…','G'=>'…','L'=>'…'].
     * Each value is a difficulty label ('M','H','N','F') or '-' when the slot
     * is not occupied by a tier piece.
     *
     * Tier pieces are identified by the presence of a `set` key on the item.
     * Difficulty is determined purely by item level; Catalyst display strings
     * are intentionally ignored.
     *
     * @param  array<int,array<string,mixed>> $equippedItems
     * @return array<string,string>
     */
    private function resolveTierPieces(array $equippedItems): array
    {
        if ($equippedItems === []) {
            return $this->emptyTierPieces;
        }

        $bySlot = $this->indexBySlotType($equippedItems);
        $result = $this->emptyTierPieces;

        $trackAbbreviations = [
            'Myth'       => 'M',
            'Hero'       => 'H',
            'Champion'   => 'C',
            'Veteran'    => 'V',
            'Adventurer' => 'A',
        ];

        foreach ($this->tierSlots as $abbrev => $slotType) {
            $item = $bySlot[$slotType] ?? null;

            if ($item === null || !isset($item['set'])) {
                continue; // Keeps the '-' default.
            }

            // Try resolving via upgrade track first
            $bonuses = $item['bonus_list'] ?? [];
            $upgrade = $this->resolveUpgradeTrack($bonuses);

            if ($upgrade !== null && isset($trackAbbreviations[$upgrade['track']])) {
                $result[$abbrev] = $trackAbbreviations[$upgrade['track']];
                continue;
            }

            // Fallback to ilvl-based classification
            $ilvl = (int) ($item['level']['value'] ?? 0);
            $result[$abbrev] = $this->classifyIlvl($ilvl);
        }

        return $result;
    }

    /**
     * Maps an item level to a difficulty label using the season thresholds.
     * Thresholds are pre-sorted descending, so the first match wins.
     * Returns '-' if ilvl is somehow below the lowest defined threshold.
     */
    private function classifyIlvl(int $ilvl): string
    {
        foreach ($this->tierThresholds as $minIlvl => $label) {
            if ($ilvl >= $minIlvl) {
                return $label;
            }
        }

        return '-';
    }

    // -------------------------------------------------------------------------
    // Raid
    // -------------------------------------------------------------------------

    /**
     * Builds a per-instance boss-progress map for every instance listed in
     * the config. Returns null when the raid column has never been synced.
     *
     * Shape: [ 'Instance Name' => [ {name, LFR, N, H, M}, … ], … ]
     *
     * @param  array<string,mixed> $raid
     * @return array<string, array<int, array{name: string, LFR: bool, N: bool, H: bool, M: bool}>>|null
     */
    private function resolveRaids(array $raid): ?array
    {
        if ($raid === []) {
            return null;
        }

        $instanceModes = $this->indexRaidInstanceModes($raid);
        $result        = [];

        foreach ($this->currentRaidInstances as $instanceName => $configBosses) {
            $modes                  = $instanceModes[$instanceName] ?? [];
            $result[$instanceName]  = $this->compileInstanceBosses($configBosses, $modes);
        }

        return $result;
    }

    /**
     * Builds a kill-flag record for every boss in the config list.
     *
     * The config boss list is the authoritative source for order and membership,
     * so every character always has the same number of entries — even for bosses
     * never attempted. Boolean flags are true when completed_count > 0 on that
     * difficulty in the raw API data.
     *
     * @param  string[]                        $configBosses Ordered boss names from config.
     * @param  array<int, array<string,mixed>> $modes        Raw API difficulty modes for this instance.
     * @return array<int, array{name: string, LFR: bool, N: bool, H: bool, M: bool}>
     */
    private function compileInstanceBosses(array $configBosses, array $modes): array
    {
        static $diffLabel = ['MYTHIC' => 'M', 'HEROIC' => 'H', 'NORMAL' => 'N', 'LFR' => 'LFR'];

        /** @var array<string, array<string, bool>> $kills bossName → [diffKey => true] */
        $kills = [];

        foreach ($modes as $mode) {
            $diffType = strtoupper((string) ($mode['difficulty']['type'] ?? ''));
            $label    = $diffLabel[$diffType] ?? null;

            if ($label === null) {
                continue;
            }

            foreach ($mode['progress']['encounters'] ?? [] as $enc) {
                $name   = (string) ($enc['encounter']['name'] ?? '');
                $killed = ((int) ($enc['completed_count'] ?? 0)) > 0;

                if ($name !== '' && $killed) {
                    $kills[$name][$label] = true;
                }
            }
        }

        $bosses = [];

        foreach ($configBosses as $bossName) {
            $k        = $kills[$bossName] ?? [];
            $bosses[] = [
                'name' => $bossName,
                'LFR'  => $k['LFR'] ?? false,
                'N'    => $k['N']   ?? false,
                'H'    => $k['H']   ?? false,
                'M'    => $k['M']   ?? false,
            ];
        }

        return $bosses;
    }

    /**
     * Flattens expansions → instances → modes into instanceName => modes[].
     *
     * @param  array<string,mixed>            $raid
     * @return array<string,array<int,mixed>>
     */
    private function indexRaidInstanceModes(array $raid): array
    {
        $map = [];

        foreach ($raid['expansions'] ?? [] as $expansion) {
            foreach ($expansion['instances'] ?? [] as $instanceData) {
                $name = (string) ($instanceData['instance']['name'] ?? '');

                if ($name !== '') {
                    $map[$name] = $instanceData['modes'] ?? [];
                }
            }
        }

        return $map;
    }

    // -------------------------------------------------------------------------
    // Equipment — compiled item list
    // -------------------------------------------------------------------------

    /**
     * Compiles equipped_items into a frontend-ready array keyed by position.
     *
     * Items with no item ID or ilvl == 0 (tabards, shirts, cosmetics) are skipped.
     * Returns null when the source array is empty, signalling that no equipment
     * sync has been performed for this character.
     *
     * Icons are sourced from the Raider.IO gear snapshot because Blizzard's
     * equipment endpoint exposes only a numeric media ID, not the icon slug
     * required by the render CDN.
     *
     * @param  array<int, array<string,mixed>> $equippedItems  Blizzard equipped_items array.
     * @param  array<string, array<string,mixed>> $rioItems    RIO gear.items keyed by slot name (e.g. "head", "finger1").
     * @param  ServiceRawData|null                $rawData     Original raw data for deep extraction.
     * @return array<int, array{
     *             slot:         string,
     *             id:           int,
     *             name:         string,
     *             ilvl:         int,
     *             quality:      string,
     *             enchant_id:   int|null,
     *             gem_ids:      int[],
     *             bonus_ids:    int[],
     *             upgrade:      array{track: string, level: int, max: int}|null,
     *             socket_count: int,
     *             icon:         string|null,
     *         }>|null
     */
    private function resolveEquipment(array $equippedItems, array $rioItems = [], ?ServiceRawData $rawData = null): ?array
    {
        if ($equippedItems === []) {
            return null;
        }

        $result     = [];
        $rioSlotMap = [
            'HEAD' => 'head', 'NECK' => 'neck', 'SHOULDER' => 'shoulder', 'CHEST' => 'chest', 'WAIST' => 'waist', 'LEGS' => 'legs', 'FEET' => 'feet', 'WRIST' => 'wrist', 'HANDS' => 'hands', 'FINGER_1' => 'finger1', 'FINGER_2' => 'finger2', 'TRINKET_1' => 'trinket1', 'TRINKET_2' => 'trinket2', 'BACK' => 'back', 'MAIN_HAND' => 'mainhand', 'OFF_HAND' => 'offhand'
        ];

        foreach ($equippedItems as $item) {
            $slotType = strtoupper((string) ($item['slot']['type'] ?? ''));
            $itemId   = (int) ($item['item']['id'] ?? 0);
            $ilvl     = (int) ($item['level']['value'] ?? 0);

            // Skip cosmetic/placeholder items that carry no stats.
            if ($itemId === 0 || $ilvl === 0) {
                continue;
            }

            $rioSlotName = $rioSlotMap[$slotType] ?? null;
            $iconName    = null;

            if ($rioSlotName && $rawData && isset($rawData->rio_profile)) {
                $iconName = data_get($rawData->rio_profile, "gear.items.{$rioSlotName}.icon");
            }

            $bonuses = $item['bonus_list'] ?? [];

            $result[] = [
                'slot'         => $slotType,
                'id'           => $itemId,
                'name'         => (string) ($item['name'] ?? ''),
                'ilvl'         => $ilvl,
                'quality'      => strtoupper((string) ($item['quality']['type'] ?? 'COMMON')),
                'enchant_id'   => $this->extractEnchantId($item),
                'gem_ids'      => $this->extractGemIds($item),
                'bonus_ids'    => $bonuses,
                'upgrade'      => $this->resolveUpgradeTrack($bonuses),
                'socket_count' => count($item['sockets'] ?? []),
                'icon'         => $iconName,
            ];
        }

        return $result !== [] ? $result : null;
    }


    /**
     * Maps a list of bonus IDs to a structured upgrade track.
     *
     * @param  int[] $bonuses
     * @return array{track: string, level: int, max: int}|null
     */
    private function resolveUpgradeTrack(array $bonuses): ?array
    {
        $tracks = config('wow_season.item_upgrade_tracks', []);

        foreach ($bonuses as $bonusId) {
            if (isset($tracks[$bonusId])) {
                return $tracks[$bonusId];
            }
        }

        return null;
    }


    /**
     * Returns the enchantment ID of the first PERMANENT enchant on an item,
     * or null when the item is unenchanted.
     *
     * @param array<string,mixed> $item
     */
    private function extractEnchantId(array $item): ?int
    {
        foreach ($item['enchantments'] ?? [] as $enchantment) {
            $type = strtoupper((string) ($enchantment['enchantment_slot']['type'] ?? ''));

            if ($type === 'PERMANENT') {
                $id = $enchantment['enchantment_id'] ?? null;
                return $id !== null ? (int) $id : null;
            }
        }

        return null;
    }

    /**
     * Returns an array of item IDs for every gem currently socketed in an item.
     * Empty sockets (socket exists but no gem) are omitted.
     *
     * @param  array<string,mixed> $item
     * @return int[]
     */
    private function extractGemIds(array $item): array
    {
        $ids = [];

        foreach ($item['sockets'] ?? [] as $socket) {
            $gemId = $socket['item']['id'] ?? null;
            if ($gemId !== null) {
                $ids[] = (int) $gemId;
            }
        }

        return $ids;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Indexes equipped_items by slot.type for O(1) lookup throughout the compiler.
     *
     * @param  array<int,array<string,mixed>>    $equippedItems
     * @return array<string,array<string,mixed>>
     */
    private function indexBySlotType(array $equippedItems): array
    {
        $indexed = [];

        foreach ($equippedItems as $item) {
            $slotType = strtoupper((string) ($item['slot']['type'] ?? ''));

            if ($slotType !== '') {
                $indexed[$slotType] = $item;
            }
        }

        return $indexed;
    }
}
