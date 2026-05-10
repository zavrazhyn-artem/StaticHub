<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Jobs\Item\SyncSingleItemMetadataJob;
use App\Models\Character;
use App\Models\GearList;
use App\Models\Item;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes a GearList of type=current for the character's active spec from a
 * Blizzard equipment payload (the `equipped_items` array). Idempotent —
 * replaces the singleton row + items in one transaction.
 *
 * Note: Blizzard returns equipment for the ACTIVE spec only at sync time.
 * Switching specs in-game and re-syncing is the only way to get a current
 * list for the alt spec.
 */
final class EquippedGearSyncService
{
    /**
     * Maps Blizzard slot.type strings to our snake_case slot enum.
     * SHIRT/TABARD are skipped — they're cosmetic-only and not tracked.
     */
    private const SLOT_MAP = [
        'HEAD'      => 'head',
        'NECK'      => 'neck',
        'SHOULDER'  => 'shoulder',
        'BACK'      => 'back',
        'CHEST'     => 'chest',
        'WRIST'     => 'wrist',
        'HANDS'     => 'hands',
        'WAIST'     => 'waist',
        'LEGS'      => 'legs',
        'FEET'      => 'feet',
        'FINGER_1'  => 'finger_1',
        'FINGER_2'  => 'finger_2',
        'TRINKET_1' => 'trinket_1',
        'TRINKET_2' => 'trinket_2',
        'MAIN_HAND' => 'main_hand',
        'OFF_HAND'  => 'off_hand',
        'RANGED'    => 'ranged',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $equippedItems  Blizzard `equipped_items` array.
     */
    public function syncForCharacter(Character $character, array $equippedItems): ?GearList
    {
        if (empty($equippedItems)) {
            return null;
        }

        $equipment = $equippedItems;

        $specId = $this->resolveActiveSpecId($character);
        if (! $specId) {
            Log::info('Skipping current-gear sync — no active spec resolved', [
                'character_id' => $character->id,
            ]);
            return null;
        }

        $items = [];
        $newItemIds = [];
        foreach ($equipment as $position => $entry) {
            $slot = self::SLOT_MAP[$entry['slot']['type'] ?? ''] ?? null;
            if (! $slot) {
                continue;
            }

            $itemId = (int) ($entry['item']['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $enchantments = $entry['enchantments'] ?? [];
            $enchantId = $enchantments[0]['enchantment_id'] ?? null;

            $items[] = [
                'slot'             => $slot,
                'item_id'          => $itemId,
                'item_level'       => (int) ($entry['level']['value'] ?? 0) ?: null,
                'enchant_id'       => is_int($enchantId) && $enchantId > 0 ? $enchantId : null,
                'bonus_ids'        => $entry['bonus_list'] ?? null,
                'gem_ids'          => $this->extractGemIds($entry['sockets'] ?? []),
                'has_empty_socket' => $this->detectEmptySocket($entry['sockets'] ?? []),
                'position'         => $position,
            ];

            $newItemIds[] = $itemId;
        }

        $this->upsertItemPlaceholders($newItemIds, $equipment);

        $list = GearList::query()->upsertSingleton(
            $character,
            $specId,
            GearList::TYPE_CURRENT,
            'Current Equipment',
            GearList::SOURCE_BNET,
            null,
            $items,
        );

        $this->dispatchMetadataResolveJobs($newItemIds);

        return $list;
    }

    /**
     * Upserts placeholder Item rows for any equipped items not yet known so the
     * gear_list_items FK resolves. Names get filled by the existing pipeline.
     *
     * @param array<int, int> $itemIds
     * @param array<int, array<string, mixed>> $bnetEquipment
     */
    private function upsertItemPlaceholders(array $itemIds, array $bnetEquipment): void
    {
        if (empty($itemIds)) {
            return;
        }

        // Bnet payload already has names — use them as a free seed before the
        // Blizzard Game Data API job runs. Less work for the queue.
        $now = now();
        $rows = [];
        foreach ($bnetEquipment as $entry) {
            $id = (int) ($entry['item']['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id'         => $id,
                'name'       => $entry['name'] ?? null,
                'quality'    => $entry['quality']['type'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Quality is item-stable so we DO want it overwritten on the existing
        // row when bnet provides it (a previously-unset row gets it; a row
        // with a different value gets corrected to bnet's truth).
        DB::table('items')->upsert(
            $rows,
            ['id'],
            ['quality', 'updated_at']
        );
    }

    private function dispatchMetadataResolveJobs(array $itemIds): void
    {
        if (empty($itemIds)) {
            return;
        }

        $needsResolve = Item::query()
            ->whereIn('id', $itemIds)
            ->where(function ($q) {
                $q->whereNull('icon')->orWhereNull('name');
            })
            ->pluck('id');

        foreach ($needsResolve as $id) {
            SyncSingleItemMetadataJob::dispatch((int) $id);
        }
    }

    /**
     * @return array<int, int>|null  Gem item IDs in socket order; tinker
     *                               sockets are skipped.
     */
    private function extractGemIds(array $sockets): ?array
    {
        $gems = [];
        foreach ($sockets as $socket) {
            $type = strtoupper((string) ($socket['socket_type']['type'] ?? ''));
            if ($type === 'TINKER') {
                continue;
            }
            $gemId = $socket['item']['id'] ?? null;
            if ($gemId) {
                $gems[] = (int) $gemId;
            }
        }
        return empty($gems) ? null : $gems;
    }

    /**
     * True when at least one non-tinker socket lacks a gem. Mirrors the
     * roster GearAuditService logic.
     */
    private function detectEmptySocket(array $sockets): bool
    {
        foreach ($sockets as $socket) {
            $type = strtoupper((string) ($socket['socket_type']['type'] ?? ''));
            if ($type === 'TINKER') {
                continue;
            }
            if (empty($socket['item'])) {
                return true;
            }
        }
        return false;
    }

    private function resolveActiveSpecId(Character $character): ?int
    {
        if (! $character->active_spec || ! $character->playable_class) {
            return null;
        }

        $spec = Specialization::query()
            ->where('class_name', $character->playable_class)
            ->where('name', $character->active_spec)
            ->first();

        return $spec?->id;
    }
}
