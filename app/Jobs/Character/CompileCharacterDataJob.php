<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Roster\RosterCompilerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Compiles a character's raw API data into a frontend-ready DTO
 * and persists it in the characters.compiled_data JSON column.
 *
 * Dispatched by FetchBnetRawDataJob / FetchRioRawDataJob after a successful fetch.
 * Safe to dispatch independently for a manual recompile.
 */
class CompileCharacterDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.compile', 'compile'));
    }

    public function handle(RosterCompilerService $compiler): void
    {
        $compiler->compileAndPersist($this->character);
    }
}
