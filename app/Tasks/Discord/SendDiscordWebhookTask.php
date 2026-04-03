<?php

declare(strict_types=1);

namespace App\Tasks\Discord;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDiscordWebhookTask
{
    /**
     * Send the payload to the provided Discord Webhook URL.
     */
    public function run(string $webhookUrl, array $payload): bool
    {
        if (empty($webhookUrl)) {
            Log::warning('Discord Webhook URL is empty.');
            return false;
        }

        try {
            $response = Http::post($webhookUrl, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Discord Webhook failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
        } catch (\Exception $e) {
            Log::error('Discord Webhook exception', [
                'message' => $e->getMessage(),
                'payload' => $payload
            ]);
        }

        return false;
    }
}
