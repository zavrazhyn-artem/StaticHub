<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Auction\AuctionSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:fetch-auctions')]
#[Description('Fetch and parse Blizzard Auction House commodities data.')]
class FetchAuctionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuctionSyncService $auctionSyncService): int
    {
        $this->info('Starting to fetch commodities...');

        $insertedCount = $auctionSyncService->executeSync();
        $this->info("Successfully saved {$insertedCount} price snapshots.");

        return 0;
    }
}
