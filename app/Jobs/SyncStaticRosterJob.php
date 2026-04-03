<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StaticGroup;
use App\Services\BlizzardApiService;
use App\Mappers\BlizzardDataMapper;
use App\Services\Analysis\RaiderIoService;
use App\Services\Analysis\WclService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStaticRosterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $staticGroup;

    /**
     * Create a new job instance.
     */
    public function __construct(StaticGroup $staticGroup)
    {
        $this->staticGroup = $staticGroup;
    }

    /**
     * Execute the job.
     */
    public function handle(BlizzardApiService $bnetService, RaiderIoService $raiderIoService, WclService $wclService): void
    {
        $charactersToSync = $this->staticGroup->characters()
            ->with('realm')
            ->get();

        foreach ($charactersToSync as $character) {
            try {
                $region = $character->realm->region ?? 'eu';
                $realmSlug = $character->realm->slug;
                $name = $character->name;

                // 1. Bnet Data (Profile, Equipment, Media, M+, Raid)
                $profile = $bnetService->getCharacterProfileSummary($realmSlug, $name);
                $equipment = $bnetService->getCharacterEquipment($region, $realmSlug, $name);
                $media = $bnetService->getCharacterMedia($realmSlug, $name);
                $mplus = $bnetService->getCharacterMythicKeystoneProfile($realmSlug, $name);
                $raids = $bnetService->getCharacterRaidEncounters($realmSlug, $name);

                $processedBnetData = null;
                if ($profile && $equipment && $media) {
                    $processedBnetData = BlizzardDataMapper::map($profile, $equipment, $media, $mplus ?? [], $raids ?? []);
                }

                // 2. Raider.IO Data (M+ Score & Progression)
                $raiderIoData = $raiderIoService->getCharacterProfile($region, $realmSlug, $name);

                // 3. WCL Data (Parses)
                $wclData = $wclService->getCharacterParses($region, $realmSlug, $name);

                // Extract values
                $ilvl = $raiderIoData?->gear->item_level_equipped ?? ($processedBnetData?->stats['equipped_item_level'] ?? null);
                $mythicRating = $raiderIoData?->mythic_plus_scores_by_season->toCollection()->first()?->scores->all;

                // Update character
                $character->update([
                    'ilvl' => $ilvl,
                    'mythic_rating' => $mythicRating,
                    'raw_bnet_data' => $processedBnetData?->toArray(),
                    'raw_raiderio_data' => $raiderIoData?->toArray(),
                    'raw_wcl_data' => $wclData,
                    'avatar_url' => $processedBnetData?->avatar_url ?? $character->avatar_url,
                ]);

                // Rate limiting
                sleep(1);

            } catch (\Exception $e) {
                Log::error("Failed to sync character {$character->name} ({$character->id}): " . $e->getMessage());
                continue;
            }
        }
    }
}
