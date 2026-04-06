<?php

namespace App\Jobs;

use App\Mappers\BlizzardDataMapper;
use App\Models\Character;
use App\Services\Blizzard\BlizzardCharacterApiService;
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
    public function handle(BlizzardCharacterApiService $blizzardApiService): void
    {
        Log::info("Syncing Bnet data for character: {$this->character->name}");

        $realmSlug = $this->character->realm->slug;
        $region = $this->character->realm->region;
        $characterName = $this->character->name;

        $profile = $blizzardApiService->getCharacterProfileSummary($realmSlug, $characterName);
        $equipment = $blizzardApiService->getCharacterEquipment($region, $realmSlug, $characterName);
        $media = $blizzardApiService->getCharacterMedia($realmSlug, $characterName);
        $mplus = $blizzardApiService->getCharacterMythicKeystoneProfile($realmSlug, $characterName);
        $raids = $blizzardApiService->getCharacterRaidEncounters($realmSlug, $characterName);

        if (!$profile || !$equipment || !$media) {
            Log::error("Failed to fetch full Bnet data for character: {$this->character->name}");
            return;
        }

        $processedData = BlizzardDataMapper::map($profile, $equipment, $media, $mplus ?? [], $raids ?? []);

        $this->character->update([
            'raw_bnet_data' => $processedData->toArray(),
            'item_level' => $processedData->stats['item_level'] ?? $this->character->item_level,
            'equipped_item_level' => $processedData->stats['equipped_item_level'] ?? $this->character->equipped_item_level,
            'active_spec' => is_array($profile['active_spec'])
                ? ($profile['active_spec']['name'] ?? $this->character->active_spec)
                : ($profile['active_spec'] ?? $this->character->active_spec),
            'avatar_url' => $processedData->avatar_url ?? $this->character->avatar_url,
        ]);
    }
}
