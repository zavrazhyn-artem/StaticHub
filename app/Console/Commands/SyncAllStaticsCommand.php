<?php

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Jobs\SyncStaticRosterJob;
use Illuminate\Console\Command;

class SyncAllStaticsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'statics:sync-rosters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch SyncStaticRosterJob for all active static groups';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting dispatching synchronization jobs for all statics...');

        // Fetch all static groups.
        // If there is an 'active' flag, we should filter by it.
        // Based on the model, there's no obvious 'active' field, so we fetch all.
        $statics = StaticGroup::withoutGlobalScopes()->get();

        foreach ($statics as $static) {
            $this->comment("Dispatching sync job for static: {$static->name} (ID: {$static->id})");
            SyncStaticRosterJob::dispatch($static);
        }

        $this->info('All jobs dispatched successfully.');
    }
}
