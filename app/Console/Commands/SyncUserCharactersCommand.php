<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Character\FetchBnetRawDataJob;
use App\Jobs\Character\FetchRioRawDataJob;
use App\Models\Character;
use App\Models\User;
use App\Services\Character\CharacterSyncService;
use Illuminate\Console\Command;

class SyncUserCharactersCommand extends Command
{
    protected $signature = 'characters:sync
        {user : User ID to sync characters for}
        {--character= : Optional character ID to sync only one character}';

    protected $description = 'Dispatch raw data sync jobs for all (or one) characters of a user, simulating the Sync Battle.net button.';

    public function handle(CharacterSyncService $syncService): int
    {
        $user = User::find($this->argument('user'));

        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        $query = Character::query()->belongingTo($user->id);

        if ($charId = $this->option('character')) {
            $query->where('id', $charId);
        }

        $characters = $query->get();

        if ($characters->isEmpty()) {
            $this->warn('No characters found.');
            return self::SUCCESS;
        }

        foreach ($characters as $character) {
            $syncService->syncProfileAndSpec($character);
            FetchBnetRawDataJob::dispatch($character);
            FetchRioRawDataJob::dispatch($character);
            $this->line("Dispatched sync for {$character->name} (ID: {$character->id})");
        }

        $this->info("Dispatched jobs for {$characters->count()} character(s).");

        return self::SUCCESS;
    }
}
