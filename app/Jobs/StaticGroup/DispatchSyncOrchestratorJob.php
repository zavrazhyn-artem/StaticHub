<?php

declare(strict_types=1);

namespace App\Jobs\StaticGroup;

use App\Services\StaticGroup\Sync\UnifiedSyncOrchestratorService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Wraps UnifiedSyncOrchestratorService::execute() as a queueable Job so the
 * scheduler pod just dispatches once a minute and the orchestrator itself
 * runs on the default-queue worker — keeps the scheduler pod minimal and
 * gives the orchestrator native retry semantics if it fails.
 *
 * The orchestrator's job is light (one StaticGroup query + Job dispatches to
 * bnet/rio queues). Heavy fetch work happens on the sync pool downstream.
 */
class DispatchSyncOrchestratorJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 30;

    /**
     * Collapse duplicate dispatches within 50 seconds. Schedule fires every
     * minute; this prevents two ticks from queueing the orchestrator twice
     * if the previous run is still processing.
     */
    public int $uniqueFor = 50;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(UnifiedSyncOrchestratorService $orchestrator): void
    {
        $orchestrator->execute();
    }
}
