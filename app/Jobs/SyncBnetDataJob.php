<?php

namespace App\Jobs;

use App\Models\Character;
use App\Services\BlizzardApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncBnetDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Character $character)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardApiService $blizzardApiService): void
    {
        Log::info("Syncing Bnet data for character: {$this->character->name}");

        $realmSlug = $this->character->realm->slug;
        $region = $this->character->realm->region;
        $characterName = $this->character->name;

        $profile = $blizzardApiService->getCharacterProfileSummary($realmSlug, $characterName);
        $equipment = $blizzardApiService->getCharacterEquipment($region, $realmSlug, $characterName);
        $avatar = $blizzardApiService->getCharacterAvatar($realmSlug, $characterName);

        $bnetData = [
            'profile' => $profile,
            'equipment' => $equipment,
            'avatar' => $avatar,
            'last_synced_at' => now()->toDateTimeString(),
        ];

        $this->character->update([
            'raw_bnet_data' => $bnetData,
            'item_level' => $profile['average_item_level'] ?? $this->character->item_level,
            'equipped_item_level' => $profile['equipped_item_level'] ?? $this->character->equipped_item_level,
            'active_spec' => $profile['active_spec'] ?? $this->character->active_spec,
            'avatar_url' => $avatar ?? $this->character->avatar_url,
        ]);
    }
}
