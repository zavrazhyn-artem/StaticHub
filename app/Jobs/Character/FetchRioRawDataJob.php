<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Character\RioSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-contained Raider.io sync for one character: fetch the rio profile and
 * atomically merge the rio-derivable fields (weekly_runs_count, vault_weekly_runs)
 * into character_weekly_data via JSON_MERGE_PATCH. No services_raw_data,
 * no follow-up Compile job.
 *
 * Runs on the dedicated 'rio' queue so Raider.io API calls never block
 * Blizzard fetches and vice versa.
 */
class FetchRioRawDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $backoff = 60;

    public int $uniqueFor = 180;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.rio', 'rio'));
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return (string) $this->character->id;
    }

    public function middleware(): array
    {
        return [new RateLimited('rio-api')];
    }

    public function handle(RioSyncService $syncService): void
    {
        Log::info('FetchRioRawDataJob: starting Raider.io data fetch.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        try {
            $syncService->syncCharacter($this->character);
        } catch (Throwable $e) {
            Log::error('FetchRioRawDataJob: fatal exception.', [
                'character_id' => $this->character->id,
                'exception'    => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('FetchRioRawDataJob: sync complete.', [
            'character_id' => $this->character->id,
        ]);
    }
}
