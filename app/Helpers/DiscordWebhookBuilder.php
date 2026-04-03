<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\StaticGroup;

class DiscordWebhookBuilder
{
    /**
     * Build the payload for a sync report.
     */
    public static function buildSyncReportPayload(StaticGroup $staticGroup, array $stats): array
    {
        $membersUpdated = $stats['members_updated'] ?? '0/0';
        $missingEnchants = $stats['missing_enchants'] ?? 'None';

        return [
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
    }

    /**
     * Build a simple test message payload.
     */
    public static function buildTestMessagePayload(): array
    {
        return [
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
    }
}
