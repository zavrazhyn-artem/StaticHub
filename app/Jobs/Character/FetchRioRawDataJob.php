<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Services\Character\CharacterSyncService;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches raw Raider.io API data for a single character and stores it in
 * services_raw_data. On completion dispatches CompileCharacterDataJob.
 *
 * Runs on the dedicated 'rio' queue so Raider.io API calls never block
 * Blizzard fetches and vice versa.
 */
class FetchRioRawDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.rio', 'rio'));
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

        CompileCharacterDataJob::dispatch($this->character)
            ->onQueue(config('sync.queues.compile', 'compile'));

        Log::info('FetchRioRawDataJob: fetch complete, CompileCharacterDataJob dispatched.', [
            'character_id' => $this->character->id,
        ]);
    }
}
