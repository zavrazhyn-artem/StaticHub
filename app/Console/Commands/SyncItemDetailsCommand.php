<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Services\BlizzardApiService;
use Illuminate\Support\Facades\DB;

#[Signature('app:sync-item-details')]
#[Description('Sync real names and icons for items in our database from Blizzard API.')]
class SyncItemDetailsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BlizzardApiService $apiService)
    {
        $items = DB::table('items')
            ->where('name', 'like', 'Item #%')
            ->orWhereNull('icon')
            ->get();

        if ($items->isEmpty()) {
            $this->info('No items to sync.');
            return 0;
        }

        $this->info("Found {$items->count()} items to sync.");

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            try {
                $apiService->syncItemMetadata($item->id);
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to sync item {$item->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Item metadata sync completed.');
        return 0;
    }
}
