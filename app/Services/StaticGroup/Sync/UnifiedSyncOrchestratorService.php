<?php

declare(strict_types=1);

namespace App\Services\StaticGroup\Sync;

use App\Enums\StaticGroup\SyncType;
use App\Jobs\FetchCharacterRawDataJob;
use App\Tasks\StaticGroup\Sync\FetchStaticsDueForSyncTask;
use App\Tasks\StaticGroup\Sync\MarkStaticAsSyncedTask;

/**
 * Orchestrates the unified character sync pipeline.
 *
 * For every static group that is due for a sync, the orchestrator dispatches
 * one FetchCharacterRawDataJob per character. That job fetches all API routes
 * at once (Blizzard + Raider.io) and, on completion, chains a
 * CompileCharacterDataJob to produce the frontend-ready compiled_data payload.
 *
 * Scheduling is driven by the BNET interval (the longest cadence), which acts
 * as the single unified trigger. All three legacy timestamp columns are stamped
 * after dispatch so that the old per-type jobs (if still scheduled) do not
 * re-trigger before the next unified cycle.
 */
class UnifiedSyncOrchestratorService
{
    public function __construct(
        private readonly FetchStaticsDueForSyncTask $fetchStaticsDueForSyncTask,
        private readonly MarkStaticAsSyncedTask $markStaticAsSyncedTask,
    ) {}

    /**
     * Find statics due for sync and dispatch a FetchCharacterRawDataJob for
     * each of their characters.
     *
     * @return string[] Human-readable status lines for the console.
     */
    public function execute(): array
    {
        // BNET has the longest interval, making it the natural unified gate.
        $staticsDue = $this->fetchStaticsDueForSyncTask->run(SyncType::BNET);

        if ($staticsDue->isEmpty()) {
            return ['No statics are currently due for sync.'];
        }

        $messages = [];

        foreach ($staticsDue as $static) {
            $characters = $static->characters;
            $dispatched = 0;

            foreach ($characters as $character) {
                FetchCharacterRawDataJob::dispatch($character);
                $dispatched++;
            }

            // Stamp all three legacy columns so that leftover per-type jobs
            // (SyncBnetDataJob, SyncRioDataJob, SyncWclDataJob) are not
            // re-triggered until the next full unified cycle.
            $this->markStaticAsSyncedTask->run($static->id, SyncType::BNET);
            $this->markStaticAsSyncedTask->run($static->id, SyncType::RIO);
            $this->markStaticAsSyncedTask->run($static->id, SyncType::WCL);

            $messages[] = sprintf(
                'Dispatched unified sync for %d character(s) in static: %s (ID: %d)',
                $dispatched,
                $static->name,
                $static->id,
            );
        }

        return $messages;
    }
}
