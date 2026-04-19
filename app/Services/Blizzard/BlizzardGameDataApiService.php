<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use App\Models\Item;
use App\Services\Logging\ApiLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Exception;

class BlizzardGameDataApiService
{
    public function __construct(
        private readonly BlizzardAuthService $authService,
        private readonly ApiLogger $apiLogger,
    ) {}

    private function loggedGet(string $url, array $headers = []): Response
    {
        $startTime = microtime(true);
        $response = Http::withToken($this->authService->getAccessToken())
            ->withHeaders($headers)
            ->timeout(15)
            ->retry(3, 1000, fn ($exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()))
            ->get($url);
        $this->apiLogger->logApiCall('blizzard', $url, 'GET', $response, $startTime);

        return $response;
    }

    /**
     * Fetch all realms for the current region.
     */
    public function getRealms(): array
    {
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/realm/index";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "dynamic-{$region}"]);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/realm/{$realmId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "dynamic-{$region}"]);

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

        $startTime = microtime(true);
        $response = Http::withToken($token)
            ->withHeaders(['Battlenet-Namespace' => "dynamic-{$region}"])
            ->timeout(60)
            ->retry(3, 2000, fn ($exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()))
            ->withOptions(['stream' => true])
            ->get($url);

        $this->apiLogger->logApiCall('blizzard', $url, 'GET', $response, $startTime);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/profession/{$professionId}/skill-tier/{$tierId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/recipe/{$recipeId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

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
        $data = $this->fetchItemData($itemId);

        if (empty($data)) {
            return;
        }

        $name = $data['name']['en_US'] ?? $data['name'] ?? "Item #{$itemId}";
        $iconUrl = $this->fetchItemIcon($itemId, $data);

        Item::query()->updateMetadata($itemId, $name, $iconUrl);
    }

    private function fetchItemData(int $itemId): array
    {
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/item/{$itemId}";

        $response = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

        return $response->successful() ? $response->json() : [];
    }

    private function fetchItemIcon(int $itemId, array $itemData): ?string
    {
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/media/item/{$itemId}";

        $mediaResponse = $this->loggedGet($url, ['Battlenet-Namespace' => "static-{$region}"]);

        if ($mediaResponse->successful()) {
            return $this->extractIconFromAssets($mediaResponse->json('assets', []));
        }

        $fallbackHref = $itemData['media']['key']['href'] ?? null;
        if ($fallbackHref) {
            $fallbackResponse = $this->loggedGet($fallbackHref, ['Battlenet-Namespace' => "static-{$region}"]);

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
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/playable-specialization/{$specId}?namespace=static-{$region}&locale=en_US";

        $response = $this->loggedGet($url);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Get icon URL for a playable specialization from Game Data API.
     */
    public function getPlayableSpecializationIcon(int $specId): ?string
    {
        $region = $this->authService->getRegion();
        $url = "https://{$region}.api.blizzard.com/data/wow/media/playable-specialization/{$specId}?namespace=static-{$region}";

        $response = $this->loggedGet($url);

        return $response->failed() ? null : $this->extractIconFromAssets($response->json('assets', []));
    }
}
