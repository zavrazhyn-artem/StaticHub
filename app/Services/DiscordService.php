<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    protected string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.discord.webhook_url') ?? env('DISCORD_WEBHOOK_URL', '');
    }

    /**
     * Send a sync report to Discord using rich embeds.
     */
    public function sendSyncReport($staticGroup, array $stats = []): bool
    {
        if (empty($this->webhookUrl)) {
            Log::warning('Discord Webhook URL is not configured.');
            return false;
        }

        $membersUpdated = $stats['members_updated'] ?? '0/0';
        $missingEnchants = $stats['missing_enchants'] ?? 'None';

        $payload = [
            'embeds' => [
                [
                    'title' => '🚀 BlastR: Sync Completed for ' . $staticGroup->name,
                    'description' => 'The roster data has been successfully updated from Blizzard and Raider.io.',
                    'color' => 3447003, // Our BlastR Blue color in decimal
                    'fields' => [
                        [
                            'name' => 'Members Updated',
                            'value' => "**{$membersUpdated}** characters",
                            'inline' => true
                        ],
                        [
                            'name' => 'Missing Enchants Found',
                            'value' => "⚠️ {$missingEnchants}",
                            'inline' => true
                        ]
                    ],
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];

        return $this->postToWebhook($payload);
    }

    /**
     * Send a test message to verify the webhook connection.
     */
    public function sendTestMessage(): bool
    {
        if (empty($this->webhookUrl)) {
            return false;
        }

        $payload = [
            'embeds' => [
                [
                    'title' => '🧪 BlastR: Webhook Test',
                    'description' => 'This is a test message to confirm your Discord Webhook integration is working correctly.',
                    'color' => 3447003,
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];

        return $this->postToWebhook($payload);
    }

    /**
     * Verify the signature of an incoming Discord interaction.
     */
    public function verifySignature(string $signature, string $timestamp, string $body): bool
    {
        $publicKey = config('services.discord.public_key');

        if (!$publicKey) {
            Log::error('Discord public key not configured');
            return false;
        }

        try {
            $binarySignature = hex2bin($signature);
            $binaryPublicKey = hex2bin($publicKey);

            $msg = $timestamp . $body;

            return sodium_crypto_sign_verify_detached($binarySignature, $msg, $binaryPublicKey);
        } catch (\Exception $e) {
            Log::error('Discord signature verification exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Internal helper to POST payload to Discord.
     */
    protected function postToWebhook(array $payload): bool
    {
        try {
            $response = Http::post($this->webhookUrl, $payload);

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
