<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class SeasonItemBuilder extends Builder
{
    /**
     * Map a gear-list slot enum (head/finger_1/main_hand/...) to the set of
     * inventoryType labels in season_items that should be shown when picking
     * for that slot.
     *
     * Cross-slot equivalence:
     *   - finger_1 / finger_2  → "Finger"
     *   - trinket_1 / trinket_2 → "Trinket"
     *   - main_hand → "One-Hand", "Two-Hand", "Main Hand", "Ranged"
     *   - off_hand  → "One-Hand", "Off Hand", "Shield"
     *
     * @return array<int, string>
     */
    public static function inventoryTypesForSlot(string $slot): array
    {
        return match ($slot) {
            'head'      => ['Head'],
            'neck'      => ['Neck'],
            'shoulder'  => ['Shoulder'],
            'back'      => ['Back'],
            'chest'     => ['Chest'],
            'wrist'     => ['Wrist'],
            'hands'     => ['Hands'],
            'waist'     => ['Waist'],
            'legs'      => ['Legs'],
            'feet'      => ['Feet'],
            'finger_1', 'finger_2' => ['Finger'],
            'trinket_1', 'trinket_2' => ['Trinket'],
            'main_hand' => ['One-Hand', 'Two-Hand', 'Main Hand', 'Ranged'],
            'off_hand'  => ['One-Hand', 'Off Hand', 'Shield'],
            'ranged'    => ['Ranged'],
            default     => [],
        };
    }

    public function forSeason(string $seasonSlug): self
    {
        return $this->where('season_slug', $seasonSlug);
    }

    /**
     * Batch-resolve item id → inventory_type so the loot ingest path can
     * stamp item_slot without one query per row.
     *
     * @param  list<int>  $itemIds
     * @return array<int, string>
     */
    public function inventoryTypesByItemIds(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }
        return $this->whereIn('id', $itemIds)
            ->whereNotNull('inventory_type')
            ->pluck('inventory_type', 'id')
            ->all();
    }

    public function forSlot(string $slot): self
    {
        $types = self::inventoryTypesForSlot($slot);
        if (empty($types)) {
            return $this->whereRaw('1 = 0'); // unknown slot → no results
        }
        return $this->whereIn('inventory_type', $types);
    }

    /**
     * Restrict to items the given class can equip and whose role overlaps
     * the spec's role.
     */
    public function forClassAndRole(string $className, string $role): self
    {
        return $this
            ->whereExists(function ($q) use ($className) {
                $q->select(\DB::raw(1))
                    ->from('season_item_class')
                    ->whereColumn('season_item_class.season_item_id', 'season_items.id')
                    ->where('season_item_class.class_name', $className);
            })
            ->whereRaw('JSON_CONTAINS(role, ?)', [json_encode($role)]);
    }
}
