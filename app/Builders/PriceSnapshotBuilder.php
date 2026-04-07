<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class PriceSnapshotBuilder extends Builder
{
    /**
     * Get the latest price for a specific item.
     */
    public function latestPriceForItem(int $itemId): ?int
    {
        return $this->where('item_id', $itemId)
            ->orderBy('created_at', 'desc')
            ->value('price');
    }

    /**
     * Get the latest prices for multiple items in a single query.
     *
     * @return \Illuminate\Support\Collection<int, int> item_id => price
     */
    public function latestPricesForItems(array $itemIds): \Illuminate\Support\Collection
    {
        if (empty($itemIds)) {
            return collect();
        }

        return $this->getModel()::query()
            ->select('item_id', 'price')
            ->whereIn('item_id', $itemIds)
            ->whereRaw('(item_id, created_at) IN (SELECT item_id, MAX(created_at) FROM price_snapshots WHERE item_id IN (' . implode(',', array_map('intval', $itemIds)) . ') GROUP BY item_id)')
            ->pluck('price', 'item_id');
    }
}
