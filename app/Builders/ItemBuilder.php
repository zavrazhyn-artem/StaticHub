<?php

namespace App\Builders;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ItemBuilder extends Builder
{
    public function updateMetadata(int $id, string $name, ?string $icon, ?string $quality = null): void
    {
        $payload = [
            'name' => $name,
            'icon' => $icon,
            'updated_at' => now(),
        ];
        if ($quality !== null) {
            $payload['quality'] = $quality;
        }

        $this->updateOrInsert(['id' => $id], $payload);
    }

    public function incompleteIds(): array
    {
        return $this->whereNull('name')
            ->orWhereNull('icon_url')
            ->pluck('id')
            ->toArray();
    }

    public function allTrackedIds(): array
    {
        return $this->pluck('id')->toArray();
    }

    /**
     * Bulk update last_price + last_price_at for many items in chunked UPDATE statements.
     *
     * @param  array<int, int>  $itemIdToPrice  item_id => price (copper)
     * @return int rows attempted
     */
    public function upsertLastPrices(array $itemIdToPrice, DateTimeInterface $now): int
    {
        if (empty($itemIdToPrice)) {
            return 0;
        }

        $touched = 0;
        $timestamp = $now->format('Y-m-d H:i:s');

        foreach (array_chunk($itemIdToPrice, 500, true) as $chunk) {
            $ids = [];
            $caseParts = [];
            $bindings = [];

            foreach ($chunk as $itemId => $price) {
                $id = (int) $itemId;
                $ids[] = $id;
                $caseParts[] = 'WHEN ? THEN ?';
                $bindings[] = $id;
                $bindings[] = (int) $price;
            }

            $bindings[] = $timestamp;

            $idList = implode(',', $ids);
            $caseSql = 'CASE id ' . implode(' ', $caseParts) . ' END';

            DB::update(
                "UPDATE items SET last_price = {$caseSql}, last_price_at = ? WHERE id IN ({$idList})",
                $bindings
            );

            $touched += count($chunk);
        }

        return $touched;
    }

    /**
     * Fetch latest cached prices for a set of items keyed by item_id.
     *
     * @param  array<int, int>  $itemIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function cachedPricesFor(array $itemIds): \Illuminate\Support\Collection
    {
        if (empty($itemIds)) {
            return collect();
        }

        return $this->getModel()::query()
            ->whereIn('id', $itemIds)
            ->whereNotNull('last_price')
            ->pluck('last_price', 'id');
    }
}
