<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\Character\BnetSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCharacterNow extends Command
{
    protected $signature = 'characters:sync-now
        {target : Character ID OR User ID (with --user flag)}
        {--user : Treat target as user ID and sync all in-static characters}';

    protected $description = 'Run BnetSyncService::syncCharacter() inline — bypasses queue/lock/dispatcher for local dev.';

    public function handle(BnetSyncService $syncService): int
    {
        $target = (int) $this->argument('target');

        $characters = $this->option('user')
            ? Character::with('realm')->belongingTo($target)->whereHas('statics')->get()
            : Character::with('realm')->where('id', $target)->get();

        if ($characters->isEmpty()) {
            $this->error('No matching characters found.');
            return self::FAILURE;
        }

        foreach ($characters as $character) {
            $this->info("Syncing {$character->name} (#{$character->id})…");
            $start = microtime(true);

            try {
                $syncService->syncCharacter($character);
            } catch (Throwable $e) {
                $this->error("  failed: {$e->getMessage()}");
                continue;
            }

            $elapsed = number_format(microtime(true) - $start, 1);
            $character->refresh();
            $talent = $character->character_data['talent_loadout_code'] ?? 'NULL';
            $bySpec = $character->character_data['talent_loadout_codes_by_spec'] ?? [];

            $this->line("  done in {$elapsed}s. active={$talent}, specs=" . count($bySpec));
        }

        return self::SUCCESS;
    }
}
