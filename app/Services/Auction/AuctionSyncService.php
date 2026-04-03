<?php

declare(strict_types=1);

namespace App\Services\Auction;

use App\Services\BlizzardApiService;
use App\Tasks\Auction\BulkInsertPriceSnapshotsTask;
use App\Tasks\Auction\CalculateMinPricesFromStreamTask;
use App\Tasks\Auction\FetchTrackedItemIdsTask;
use RuntimeException;

class AuctionSyncService
{
    public function __construct(
        private readonly BlizzardApiService $blizzardApiService,
        private readonly FetchTrackedItemIdsTask $fetchTrackedItemIdsTask,
        private readonly CalculateMinPricesFromStreamTask $calculateMinPricesFromStreamTask,
        private readonly BulkInsertPriceSnapshotsTask $bulkInsertPriceSnapshotsTask,
    ) {
    }

    /**
     * Coordinate the auction synchronization process.
     *
     * @throws RuntimeException
     */
    public function executeSync(): int
    {
        $trackedIds = $this->fetchTrackedItemIdsTask->run();
        $stream = $this->blizzardApiService->getCommoditiesStream();
        $minPrices = $this->calculateMinPricesFromStreamTask->run($stream, $trackedIds);

        if (empty($minPrices)) {
            throw new RuntimeException('No auctions found for tracked items.');
        }

        return $this->bulkInsertPriceSnapshotsTask->run($minPrices);
    }
}
