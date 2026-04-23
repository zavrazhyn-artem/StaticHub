<?php

declare(strict_types=1);

namespace App\Services\StaticGroup\Sync;

use App\Enums\StaticGroup\SyncType;
use App\Helpers\SyncIntervalHelper;
use App\Jobs\Character\FetchBnetRawDataJob;
use App\Jobs\Character\FetchRioRawDataJob;
use App\Jobs\StaticGroup\RecalculateStaticProgressionJob;
use App\Models\StaticGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

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
    /**
     * Find statics due for sync and dispatch fetch jobs for each character.
     *
     * @return string[] Human-readable status lines for the console.
     */
    public function execute(): array
    {
        $staticsDue = $this->fetchStaticsDueForSync(SyncType::BNET);

        if ($staticsDue->isEmpty()) {
            return ['No statics are currently due for sync.'];
        }

        $messages = [];

        foreach ($staticsDue as $static) {
            $characters = $static->characters;
            $dispatched = 0;

            // Spread dispatches across a 50s window so 20 statics × 30 chars
            // don't all hit Redis/APIs in the same second. Paired with
            // ShouldBeUnique + RateLimited middleware on the fetch jobs.
            foreach ($characters as $character) {
                $jitter = random_int(0, 50);
                FetchBnetRawDataJob::dispatch($character)
                    ->delay(now()->addSeconds($jitter));
                FetchRioRawDataJob::dispatch($character)
                    ->delay(now()->addSeconds(random_int(0, 50)));
                $dispatched++;
            }

            $this->markStaticAsSynced($static->id, SyncType::BNET);
            $this->markStaticAsSynced($static->id, SyncType::RIO);
            $this->markStaticAsSynced($static->id, SyncType::WCL);

            RecalculateStaticProgressionJob::dispatch($static->id)
                ->delay(now()->addMinutes(5));

            $messages[] = sprintf(
                'Dispatched bnet+rio sync for %d character(s) in static: %s (ID: %d)',
                $dispatched,
                $static->name,
                $static->id,
            );
        }

        return $messages;
    }

    /**
     * Fetch static groups that are due for a specific sync type.
     *
     * @param SyncType $syncType The sync type enum.
     * @return Collection<int, StaticGroup>
     */
    public function fetchStaticsDueForSync(SyncType $syncType): Collection
    {
        $syncTypeValue = $syncType->value;
        $syncColumn = "{$syncTypeValue}_last_synced_at";

        return StaticGroup::withoutGlobalScopes()->with('characters')->get()->filter(function (StaticGroup $static) use ($syncType, $syncColumn) {
            $tier = $static->plan_tier ?? 'free';
            $interval = SyncIntervalHelper::getIntervalInMinutes($tier, $syncType);
            $lastSyncAt = $static->$syncColumn;

            if ($lastSyncAt === null) {
                return true;
            }

            $lastSyncAt = $lastSyncAt instanceof Carbon ? $lastSyncAt : Carbon::parse($lastSyncAt);

            return $lastSyncAt->isBefore(now()->subMinutes($interval));
        });
    }

    /**
     * Update the last sync timestamp for a specific sync type of a static group.
     *
     * @param int $staticId The ID of the static group.
     * @param SyncType $syncType The sync type enum.
     * @return void
     */
    public function markStaticAsSynced(int $staticId, SyncType $syncType): void
    {
        $syncTypeValue = $syncType->value;
        $syncColumn = "{$syncTypeValue}_last_synced_at";

        StaticGroup::withoutGlobalScopes()
            ->where('id', $staticId)
            ->update([
                $syncColumn => now(),
            ]);
    }
}
