<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use App\Services\Logging\ApiLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\Pool;
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
     * Fetch all character endpoints concurrently using Http::pool().
     * Returns [column => responseArray|null] for each endpoint.
     *
     * @param array<string> $skip Column names to skip (e.g. ['bnet_completed_quests'])
     */
    public function fetchAllCharacterEndpoints(string $region, string $realmSlug, string $characterName, array $skip = []): array
    {
        $token = $this->authService->getAccessToken();
        $base  = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}";
        $query = "namespace=profile-{$region}&locale=en_US";

        $endpoints = [
            'bnet_profile'                => '',
            'bnet_equipment'              => '/equipment',
            'bnet_media'                  => '/character-media',
            'bnet_mplus'                  => '/mythic-keystone-profile',
            'bnet_raid'                   => '/encounters/raids',
            'bnet_achievement_statistics' => '/achievements/statistics',
            'bnet_completed_quests'       => '/quests/completed',
            'bnet_pvp_summary'            => '/pvp-summary',
            'bnet_reputations'            => '/reputations',
            'bnet_titles'                 => '/titles',
            'bnet_mounts'                 => '/collections/mounts',
            'bnet_pets'                   => '/collections/pets',
        ];

        $toFetch = array_diff_key($endpoints, array_flip($skip));

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $path, string $key) => $pool->as($key)->withToken($token)->get("{$base}{$path}?{$query}"),
            array_values($toFetch),
            array_keys($toFetch),
        ));

        $results = [];
        foreach ($toFetch as $key => $_) {
            $response = $responses[$key] ?? null;
            $results[$key] = ($response instanceof \Illuminate\Http\Client\Response && $response->successful())
                ? $response->json()
                : null;
        }

        return $results;
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
     * Fetch avatars for multiple characters concurrently.
     * Returns [index => avatarUrl|null].
     */
    public function fetchAvatarsBatch(array $characters): array
    {
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (array $char, int $idx) => $pool->as((string) $idx)
                ->withToken($token)
                ->get("https://{$region}.api.blizzard.com/profile/wow/character/"
                    . strtolower($char['realm_slug']) . '/' . mb_strtolower($char['name'])
                    . "/character-media?namespace=profile-{$region}&locale=en_US"),
            $characters,
            array_keys($characters),
        ));

        $results = [];
        foreach ($characters as $idx => $char) {
            $response = $responses[(string) $idx] ?? null;
            $results[$idx] = ($response instanceof \Illuminate\Http\Client\Response && $response->successful())
                ? $this->extractAvatarUrl($response->json('assets', []))
                : null;
        }

        return $results;
    }

    /**
     * Fetch profile summaries for multiple characters concurrently.
     * Returns [index => profileArray|null].
     */
    public function fetchProfilesBatch(array $characters): array
    {
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (array $char, int $idx) => $pool->as((string) $idx)
                ->withToken($token)
                ->get("https://{$region}.api.blizzard.com/profile/wow/character/"
                    . strtolower($char['realm_slug']) . '/' . mb_strtolower($char['name'])
                    . "?namespace=profile-{$region}&locale=en_US"),
            $characters,
            array_keys($characters),
        ));

        $results = [];
        foreach ($characters as $idx => $char) {
            $response = $responses[(string) $idx] ?? null;
            $results[$idx] = ($response instanceof \Illuminate\Http\Client\Response && $response->successful())
                ? $response->json()
                : null;
        }

        return $results;
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
