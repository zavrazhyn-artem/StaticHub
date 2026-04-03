<?php

declare(strict_types=1);

namespace App\Tasks\Auction;

use Illuminate\Support\Facades\DB;

class BulkInsertPriceSnapshotsTask
{
    /**
     * Save price snapshots in chunks.
     */
    public function run(array $minPrices): int
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
}
