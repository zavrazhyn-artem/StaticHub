<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Helpers\DiscordWebhookBuilder;
use App\Models\StaticGroup;
use App\Tasks\Discord\SendDiscordWebhookTask;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordWebhookService
{
    public function __construct(
        private readonly SendDiscordWebhookTask $sendWebhookTask
    ) {}

    /**
     * Send an automatic service notification to the static's webhook.
     * Respects the webhook_muted flag — returns false silently when muted.
     *
     * Use this for all automated messages (AI analysis done, roster alerts, etc.).
     */
    public function sendNotification(StaticGroup $staticGroup, array $payload): bool
    {
        if ($staticGroup->automation_settings['webhook_muted'] ?? false) {
            return false;
        }

        $webhookUrl = $staticGroup->discord_webhook_url;

        if (empty($webhookUrl)) {
            return false;
        }

        return $this->sendWebhookTask->run($webhookUrl, $payload);
    }

    /**
     * Send a test message to the static's webhook URL.
     * Always sends regardless of mute setting — intentional user action.
     * Returns the Discord message ID on success so the caller can offer a delete option.
     */
    public function sendTestMessage(StaticGroup $staticGroup): ?string
    {
        $webhookUrl = $staticGroup->discord_webhook_url;

        if (empty($webhookUrl)) {
            Log::warning('Discord Webhook URL is not configured for static.', ['static_id' => $staticGroup->id]);
            return null;
        }

        $payload = DiscordWebhookBuilder::buildTestMessagePayload();

        // Append ?wait=true so Discord returns the created message (including its ID).
        $response = Http::post($webhookUrl . '?wait=true', $payload);

        if ($response->successful()) {
            return (string) ($response->json('id') ?? '');
        }

        Log::error('Discord Webhook test failed', [
            'static_id' => $staticGroup->id,
            'status'    => $response->status(),
            'body'      => $response->body(),
        ]);

        return null;
    }

    /**
     * Delete a previously sent webhook message.
     */
    public function deleteMessage(StaticGroup $staticGroup, string $messageId): bool
    {
        $webhookUrl = $staticGroup->discord_webhook_url;

        if (empty($webhookUrl)) {
            return false;
        }

        [$webhookId, $webhookToken] = $this->parseWebhookUrl($webhookUrl);

        if (!$webhookId || !$webhookToken) {
            return false;
        }

        $response = Http::delete(
            "https://discord.com/api/v10/webhooks/{$webhookId}/{$webhookToken}/messages/{$messageId}"
        );

        return $response->successful() || $response->status() === 404;
    }

    /**
     * Resolve the channel name for the given webhook URL by calling Discord's webhook endpoint.
     * Returns ['channel_id' => ..., 'channel_name' => ..., 'guild_id' => ...] or null on failure.
     *
     * @return array{channel_id: string, channel_name: string, guild_id: string}|null
     */
    public function resolveWebhookChannel(string $webhookUrl): ?array
    {
        [$webhookId, $webhookToken] = $this->parseWebhookUrl($webhookUrl);

        if (!$webhookId || !$webhookToken) {
            return null;
        }

        // GET /webhooks/{id}/{token} — no bot auth required.
        $webhookInfo = Http::get("https://discord.com/api/v10/webhooks/{$webhookId}/{$webhookToken}")->json();

        if (empty($webhookInfo['channel_id'])) {
            return null;
        }

        $channelId = (string) $webhookInfo['channel_id'];
        $guildId   = (string) ($webhookInfo['guild_id'] ?? '');

        // GET /channels/{channel_id} — requires bot token.
        $botToken = config('services.discord.bot_token');
        $channelInfo = Http::withToken($botToken, 'Bot')
            ->get("https://discord.com/api/v10/channels/{$channelId}")
            ->json();

        $channelName = (string) ($channelInfo['name'] ?? $channelId);

        return [
            'channel_id'   => $channelId,
            'channel_name' => $channelName,
            'guild_id'     => $guildId,
        ];
    }

    // -----------------------------------------------------------------------

    /**
     * Parse a Discord webhook URL and return [webhookId, webhookToken].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseWebhookUrl(string $url): array
    {
        if (preg_match('#/webhooks/(\d+)/([^/?]+)#', $url, $m)) {
            return [$m[1], $m[2]];
        }

        return [null, null];
    }
}
