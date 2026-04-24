<?php

declare(strict_types=1);

namespace App\Services\Discord;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordApiService
{
    private string $botToken;
    private string $baseUrl = 'https://discord.com/api/v10';

    public function __construct()
    {
        $this->botToken = (string) config('services.discord.bot_token');
    }

    /**
     * Internal request handler with logging.
     * Returns response array on success, false on 404, null on other errors.
     */
    private function makeRequest(string $method, string $endpoint, array $payload = []): array|false|null
    {
        if (empty($this->botToken)) {
            Log::error('Discord bot token is missing in configuration.');
            return null;
        }

        $response = Http::withToken($this->botToken, 'Bot')
            ->{strtolower($method)}($this->baseUrl . $endpoint, $payload);

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($response->status() === 404) {
            Log::warning("Discord message not found on {$method} {$endpoint} (deleted?)");
            return false;
        }

        Log::error("Discord API error on {$method} {$endpoint}", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return null;
    }

    public function getChannels(string $guildId): array
    {
        $result = $this->makeRequest('GET', "/guilds/{$guildId}/channels");

        return is_array($result) ? $result : [];
    }

    public function getRoles(string $guildId): array
    {
        $result = $this->makeRequest('GET', "/guilds/{$guildId}/roles");

        return is_array($result) ? $result : [];
    }

    public function sendMessage(string $channelId, array $payload): ?array
    {
        $result = $this->makeRequest('POST', "/channels/{$channelId}/messages", $payload);

        return is_array($result) ? $result : null;
    }

    /**
     * Send a message and return detailed result including error info.
     */
    public function sendMessageDetailed(string $channelId, array $payload): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'error' => 'Bot token is missing in configuration.'];
        }

        $response = Http::withToken($this->botToken, 'Bot')
            ->post($this->baseUrl . "/channels/{$channelId}/messages", $payload);

        if ($response->successful()) {
            $data = $response->json() ?? [];
            return ['success' => true, 'message_id' => $data['id'] ?? null];
        }

        $body = $response->json() ?? [];

        Log::error("Discord API error on POST /channels/{$channelId}/messages", [
            'status' => $response->status(),
            'body'   => $response->body(),
        ]);

        return [
            'success'    => false,
            'error'      => $body['message'] ?? 'Unknown Discord API error',
            'error_code' => $body['code'] ?? $response->status(),
        ];
    }

    public function updateMessage(string $channelId, string $messageId, array $payload): array|false|null
    {
        return $this->makeRequest('PATCH', "/channels/{$channelId}/messages/{$messageId}", $payload);
    }

    public function deleteMessage(string $channelId, string $messageId): bool
    {
        $result = $this->makeRequest('DELETE', "/channels/{$channelId}/messages/{$messageId}");
        return $result !== null;
    }

    /**
     * Fetch guilds + (optionally) channels and roles for one guild in a single parallel pool.
     * Throws if any required endpoint fails — caller decides how to surface the error.
     *
     * @return array{guilds: array, channels: array, roles: array}
     * @throws \RuntimeException
     */
    public function fetchGuildContext(?string $guildId): array
    {
        if (empty($this->botToken)) {
            throw new \RuntimeException('Discord bot token is missing in configuration.');
        }

        $endpoints = ['guilds' => '/users/@me/guilds'];
        if ($guildId) {
            $endpoints['channels'] = "/guilds/{$guildId}/channels";
            $endpoints['roles']    = "/guilds/{$guildId}/roles";
        }

        $responses = Http::pool(fn (Pool $pool) => collect($endpoints)
            ->map(fn (string $endpoint, string $key) => $pool
                ->as($key)
                ->withToken($this->botToken, 'Bot')
                ->get($this->baseUrl . $endpoint))
            ->all());

        $result = ['guilds' => [], 'channels' => [], 'roles' => []];

        foreach ($endpoints as $key => $endpoint) {
            $response = $responses[$key] ?? null;

            if (!$response instanceof \Illuminate\Http\Client\Response) {
                Log::error("Discord pool transport error on GET {$endpoint}", [
                    'exception' => $response instanceof \Throwable ? $response->getMessage() : 'unknown',
                ]);
                throw new \RuntimeException("Discord API unreachable: GET {$endpoint}");
            }

            if (!$response->successful()) {
                Log::error("Discord pool error on GET {$endpoint}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException("Discord API error {$response->status()} on GET {$endpoint}");
            }

            $result[$key] = $response->json() ?? [];
        }

        return $result;
    }

    /**
     * Resolve which of the given guild IDs the user is a member of, in a single parallel pool.
     * 404 means "not a member" (expected); other failures throw.
     *
     * @return array<string, bool> Map of guildId → membership status.
     * @throws \RuntimeException
     */
    public function fetchMembershipMap(array $guildIds, string $userId): array
    {
        if (empty($guildIds)) {
            return [];
        }

        if (empty($this->botToken)) {
            throw new \RuntimeException('Discord bot token is missing in configuration.');
        }

        $responses = Http::pool(fn (Pool $pool) => collect($guildIds)
            ->map(fn (string $guildId) => $pool
                ->as($guildId)
                ->withToken($this->botToken, 'Bot')
                ->get($this->baseUrl . "/guilds/{$guildId}/members/{$userId}"))
            ->all());

        $map = [];
        foreach ($guildIds as $guildId) {
            $response = $responses[$guildId] ?? null;

            if (!$response instanceof \Illuminate\Http\Client\Response) {
                Log::error("Discord pool transport error on membership lookup for guild {$guildId}", [
                    'exception' => $response instanceof \Throwable ? $response->getMessage() : 'unknown',
                ]);
                throw new \RuntimeException("Discord API unreachable: membership lookup for guild {$guildId}");
            }

            if ($response->successful()) {
                $map[$guildId] = true;
                continue;
            }

            if ($response->status() === 404) {
                $map[$guildId] = false;
                continue;
            }

            Log::error("Discord pool error on membership lookup for guild {$guildId}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("Discord API error {$response->status()} on membership lookup for guild {$guildId}");
        }

        return $map;
    }
}
