<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class BlizzardAuthService
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
     * Get the configured region.
     */
    public function getRegion(): string
    {
        return $this->region;
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
}
