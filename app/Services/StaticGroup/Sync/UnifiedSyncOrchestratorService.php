<?php

declare(strict_types=1);

namespace App\Services\StaticGroup\Sync;

use App\Enums\StaticGroup\SyncType;
use App\Jobs\FetchBnetRawDataJob;
use App\Jobs\FetchRioRawDataJob;
use App\Tasks\StaticGroup\Sync\FetchStaticsDueForSyncTask;
use App\Tasks\StaticGroup\Sync\MarkStaticAsSyncedTask;

/**
 * Orchestrates the unified character sync pipeline.
 *
 * For every static group that is due for a sync, the orchestrator dispatches
 * two independent jobs per character:
 *   - FetchBnetRawDataJob  → 'bnet' queue  (Blizzard API routes)
 *   - FetchRioRawDataJob   → 'rio'  queue  (Raider.io API route)
 *
 * Each fetch job dispatches CompileCharacterDataJob on the 'compile' queue
 * upon completion. Because bnet and rio run in parallel on separate queues
 * they never block each other. CompileCharacterDataJob is idempotent — running
 * it twice (once after bnet, once after rio) is safe and ensures the roster
 * table always reflects the latest available data.
 *
 * Scheduling is driven by the BNET interval (longest cadence) as the unified
 * trigger. All three legacy timestamp columns are stamped after dispatch.
 */
class UnifiedSyncOrchestratorService
{
    public function __construct(
        private readonly FetchStaticsDueForSyncTask $fetchStaticsDueForSyncTask,
        private readonly MarkStaticAsSyncedTask $markStaticAsSyncedTask,
    ) {}

    /**
     * Find statics due for sync and dispatch fetch jobs for each character.
     *
     * @return string[] Human-readable status lines for the console.
     */
    public function execute(): array
    {
        $staticsDue = $this->fetchStaticsDueForSyncTask->run(SyncType::BNET);

        if ($staticsDue->isEmpty()) {
            return ['No statics are currently due for sync.'];
        }

        $messages = [];

        foreach ($staticsDue as $static) {
            $characters = $static->characters;
            $dispatched = 0;

            foreach ($characters as $character) {
                FetchBnetRawDataJob::dispatch($character);
                FetchRioRawDataJob::dispatch($character);
                $dispatched++;
            }

            $this->markStaticAsSyncedTask->run($static->id, SyncType::BNET);
            $this->markStaticAsSyncedTask->run($static->id, SyncType::RIO);
            $this->markStaticAsSyncedTask->run($static->id, SyncType::WCL);

            $messages[] = sprintf(
                'Dispatched bnet+rio sync for %d character(s) in static: %s (ID: %d)',
                $dispatched,
                $static->name,
                $static->id,
            );
        }

        return $messages;
    }
}
