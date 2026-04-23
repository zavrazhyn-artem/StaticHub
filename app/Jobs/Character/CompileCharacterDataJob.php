<?php

declare(strict_types=1);

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Roster\RosterCompilerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Compiles a character's raw API data into frontend-ready DTOs
 * and persists them in characters.character_data + character_weekly_data.
 *
 * Dispatched by FetchBnetRawDataJob after a successful fetch.
 * Safe to dispatch independently for a manual recompile.
 */
class CompileCharacterDataJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 30;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly Character $character,
    ) {
        $this->onQueue(config('sync.queues.compile', 'compile'));
    }

    public function uniqueId(): string
    {
        return (string) $this->character->id;
    }

    public function handle(RosterCompilerService $compiler): void
    {
        $compiler->compileAndPersist($this->character);
    }
}
