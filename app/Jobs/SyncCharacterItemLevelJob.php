<?php

namespace App\Jobs;

use App\Models\Character;
use App\Services\BlizzardApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCharacterItemLevelJob implements ShouldQueue
{
    use Queueable;

    protected Character $character;

    /**
     * Create a new job instance.
     */
    public function __construct(Character $character)
    {
        $this->character = $character;
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardApiService $blizzardApiService): void
    {
        $profileData = $blizzardApiService->getCharacterProfileSummary(
            $this->character->realm->slug,
            $this->character->name
        );

        if ($profileData !== null) {
            $this->character->update([
                'equipped_item_level' => $profileData['equipped_item_level'],
                'active_spec' => $profileData['active_spec'],
            ]);
        }
    }
}
