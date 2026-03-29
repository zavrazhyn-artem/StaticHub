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
}
