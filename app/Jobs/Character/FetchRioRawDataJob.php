<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Services\Character\CharacterSyncService;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches raw Raider.io API data for a single character and stores it in
 * services_raw_data.
 *
 * Runs on the dedicated 'rio' queue so Raider.io API calls never block
 * Blizzard fetches and vice versa. CompileCharacterDataJob is dispatched
 * only by FetchBnetRawDataJob to avoid double-queuing the compile job.
 */
class FetchRioRawDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 180;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.rio', 'rio'));
    }

    public function uniqueId(): string
    {
        return (string) $this->character->id;
    }

    public function middleware(): array
    {
        return [new RateLimited('rio-api')];
    }

    public function handle(CharacterSyncService $syncService): void
    {
        Log::info('FetchRioRawDataJob: starting Raider.io data fetch.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        try {
            $syncService->syncRawData($this->character, 'rio');
        } catch (Throwable $e) {
            Log::error('FetchRioRawDataJob: fatal exception.', [
                'character_id' => $this->character->id,
                'exception'    => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('FetchRioRawDataJob: fetch complete.', [
            'character_id' => $this->character->id,
        ]);
    }
}
