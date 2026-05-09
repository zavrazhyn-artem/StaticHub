<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Exceptions\GearListException;
use App\Jobs\Item\SyncSingleItemMetadataJob;
use App\Models\Character;
use App\Models\GearList;
use App\Models\GearListItem;
use App\Models\Item;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns the lifecycle of GearList records. Authorization (own-character checks)
 * happens here so any caller — controllers, jobs, console commands — gets the
 * same enforcement.
 */
final class GearListService
{
    public function __construct(
        private readonly IcyVeinsBisImportService $icyVeins,
        private readonly SimcStringParserService $simc,
        private readonly EquippedGearSyncService $equippedSync,
    ) {}

    /**
     * Ensures the (character, spec) has the two singleton lists the UI relies
     * on: a Current list (synced from bnet equipment when possible, otherwise
     * an empty placeholder for alt specs we have no equipment payload for) and
     * an empty BiS list ready for an icy-veins import. Idempotent: if either
     * list already exists, it's left untouched.
     *
     * Called from the summaries endpoint so opening Gear for a freshly added
     * character "just works" without any explicit init step.
     */
    public function ensureInitialLists(User $user, int $characterId, int $specId): void
    {
        $character = $this->ensureOwned($user, $characterId);
        $this->ensureSpecMatchesClass($specId, $character);

        $hasCurrent = GearList::query()
            ->forContext($characterId, $specId)
            ->ofType(GearList::TYPE_CURRENT)
            ->exists();

        if (! $hasCurrent) {
            // Try to populate from cached bnet equipment first — this only
            // succeeds when the character's currently-active spec in-game
            // matches the requested spec (Blizzard returns equipment for the
            // active spec only). Falls through to the empty placeholder
            // below for alt specs.
            $this->equippedSync->syncForCharacter($character);

            $hasCurrent = GearList::query()
                ->forContext($characterId, $specId)
                ->ofType(GearList::TYPE_CURRENT)
                ->exists();

            if (! $hasCurrent) {
                GearList::query()->create([
                    'character_id' => $characterId,
                    'spec_id'      => $specId,
                    'type'         => GearList::TYPE_CURRENT,
                    'name'         => 'Current Equipment',
                    'source'       => GearList::SOURCE_BNET,
                    'source_url'   => null,
                    'imported_at'  => null,
                ]);
            }
        }

        $hasBis = GearList::query()
            ->forContext($characterId, $specId)
            ->ofType(GearList::TYPE_BIS)
            ->exists();

        if (! $hasBis) {
            GearList::query()->create([
                'character_id' => $characterId,
                'spec_id'      => $specId,
                'type'         => GearList::TYPE_BIS,
                'name'         => 'Best in Slot',
                'source'       => GearList::SOURCE_MANUAL,
                'source_url'   => null,
                'imported_at'  => null,
            ]);
        }
    }

    public function createCustom(User $user, int $characterId, int $specId, string $name): GearList
    {
        $character = $this->ensureOwned($user, $characterId);
        $this->ensureSpecMatchesClass($specId, $character);

        $existing = GearList::query()->customCount($characterId, $specId);
        if ($existing >= GearList::CUSTOM_LIMIT_PER_CONTEXT) {
            throw GearListException::customLimitReached(GearList::CUSTOM_LIMIT_PER_CONTEXT);
        }

        return GearList::query()->create([
            'character_id' => $characterId,
            'spec_id'      => $specId,
            'type'         => GearList::TYPE_CUSTOM,
            'name'         => trim($name) !== '' ? trim($name) : 'New List',
            'source'       => GearList::SOURCE_MANUAL,
            'source_url'   => null,
            'imported_at'  => null,
        ]);
    }

    public function rename(User $user, int $listId, string $newName): GearList
    {
        $list = $this->loadOwnedList($user, $listId);
        if ($list->type !== GearList::TYPE_CUSTOM) {
            throw GearListException::cannotMutateCurrent();
        }
        $list->update(['name' => trim($newName) !== '' ? trim($newName) : $list->name]);
        return $list->fresh();
    }

    public function delete(User $user, int $listId): void
    {
        $list = $this->loadOwnedList($user, $listId);
        if ($list->type === GearList::TYPE_CURRENT) {
            throw GearListException::cannotMutateCurrent();
        }
        if ($list->type === GearList::TYPE_BIS) {
            throw GearListException::cannotDeleteBis();
        }
        $list->delete();
    }

    /**
     * Set or unset a single slot in a Custom or BiS list. Pass $itemId=null
     * to clear. The Current list is read-only — it's auto-managed by the
     * bnet sync pipeline and editing it directly would race that sync.
     *
     * If the item is in the season catalog and its inventory_type is
     * Two-Hand, the off_hand slot is auto-cleared (and vice versa for any
     * two-hand item already equipped when picking a one-hand for off_hand).
     */
    public function setSlot(
        User $user,
        int $listId,
        string $slot,
        ?int $itemId,
        ?int $itemLevel = null,
        ?int $enchantId = null,
        ?array $bonusIds = null,
        ?array $chosenStats = null,
    ): GearList {
        $list = $this->loadOwnedList($user, $listId);
        if ($list->type === GearList::TYPE_CURRENT) {
            throw GearListException::cannotMutateCurrent();
        }

        if ($itemId === null) {
            GearListItem::query()->where('list_id', $list->id)->where('slot', $slot)->delete();
            return $list->fresh('items');
        }

        // Pull season-catalog metadata so the items-table placeholder gets a
        // proper name + icon (instead of NULL) and we can detect Two-Hand for
        // the auto-clear of off_hand.
        $seasonItem = \App\Models\SeasonItem::query()->find($itemId);

        $this->upsertItemPlaceholder(
            $itemId,
            $seasonItem?->name,
            $seasonItem?->icon,
        );

        // chosen_stats is only meaningful for crafted items; clear it for
        // non-craftables so a stale pick doesn't leak across an item swap.
        $persistedChosenStats = $seasonItem?->is_craftable ? $chosenStats : null;

        // For crafted items, stamp the season's "Radiance Crafted" bonus
        // (always) plus the matching missive bonus_id (when a pair was
        // chosen). Wowhead resolves both in tooltip rendering — green
        // "Radiance Crafted" caption and concrete secondary names.
        $bonusIdsToStore = is_array($bonusIds) ? array_values($bonusIds) : [];
        if ($seasonItem?->is_craftable) {
            $seasonBonus = (int) config('wow_season.crafted_season_bonus_id', 0);
            if ($seasonBonus > 0 && ! in_array($seasonBonus, $bonusIdsToStore, true)) {
                $bonusIdsToStore[] = $seasonBonus;
            }
        }
        if ($persistedChosenStats !== null && count($persistedChosenStats) === 2) {
            $sorted = $persistedChosenStats;
            sort($sorted);
            $key = implode('_', $sorted);
            $missiveBonus = (int) config("wow_season.crafted_missive_bonus_ids.{$key}", 0);
            if ($missiveBonus > 0 && ! in_array($missiveBonus, $bonusIdsToStore, true)) {
                $bonusIdsToStore[] = $missiveBonus;
            }
        }

        GearListItem::query()->updateOrCreate(
            ['list_id' => $list->id, 'slot' => $slot],
            [
                'item_id'      => $itemId,
                'item_level'   => $itemLevel,
                'enchant_id'   => ($enchantId ?? 0) > 0 ? $enchantId : null,
                'bonus_ids'    => empty($bonusIdsToStore) ? null : $bonusIdsToStore,
                'chosen_stats' => $persistedChosenStats,
            ],
        );

        // Two-hand picked into main_hand → off_hand can't co-exist; clear it.
        if ($slot === 'main_hand' && $seasonItem?->inventory_type === 'Two-Hand') {
            GearListItem::query()->where('list_id', $list->id)->where('slot', 'off_hand')->delete();
        }
        // One-hand/off-hand/shield picked into off_hand → if main_hand currently
        // holds a Two-Hand it would conflict; clear it.
        if ($slot === 'off_hand') {
            $mainHand = GearListItem::query()
                ->where('list_id', $list->id)
                ->where('slot', 'main_hand')
                ->first();
            if ($mainHand) {
                $mhItem = \App\Models\SeasonItem::query()->find($mainHand->item_id);
                if ($mhItem?->inventory_type === 'Two-Hand') {
                    $mainHand->delete();
                }
            }
        }

        SyncSingleItemMetadataJob::dispatch($itemId);

        return $list->fresh('items');
    }

    /**
     * Replace the entire BiS list for (character, spec) from an icy-veins URL.
     * Each item is auto-tagged with the highest tier for its source so wowhead
     * tooltips show realistic ilvl: raid items get the max-Myth bonus_id,
     * crafted items get the highest crafted ilvl.
     */
    public function importBis(User $user, int $characterId, int $specId, string $url): GearList
    {
        $character = $this->ensureOwned($user, $characterId);
        $this->ensureSpecMatchesClass($specId, $character);

        $dto = $this->icyVeins->importFromUrl($url);

        $mythBonus = $this->maxRaidMythBonus();
        $mythTopIlvl = $this->ilvlForMaxRaidTrack();
        $craftedIlvl = $this->maxCraftedIlvl();

        $items = [];
        foreach ($dto['items'] as $idx => $row) {
            $this->upsertItemPlaceholder($row['item_id'], $row['name']);

            $isCrafted = (bool) preg_match('/crafted/i', $row['source_note'] ?? '');

            $items[] = [
                'slot'       => $row['slot'],
                'item_id'    => $row['item_id'],
                'item_level' => $isCrafted ? $craftedIlvl : $mythTopIlvl,
                'enchant_id' => null,
                'bonus_ids'  => $isCrafted ? null : [$mythBonus],
                'position'   => $idx,
            ];
            SyncSingleItemMetadataJob::dispatch($row['item_id']);
        }

        return GearList::query()->upsertSingleton(
            $character,
            $specId,
            GearList::TYPE_BIS,
            'Best in Slot',
            GearList::SOURCE_ICY_VEINS,
            $url,
            $items,
        );
    }

    /** Highest bonus_id whose track is Myth (level == max). */
    private function maxRaidMythBonus(): int
    {
        $tracks = config('wow_season.item_upgrade_tracks', []);
        foreach ($tracks as $bonusId => $info) {
            if (($info['track'] ?? null) === 'Myth'
                && ($info['level'] ?? 0) === ($info['max'] ?? 0)
                && ($info['max'] ?? 0) > 0
            ) {
                return (int) $bonusId;
            }
        }
        return 12806; // sane fallback for season 17
    }

    /** Maximum crafted ilvl for the current season (top tier in config). */
    private function maxCraftedIlvl(): int
    {
        $tiers = config('wow_season.crafted_ilvl_tiers', []);
        return (int) ($tiers[0]['max'] ?? 285);
    }

    /**
     * Exact ilvl of the top Myth raid track (level === max) — read from the
     * canonical `item_upgrade_tracks` config so it stays correct as seasons
     * change.
     */
    private function ilvlForMaxRaidTrack(): int
    {
        $tracks = config('wow_season.item_upgrade_tracks', []);
        foreach ($tracks as $info) {
            if (($info['track'] ?? null) === 'Myth'
                && ($info['level'] ?? 0) === ($info['max'] ?? 0)
                && ! empty($info['ilvl'])
            ) {
                return (int) $info['ilvl'];
            }
        }
        return 289; // S17 known max as last resort
    }

    /**
     * Replace a custom list's contents from a /simc paste. Wipe-and-replace.
     */
    public function importSimcIntoCustom(User $user, int $listId, string $simcText): GearList
    {
        $list = $this->loadOwnedList($user, $listId);
        if ($list->type !== GearList::TYPE_CUSTOM) {
            throw GearListException::cannotMutateCurrent();
        }

        $items = $this->simc->parse($simcText);

        DB::transaction(function () use ($list, $items) {
            GearListItem::query()->where('list_id', $list->id)->delete();

            $now = now();
            $rows = [];
            foreach ($items as $idx => $row) {
                $this->upsertItemPlaceholder($row['item_id']);
                SyncSingleItemMetadataJob::dispatch($row['item_id']);
                $rows[] = [
                    'list_id'    => $list->id,
                    'slot'       => $row['slot'],
                    'item_id'    => $row['item_id'],
                    'item_level' => $row['item_level'] ?? null,
                    'enchant_id' => $row['enchant_id'] ?? null,
                    'bonus_ids'  => ! empty($row['bonus_ids']) ? json_encode($row['bonus_ids']) : null,
                    'position'   => $idx,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (! empty($rows)) {
                GearListItem::query()->insert($rows);
            }

            $list->update([
                'source'      => GearList::SOURCE_SIMC,
                'imported_at' => now(),
            ]);
        });

        return $list->fresh('items');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function ensureOwned(User $user, int $characterId): Character
    {
        $character = Character::query()->find($characterId);
        if (! $character || $character->user_id !== $user->id) {
            throw GearListException::characterNotOwned($characterId);
        }
        return $character;
    }

    private function loadOwnedList(User $user, int $listId): GearList
    {
        $list = GearList::query()->with('character')->find($listId);
        if (! $list) {
            throw GearListException::listNotFound($listId);
        }
        if ($list->character->user_id !== $user->id) {
            throw GearListException::characterNotOwned($list->character_id);
        }
        return $list;
    }

    private function ensureSpecMatchesClass(int $specId, Character $character): void
    {
        $spec = Specialization::query()->find($specId);
        if (! $spec || $spec->class_name !== $character->playable_class) {
            throw GearListException::specNotForClass($specId, $character->playable_class ?? 'unknown');
        }
    }

    private function upsertItemPlaceholder(int $itemId, ?string $name = null, ?string $icon = null): void
    {
        $existing = Item::query()->find($itemId);
        if ($existing) {
            $patch = [];
            if ($name && ! $existing->name) $patch['name'] = $name;
            if ($icon && ! $existing->icon) $patch['icon'] = $icon;
            if (! empty($patch)) {
                $existing->update($patch);
            }
            return;
        }
        Item::query()->create([
            'id'   => $itemId,
            'name' => $name,
            'icon' => $icon,
        ]);
    }
}
