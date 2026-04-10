<?php

declare(strict_types=1);

namespace App\Jobs\StaticGroup;

use App\Models\StaticGroup;
use App\Services\StaticGroup\StaticProgressionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Recalculates raid progression for a static group after a sync cycle.
 *
 * Dispatched by UnifiedSyncOrchestratorService with a delay to allow
 * character fetch + compile jobs to finish first.
 */
class RecalculateStaticProgressionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public readonly int $staticGroupId,
    ) {
        $this->onQueue(config('sync.queues.compile', 'compile'));
    }

    public function handle(StaticProgressionService $service): void
    {
        $static = StaticGroup::withoutGlobalScopes()->find($this->staticGroupId);

        if ($static === null) {
            Log::warning("RecalculateStaticProgressionJob: static {$this->staticGroupId} not found, skipping.");
            return;
        }

        $newCount = $service->recalculate($static);

        if ($newCount > 0) {
            Log::info("RecalculateStaticProgressionJob: {$newCount} new achievement(s) recorded for static {$static->name} (ID: {$this->staticGroupId}).");
        }
    }
}
