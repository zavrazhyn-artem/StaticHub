<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\Character;
use App\Models\GearList;
use App\Models\GearListItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GearListBuilder extends Builder
{
    public function forContext(int $characterId, int $specId): self
    {
        return $this->where('character_id', $characterId)
            ->where('spec_id', $specId);
    }

    public function ofType(string $type): self
    {
        return $this->where('type', $type);
    }

    public function withDisplayRelations(): self
    {
        return $this->with([
            'character:id,user_id,name,realm_id,playable_class,avatar_url',
            'character.realm:id,name,slug',
            'specialization',
            'items' => fn ($q) => $q->orderBy('position'),
            'items.item',
        ]);
    }

    public function findCurrent(int $characterId, int $specId): ?GearList
    {
        return $this->forContext($characterId, $specId)->ofType(GearList::TYPE_CURRENT)->first();
    }

    public function findBis(int $characterId, int $specId): ?GearList
    {
        return $this->forContext($characterId, $specId)->ofType(GearList::TYPE_BIS)->first();
    }

    public function customCount(int $characterId, int $specId): int
    {
        return $this->forContext($characterId, $specId)
            ->ofType(GearList::TYPE_CUSTOM)
            ->count();
    }

    /**
     * Wipe-and-replace upsert for the singleton current/bis lists.
     * Items array shape: [{slot, item_id, item_level, enchant_id, bonus_ids, position}].
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function upsertSingleton(
        Character $character,
        int $specId,
        string $type,
        string $name,
        string $source,
        ?string $sourceUrl,
        array $items,
    ): GearList {
        return DB::transaction(function () use ($character, $specId, $type, $name, $source, $sourceUrl, $items) {
            $list = GearList::query()->updateOrCreate(
                [
                    'character_id' => $character->id,
                    'spec_id'      => $specId,
                    'type'         => $type,
                ],
                [
                    'name'        => $name,
                    'source'      => $source,
                    'source_url'  => $sourceUrl,
                    'imported_at' => now(),
                ]
            );

            GearListItem::query()->where('list_id', $list->id)->delete();

            if (! empty($items)) {
                $now = now();
                $rows = array_map(fn (array $row, int $idx) => [
                    'list_id'          => $list->id,
                    'slot'             => $row['slot'],
                    'item_id'          => $row['item_id'],
                    'item_level'       => $row['item_level'] ?? null,
                    'enchant_id'       => ($row['enchant_id'] ?? 0) > 0 ? $row['enchant_id'] : null,
                    'bonus_ids'        => isset($row['bonus_ids']) ? json_encode($row['bonus_ids']) : null,
                    'gem_ids'          => ! empty($row['gem_ids']) ? json_encode($row['gem_ids']) : null,
                    'has_empty_socket' => (bool) ($row['has_empty_socket'] ?? false),
                    'position'         => $row['position'] ?? $idx,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ], $items, array_keys($items));
                GearListItem::query()->insert($rows);
            }

            return $list->fresh('items');
        });
    }
}
