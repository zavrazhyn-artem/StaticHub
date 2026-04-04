<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\RaidEvent;
use App\Support\IconHelper;
use Illuminate\Support\Collection;

class DiscordMessageBuilder
{
    public static function buildRaidMessage(RaidEvent $event, array $rosterData): array
    {
        $mainRoster = $rosterData['mainRoster'];
        $absentRoster = $rosterData['absentRoster'];

        $unixStart = $event->start_time->timestamp;
        $descriptionText = $event->description ? "*" . trim($event->description) . "*\n\n" : "";

        $analysisText = "";
        if ($event->tacticalReport && $event->tacticalReport->ai_analysis) {
            $analysisText = "\n\n🧠 **Tactical Analysis Ready!**\n[Read Full Review](" . route('statics.logs.show', [$event->static->id, $event->tacticalReport->id]) . ")";
        }

        $embed = [
            'title' => "📣 Raid Call",
            'description' => "🗓️ **Start:** <t:{$unixStart}:F>\n⏳ **Status:** <t:{$unixStart}:R>\n\n" .
                $descriptionText .
                "**Combat Roster:**" . $analysisText . "\n──────────────────────────",
            'color' => 0x00A3FF,
            'thumbnail' => [
                'url' => config('app.url') . '/images/logo.svg',
            ],
            'fields' => [
                [
                    'name' => IconHelper::roleEmoji('tank') . ' Tanks',
                    'value' => self::formatRosterField($mainRoster['tank'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => IconHelper::roleEmoji('heal') . ' Healers',
                    'value' => self::formatRosterField($mainRoster['heal'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => "\u{200B}",
                    'value' => "\u{200B}",
                    'inline' => true,
                ],
                [
                    'name' => IconHelper::roleEmoji('melee') . ' Melee DPS',
                    'value' => self::formatRosterField($mainRoster['mdps'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => IconHelper::roleEmoji('range') . ' Ranged DPS',
                    'value' => self::formatRosterField($mainRoster['rdps'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => "\u{200B}",
                    'value' => "\u{200B}",
                    'inline' => true,
                ],
                [
                    'name' => '❌ Absent / ❓ Tentative',
                    'value' => self::formatRosterField($absentRoster),
                    'inline' => false,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'StaticHub Tactical HUD',
                'icon_url' => config('app.url') . '/images/logo.svg',
            ],
        ];

        return [
            'embeds' => [$embed],
            'components' => [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 3,
                            'custom_id' => "rsvp_select_{$event->id}",
                            'options' => [
                                ['label' => 'Present', 'value' => 'present', 'emoji' => ['name' => '✅']],
                                ['label' => 'Late', 'value' => 'late', 'emoji' => ['name' => '⏰']],
                                ['label' => 'Tentative', 'value' => 'tentative', 'emoji' => ['name' => '❓']],
                                ['label' => 'Absent', 'value' => 'absent', 'emoji' => ['name' => '❌']],
                            ],
                            'placeholder' => '👇 Select your status',
                        ],
                    ],
                ],
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type' => 2,
                            'style' => 5,
                            'label' => 'Open on Website',
                            'url' => route('schedule.event.show', $event->id),
                            'emoji' => ['name' => '🌐']
                        ],
                        [
                            'type' => 2,
                            'style' => 2,
                            'label' => '👤 Change Character',
                            'custom_id' => "rsvp_switch_{$event->id}",
                        ],
                        [
                            'type' => 2,
                            'style' => 2,
                            'label' => 'Comment',
                            'custom_id' => "rsvp_comment_{$event->id}",
                            'emoji' => ['name' => '💬']
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function formatRosterField(Collection $characters): string
    {
        if ($characters->isEmpty()) {
            return '*None*';
        }

        return $characters->map(function ($char) {
            $statusEmoji = match ($char->pivot->status ?? 'pending') {
                'present' => '✅',
                'late' => '⏰',
                'tentative' => '❓',
                'absent' => '❌',
                default => '⏳',
            };

            $classIconUrl = IconHelper::classUrlAbsolute($char->playable_class);
            $roleIconUrl = IconHelper::roleUrlAbsolute($char->pivot->combat_role ?? 'rdps');
            $classEmoji = IconHelper::classEmoji($char->playable_class);

            return "{$statusEmoji} {$classEmoji} **{$char->name}**";
        })->implode("\n");
    }
}
