<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Event;
use App\Support\IconHelper;
use Illuminate\Support\Collection;

class DiscordMessageBuilder
{
    public static function buildRaidMessage(Event $event, array $rosterData): array
    {
        // --- Reclassify for Discord display ---
        // Web keeps pending in mainRoster and tentative in absentRoster.
        // Discord: tentative → show in role group (with ❓), pending → separate section.

        $rawMain   = $rosterData['mainRoster'];   // tank/heal/mdps/rdps, includes pending
        $rawAbsent = $rosterData['absentRoster'];  // absent + tentative

        // Split pending out of mainRoster; keep present/late/tentative in role groups
        $mainRoster    = ['tank' => collect(), 'heal' => collect(), 'mdps' => collect(), 'rdps' => collect()];
        $pendingRoster = collect();

        foreach (['tank', 'heal', 'mdps', 'rdps'] as $role) {
            foreach ($rawMain[$role] ?? collect() as $char) {
                $status = $char->pivot->status ?? 'pending';
                if ($status === 'pending') {
                    $pendingRoster->push($char);
                } else {
                    $mainRoster[$role]->push($char);
                }
            }
        }

        // From absentRoster: tentative → role group, absent → absentRoster
        $absentRoster = collect();
        foreach ($rawAbsent as $char) {
            $status = $char->pivot->status ?? 'pending';
            if ($status === 'tentative') {
                $role = $char->assigned_role ?? $char->getAttribute('assigned_role') ?? 'rdps';
                ($mainRoster[$role] ?? $mainRoster['rdps'])->push($char);
            } else {
                $absentRoster->push($char);
            }
        }

        // --- Stats ---
        $countPresent  = 0;
        $countTentative = 0;
        foreach (['tank', 'heal', 'mdps', 'rdps'] as $role) {
            foreach ($mainRoster[$role] as $char) {
                $status = $char->pivot->status ?? 'pending';
                if ($status === 'tentative') {
                    $countTentative++;
                } else {
                    $countPresent++;
                }
            }
        }
        $countPending = $pendingRoster->count();
        $countAbsent  = $absentRoster->count();
        $totalRoster  = $countPresent + $countTentative + $countPending + $countAbsent;

        $statsLine = "**{$countPresent}/{$totalRoster}** present"
            . ($countTentative > 0 ? " · **{$countTentative}** tentative" : '')
            . ($countPending   > 0 ? " · **{$countPending}** pending"     : '')
            . ($countAbsent    > 0 ? " · **{$countAbsent}** absent"       : '');

        // --- Build embed ---
        $unixStart = $event->start_time->timestamp;
        $descriptionText = $event->description ? "*" . trim($event->description) . "*\n\n" : "";

        $analysisText = "";
        if ($event->tacticalReport && $event->tacticalReport->ai_analysis) {
            $analysisText = "\n\n🧠 **Tactical Analysis Ready!**\n[Read Full Review](" . route('statics.logs.show', [$event->static->id, $event->tacticalReport->id]) . ")";
        }

        $fields = [
            [
                'name'   => IconHelper::roleEmoji('tank') . ' Tanks',
                'value'  => self::formatRosterField($mainRoster['tank']),
                'inline' => true,
            ],
            [
                'name'   => IconHelper::roleEmoji('heal') . ' Healers',
                'value'  => self::formatRosterField($mainRoster['heal']),
                'inline' => true,
            ],
            ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => true],
            [
                'name'   => IconHelper::roleEmoji('melee') . ' Melee DPS',
                'value'  => self::formatRosterField($mainRoster['mdps']),
                'inline' => true,
            ],
            [
                'name'   => IconHelper::roleEmoji('range') . ' Ranged DPS',
                'value'  => self::formatRosterField($mainRoster['rdps']),
                'inline' => true,
            ],
            ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => true],
        ];

        if ($absentRoster->isNotEmpty()) {
            $fields[] = [
                'name'   => '❌ Absent',
                'value'  => self::formatRosterField($absentRoster),
                'inline' => false,
            ];
        }

        if ($pendingRoster->isNotEmpty()) {
            $fields[] = [
                'name'   => '⏳ Pending',
                'value'  => self::formatRosterField($pendingRoster),
                'inline' => false,
            ];
        }

        $fields[] = [
            'name'   => '📊 Attendance',
            'value'  => $statsLine,
            'inline' => false,
        ];

        $embed = [
            'title'       => "📣 Raid Call",
            'description' => "🗓️ **Start:** <t:{$unixStart}:F>\n⏳ **Status:** <t:{$unixStart}:R>\n\n"
                . $descriptionText
                . "**Combat Roster:**" . $analysisText . "\n──────────────────────────",
            'color'     => 0x00A3FF,
            'thumbnail' => ['url' => config('app.url') . '/images/logo.svg'],
            'image'     => ['url' => config('app.url') . '/images/spacer-365.png'],
            'fields'    => $fields,
            'timestamp' => now()->toIso8601String(),
            'footer'    => [
                'text'     => 'StaticHub Tactical HUD',
                'icon_url' => config('app.url') . '/images/logo.svg',
            ],
        ];

        // When raid has started, only show "Open on Website" button
        if ($event->raid_started) {
            $components = [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type'  => 2,
                            'style' => 5,
                            'label' => 'Open on Website',
                            'url'   => route('schedule.event.show', $event->id),
                            'emoji' => ['name' => '🌐'],
                        ],
                    ],
                ],
            ];
        } else {
            $components = [
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type'        => 3,
                            'custom_id'   => "rsvp_select_{$event->id}",
                            'options'     => [
                                ['label' => 'Present',  'value' => 'present',  'emoji' => ['name' => '✅']],
                                ['label' => 'Late',     'value' => 'late',     'emoji' => ['name' => '⏰']],
                                ['label' => 'Tentative','value' => 'tentative','emoji' => ['name' => '❓']],
                                ['label' => 'Absent',   'value' => 'absent',   'emoji' => ['name' => '❌']],
                            ],
                            'placeholder' => '👇 Select your status',
                        ],
                    ],
                ],
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type'  => 2,
                            'style' => 5,
                            'label' => 'Open on Website',
                            'url'   => route('schedule.event.show', $event->id),
                            'emoji' => ['name' => '🌐'],
                        ],
                        [
                            'type'      => 2,
                            'style'     => 2,
                            'label'     => 'Comment',
                            'custom_id' => "rsvp_comment_{$event->id}",
                            'emoji'     => ['name' => '💬'],
                        ],
                        [
                            'type'      => 2,
                            'style'     => 2,
                            'label'     => 'Refresh',
                            'custom_id' => "rsvp_refresh_{$event->id}",
                            'emoji'     => ['name' => '🔄'],
                        ],
                    ],
                ],
                [
                    'type' => 1,
                    'components' => [
                        [
                            'type'      => 2,
                            'style'     => 2,
                            'label'     => '🎯 Change Spec',
                            'custom_id' => "rsvp_spec_{$event->id}",
                        ],
                        [
                            'type'      => 2,
                            'style'     => 2,
                            'label'     => '👤 Change Character',
                            'custom_id' => "rsvp_switch_{$event->id}",
                        ],
                    ],
                ],
            ];
        }

        return [
            'embeds'     => [$embed],
            'components' => $components,
        ];
    }

    private static function formatRosterField(Collection $characters): string
    {
        if ($characters->isEmpty()) {
            return '*None*';
        }

        return $characters->map(function ($char) {
            $statusEmoji = match ($char->pivot->status ?? 'pending') {
                'present'  => '✅',
                'late'     => '⏰',
                'tentative'=> '❓',
                'absent'   => '❌',
                default    => '⏳',
            };

            $classEmoji = IconHelper::classEmoji($char->playable_class);

            return "{$statusEmoji} {$classEmoji} **{$char->name}**";
        })->implode("\n");
    }
}
