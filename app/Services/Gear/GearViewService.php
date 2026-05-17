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
                'region'         => $char->realm?->region,
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
            ->with(['items.item', 'specialization', 'character'])
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
            'talent_loadout_code' => $list->type === GearList::TYPE_CURRENT
                ? ($list->character?->character_data['talent_loadout_codes_by_spec'][$list->spec_id]
                    ?? $list->character?->character_data['talent_loadout_code']
                    ?? null)
                : $list->talent_loadout_code,
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
     * Stats panel. Every list type uses rating-based aggregation from
     * season_items.real_stats × ilvl-scale factor.
     *
     * For BiS/Custom we backfill missing slots from current so a partial
     * list still aggregates against a full loadout. Each slot in the
     * comparison must satisfy two preconditions:
     *   1. The merged item (list pick or current backfill) is in the
     *      season_items catalog so we can compute its stats.
     *   2. The current item in the same slot is also in the catalog so
     *      there's an honest baseline to subtract.
     * Slots failing either are dropped from BOTH sides — without this
     * we'd report fake −Stamina deltas like "−979" simply because BiS
     * picked an item we don't have stat data for, while current's same-
     * slot item was fully cataloged (and the mirror case for current
     * gaps).
     *
     * Returns null when nothing comparable remains.
     */
    private function resolveStats(GearList $list): ?array
    {
        $mainStat = $this->mainStatForSpec(
            (string) $list->character?->playable_class,
            (string) $list->specialization?->name,
        );

        $current = GearList::query()
            ->findCurrent((int) $list->character_id, (int) $list->spec_id);
        if ($current !== null && $current->id !== $list->id) {
            $current->load('items');
        }

        // One catalog query covers both sides — keeps slot-inclusion
        // decisions consistent across the two aggregates.
        $catalog = $this->loadCatalog(
            collect()
                ->merge($list->items->pluck('item_id'))
                ->merge($current?->items->pluck('item_id') ?? collect())
                ->filter()
                ->unique()
                ->all()
        );

        if ($list->type === GearList::TYPE_CURRENT) {
            $items = ($current ?? $list)->items->filter(fn ($li) => $catalog->has($li->item_id));
            return $items->isEmpty() ? null : $this->aggregateFromItems($items, $mainStat, $catalog);
        }

        $listBySlot    = $list->items->keyBy('slot');
        $currentBySlot = $current ? $current->items->keyBy('slot') : collect();
        $allSlots      = $listBySlot->keys()->merge($currentBySlot->keys())->unique();

        $mergedItems  = collect();
        $currentItems = collect();
        foreach ($allSlots as $slot) {
            if (empty($slot)) continue;
            $listLi    = $listBySlot[$slot] ?? null;
            $currentLi = $currentBySlot[$slot] ?? null;

            // Merged side: prefer list's pick when stat-known; otherwise
            // backfill from current (matches the "honest swap" semantic).
            $picked = ($listLi && $catalog->has($listLi->item_id)) ? $listLi : $currentLi;
            if ($picked === null || ! $catalog->has($picked->item_id)) continue;

            // Current side must also be stat-known for the slot to be
            // comparable — drop slot from BOTH sides otherwise.
            if ($current !== null) {
                if ($currentLi === null || ! $catalog->has($currentLi->item_id)) continue;
                $currentItems->push($currentLi);
            }
            $mergedItems->push($picked);
        }

        if ($mergedItems->isEmpty()) return null;

        $listAggregate = $this->aggregateFromItems($mergedItems, $mainStat, $catalog);
        if ($listAggregate === null) return null;

        if ($current === null || $currentItems->isEmpty()) return $listAggregate;

        $currentAggregate = $this->aggregateFromItems($currentItems, $mainStat, $catalog);
        if ($currentAggregate === null) return $listAggregate;

        return $this->withDeltas($listAggregate, $currentAggregate);
    }

    /**
     * @param array<int,int> $itemIds
     * @return \Illuminate\Support\Collection<int,\App\Models\SeasonItem>
     */
    private function loadCatalog(array $itemIds): \Illuminate\Support\Collection
    {
        if (empty($itemIds)) return collect();
        return \App\Models\SeasonItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id', 'stats', 'real_stats', 'base_item_level', 'is_craftable'])
            ->keyBy('id');
    }

    /**
     * Pair each list stat with its delta against current. The list
     * aggregate already carries the "absolute" value (post-backfill);
     * we just attach a `delta` field so the frontend can render
     * "2400 (+150)" without doing the math again.
     *
     * is_delta_mode=true tells CharacterStatsPanel to render the
     * paired format. Distinct from the legacy `is_delta` flag, which
     * meant "value field IS the delta" — different semantics.
     */
    private function withDeltas(array $list, array $current): array
    {
        $attach = function (array $a, array $b): array {
            return array_map(fn ($x, $y) => [
                'label'   => $x['label'],
                'value'   => (int) ($x['value'] ?? 0),
                'delta'   => (int) ($x['value'] ?? 0) - (int) ($y['value'] ?? 0),
                'is_main' => $x['is_main'] ?? false,
            ], $a, $b);
        };

        return [
            'item_level'       => (int) ($list['item_level'] ?? 0),
            'item_level_delta' => (int) ($list['item_level'] ?? 0) - (int) ($current['item_level'] ?? 0),
            'attributes'       => $attach($list['attributes'] ?? [], $current['attributes'] ?? []),
            'enhancements'     => $attach($list['enhancements'] ?? [], $current['enhancements'] ?? []),
            'is_delta_mode'    => true,
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
    private function aggregateFromItems(\Illuminate\Support\Collection $items, string $mainStat, \Illuminate\Support\Collection $catalog): ?array
    {
        if ($items->isEmpty() || $catalog->isEmpty()) {
            return null;
        }

        $totals = ['intellect' => 0, 'agility' => 0, 'strength' => 0, 'stamina' => 0,
                   'crit' => 0, 'haste' => 0, 'mastery' => 0, 'versatility' => 0];
        $totalIlvl = 0;
        $countedSlots = 0;

        foreach ($items as $li) {
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

            $ilvl = (int) ($li->item_level ?? $baseIlvl);
            $delta15 = ($ilvl - $baseIlvl) / 15;
            // Midnight S1 stat scaling — three separate curves derived
            // empirically from BNet preview_item.stats sampled across the
            // crafted-item quality ladder (Silvermoon Mantle 246→285 in
            // user-supplied tooltips):
            //   primary   ×1.45 over 39 ilvls  → 1.154^(Δ/15)
            //   stamina   ×1.63 over 39 ilvls  → 1.208^(Δ/15)
            //   secondary ×1.22 over 39 ilvls  → 1.084^(Δ/15)  (slower!)
            // Stamina scales faster than combat stats, secondaries scale
            // slower — using one curve for everything (the old behaviour)
            // misreports BiS deltas by 30-40% on big ilvl jumps.
            $factorPrimary   = pow(1.154, $delta15);
            $factorStamina   = pow(1.208, $delta15);
            $factorSecondary = pow(1.084, $delta15);

            $isCraftable = (bool) ($entry->is_craftable ?? false);
            $chosenStats = is_array($li->chosen_stats ?? null) ? $li->chosen_stats : [];

            // Pre-existing concrete secondaries on the item (raid drops,
            // fixed-secondary crafted items like Arcanoweave Cloak) use
            // the secondary curve. For Missive-socket crafted items the
            // sync stripped these from real_stats so this loop adds zero
            // and we project from secondary_per_stat_at_base instead.
            $secondaryKeys = ['crit', 'haste', 'mastery', 'versatility'];
            foreach ($secondaryKeys as $k) {
                $totals[$k] += (int) round((float) ($stats[$k] ?? 0) * $factorSecondary);
            }
            if ($isCraftable && count($chosenStats) === 2) {
                // Both chosen stats receive the SAME value at craft time
                // (it's how missives work), so each gets the full per-
                // stat amount — no /2 split.
                $perStatAtBase = (float) ($stats['secondary_per_stat_at_base'] ?? 0);
                if ($perStatAtBase > 0) {
                    $perStat = (int) round($perStatAtBase * $factorSecondary);
                    foreach ($chosenStats as $picked) {
                        if (in_array($picked, $secondaryKeys, true)) {
                            $totals[$picked] += $perStat;
                        }
                    }
                }
            }

            $totals['stamina'] += (int) round((float) ($stats['stamina'] ?? 0) * $factorStamina);

            // Primary: only the spec's mainstat. Hybrid-primary items have
            // both keys populated; we take only one to avoid double-count.
            $primaryValue = (float) ($stats[$mainStat] ?? 0);
            if ($primaryValue > 0) {
                $totals[$mainStat] += (int) round($primaryValue * $factorPrimary);
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
            'chosen_stats'     => $li->chosen_stats,
            'has_empty_socket' => (bool) $li->has_empty_socket,
            // Surface is_craftable on the slot payload so the GearTab
            // header can show the "stat math is approximate" disclaimer
            // when any slot in a list is a Missive crafted item.
            'is_craftable'     => $this->isItemCraftable((int) $li->item_id),
        ];
    }

    /**
     * Cached lookup: item_id → is_craftable flag from season_items.
     * The DTO loop hits this many times per list payload; the static
     * cache turns N selects into 1 per request lifetime.
     */
    private array $craftableItemIdCache = [];

    private function isItemCraftable(int $itemId): bool
    {
        if ($itemId <= 0) return false;
        if (! array_key_exists($itemId, $this->craftableItemIdCache)) {
            $this->craftableItemIdCache[$itemId] = (bool) \App\Models\SeasonItem::query()
                ->where('id', $itemId)
                ->value('is_craftable');
        }
        return $this->craftableItemIdCache[$itemId];
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
