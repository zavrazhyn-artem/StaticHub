<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Data\Analysis\RaiderIo\RaiderIoProfileData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RaiderIoService
{
    private const BASE_URL = 'https://raider.io/api/v1/characters/profile';

    private const DEFAULT_FIELDS = 'mythic_plus_scores_by_season:current,mythic_plus_ranks,previous_mythic_plus_ranks,mythic_plus_recent_runs,mythic_plus_best_runs,mythic_plus_weekly_highest_level_runs,mythic_plus_highest_level_runs,gear,talents,raid_progression,raid_achievement_curve,guild';

    /**
     * Fetch character profile from the Raider.io API.
     */
    public function getCharacterProfile(string $region, string $realm, string $name): ?RaiderIoProfileData
    {
        $response = Http::get(self::BASE_URL, [
            'region' => $region,
            'realm' => $realm,
            'name' => $name,
            'fields' => self::DEFAULT_FIELDS,
        ]);

        if ($response->failed()) {
            Log::error('Raider.io API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'character' => $name,
                'realm' => $realm,
                'region' => $region,
            ]);

            return null;
        }

        return RaiderIoProfileData::from($response->json());
    }
}
