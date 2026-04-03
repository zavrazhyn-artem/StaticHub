<?php

namespace App\Jobs;

use App\Models\Character;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncRioDataJob implements ShouldQueue
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
    public function handle(): void
    {
        Log::info("Syncing Raider.io data for character: {$this->character->name}");

        $region = $this->character->realm->region;
        $realm = $this->character->realm->slug;
        $name = $this->character->name;

        $url = "https://raider.io/api/v1/characters/profile?region={$region}&realm={$realm}&name={$name}&fields=mythic_plus_scores_by_season:current,raid_progression,gear";

        $response = Http::get($url);

        if ($response->successful()) {
            $data = $response->json();

            $this->character->update([
                'raw_raiderio_data' => $data,
                'mythic_rating' => $data['mythic_plus_scores_by_season'][0]['scores']['all'] ?? $this->character->mythic_rating,
            ]);
        } else {
            Log::warning("Failed to fetch Raider.io data for {$this->character->name}: " . $response->body());
        }
    }
}
