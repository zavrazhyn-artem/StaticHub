<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\StaticGroup\Sync\UnifiedSyncOrchestratorService;
use Illuminate\Console\Command;
use Throwable;

class SyncAllStaticsCommand extends Command
{
    protected $signature = 'statics:sync';

    protected $description = 'Dispatch unified raw-data fetch jobs for all statics due for synchronization.';

    public function handle(UnifiedSyncOrchestratorService $orchestrator): int
    {
        $this->info('Starting unified sync dispatch...');

        try {
            $messages = $orchestrator->execute();
        } catch (Throwable $e) {
            $this->error('Sync orchestration failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        foreach ($messages as $message) {
            $this->line($message);
        }

        $this->info('Sync dispatch completed.');

        return self::SUCCESS;
    }
}
