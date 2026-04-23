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
 * Fetches raw Blizzard API data for a single character and stores it in
 * services_raw_data. On completion dispatches CompileCharacterDataJob.
 *
 * Runs on the dedicated 'bnet' queue so Blizzard API calls never block
 * Raider.io fetches and vice versa.
 */
class FetchBnetRawDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 180;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.bnet', 'bnet'));
    }

    public function uniqueId(): string
    {
        return (string) $this->character->id;
    }

    public function middleware(): array
    {
        return [new RateLimited('bnet-api')];
    }

    public function handle(CharacterSyncService $syncService): void
    {
        Log::info('FetchBnetRawDataJob: starting Blizzard data fetch.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        try {
            $syncService->syncRawData($this->character, 'bnet');
        } catch (Throwable $e) {
            Log::error('FetchBnetRawDataJob: fatal exception.', [
                'character_id' => $this->character->id,
                'exception'    => $e->getMessage(),
            ]);

            throw $e;
        }

        CompileCharacterDataJob::dispatch($this->character)
            ->onQueue(config('sync.queues.compile', 'compile'));

        Log::info('FetchBnetRawDataJob: fetch complete, CompileCharacterDataJob dispatched.', [
            'character_id' => $this->character->id,
        ]);
    }
}
