<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Services\BlizzardApiService;
use App\Services\RaiderIoService;
use App\Services\WclService;
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

                // 1. Bnet Data (Equipment & ilvl)
                $bnetData = $bnetService->getCharacterEquipment($region, $realmSlug, $name);

                // 2. Raider.IO Data (M+ Score & Progression)
                $raiderIoData = $raiderIoService->getCharacterProfile($region, $realmSlug, $name);

                // 3. WCL Data (Parses)
                $wclData = $wclService->getCharacterParses($region, $realmSlug, $name);

                // Extract values
                $ilvl = null;
                if ($raiderIoData && isset($raiderIoData['gear']['item_level_equipped'])) {
                    $ilvl = $raiderIoData['gear']['item_level_equipped'];
                }

                $mythicRating = null;
                if ($raiderIoData && isset($raiderIoData['mythic_plus_scores_by_season'])) {
                    // Public API returns an array or object depending on fields
                    $scores = $raiderIoData['mythic_plus_scores_by_season'] ?? [];
                    if (!empty($scores)) {
                        $mythicRating = $scores[0]['scores']['all'] ?? null;
                    }
                }

                // Update character
                $character->update([
                    'ilvl' => $ilvl,
                    'mythic_rating' => $mythicRating,
                    'raw_bnet_data' => $bnetData,
                    'raw_raiderio_data' => $raiderIoData,
                    'raw_wcl_data' => $wclData,
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
