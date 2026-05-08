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

    /**
     * Blizzard class id by playable_class label. Forwarded to Wowhead's
     * tooltip endpoint via cl=N (and spec=N) so hybrid stat lines like
     * "+93 Agility or Intellect" resolve to the spec's actual mainstat
     * and tier-set bonuses render under the right class.
     */
    public const CLASS_IDS = [
        'Warrior'      => 1,
        'Paladin'      => 2,
        'Hunter'       => 3,
        'Rogue'        => 4,
        'Priest'       => 5,
        'Death Knight' => 6,
        'Shaman'       => 7,
        'Mage'         => 8,
        'Warlock'      => 9,
        'Monk'         => 10,
        'Druid'        => 11,
        'Demon Hunter' => 12,
        'Evoker'       => 13,
    ];

    public static function classId(?string $className): ?int
    {
        return self::CLASS_IDS[$className ?? ''] ?? null;
    }

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
                    'id'       => $css->specialization->id,
                    'name'     => $css->specialization->name,
                    'role'     => $css->specialization->role,
                    'icon_url' => $css->specialization->icon_url,
                    'is_main'  => (bool) $css->is_main,
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
            'class_name'  => $list->character?->playable_class,
            'class_id'    => self::classId($list->character?->playable_class),
            // Custom AND BiS lists can be edited slot-by-slot via the picker.
            // Current is read-only — bnet sync owns it.
            'editable'    => in_array($list->type, [GearList::TYPE_CUSTOM, GearList::TYPE_BIS], true),
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
     * Stats panel:
     *  - Current list  → real in-game stats from bnet_statistics (% values
     *                    that mirror what the player sees in-game)
     *  - BiS / Custom  → DELTA against the current list, computed by
     *                    aggregating both lists' real_stats (rating units)
     *                    and subtracting. We pick deltas over absolute totals
     *                    so the user immediately sees how a build differs
     *                    from what's equipped right now — the absolute number
     *                    "+150 Haste" is more actionable than "2400 Haste"
     *                    when the question is "should I run this set tonight".
     *
     * If there's no current list to baseline against (BNet not synced yet,
     * spec was just created, etc), we fall back to the absolute aggregate
     * so the user still sees something meaningful.
     */
    private function resolveStats(GearList $list): ?array
    {
        if ($list->type === GearList::TYPE_CURRENT) {
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

        $listAggregate = $this->aggregateSeasonStats($list);
        if ($listAggregate === null) {
            return null;
        }

        $current = GearList::query()
            ->findCurrent((int) $list->character_id, (int) $list->spec_id);
        if ($current === null) {
            return $listAggregate;
        }
        $current->load('items');
        $currentAggregate = $this->aggregateSeasonStats($current);
        if ($currentAggregate === null) {
            return $listAggregate;
        }

        return $this->subtractAggregates($listAggregate, $currentAggregate);
    }

    /**
     * Element-wise list - current. Both inputs share the same schema (the
     * one returned by aggregateSeasonStats), so we can zip attributes /
     * enhancements pairwise without remapping by label.
     *
     * is_delta=true tells CharacterStatsPanel to render with sign + colour
     * cues (green/red/grey for positive/negative/zero).
     */
    private function subtractAggregates(array $list, array $current): array
    {
        $sub = function (array $a, array $b): array {
            return array_map(fn ($x, $y) => [
                'label'   => $x['label'],
                'value'   => (int) ($x['value'] ?? 0) - (int) ($y['value'] ?? 0),
                'is_main' => $x['is_main'] ?? false,
            ], $a, $b);
        };

        return [
            'item_level'   => (int) ($list['item_level'] ?? 0) - (int) ($current['item_level'] ?? 0),
            'attributes'   => $sub($list['attributes'] ?? [], $current['attributes'] ?? []),
            'enhancements' => $sub($list['enhancements'] ?? [], $current['enhancements'] ?? []),
            'is_delta'     => true,
        ];
    }

    /**
     * Sum each picked item's real_stats (Wowhead-authoritative, anchored at
     * Myth 6/6 = 289 ilvl) × ilvl scale factor and shape the result like
     * CharacterStatsExtractor's output so CharacterStatsPanel renders it
     * without a special case.
     *
     * Hybrid primaries (Druid/Monk "Agility or Intellect" gear) are stored
     * with both keys at full value in real_stats — the spec's mainstat
     * decides which one to add so we don't double-count.
     *
     * Returns null when no slots have items mapped to season_items.
     */
    private function aggregateSeasonStats(GearList $list): ?array
    {
        $itemIds = $list->items->pluck('item_id')->filter()->unique()->all();
        if (empty($itemIds)) {
            return null;
        }

        $catalog = \App\Models\SeasonItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'stats', 'real_stats', 'base_item_level'])
            ->keyBy('id');

        if ($catalog->isEmpty()) {
            return null;
        }

        $mainStat = $this->mainStatForSpec(
            (string) $list->character?->playable_class,
            (string) $list->specialization?->name,
        );

        $totals = ['intellect' => 0, 'agility' => 0, 'strength' => 0, 'stamina' => 0,
                   'crit' => 0, 'haste' => 0, 'mastery' => 0, 'versatility' => 0];
        $totalIlvl = 0;
        $countedSlots = 0;

        foreach ($list->items as $li) {
            $entry = $catalog->get($li->item_id);
            if (! $entry) continue;

            $stats = is_array($entry->real_stats) ? $entry->real_stats : null;
            $baseIlvl = (int) ($entry->base_item_level ?? 289);

            // Fallback to liquidarmory's allocation values if backfill hasn't
            // run yet for this row (gives directional info, not exact totals).
            if ($stats === null && is_array($entry->stats)) {
                $stats = $entry->stats;
                $baseIlvl = 289;
            }
            if (! is_array($stats)) continue;

            $ilvl   = (int) ($li->item_level ?? $baseIlvl);
            $factor = pow(1.083, ($ilvl - $baseIlvl) / 15);

            // Stamina + secondaries: always sum.
            foreach (['stamina', 'crit', 'haste', 'mastery', 'versatility'] as $k) {
                $totals[$k] += (int) round((float) ($stats[$k] ?? 0) * $factor);
            }
            // Primary: only the spec's mainstat. Hybrid-primary items have
            // both keys populated; we take only one to avoid double-count.
            $primaryValue = (float) ($stats[$mainStat] ?? 0);
            if ($primaryValue > 0) {
                $totals[$mainStat] += (int) round($primaryValue * $factor);
            }

            $totalIlvl += $ilvl;
            $countedSlots++;
        }

        if ($countedSlots === 0) {
            return null;
        }

        return [
            'item_level'   => (int) round($totalIlvl / $countedSlots),
            'attributes'   => [
                ['label' => 'Stamina',   'value' => $totals['stamina'],   'is_main' => false],
                ['label' => 'Strength',  'value' => $totals['strength'],  'is_main' => $mainStat === 'strength'],
                ['label' => 'Agility',   'value' => $totals['agility'],   'is_main' => $mainStat === 'agility'],
                ['label' => 'Intellect', 'value' => $totals['intellect'], 'is_main' => $mainStat === 'intellect'],
            ],
            'enhancements' => [
                ['label' => 'Crit',         'value' => $totals['crit']],
                ['label' => 'Haste',        'value' => $totals['haste']],
                ['label' => 'Mastery',      'value' => $totals['mastery']],
                ['label' => 'Versatility',  'value' => $totals['versatility']],
            ],
            'is_set_total' => true,
        ];
    }

    /**
     * Mainstat for a (class, spec) — covers the special cases where a hybrid
     * class's caster specs use intellect even though the rest of the class
     * uses agility (Druid Balance/Resto, Shaman Ele/Resto, Monk Mistweaver).
     * Evoker is all-intellect; the dedicated str/int classes are obvious.
     */
    private function mainStatForSpec(string $class, string $specName): string
    {
        if (in_array($class, ['Warrior', 'Paladin', 'Death Knight'], true)) return 'strength';
        if (in_array($class, ['Mage', 'Warlock', 'Priest', 'Evoker'], true)) return 'intellect';

        // Hybrid classes — depends on the spec.
        $intSpecs = [
            'Druid'  => ['Balance', 'Restoration'],
            'Monk'   => ['Mistweaver'],
            'Shaman' => ['Elemental', 'Restoration'],
            'Paladin' => ['Holy'], // safety net (already int via class branch above)
        ];
        if (isset($intSpecs[$class]) && in_array($specName, $intSpecs[$class], true)) {
            return 'intellect';
        }

        return 'agility';
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
