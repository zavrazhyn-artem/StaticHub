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
 * Orchestrates per-static character sync. Each SyncType is evaluated
 * independently against its own interval — Bnet at SYNC_INTERVAL_*_BNET,
 * Rio at SYNC_INTERVAL_*_RIO, etc. — so they fire on independent cadences
 * (e.g. bnet=60min, rio=480min) without one tying the other.
 *
 * Per type:
 *   - BNET → FetchBnetRawDataJob on 'bnet' queue. Self-contained: fetch +
 *            accumulate + compile + JSON_MERGE_PATCH onto characters.
 *   - RIO  → FetchRioRawDataJob on 'rio' queue. Self-contained: fetch +
 *            compile rio slice + JSON_MERGE_PATCH onto character_weekly_data.
 *   - WCL  → no fetch job exists; we just bump wcl_last_synced_at on its
 *            interval so the dashboard "last synced" widget remains coherent.
 *            (Raid log analysis is on-demand, not periodic.)
 *
 * Only characters that belong to at least one static are synced — orchestrator
 * iterates `$static->characters`, which already filters via the pivot.
 */
class UnifiedSyncOrchestratorService
{
    /**
     * Run one tick of the orchestrator. Called every minute by SyncAllStaticsCommand.
     *
     * @return string[] Human-readable status lines for the console.
     */
    public function execute(): array
    {
        $messages = [];

        $messages = array_merge($messages, $this->dispatchTypeSync(SyncType::BNET));
        $messages = array_merge($messages, $this->dispatchTypeSync(SyncType::RIO));
        $messages = array_merge($messages, $this->bumpWclTimestamps());

        if ($messages === []) {
            return ['No statics are currently due for sync.'];
        }

        return $messages;
    }

    /**
     * Dispatch the fetch job for one sync type across every static that's
     * past its per-type interval. Each static gets its own timestamp bumped
     * only for THIS type, so other types stay on their own clocks.
     *
     * @return string[]
     */
    private function dispatchTypeSync(SyncType $syncType): array
    {
        $statics = $this->fetchStaticsDueForSync($syncType);

        if ($statics->isEmpty()) {
            return [];
        }

        $messages = [];

        foreach ($statics as $static) {
            $dispatched = 0;

            foreach ($static->characters as $character) {
                $this->dispatchFetchJob($syncType, $character);
                $dispatched++;
            }

            $this->markStaticAsSynced($static->id, $syncType);

            // Recalculate static-level raid progression after Bnet sync only —
            // Rio doesn't change raid kill state and WCL is no-op here.
            if ($syncType === SyncType::BNET) {
                RecalculateStaticProgressionJob::dispatch($static->id)
                    ->delay(now()->addMinutes(5));
            }

            $messages[] = sprintf(
                'Dispatched %s sync for %d character(s) in static: %s (ID: %d)',
                $syncType->value,
                $dispatched,
                $static->name,
                $static->id,
            );
        }

        return $messages;
    }

    /**
     * WCL has no real fetch job — just stamp wcl_last_synced_at on its
     * cadence so the "Last synced" UI widget stays sane and users see the
     * service rotating through its expected interval.
     *
     * @return string[]
     */
    private function bumpWclTimestamps(): array
    {
        $statics = $this->fetchStaticsDueForSync(SyncType::WCL);

        if ($statics->isEmpty()) {
            return [];
        }

        $messages = [];

        foreach ($statics as $static) {
            $this->markStaticAsSynced($static->id, SyncType::WCL);
            $messages[] = sprintf(
                'Marked WCL synced for static: %s (ID: %d) [no-op fetch]',
                $static->name,
                $static->id,
            );
        }

        return $messages;
    }

    /**
     * Dispatch one character's fetch job for the given sync type, with a small
     * jitter so 20 statics × 30 chars don't all hit the API in the same second.
     * `ShouldBeUnique` on the jobs collapses duplicate dispatches within 180s.
     */
    private function dispatchFetchJob(SyncType $syncType, $character): void
    {
        $delay = now()->addSeconds(random_int(0, 50));

        match ($syncType) {
            SyncType::BNET => FetchBnetRawDataJob::dispatch($character)->delay($delay),
            SyncType::RIO  => FetchRioRawDataJob::dispatch($character)->delay($delay),
            SyncType::WCL  => null,
        };
    }

    /**
     * Find statics due for a sync type — `<type>_last_synced_at` either NULL
     * or older than the tier's per-type interval.
     *
     * @return Collection<int, StaticGroup>
     */
    public function fetchStaticsDueForSync(SyncType $syncType): Collection
    {
        $syncColumn = "{$syncType->value}_last_synced_at";

        return StaticGroup::withoutGlobalScopes()
            ->with('characters')
            ->get()
            ->filter(function (StaticGroup $static) use ($syncType, $syncColumn) {
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

    public function markStaticAsSynced(int $staticId, SyncType $syncType): void
    {
        $syncColumn = "{$syncType->value}_last_synced_at";

        StaticGroup::withoutGlobalScopes()
            ->where('id', $staticId)
            ->update([$syncColumn => now()]);
    }
}
