<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ExecuteWclGraphqlTask
{
    protected string $baseUrl = 'https://www.warcraftlogs.com/api/v2/client';
    protected ?string $accessToken = null;

    public function run(string $query, array $variables = []): array
    {
        $response = Http::withToken($this->getAccessToken())->post($this->baseUrl, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $data = $response->json();
        if (isset($data['errors'])) {
            throw new \Exception('WCL GraphQL Error: ' . json_encode($data['errors']));
        }

        return $data['data'] ?? [];
    }

    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $clientId = config('services.wcl.public_key');
        $clientSecret = config('services.wcl.private_key');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Warcraft Logs API credentials missing.');
        }

        $token = Cache::get('wcl_access_token');
        if (is_string($token)) {
            return $this->accessToken = $token;
        }

        $response = Http::asForm()->post('https://www.warcraftlogs.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $token = $response->json('access_token');
        Cache::put('wcl_access_token', $token, 3600);

        return $this->accessToken = $token;
    }
}
