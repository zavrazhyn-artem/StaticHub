<?php

declare(strict_types=1);

namespace App\Tasks\Auction;

use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

class CalculateMinPricesFromStreamTask
{
    /**
     * Calculate min prices for tracked items from the JSON stream.
     */
    public function run($stream, array $trackedIds): array
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
}
