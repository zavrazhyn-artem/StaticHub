<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Services\BlizzardApiService;
use Illuminate\Support\Facades\DB;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

#[Signature('app:fetch-auctions')]
#[Description('Fetch and parse Blizzard Auction House commodities data.')]
class FetchAuctionsCommand extends Command
{
    /**
     * The item IDs we are interested in.
     */
    protected array $trackedItemIds = [236767];

    /**
     * Execute the console command.
     */
    public function handle(BlizzardApiService $apiService)
    {
        $this->info('Starting to fetch commodities...');

        try {
            $stream = $apiService->getCommoditiesStream();
            $auctions = Items::fromStream($stream, [
                'pointer' => '/auctions',
                'decoder' => new ExtJsonDecoder(true)
            ]);

            $minPrices = [];

            // Collect all unique item IDs from our database
            $trackedIds = DB::table('items')->pluck('id')->toArray();
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

            if (empty($minPrices)) {
                $this->warn('No auctions found for items in our database.');
                return;
            }

            $now = now();
            $insertedCount = 0;

            $this->info('Saving price snapshots...');
            // Process in chunks to avoid memory and performance issues
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

            $this->info("Successfully saved {$insertedCount} price snapshots.");
        } catch (\Exception $e) {
            $this->error('Error fetching or parsing auctions: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
