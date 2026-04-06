<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Services\Blizzard\BlizzardGameDataApiService;
use Illuminate\Support\Facades\DB;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use RuntimeException;

class AuctionSyncService
{
    public function __construct(
        private readonly BlizzardGameDataApiService $blizzardApiService,
    ) {
    }

    /**
     * Coordinate the auction synchronization process.
     *
     * @throws RuntimeException
     */
    public function executeSync(): int
    {
        $trackedIds = $this->fetchTrackedItemIds();
        $stream = $this->blizzardApiService->getCommoditiesStream();
        $minPrices = $this->calculateMinPricesFromStream($stream, $trackedIds);

        if (empty($minPrices)) {
            throw new RuntimeException('No auctions found for tracked items.');
        }

        return $this->bulkInsertPriceSnapshots($minPrices);
    }

    /**
     * Get the item IDs we are interested in.
     */
    public function fetchTrackedItemIds(): array
    {
        return DB::table('items')->pluck('id')->toArray();
    }

    /**
     * Calculate min prices for tracked items from the JSON stream.
     */
    public function calculateMinPricesFromStream($stream, array $trackedIds): array
    {
        $auctions = Items::fromStream($stream, [
            'pointer' => '/auctions',
            'decoder' => new ExtJsonDecoder(true)
        ]);

        $minPrices = [];
        $trackedIdsSet = array_flip($trackedIds);

        foreach ($auctions as $auction) {
            $itemId = $auction['item']['id'] ?? null;
            $unitPrice = $auction['unit_price'] ?? null;

            if ($itemId && $unitPrice !== null && isset($trackedIdsSet[$itemId])) {
                if (!isset($minPrices[$itemId]) || $unitPrice < $minPrices[$itemId]) {
                    $minPrices[$itemId] = $unitPrice;
                }
            }
        }

        return $minPrices;
    }

    /**
     * Save price snapshots in chunks.
     */
    public function bulkInsertPriceSnapshots(array $minPrices): int
    {
        $now = now();
        $insertedCount = 0;

        foreach (array_chunk($minPrices, 500, true) as $chunk) {
            $snapshots = [];
            foreach ($chunk as $itemId => $price) {
                $snapshots[] = [
                    'item_id' => $itemId,
                    'price' => $price,
                    'created_at' => $now,
                ];
            }
            DB::table('price_snapshots')->insert($snapshots);
            $insertedCount += count($snapshots);
        }

        return $insertedCount;
    }

    /**
     * Fetch IDs of items that need their metadata synced.
     *
     * @return array<int>
     */
    public function fetchIncompleteItemIds(): array
    {
        return DB::table('items')
            ->where('name', 'like', 'Item #%')
            ->orWhereNull('icon')
            ->pluck('id')
            ->toArray();
    }
}
