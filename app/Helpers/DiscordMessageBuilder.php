<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Event;
use App\Models\EventEncounterRoster;
use App\Support\IconHelper;
use Illuminate\Support\Collection;

class DiscordMessageBuilder
{
    private static function spacerImageUrl(): string
    {
        return config('app.url') . '/images/spacer.png';
    }

    /**
     * Build a simple test message payload for the announcement channel.
     */
    public static function buildChannelTestPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title'       => '🧪 BlastR: Channel Test',
                    'description' => 'This is a test message to confirm the bot can post in this channel. You can safely delete it.',
                    'color'       => 3447003,
                    'image'       => ['url' => self::spacerImageUrl()],
                    'footer'      => ['text' => 'Blast Your Raid • blastr.pro'],
                    'timestamp'   => now()->toIso8601String(),
                ],
            ],
        ];
    }

    public static function buildRaidMessage(Event $event, array $rosterData): array
    {
        // --- Reclassify for Discord display ---
        // Web keeps pending in mainRoster and tentative in absentRoster.
        // Discord: tentative → show in role group (with ❓), pending → separate section.

        $rawMain   = $rosterData['mainRoster'];   // tank/heal/mdps/rdps, includes pending
        $rawAbsent = $rosterData['absentRoster'];  // absent + tentative

        // Character IDs benched across all encounters for this event.
        $benchedIds = EventEncounterRoster::query()
            ->fullyBenchedCharacterIds($event->id);

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
            $analysisText = "\n\n🧠 **Tactical Analysis Ready!**\n[Read Full Review](" . route('statics.logs.show', $event->tacticalReport->id) . ")";
        }

        $fields = [];
        $divider = '───────────────────────────────';

        // Inline role fields — each role group gets chunked if needed.
        // 'row_end' = inline spacer completing a 3-col row (keeps columns at 1/3 width);
        // 'gap'     = full-width empty field adding vertical air.
        $inlineRoles = [
            ['emoji' => IconHelper::roleEmoji('tank'),  'label' => '*Tanks*',      'roster' => $mainRoster['tank']],
            ['emoji' => IconHelper::roleEmoji('heal'),  'label' => '*Healers*',    'roster' => $mainRoster['heal']],
            'row_end',
            ['emoji' => IconHelper::roleEmoji('melee'), 'label' => '*Melee DPS*',  'roster' => $mainRoster['mdps']],
            ['emoji' => IconHelper::roleEmoji('range'), 'label' => '*Ranged DPS*', 'roster' => $mainRoster['rdps']],
            'row_end',
        ];

        foreach ($inlineRoles as $role) {
            if ($role === 'row_end') {
                $fields[] = ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => true];
                continue;
            }
            if ($role === 'gap') {
                $fields[] = ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => false];
                continue;
            }
            $chunks = self::formatRosterChunks($role['roster'], benchedIds: $benchedIds);
            $fields[] = ['name' => $role['emoji'] . ' ' . $role['label'], 'value' => $chunks[0], 'inline' => true];
            for ($i = 1; $i < count($chunks); $i++) {
                $fields[] = ['name' => $role['emoji'] . ' ' . $role['label'] . ' (CONT.)', 'value' => $chunks[$i], 'inline' => true];
            }
        }

        // Alphabetical sort for Pending so the raid leader can scan by name.
        $pendingRoster = $pendingRoster->sortBy(fn ($c) => mb_strtolower($c->name))->values();

        // Absent & Pending — each preceded by a full-width divider line, two columns each.
        $sections = [
            ['label' => IconHelper::statusEmoji('absent')  . ' *Absent*',  'roster' => $absentRoster],
            ['label' => IconHelper::statusEmoji('pending') . ' *Pending*', 'roster' => $pendingRoster],
        ];
        foreach ($sections as $section) {
            $roster = $section['roster'];
            if ($roster->isEmpty()) continue;

            $fields[] = ['name' => "\u{200B}", 'value' => $divider, 'inline' => false];

            $half = (int) ceil($roster->count() / 2);
            $col1 = self::formatRosterChunks($roster->slice(0, $half)->values(), suppressStatus: true, benchedIds: $benchedIds);
            $col2 = self::formatRosterChunks($roster->slice($half)->values(), suppressStatus: true, benchedIds: $benchedIds);

            $pad = "\u{200B}\n";
            $fields[] = ['name' => $section['label'], 'value' => $pad . $col1[0], 'inline' => true];
            $fields[] = ['name' => "\u{200B}", 'value' => $pad . $col2[0], 'inline' => true];
            $fields[] = ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => true];
        }

        $fields[] = ['name' => "\u{200B}", 'value' => "\u{200B}", 'inline' => false];
        $fields[] = [
            'name'   => '📊 *Attendance*',
            'value'  => '> ' . $statsLine,
            'inline' => false,
        ];

        $embed = [
            'title'       => "📣 " . ($event->static?->name ?? 'Raid Call'),
            'description' => "🗓️ **Start:** <t:{$unixStart}:F>\n⏳ **Status:** <t:{$unixStart}:R>\n\n"
                . $descriptionText
                . "***Combat Roster***" . $analysisText . "\n{$divider}",
            'color'     => 0x00A3FF,
            'thumbnail' => ['url' => config('app.url') . '/images/logo.svg'],
            'image'     => ['url' => config('app.url') . '/images/spacer-365.png'],
            'fields'    => $fields,
            'timestamp' => now()->toIso8601String(),
            'footer'    => [
                'text'     => 'BlastR Tactical HUD',
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
                                ['label' => 'Present',   'value' => 'present',   'emoji' => ['name' => 'rsvp_present',   'id' => config('discord_emojis.rsvp.present')]],
                                ['label' => 'Late',      'value' => 'late',      'emoji' => ['name' => 'rsvp_late',      'id' => config('discord_emojis.rsvp.late')]],
                                ['label' => 'Tentative', 'value' => 'tentative', 'emoji' => ['name' => 'rsvp_tentative', 'id' => config('discord_emojis.rsvp.tentative')]],
                                ['label' => 'Absent',    'value' => 'absent',    'emoji' => ['name' => 'rsvp_absent',    'id' => config('discord_emojis.rsvp.absent')]],
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

    /**
     * Format roster into chunks that fit Discord's 1024-char field limit.
     *
     * @return array<int, string>
     */
    private static function formatRosterChunks(
        Collection $characters,
        bool $suppressStatus = false,
        ?Collection $benchedIds = null,
    ): array {
        if ($characters->isEmpty()) {
            return ['*None*'];
        }

        $lines = $characters->map(function ($char) use ($suppressStatus, $benchedIds) {
            $classEmoji = IconHelper::classEmoji($char->playable_class);
            $isBenched = $benchedIds && $benchedIds->contains(fn ($id) => (int) $id === (int) $char->id);
            $benchSuffix = $isBenched ? ' ' . IconHelper::benchEmoji() : '';

            if ($suppressStatus) {
                return "{$classEmoji} {$char->name}{$benchSuffix}";
            }

            $statusEmoji = IconHelper::statusEmoji($char->pivot->status ?? 'pending');

            return "{$statusEmoji} {$classEmoji} {$char->name}{$benchSuffix}";
        })->all();

        $chunks = [];
        $current = '';

        foreach ($lines as $line) {
            $append = $current === '' ? $line : "\n{$line}";
            if (mb_strlen($current . $append) > 1024) {
                $chunks[] = $current;
                $current = $line;
            } else {
                $current .= $append;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
