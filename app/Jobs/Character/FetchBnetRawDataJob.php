<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Character\BnetSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-contained Blizzard sync for one character: fetch every endpoint we
 * need, update the accumulator columns on `characters`, then atomically
 * merge the compiled bnet slice into character_data / character_weekly_data
 * via JSON_MERGE_PATCH. No services_raw_data, no follow-up Compile job.
 *
 * Runs on the dedicated 'bnet' queue so Blizzard API calls never block
 * Raider.io fetches and vice versa.
 */
class FetchBnetRawDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $backoff = 60;

    public int $uniqueFor = 180;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.bnet', 'bnet'));
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
        return [new RateLimited('bnet-api')];
    }

    public function handle(BnetSyncService $syncService): void
    {
        Log::info('FetchBnetRawDataJob: starting Blizzard data fetch.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        try {
            $syncService->syncCharacter($this->character);
        } catch (Throwable $e) {
            Log::error('FetchBnetRawDataJob: fatal exception.', [
                'character_id' => $this->character->id,
                'exception'    => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('FetchBnetRawDataJob: sync complete.', [
            'character_id' => $this->character->id,
        ]);
    }
}
