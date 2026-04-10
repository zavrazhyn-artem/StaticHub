<?php

declare(strict_types=1);

namespace App\Services\Discord;

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

    public function getGuilds(): array
    {
        $result = $this->makeRequest('GET', '/users/@me/guilds');

        return is_array($result) ? $result : [];
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

    public function getGuildMember(string $guildId, string $userId): ?array
    {
        $result = $this->makeRequest('GET', "/guilds/{$guildId}/members/{$userId}");

        return is_array($result) ? $result : null;
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
}
