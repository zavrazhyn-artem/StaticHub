<?php

declare(strict_types=1);

namespace App\Tasks\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordApiTask
{
    private string $botToken;
    private string $baseUrl = 'https://discord.com/api/v10';

    public function __construct()
    {
        $this->botToken = (string) config('services.discord.bot_token');
    }

    /**
     * Internal request handler with logging.
     */
    private function makeRequest(string $method, string $endpoint, array $payload = []): ?array
    {
        if (empty($this->botToken)) {
            Log::error('Discord bot token is missing in configuration.');
            return null;
        }

        $response = Http::withToken($this->botToken, 'Bot')
            ->{strtolower($method)}($this->baseUrl . $endpoint, $payload);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Discord API error on {$method} {$endpoint}", [
            'status' => $response->status(),
            'body' => $response->body(),
            'payload' => $payload,
        ]);

        return null;
    }

    public function getGuilds(): array
    {
        return $this->makeRequest('GET', '/users/@me/guilds') ?? [];
    }

    public function getChannels(string $guildId): array
    {
        return $this->makeRequest('GET', "/guilds/{$guildId}/channels") ?? [];
    }

    public function getRoles(string $guildId): array
    {
        return $this->makeRequest('GET', "/guilds/{$guildId}/roles") ?? [];
    }

    public function sendMessage(string $channelId, array $payload): ?array
    {
        return $this->makeRequest('POST', "/channels/{$channelId}/messages", $payload);
    }

    public function updateMessage(string $channelId, string $messageId, array $payload): ?array
    {
        return $this->makeRequest('PATCH', "/channels/{$channelId}/messages/{$messageId}", $payload);
    }
}
