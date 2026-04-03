<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\SyncCharacterRawDataAction;
use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches raw API data for a single character via SyncCharacterRawDataAction
 * and stores it in the services_raw_data table.
 *
 * On successful completion it immediately dispatches CompileCharacterDataJob
 * to transform the fresh raw data into a compiled frontend payload.
 *
 * Individual API-route failures are handled inside the action (logged,
 * skipped); only fatal infrastructure failures (DB down, etc.) will cause
 * this job to fail and be retried.
 */
class FetchCharacterRawDataJob implements ShouldQueue
{
    use Queueable;

    /** Retry up to 3 times before marking the job as failed. */
    public int $tries = 3;

    /** Wait 60 s between retry attempts. */
    public int $backoff = 60;

    public function __construct(
        public readonly Character $character,
    ) {}

    public function handle(SyncCharacterRawDataAction $action): void
    {
        Log::info('FetchCharacterRawDataJob: starting raw data fetch.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        try {
            // The action handles per-route failures internally (logs + skips).
            // Any exception escaping here is a fatal infrastructure problem.
            $action->execute($this->character);
        } catch (Throwable $e) {
            Log::error('FetchCharacterRawDataJob: action threw a fatal exception.', [
                'character_id' => $this->character->id,
                'exception'    => $e->getMessage(),
            ]);

            // Re-throw so the queue worker marks this attempt as failed
            // and honours the $tries / $backoff policy.
            throw $e;
        }

        // Dispatch compilation regardless of partial route failures —
        // CompileCharacterDataJob will gracefully handle absent columns.
        CompileCharacterDataJob::dispatch($this->character);

        Log::info('FetchCharacterRawDataJob: fetch complete, CompileCharacterDataJob dispatched.', [
            'character_id' => $this->character->id,
        ]);
    }
}
