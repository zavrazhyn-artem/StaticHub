<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Character;
use App\Services\Roster\RosterCompilerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Reads a character's ServiceRawData record, compiles it into a
 * CompiledRosterMemberDTO via RosterCompilerService, and persists the result
 * as a plain array in the characters.compiled_data JSON column.
 *
 * This job is always dispatched by FetchCharacterRawDataJob after a successful
 * raw-data fetch. It is safe to dispatch independently for a manual recompile.
 */
class CompileCharacterDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Character $character,
    ) {}

    public function handle(RosterCompilerService $compiler): void
    {
        Log::info('CompileCharacterDataJob: starting compilation.', [
            'character_id'   => $this->character->id,
            'character_name' => $this->character->name,
        ]);

        // Always reload from DB — the model instance carried by the job may be
        // stale relative to what FetchCharacterRawDataJob just persisted.
        $rawData = $this->character->serviceRawData()->first();

        if ($rawData === null) {
            Log::warning('CompileCharacterDataJob: no ServiceRawData row found, skipping.', [
                'character_id' => $this->character->id,
            ]);
            return;
        }

        $dto = $compiler->compile($rawData);

        // CompiledRosterMemberDTO is a plain readonly class with no toArray().
        // json_encode → json_decode round-trip reliably serialises all public
        // properties (scalars + arrays) without reflection overhead.
        /** @var array<string,mixed> $compiled */
        $compiled = json_decode((string) json_encode($dto), associative: true);

        $this->character->update(['compiled_data' => $compiled]);

        Log::info('CompileCharacterDataJob: compiled_data persisted.', [
            'character_id' => $this->character->id,
        ]);
    }
}
