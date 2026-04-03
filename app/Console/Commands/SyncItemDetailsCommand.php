<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncSingleItemMetadataJob;
use App\Tasks\Item\FetchIncompleteItemIdsTask;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-item-details')]
#[Description('Sync real names and icons for items in our database from Blizzard API.')]
class SyncItemDetailsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FetchIncompleteItemIdsTask $fetchIncompleteItemIdsTask): int
    {
        $itemIds = $fetchIncompleteItemIdsTask->run();

        if (empty($itemIds)) {
            $this->info('No items to sync.');
            return 0;
        }

        $count = count($itemIds);
        $this->info("Found {$count} items to sync. Dispatching jobs...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($itemIds as $id) {
            SyncSingleItemMetadataJob::dispatch($id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Successfully dispatched item metadata sync jobs.');

        return 0;
    }
}
