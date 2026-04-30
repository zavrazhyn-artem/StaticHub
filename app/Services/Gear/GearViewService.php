<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Models\Character;
use App\Models\GearList;
use App\Models\GearListItem;
use App\Models\Specialization;



/**
 * Builds Vue-ready payloads for the Gear tab. Pure read service — never
 * mutates. Authorization happens upstream (StaticGroupPermissionPolicy +
 * member-of-static gate).
 *
 * Payload shape:
 * [
 *   'characters' => [{id, name, realm, class, avatar, is_own, specs:[{id,name,role}]}],
 *   'lists'      => [{id, type, name, source, source_url, imported_at, item_count}],
 *   'active'     => null | {id, type, name, slots:{slot=>itemDTO|null}, ...},
 * ]
 */
final class GearViewService
{
    /** Canonical 17-slot order (no shirt/tabard — cosmetic only). */
    public const ALL_SLOTS = [
        'head', 'neck', 'shoulder', 'back', 'chest', 'wrist', 'hands', 'waist',
        'legs', 'feet', 'finger_1', 'finger_2', 'trinket_1', 'trinket_2',
        'main_hand', 'off_hand', 'ranged',
    ];

    public function __construct(
        private readonly CharacterStatsExtractor $statsExtractor,
    ) {}

    /**
     * @param int $staticId — only characters in this static are exposed
     * @param ?int $userId  — used to mark is_own / filter wishlist contexts
     */
    public function buildContextPayload(int $staticId, ?int $userId): array
    {
        if ($userId === null) {
            return [];
        }

        $characters = Character::query()
            ->where('user_id', $userId)
            ->whereHas('statics', fn ($q) => $q->where('statics.id', $staticId))
            ->with([
                'realm:id,name,slug',
                'characterStaticSpecs:id,character_id,spec_id,is_main',
                'characterStaticSpecs.specialization',
            ])
            ->orderBy('name')
            ->get(['id', 'user_id', 'name', 'realm_id', 'playable_class', 'avatar_url']);

        $payload = [];
        foreach ($characters as $char) {
            $specs = $char->characterStaticSpecs
                ->filter(fn ($css) => $css->specialization !== null)
                ->map(fn ($css) => [
                    'id'      => $css->specialization->id,
                    'name'    => $css->specialization->name,
                    'role'    => $css->specialization->role,
                    'is_main' => (bool) $css->is_main,
                ])
                ->values()
                ->all();

            $payload[] = [
                'id'             => $char->id,
                'name'           => $char->name,
                'realm'          => $char->realm?->slug,
                'playable_class' => $char->playable_class,
                'avatar_url'     => $char->avatar_url,
                'is_own'         => $userId !== null && $char->user_id === $userId,
                'specs'          => $specs,
            ];
        }

        return $payload;
    }

    /**
     * Returns lightweight list summaries for sidebar rendering.
     */
    public function listSummaries(int $characterId, int $specId): array
    {
        $lists = GearList::query()
            ->forContext($characterId, $specId)
            ->withCount('items')
            ->orderByRaw("CASE type WHEN 'current' THEN 0 WHEN 'bis' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        return $lists->map(fn (GearList $l) => [
            'id'          => $l->id,
            'type'        => $l->type,
            'name'        => $l->name,
            'source'      => $l->source,
            'source_url'  => $l->source_url,
            'imported_at' => $l->imported_at?->toIso8601String(),
            'item_count'  => $l->items_count,
        ])->all();
    }

    /**
     * Returns a single list with all 17 slots filled (null where empty).
     * Slot order matches ALL_SLOTS for stable UI rendering.
     */
    public function activeList(int $listId): ?array
    {
        $list = GearList::query()
            ->with(['items.item', 'specialization', 'character.serviceRawData'])
            ->find($listId);

        if (! $list) {
            return null;
        }

        $bySlot = $list->items->keyBy('slot');

        // For the Current list we annotate each slot with is_bis_match so the
        // UI can highlight slots that already have the BiS-listed item.
        // Match is by item_id only — same item with a different upgrade
        // tier still counts. Finger 1/2 and Trinket 1/2 cross-match, since
        // the BiS list and the equipped layout may swap their positions.
        $bisBySlot = $list->type === GearList::TYPE_CURRENT
            ? $this->bisItemIdsBySlot($list->character_id, $list->spec_id)
            : [];

        $slots = [];
        foreach (self::ALL_SLOTS as $slot) {
            $li = $bySlot->get($slot);
            if (! $li) {
                $slots[$slot] = null;
                continue;
            }
            $dto = $this->itemDto($li);
            $dto['is_bis_match'] = $this->matchesBis($slot, (int) $li->item_id, $bisBySlot);
            $slots[$slot] = $dto;
        }

        return [
            'id'          => $list->id,
            'type'        => $list->type,
            'name'        => $list->name,
            'source'      => $list->source,
            'source_url'  => $list->source_url,
            'imported_at' => $list->imported_at?->toIso8601String(),
            'spec_id'     => $list->spec_id,
            'spec_name'   => $list->specialization?->name,
            'editable'    => $list->type === GearList::TYPE_CUSTOM,
            'slots'       => $slots,
            'stats'       => $this->resolveStats($list),
        ];
    }

    /**
     * Equivalent slot groups for BiS matching — paired finger/trinket slots
     * are interchangeable (BiS may say finger_1=A but the player wears A in
     * finger_2 because of equip-time order).
     */
    private const SLOT_GROUPS = [
        'finger_1'  => ['finger_1', 'finger_2'],
        'finger_2'  => ['finger_1', 'finger_2'],
        'trinket_1' => ['trinket_1', 'trinket_2'],
        'trinket_2' => ['trinket_1', 'trinket_2'],
    ];

    private function matchesBis(string $slot, int $itemId, array $bisBySlot): bool
    {
        $candidates = self::SLOT_GROUPS[$slot] ?? [$slot];
        foreach ($candidates as $bisSlot) {
            if (isset($bisBySlot[$bisSlot]) && (int) $bisBySlot[$bisSlot] === $itemId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, int>  slot => bis item_id
     */
    private function bisItemIdsBySlot(int $characterId, int $specId): array
    {
        $bis = GearList::query()
            ->forContext($characterId, $specId)
            ->ofType(GearList::TYPE_BIS)
            ->with('items:id,list_id,slot,item_id')
            ->first();

        if (! $bis) {
            return [];
        }

        return $bis->items->mapWithKeys(fn ($i) => [$i->slot => (int) $i->item_id])->all();
    }

    /**
     * Stats panel is populated from bnet_statistics — meaningful only for the
     * Current Equipment list (it reflects the in-game character right now).
     * For BiS/custom, return null and the UI shows a "stats only for current"
     * placeholder.
     */
    private function resolveStats(GearList $list): ?array
    {
        if ($list->type !== GearList::TYPE_CURRENT) {
            return null;
        }

        $raw = $list->character?->serviceRawData;
        if (! $raw) {
            return null;
        }

        return $this->statsExtractor->extract(
            $raw->bnet_statistics,
            $raw->bnet_profile,
            $list->specialization?->role,
        );
    }

    private function itemDto(GearListItem $li): array
    {
        return [
            'item_id'          => $li->item_id,
            'item_name'        => $li->item?->name,
            'item_icon'        => $li->item?->icon,
            'item_quality'     => $li->item?->quality,
            'item_level'       => $li->item_level,
            'enchant_id'       => $li->enchant_id,
            'bonus_ids'        => $li->bonus_ids,
            'gem_ids'          => $li->gem_ids,
            'has_empty_socket' => (bool) $li->has_empty_socket,
        ];
    }

    /**
     * Slots (lower-cased to match our payload) that REQUIRE a permanent
     * enchant in the current season. Sourced from wow_season config so this
     * stays in lock-step with the roster GearAuditService.
     *
     * @return array<int, string>
     */
    public function enchantableSlots(): array
    {
        return array_map(
            fn (string $s) => strtolower($s),
            config('wow_season.enchantable_slots', [])
        );
    }
}
