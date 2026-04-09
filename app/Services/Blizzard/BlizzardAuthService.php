<?php

declare(strict_types=1);

namespace App\Services\Blizzard;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Exception;

class BlizzardAuthService
{
    private const CACHE_KEY = 'blizzard_api_token';

    /** Refresh the token 5 minutes before it actually expires. */
    private const TTL_BUFFER_SECONDS = 300;

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
     * Token is stored in Redis with the TTL returned by Blizzard.
     */
    public function getAccessToken(): string
    {
        $token = Cache::get(self::CACHE_KEY);

        if ($token !== null) {
            return $token;
        }

        return $this->refreshToken();
    }

    /**
     * Force-refresh the token (useful if the current one is rejected).
     */
    public function refreshToken(): string
    {
        $data = $this->fetchTokenData();

        $ttl = max(($data['expires_in'] ?? 86400) - self::TTL_BUFFER_SECONDS, 60);

        Cache::put(self::CACHE_KEY, $data['access_token'], $ttl);

        return $data['access_token'];
    }

    private function fetchTokenData(): array
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->retry(3, 1000, fn ($exception) => $exception instanceof \Illuminate\Http\Client\RequestException
                && $exception->response->status() === 429)
            ->post("https://{$this->region}.battle.net/oauth/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch Blizzard access token: ' . $response->body());
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new Exception('Blizzard OAuth response missing access_token.');
        }

        return $data;
    }
}
