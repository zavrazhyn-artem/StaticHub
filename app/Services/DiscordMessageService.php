<?php

namespace App\Services;

use App\Models\RaidEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordMessageService
{
    protected RaidAttendanceService $attendanceService;

    public function __construct(RaidAttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Fetch all guilds the bot is in.
     */
    public function getGuildsTheBotIsIn(): array
    {
        $botToken = config('services.discord.bot_token');

        if (!$botToken) {
            return [];
        }

        $response = Http::withToken($botToken, 'Bot')
            ->get("https://discord.com/api/v10/users/@me/guilds");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Failed to fetch Discord guilds for the bot", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [];
    }

    /**
     * Fetch all text channels from the guild where the bot is present.
     */
    public function getGuildChannels(string $guildId): array
    {
        $botToken = config('services.discord.bot_token');

        if (!$guildId || !$botToken) {
            return [];
        }

        $response = Http::withToken($botToken, 'Bot')
            ->get("https://discord.com/api/v10/guilds/{$guildId}/channels");

        if ($response->successful()) {
            return collect($response->json())
                ->filter(fn($channel) => $channel['type'] === 0) // 0 is text channel
                ->values()
                ->toArray();
        }

        Log::error("Failed to fetch Discord channels for Guild ID: {$guildId}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [];
    }

    /**
     * Fetch all roles from the guild.
     */
    public function getGuildRoles(string $guildId): array
    {
        $botToken = config('services.discord.bot_token');

        if (!$guildId || !$botToken) {
            return [];
        }

        $response = Http::withToken($botToken, 'Bot')
            ->get("https://discord.com/api/v10/guilds/{$guildId}/roles");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Failed to fetch Discord roles for Guild ID: {$guildId}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [];
    }

    /**
     * Send a new raid announcement or update an existing one.
     */
    public function sendOrUpdateRaidAnnouncement(RaidEvent $event): bool
    {
        $channelId = $event->static->discord_channel_id;
        $botToken = config('services.discord.bot_token');

        if (!$channelId || !$botToken) {
            Log::warning("Discord channel ID or bot token missing for Event ID: {$event->id}");
            return false;
        }

        $payload = $this->buildRaidMessage($event);

        // Add Mention if configured
        $automation = $event->static->automation_settings ?? [];
        if (!empty($automation['ping_role_id'])) {
            $payload['content'] = "<@&{$automation['ping_role_id']}>";
        }

        $baseUrl = "https://discord.com/api/v10/channels/{$channelId}/messages";

        if (!$event->discord_message_id) {
            // Send new message
            $response = Http::withToken($botToken, 'Bot')
                ->post($baseUrl, $payload);

            if ($response->successful()) {
                $event->update(['discord_message_id' => $response->json('id')]);
                return true;
            }
        } else {
            // Update existing message
            $response = Http::withToken($botToken, 'Bot')
                ->patch("{$baseUrl}/{$event->discord_message_id}", $payload);

            if ($response->successful()) {
                return true;
            }
        }

        Log::error("Failed to send/update Discord message for Event ID: {$event->id}", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    public function buildRaidMessage(RaidEvent $event): array
    {
        $rosterData = $this->attendanceService->getGroupedRoster($event);
        $mainRoster = $rosterData['mainRoster'];
        $absentRoster = $rosterData['absentRoster'];

        $unixStart = $event->start_time->timestamp;

        // Чистий опис без зайвих пробілів
        $descriptionText = $event->description ? "*" . trim($event->description) . "*\n\n" : "";

        $analysisText = "";
        if ($event->tacticalReport && $event->tacticalReport->ai_analysis) {
            $analysisText = "\n\n🧠 **Tactical Analysis Ready!**\n[Read Full Review](" . route('statics.logs.show', [$event->static->id, $event->tacticalReport->id]) . ")";
        }

        $embed = [
            'title' => "📣 Raid Call: " . $event->title,
            'description' => "🗓️ **Start:** <t:{$unixStart}:F>\n⏳ **Status:** <t:{$unixStart}:R>\n\n" .
                $descriptionText .
                "**Combat Roster:**" . $analysisText . "\n──────────────────────────",
            'color' => 0x00A3FF,
            'fields' => [
                [
                    'name' => '🛡️ Tanks',
                    'value' => $this->formatRosterField($mainRoster['tank'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => '🏥 Healers',
                    'value' => $this->formatRosterField($mainRoster['heal'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => "\u{200B}",
                    'value' => "\u{200B}",
                    'inline' => true,
                ],
                [
                    'name' => '⚔️ Melee DPS',
                    'value' => $this->formatRosterField($mainRoster['mdps'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => '🏹 Ranged DPS',
                    'value' => $this->formatRosterField($mainRoster['rdps'] ?? collect()),
                    'inline' => true,
                ],
                [
                    'name' => "\u{200B}",
                    'value' => "\u{200B}",
                    'inline' => true,
                ],
                [
                    'name' => '❌ Absent / ❓ Tentative',
                    'value' => $this->formatRosterField($absentRoster),
                    'inline' => false,
                ],
            ],
            // Тимчасово прибрав thumbnail, щоб не провокувати 400 помилку
            'timestamp' => now()->toIso8601String(),
            'footer' => [
                'text' => 'StaticHub Tactical HUD',
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
                            'label' => 'Switch Character',
                            'custom_id' => "rsvp_switch_{$event->id}",
                            'emoji' => ['name' => '🔄']
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

    protected function formatRosterField($characters): string
    {
        if (!$characters || $characters->isEmpty()) {
            return '*None*'; // Компактний варіант замість великого чорного блоку
        }

        return $characters->map(function ($char) {
            $statusEmoji = match ($char->pivot->status ?? 'pending') {
                'present' => '✅',
                'late' => '⏰',
                'tentative' => '❓',
                'absent' => '❌',
                default => '⏳',
            };

            return "{$statusEmoji} **{$char->name}**";
        })->implode("\n");
    }
}
