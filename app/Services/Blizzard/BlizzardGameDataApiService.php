<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use App\Models\Item;
use Illuminate\Support\Facades\Http;
use Exception;

class BlizzardGameDataApiService
{
    public function __construct(
        private readonly BlizzardAuthService $authService,
    ) {}

    /**
     * Fetch all realms for the current region.
     */
    public function getRealms(): array
    {
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/realm/index");

        if ($response->failed()) {
            throw new Exception('Failed to fetch realms from Blizzard API: ' . $response->body());
        }

        return $response->json('realms', []);
    }

    /**
     * Fetch detailed realm data.
     */
    public function getRealmDetails(int $realmId): array
    {
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/realm/{$realmId}");

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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/auctions/commodities";

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$region}"])
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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}");

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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}");

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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}");

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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/recipe/{$recipeId}");

        if ($response->failed()) {
            throw new Exception("Failed to fetch recipe details for ID {$recipeId}: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Fetch the real name, icon, and quality from the /data/wow/item/{id} endpoint and sync to DB.
     */
    public function syncItemMetadata(int $itemId): void
    {
        $token = $this->authService->getAccessToken();
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
        $region = $this->authService->getRegion();

        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/item/{$itemId}");

        return $response->successful() ? $response->json() : [];
    }

    private function fetchItemIcon(int $itemId, string $token, array $itemData): ?string
    {
        $region = $this->authService->getRegion();

        $mediaResponse = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
            ->get("https://{$region}.api.blizzard.com/data/wow/media/item/{$itemId}");

        if ($mediaResponse->successful()) {
            return $this->extractIconFromAssets($mediaResponse->json('assets', []));
        }

        $fallbackHref = $itemData['media']['key']['href'] ?? null;
        if ($fallbackHref) {
            $fallbackResponse = Http::withToken($token)
                ->withHeaders(['Battlenet-Namespace' => "static-{$region}"])
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
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/playable-specialization/{$specId}?namespace=static-{$region}&locale=en_US";

        $response = Http::withToken($token)->get($url);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Get icon URL for a playable specialization from Game Data API.
     */
    public function getPlayableSpecializationIcon(int $specId): ?string
    {
        $token = $this->authService->getAccessToken();
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/media/playable-specialization/{$specId}?namespace=static-{$region}";

        $response = Http::withToken($token)->get($url);

        if ($response->failed()) {
            return null;
        }

        return $this->extractIconFromAssets($response->json('assets', []));
    }
}
