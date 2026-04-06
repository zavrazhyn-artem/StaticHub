<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Character;
use App\Services\Roster\RosterCompilerService;
use App\Tasks\StaticGroup\AssignCharacterRoleTask;
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
    ) {
        $this->onQueue(config('sync.queues.compile', 'compile'));
    }

    public function handle(RosterCompilerService $compiler, AssignCharacterRoleTask $assignTask): void
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

        // Extract active_spec name from raw bnet profile to keep the characters
        // table up-to-date alongside compiled_data.
        $profile    = $rawData->bnet_profile ?? [];
        $activeSpec = $profile['active_spec']['name']
            ?? $profile['active_specialization']['name']
            ?? null;

        $charUpdate = ['compiled_data' => $compiled];
        if ($activeSpec !== null) {
            $charUpdate['active_spec'] = (string) $activeSpec;
        }

        $this->character->update($charUpdate);
        $this->character->refresh();

        // Auto-set main spec for every static this character belongs to
        // (skipped if specs are already configured for a given static).
        $staticIds = $this->character->statics()->pluck('statics.id');
        foreach ($staticIds as $staticId) {
            $assignTask->autoSetMainSpecIfMissing($this->character, (int) $staticId);
        }

        Log::info('CompileCharacterDataJob: compiled_data persisted.', [
            'character_id' => $this->character->id,
        ]);
    }
}
