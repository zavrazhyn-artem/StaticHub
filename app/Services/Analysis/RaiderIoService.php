<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Data\Analysis\RaiderIo\RaiderIoProfileData;
use App\Services\Logging\ApiLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RaiderIoService
{
    private const BASE_URL = 'https://raider.io/api/v1/characters/profile';

    private const DEFAULT_FIELDS = 'mythic_plus_scores_by_season:current,mythic_plus_ranks,previous_mythic_plus_ranks,mythic_plus_recent_runs,mythic_plus_best_runs,mythic_plus_weekly_highest_level_runs,mythic_plus_highest_level_runs,gear,talents,raid_progression,raid_achievement_curve,guild';

    public function __construct(
        private readonly ApiLogger $apiLogger,
    ) {}

    /**
     * Fetch character profile from the Raider.io API.
     */
    public function getCharacterProfile(string $region, string $realm, string $name): ?RaiderIoProfileData
    {
        $startTime = microtime(true);

        $response = Http::get(self::BASE_URL, [
            'region' => $region,
            'realm' => $realm,
            'name' => $name,
            'fields' => self::DEFAULT_FIELDS,
        ]);

        $this->apiLogger->logApiCall('raiderio', self::BASE_URL, 'GET', $response, $startTime);

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
