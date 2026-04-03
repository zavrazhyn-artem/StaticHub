<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\StaticGroup;
use App\Helpers\DiscordWebhookBuilder;
use App\Tasks\Discord\SendDiscordWebhookTask;
use Illuminate\Support\Facades\Log;

class DiscordWebhookService
{
    private string $webhookUrl;

    public function __construct(
        private readonly SendDiscordWebhookTask $sendWebhookTask
    ) {
        $this->webhookUrl = (string) (config('services.discord.webhook_url'));
    }

    /**
     * Send a sync report to Discord using rich embeds.
     */
    public function sendSyncReport(StaticGroup $staticGroup, array $stats = []): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Discord Webhook URL is not configured.');
            return false;
        }

        $payload = DiscordWebhookBuilder::buildSyncReportPayload($staticGroup, $stats);

        return $this->sendWebhookTask->run($this->webhookUrl, $payload);
    }

    /**
     * Send a test message to verify the webhook connection.
     */
    public function sendTestMessage(): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Discord Webhook URL is not configured.');
            return false;
        }

        $payload = DiscordWebhookBuilder::buildTestMessagePayload();

        return $this->sendWebhookTask->run($this->webhookUrl, $payload);
    }
}
