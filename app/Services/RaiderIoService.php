<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RaiderIoService
{
    /**
     * Fetch character profile from the Raider.io API.
     */
    public function getCharacterProfile(string $region, string $realm, string $name): ?array
    {
        $response = Http::get("https://raider.io/api/v1/characters/profile", [
            'region' => $region,
            'realm' => $realm,
            'name' => $name,
            'fields' => 'mythic_plus_scores_by_season:current,gear,raid_progression,mythic_plus_weekly_highest_level_runs',
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }
}
