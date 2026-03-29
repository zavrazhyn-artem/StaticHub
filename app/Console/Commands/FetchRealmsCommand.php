<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Services\BlizzardApiService;
use App\Models\Realm;
use Illuminate\Support\Facades\Log;

#[Signature('app:fetch-realms')]
#[Description('Fetch all WoW realms from Blizzard API and store them in the database.')]
class FetchRealmsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BlizzardApiService $apiService)
    {
        $this->info('Starting to fetch realms...');

        try {
            $realms = $apiService->getRealms();
            $count = count($realms);
            $this->info("Found {$count} realms. Syncing with database...");

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            foreach ($realms as $realmData) {
                // Fetching individual details might be slow, let's see if we can optimize
                // But the requirement says fetch all European realms and store them.
                // The index already gives id, name, and slug.
                // Timezone and is_online might need individual calls.

                // For performance, we can skip individual calls if not strictly needed for the task,
                // but let's try to get what we can from the index first.

                Realm::updateOrCreate(
                    ['id' => $realmData['id']],
                    [
                        'name' => $realmData['name']['en_GB'] ?? $realmData['name']['en_US'] ?? reset($realmData['name']),
                        'slug' => $realmData['slug'],
                        'region' => config('services.battlenet.region', 'eu'),
                        // timezone and is_online will be null/default unless we call individual endpoint
                    ]
                );

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Successfully synced realms.');
        } catch (\Exception $e) {
            $this->error('Error fetching realms: ' . $e->getMessage());
            Log::error('FetchRealmsCommand error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
