<?php

namespace App\Jobs\Character;

use App\Models\Character;
use App\Services\Character\CharacterSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCharacterItemLevelJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Character $character
    ) {}

    public function handle(CharacterSyncService $syncService): void
    {
        $syncService->syncProfileAndSpec($this->character);
    }
}
