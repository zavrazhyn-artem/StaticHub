<?php

declare(strict_types=1);

namespace App\Services\Roster;

use App\Models\ServiceRawData;

/**
 * Handles gear audit concerns: enchants, sockets, upgrades, sparks,
 * embellishments, tier pieces, and equipment compilation.
 */
final class GearAuditService
{
    /** @var string[] */
    private readonly array $enchantableSlots;

    /** ilvl (int) => track name (string), sorted highest-key-first. */
    private readonly array $tierThresholds;

    /** Short abbreviation => Blizzard API slot.type. */
    private readonly array $tierSlots;

    /** Blank tier_pieces skeleton. */
    private readonly array $emptyTierPieces;

    /** Spark crafted label for this season. */
    private readonly string $sparkLabel;

    public function __construct()
    {
        $this->enchantableSlots = config('wow_season.enchantable_slots', []);
        $this->sparkLabel       = (string) config('wow_season.spark_label', '');

        $tierSlots             = config('wow_season.tier_slots', []);
        $this->tierSlots       = $tierSlots;
        $this->emptyTierPieces = array_fill_keys(array_keys($tierSlots), '-');

        $thresholds = config('wow_season.tier_ilvl_thresholds', []);
        krsort($thresholds);
        $this->tierThresholds = $thresholds;
    }

    // =========================================================================
    // ENCHANTS
    // =========================================================================

    public function resolveMissingEnchants(array $equippedItems): array
    {
        $bySlot  = $this->indexBySlotType($equippedItems);
        $missing = [];

        foreach ($this->enchantableSlots as $slotType) {
            $item = $bySlot[$slotType] ?? null;
            if ($item === null) {
                continue;
            }

            // Off-hand items that are not weapons can't be enchanted (wowaudit fix)
            if ($slotType === 'OFF_HAND' && !isset($item['weapon'])) {
                continue;
            }

            if (!$this->hasPermanentEnchant($item)) {
                $missing[] = $slotType;
            }
        }

        return $missing;
    }

    public function hasPermanentEnchant(array $item): bool
    {
        foreach ($item['enchantments'] ?? [] as $enchantment) {
            $type = strtoupper((string) ($enchantment['enchantment_slot']['type'] ?? ''));
            if ($type === 'PERMANENT') {
                return true;
            }
        }
        return false;
    }

    // =========================================================================
    // SOCKETS
    // =========================================================================

    public function resolveEmptySockets(array $equippedItems): int
    {
        $empty = 0;
        foreach ($equippedItems as $item) {
            foreach ($item['sockets'] ?? [] as $socket) {
                $socketType = strtoupper((string) ($socket['socket_type']['type'] ?? ''));
                if ($socketType === 'TINKER') {
                    continue;
                }
                if (empty($socket['item'])) {
                    $empty++;
                }
            }
        }
        return $empty;
    }

    // =========================================================================
    // UPGRADES
    // =========================================================================

    public function resolveTotalUpgradesMissing(array $equippedItems): int
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

    public function resolveUpgradeTrack(array $bonuses): ?array
    {
        $tracks = config('wow_season.item_upgrade_tracks', []);

        foreach ($bonuses as $bonusId) {
            if (isset($tracks[$bonusId])) {
                return $tracks[$bonusId];
            }
        }

        return null;
    }

    // =========================================================================
    // SPARK GEAR
    // =========================================================================

    public function resolveSparksEquipped(array $equippedItems): int
    {
        if ($this->sparkLabel === '') {
            return 0;
        }

        $count = 0;
        foreach ($equippedItems as $item) {
            $label = (string) ($item['name_description']['display_string'] ?? '');
            if ($label === $this->sparkLabel) {
                $invType = strtoupper((string) ($item['inventory_type']['type'] ?? ''));
                if ($invType === 'TWOHWEAPON') {
                    $count += 2;
                } else {
                    $count += 1;
                }
            }
        }
        return $count;
    }

    public function resolveSparkGear(array $equippedItems): ?array
    {
        if ($this->sparkLabel === '' || $equippedItems === []) {
            return null;
        }

        $result = [];
        foreach ($equippedItems as $item) {
            $label = (string) ($item['name_description']['display_string'] ?? '');
            if ($label !== $this->sparkLabel) {
                continue;
            }
            $slot = strtolower((string) ($item['slot']['type'] ?? ''));
            $result[$slot] = [
                'ilvl'    => (int) ($item['level']['value'] ?? 0),
                'id'      => (int) ($item['item']['id'] ?? 0),
                'name'    => (string) ($item['name'] ?? ''),
            ];
        }

        return $result !== [] ? $result : null;
    }

    // =========================================================================
    // EMBELLISHED ITEMS
    // =========================================================================

    public function resolveEmbellishedItems(array $equippedItems): ?array
    {
        $result = [];
        foreach ($equippedItems as $item) {
            if (($item['limit_category'] ?? '') !== 'Unique-Equipped: Embellished (2)') {
                continue;
            }
            $entry = [
                'id'         => (int) ($item['item']['id'] ?? 0),
                'ilvl'       => (int) ($item['level']['value'] ?? 0),
                'name'       => (string) ($item['name'] ?? ''),
                'spell_id'   => null,
                'spell_name' => null,
            ];
            if (!empty($item['spells'])) {
                $entry['spell_id']   = (int) ($item['spells'][0]['spell']['id'] ?? 0);
                $entry['spell_name'] = (string) ($item['spells'][0]['spell']['name'] ?? '');
            }
            $result[] = $entry;
        }
        return $result !== [] ? $result : null;
    }

    // =========================================================================
    // TIER PIECES
    // =========================================================================

    public function resolveTierPieces(array $equippedItems): array
    {
        if ($equippedItems === []) {
            return $this->emptyTierPieces;
        }

        $bySlot = $this->indexBySlotType($equippedItems);
        $result = $this->emptyTierPieces;

        foreach ($this->tierSlots as $abbrev => $slotType) {
            $item = $bySlot[$slotType] ?? null;
            if ($item === null || !isset($item['set'])) {
                continue;
            }

            $bonuses = $item['bonus_list'] ?? [];
            $upgrade = $this->resolveUpgradeTrack($bonuses);

            if ($upgrade !== null) {
                $result[$abbrev] = $upgrade['track'];
                continue;
            }

            // Fallback to ilvl-based classification
            $ilvl = (int) ($item['level']['value'] ?? 0);
            $result[$abbrev] = $this->classifyIlvlToTrack($ilvl);
        }

        return $result;
    }

    public function resolveTierIlvls(array $equippedItems): ?array
    {
        if ($equippedItems === []) {
            return null;
        }

        $bySlot = $this->indexBySlotType($equippedItems);
        $result = [];

        foreach ($this->tierSlots as $abbrev => $slotType) {
            $item = $bySlot[$slotType] ?? null;
            if ($item !== null && isset($item['set'])) {
                $result[$abbrev] = (int) ($item['level']['value'] ?? 0);
            } else {
                $result[$abbrev] = 0;
            }
        }

        return $result;
    }

    public function classifyIlvlToTrack(int $ilvl): string
    {
        foreach ($this->tierThresholds as $minIlvl => $track) {
            if ($ilvl >= $minIlvl) {
                return $track;
            }
        }
        return '-';
    }

    // =========================================================================
    // EQUIPMENT — compiled item list
    // =========================================================================

    public function resolveEquipment(array $equippedItems, array $rioItems = [], ?ServiceRawData $rawData = null): ?array
    {
        if ($equippedItems === []) {
            return null;
        }

        $result     = [];
        $rioSlotMap = [
            'HEAD' => 'head', 'NECK' => 'neck', 'SHOULDER' => 'shoulder',
            'CHEST' => 'chest', 'WAIST' => 'waist', 'LEGS' => 'legs',
            'FEET' => 'feet', 'WRIST' => 'wrist', 'HANDS' => 'hands',
            'FINGER_1' => 'finger1', 'FINGER_2' => 'finger2',
            'TRINKET_1' => 'trinket1', 'TRINKET_2' => 'trinket2',
            'BACK' => 'back', 'MAIN_HAND' => 'mainhand', 'OFF_HAND' => 'offhand',
        ];

        foreach ($equippedItems as $item) {
            $slotType = strtoupper((string) ($item['slot']['type'] ?? ''));
            $itemId   = (int) ($item['item']['id'] ?? 0);
            $ilvl     = (int) ($item['level']['value'] ?? 0);

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

    public function extractEnchantId(array $item): ?int
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

    public function extractGemIds(array $item): array
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

    // =========================================================================
    // GENERIC HELPERS
    // =========================================================================

    public function indexBySlotType(array $equippedItems): array
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
