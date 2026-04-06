<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class BlizzardApiService
{
    private string $clientId;
    private string $clientSecret;
    private string $region;

    public function __construct()
    {
        $this->clientId = (string) config('services.battlenet.client_id');
        $this->clientSecret = (string) config('services.battlenet.client_secret');
        $this->region = (string) config('services.battlenet.region', 'eu');
    }

    /**
     * Get OAuth2 Access Token using Client Credentials flow.
     */
    public function getAccessToken(): string
    {
        return Cache::remember('blizzard_api_token', 86400, fn() => $this->fetchNewToken());
    }

    private function fetchNewToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post("https://{$this->region}.battle.net/oauth/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch Blizzard access token: ' . $response->body());
        }

        return (string) $response->json('access_token');
    }

    /**
     * Fetch user characters and guilds they lead.
     */
    public function getUserGuilds(string $userToken): array
    {
        $data = $this->fetchUserProfile($userToken);
        if (empty($data)) {
            return [];
        }

        return $this->parseGuildsFromAccounts($data['wow_accounts'] ?? []);
    }

    private function fetchUserProfile(string $userToken): array
    {
        $response = Http::withToken($userToken)
            ->get("https://{$this->region}.api.blizzard.com/profile/user/wow");

        return $response->successful() ? $response->json() : [];
    }

    private function parseGuildsFromAccounts(array $accounts): array
    {
        $guilds = [];

        foreach ($accounts as $account) {
            $characters = $account['characters'] ?? [];
            foreach ($characters as $character) {
                if (isset($character['guild'])) {
                    $guildKey = "{$character['guild']['name']}-{$character['realm']['slug']}";
                    if (!isset($guilds[$guildKey])) {
                        $guilds[$guildKey] = [
                            'name' => $character['guild']['name'],
                            'realm' => $character['realm']['name'],
                            'realm_slug' => $character['realm']['slug'],
                            'character_name' => $character['name'],
                        ];
                    }
                }
            }
        }

        return array_values($guilds);
    }

    /**
     * Check if a character is the leader of a guild.
     */
    public function isGuildLeader(string $userToken, string $realmSlug, string $guildSlug): bool
    {
        $roster = $this->fetchGuildRoster($userToken, $realmSlug, $guildSlug);

        return $this->hasLeaderInRoster($roster);
    }

    private function fetchGuildRoster(string $userToken, string $realmSlug, string $guildSlug): array
    {
        $response = Http::withToken($userToken)
            ->withHeaders(['Battlenet-Namespace' => "profile-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/guild/{$realmSlug}/{$guildSlug}/roster");

        return $response->successful() ? $response->json('members', []) : [];
    }

    private function hasLeaderInRoster(array $members): bool
    {
        foreach ($members as $member) {
            // Rank 0 is usually Guild Master
            if (($member['rank'] ?? -1) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the authenticated user's WoW characters.
     */
    public function getUserCharacters(string $userAccessToken): array
    {
        $response = Http::withToken($userAccessToken)
            ->get("https://{$this->region}.api.blizzard.com/profile/user/wow?namespace=profile-{$this->region}&locale=en_US");

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
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $url = "https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}?namespace=profile-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get the equipment for a character.
     */
    public function getCharacterEquipment(string $region, string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $url = "https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/equipment?namespace=profile-{$region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get the media for a character.
     */
    public function getCharacterMedia(string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $url = "https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/character-media?namespace=profile-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get the specializations for a character.
     */
    public function getCharacterSpecializations(string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $url = "https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/specializations?namespace=profile-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get Mythic Keystone Profile for a character.
     */
    public function getCharacterMythicKeystoneProfile(string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        // Зверни увагу на зміну сегмента в URL: mythic-keystone-profile
        $url = "https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/mythic-keystone-profile?namespace=profile-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Get Raid Encounters for a character.
     */
    public function getCharacterRaidEncounters(string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $url = "https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/encounters/raids?namespace=profile-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Fetch character avatar from Blizzard API.
     */
    public function getCharacterAvatar(string $realmSlug, string $characterName): ?string
    {
        $token = $this->getAccessToken();
        $characterNameLower = strtolower($characterName);

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "profile-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterNameLower}/character-media");

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
     * Fetch all realms for the current region.
     */
    public function getRealms(): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/realm/index");

        if ($response->failed()) {
            throw new Exception('Failed to fetch realms from Blizzard API: ' . $response->body());
        }

        return $response->json('realms', []);
    }

    /**
     * Fetch all realms for the current region.
     */
    public function test(): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$this->region}"])
            ->get("https://eu.api.blizzard.com/data/wow/mythic-keystone/period/1057?namespace=dynamic-eu");

        if ($response->failed()) {
            throw new Exception('Failed to fetch realms from Blizzard API: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch detailed realm data.
     */
    public function getRealmDetails(int $realmId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/realm/{$realmId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch realm details for ID {$realmId}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Get a stream for the commodities dump.
     */
    public function getCommoditiesStream()
    {
        $token = $this->getAccessToken();
        $url = "https://{$this->region}.api.blizzard.com/data/wow/auctions/commodities";

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$this->region}"])
            ->withOptions(['stream' => true])
            ->get($url);

        if ($response->failed()) {
            throw new Exception('Failed to fetch commodities: ' . $response->body());
        }

        return $response->toPsrResponse()->getBody()->detach();
    }

    /**
     * Fetch the profession tiers and find the one for "Midnight" (or latest).
     */
    public function getMidnightProfessionTier(int $professionId): ?int
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/{$professionId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch profession tiers for ID {$professionId}: " . $response->body());
        }

        return $this->findMidnightTierId($response->json('skill_tiers', []));
    }

    private function findMidnightTierId(array $tiers): ?int
    {
        foreach ($tiers as $tier) {
            if (str_contains($tier['name']['en_US'] ?? '', 'Midnight')) {
                return (int) $tier['id'];
            }
        }

        if (!empty($tiers)) {
            return (int) max(array_column($tiers, 'id'));
        }

        return null;
    }

    /**
     * Fetch all recipe references for a given profession and skill tier by iterating through categories.
     */
    public function getRecipesFromTier(int $professionId, int $tierId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch recipes: " . $response->body());
        }

        return $this->extractRecipesFromTierData($response->json());
    }

    private function extractRecipesFromTierData(array $data): array
    {
        $recipes = [];
        $categories = $data['categories'] ?? [];

        foreach ($categories as $category) {
            foreach ($category['recipes'] ?? [] as $recipe) {
                $recipes[] = $recipe;
            }
        }

        return empty($recipes) ? ($data['recipes'] ?? []) : $recipes;
    }

    /**
     * Fetch all categories and recipes for a given profession and skill tier.
     */
    public function getTierDetails(int $professionId, int $tierId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch tier details: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch detailed recipe data.
     */
    public function getRecipeDetails(int $recipeId): array
    {
        $token = $this->getAccessToken();
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/recipe/{$recipeId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch recipe details for ID {$recipeId}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch character equipment and M+ progression from the WoW Profile API.
     */
    public function getCharacterEquipmentExtended(string $region, string $realmSlug, string $characterName): ?array
    {
        $token = $this->getAccessToken();
        $realmSlug = strtolower($realmSlug);
        $characterName = strtolower($characterName);

        $equipment = $this->fetchRawEquipment($region, $token, $realmSlug, $characterName);
        $mPlus = $this->fetchRawMPlusProgression($region, $token, $realmSlug, $characterName);

        if ($mPlus) {
            $equipment = $equipment ?? [];
            $equipment['mythic_plus_progression'] = $mPlus;
        }

        return $equipment;
    }

    private function fetchRawEquipment(string $region, string $token, string $realmSlug, string $characterName): ?array
    {
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "profile-{$region}"])
            ->get("https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/equipment");

        return $response->successful() ? $response->json() : null;
    }

    private function fetchRawMPlusProgression(string $region, string $token, string $realmSlug, string $characterName): ?array
    {
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "profile-{$region}"])
            ->get("https://{$region}.api.blizzard.com/profile/wow/character/{$realmSlug}/{$characterName}/mythic-plus-progression/summary");

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Fetch the real name, icon, and quality from the /data/wow/item/{id} endpoint and sync to DB.
     */
    public function syncItemMetadata(int $itemId): void
    {
        $token = $this->getAccessToken();
        $data = $this->fetchItemData($itemId, $token);

        if (empty($data)) {
            return;
        }

        $name = $data['name']['en_US'] ?? $data['name'] ?? "Item #{$itemId}";
        $iconUrl = $this->fetchItemIcon($itemId, $token, $data);

        Item::query()->updateMetadata($itemId, $name, $iconUrl);
    }

    private function fetchItemData(int $itemId, string $token): array
    {
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/item/{$itemId}");

        return $response->successful() ? $response->json() : [];
    }

    private function fetchItemIcon(int $itemId, string $token, array $itemData): ?string
    {
        $mediaResponse = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
            ->get("https://{$this->region}.api.blizzard.com/data/wow/media/item/{$itemId}");

        if ($mediaResponse->successful()) {
            return $this->extractIconFromAssets($mediaResponse->json('assets', []));
        }

        $fallbackHref = $itemData['media']['key']['href'] ?? null;
        if ($fallbackHref) {
            $fallbackResponse = Http::withToken($token)
                ->withHeaders(['Battlenet-Namespace' => "static-{$this->region}"])
                ->get($fallbackHref);

            if ($fallbackResponse->successful()) {
                return $this->extractIconFromAssets($fallbackResponse->json('assets', []));
            }
        }

        return null;
    }

    private function extractIconFromAssets(array $assets): ?string
    {
        foreach ($assets as $asset) {
            if (($asset['key'] ?? '') === 'icon') {
                return $asset['value'];
            }
        }

        return null;
    }

    /**
     * Get playable specialization data from Game Data API.
     * Returns name, playable_class.name, role.type.
     */
    public function getPlayableSpecialization(int $specId): ?array
    {
        $token = $this->getAccessToken();
        $url = "https://{$this->region}.api.blizzard.com/data/wow/playable-specialization/{$specId}?namespace=static-{$this->region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Get icon URL for a playable specialization from Game Data API.
     */
    public function getPlayableSpecializationIcon(int $specId): ?string
    {
        $token = $this->getAccessToken();
        $url = "https://{$this->region}.api.blizzard.com/data/wow/media/playable-specialization/{$specId}?namespace=static-{$this->region}";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $this->extractIconFromAssets($response->json('assets', []));
    }
}
