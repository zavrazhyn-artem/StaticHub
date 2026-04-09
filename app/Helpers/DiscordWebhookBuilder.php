<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\StaticGroup;

class DiscordWebhookBuilder
{
    private static function spacerImageUrl(): string
    {
        return config('app.url') . '/images/spacer.png';
    }
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
                    'image' => ['url' => self::spacerImageUrl()],
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];
    }

    /**
     * Build the payload for "AI analysis ready" notification.
     */
    public static function buildAnalysisReadyPayload(string $reportTitle, string $reportUrl): array
    {
        return [
            'embeds' => [
                [
                    'title' => '🧠 AI Tactical Report Ready',
                    'description' => "Analysis for **{$reportTitle}** has been completed.\n\n📄 [View Full Report]({$reportUrl})",
                    'color' => 5763719, // Green
                    'fields' => [],
                    'image' => ['url' => self::spacerImageUrl()],
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ],
            'components' => [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'View Full Report',
                            'url' => $reportUrl,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build the payload for "no log found after raid" notification.
     */
    public static function buildNoLogFoundPayload(string $raidDate, string $manualUploadUrl): array
    {
        return [
            'embeds' => [
                [
                    'title' => '⚠️ No WCL Log Found',
                    'description' => "No Warcraft Logs report was found for the raid on **{$raidDate}**. Please upload a log manually for AI analysis.",
                    'color' => 15105570, // Orange / warning
                    'image' => ['url' => self::spacerImageUrl()],
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ],
            'components' => [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Upload Log Manually',
                            'url' => $manualUploadUrl,
                        ],
                    ],
                ],
            ],
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
                    'image' => ['url' => self::spacerImageUrl()],
                    'footer' => [
                        'text' => 'Blast Your Raid • blastr.pro',
                    ],
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ];
    }
}
