<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use App\Services\Logging\ApiLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BlizzardCharacterApiService
{
    public function __construct(
        private readonly BlizzardAuthService $authService,
        private readonly ApiLogger $apiLogger,
    ) {}

    private function loggedGet(string $url, ?string $token = null, array $headers = []): Response
    {
        $startTime = microtime(true);
        $response = Http::withToken($token ?? $this->authService->getAccessToken())
            ->withHeaders($headers)
            ->get($url);
        $this->apiLogger->logApiCall('blizzard', $url, 'GET', $response, $startTime);

        return $response;
    }

    /**
     * Get the authenticated user's WoW characters.
     */
    public function getUserCharacters(string $userAccessToken): array
    {
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/profile/user/wow?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url, $userAccessToken);

        if ($response->failed()) {
            return [];
        }

        return $this->filterAndMapCharacters($response->json('wow_accounts', []));
    }

    private function filterAndMapCharacters(array $accounts): array
    {
        $allCharacters = [];

        foreach ($accounts as $account) {
            $characters = $account['characters'] ?? [];
            foreach ($characters as $character) {
                // Filter only level 80 characters (Midnight max level).
                if (($character['level'] ?? 0) >= 80) {
                    $allCharacters[] = [
                        'id' => $character['id'],
                        'name' => $character['name'],
                        'realm' => $character['realm']['name'],
                        'realm_slug' => $character['realm']['slug'],
                        'playable_class' => $character['playable_class']['name'],
                        'playable_race' => $character['playable_race']['name'],
                        'level' => $character['level'],
                    ];
                }
            }
        }

        return $allCharacters;
    }

    /**
     * Get the profile summary for a character.
     */
    public function getCharacterProfileSummary(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get the equipment for a character.
     */
    public function getCharacterEquipment(string $region, string $realmSlug, string $characterName): ?array
    {
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/equipment?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get the media for a character.
     */
    public function getCharacterMedia(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/character-media?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get the specializations for a character.
     */
    public function getCharacterSpecializations(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/specializations?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get Mythic Keystone Profile for a character.
     */
    public function getCharacterMythicKeystoneProfile(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/mythic-keystone-profile?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get Raid Encounters for a character.
     */
    public function getCharacterRaidEncounters(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/encounters/raids?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get achievement statistics for a character.
     * Contains delve tier completion counts used for World vault slot calculation.
     */
    public function getCharacterAchievementStatistics(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/achievements/statistics?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get completed quests for a character (used for weekly quest tracking, Prey, etc.).
     */
    public function getCharacterCompletedQuests(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/quests/completed?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get PvP summary for a character.
     */
    public function getCharacterPvpSummary(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/pvp-summary?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get PvP bracket data (2v2, 3v3, rbg, shuffle-*).
     */
    public function getCharacterPvpBracket(string $realmSlug, string $characterName, string $bracket): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/pvp-bracket/{$bracket}?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get reputations for a character.
     */
    public function getCharacterReputations(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/reputations?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get titles for a character.
     */
    public function getCharacterTitles(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/titles?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get mount collection for a character.
     */
    public function getCharacterMounts(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/collections/mounts?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Get pet collection for a character.
     */
    public function getCharacterPets(string $realmSlug, string $characterName): ?array
    {
        $region = $this->authService->getRegion();
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/collections/pets?namespace=profile-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $response->json();
    }

    /**
     * Fetch character avatar from Blizzard API.
     */
    public function getCharacterAvatar(string $realmSlug, string $characterName): ?string
    {
        $region = $this->authService->getRegion();
        $characterNameLower = mb_strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterNameLower}/character-media";

        $response = $this->loggedGet($url, null, ['Battlenet-Namespace' => "profile-{$region}"]);

        if ($response->failed()) {
            return null;
        }

        return $this->extractAvatarUrl($response->json('assets', []));
    }

    private function extractAvatarUrl(array $assets): ?string
    {
        foreach ($assets as $asset) {
            if (($asset['key'] ?? '') === 'avatar') {
                return $asset['value'];
            }
        }

        return null;
    }

    /**
     * Fetch character equipment and M+ progression from the WoW Profile API.
     */
    public function getCharacterEquipmentExtended(string $region, string $realmSlug, string $characterName): ?array
    {
        $realmSlug = strtolower($realmSlug);
        $characterName = mb_strtolower($characterName);

        $equipment = $this->fetchRawEquipment($region, $realmSlug, $characterName);
        $mPlus = $this->fetchRawMPlusProgression($region, $realmSlug, $characterName);

        if ($mPlus) {
            $equipment = $equipment ?? [];
            $equipment['mythic_plus_progression'] = $mPlus;
        }

        return $equipment;
    }

    private function fetchRawEquipment(string $region, string $realmSlug, string $characterName): ?array
    {
        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/equipment";

        $response = $this->loggedGet($url, null, ['Battlenet-Namespace' => "profile-{$region}"]);

        return $response->successful() ? $response->json() : null;
    }

    private function fetchRawMPlusProgression(string $region, string $realmSlug, string $characterName): ?array
    {
        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/mythic-plus-progression/summary";

        $response = $this->loggedGet($url, null, ['Battlenet-Namespace' => "profile-{$region}"]);

        return $response->successful() ? $response->json() : null;
    }
}
