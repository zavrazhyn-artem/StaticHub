<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Realm\RealmSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('app:fetch-realms')]
#[Description('Fetch all WoW realms from Blizzard API and store them in the database.')]
class FetchRealmsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(RealmSyncService $realmSyncService): int
    {
        $this->info('Starting to fetch realms...');

        try {
            $count = $realmSyncService->executeSync();
            $this->info("Successfully synced {$count} realms.");
        } catch (Throwable $e) {
            $this->error('Error fetching realms: ' . $e->getMessage());
            Log::error('FetchRealmsCommand error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
