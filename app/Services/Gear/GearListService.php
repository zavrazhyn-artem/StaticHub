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
    ) {}

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
     * Set or unset a single slot in a custom list. Pass $itemId=null to clear.
     */
    public function setSlot(
        User $user,
        int $listId,
        string $slot,
        ?int $itemId,
        ?int $itemLevel = null,
        ?int $enchantId = null,
        ?array $bonusIds = null,
    ): GearList {
        $list = $this->loadOwnedList($user, $listId);
        if ($list->type !== GearList::TYPE_CUSTOM) {
            throw GearListException::cannotMutateCurrent();
        }

        if ($itemId === null) {
            GearListItem::query()->where('list_id', $list->id)->where('slot', $slot)->delete();
            return $list->fresh('items');
        }

        $this->upsertItemPlaceholder($itemId);

        GearListItem::query()->updateOrCreate(
            ['list_id' => $list->id, 'slot' => $slot],
            [
                'item_id'    => $itemId,
                'item_level' => $itemLevel,
                'enchant_id' => ($enchantId ?? 0) > 0 ? $enchantId : null,
                'bonus_ids'  => $bonusIds,
            ],
        );

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

    private function upsertItemPlaceholder(int $itemId, ?string $name = null): void
    {
        $existing = Item::query()->find($itemId);
        if ($existing) {
            // Only fill name if we have one and the row currently has none.
            if ($name && ! $existing->name) {
                $existing->update(['name' => $name]);
            }
            return;
        }
        Item::query()->create([
            'id'   => $itemId,
            'name' => $name,
        ]);
    }
}
