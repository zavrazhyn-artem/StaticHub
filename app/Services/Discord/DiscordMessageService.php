<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Helpers\DiscordMessageBuilder;
use App\Models\RaidEvent;
use App\Services\Raid\RaidAttendanceService;
use App\Tasks\Discord\DiscordApiTask;

class DiscordMessageService
{
    public function __construct(
        private readonly RaidAttendanceService $attendanceService,
        private readonly DiscordApiTask $discordApiTask
    ) {
    }

    /**
     * Fetch all guilds the bot is in.
     */
    public function getGuildsTheBotIsIn(): array
    {
        return $this->discordApiTask->getGuilds();
    }

    /**
     * Fetch all text channels from the guild where the bot is present.
     */
    public function getGuildChannels(string $guildId): array
    {
        $channels = $this->discordApiTask->getChannels($guildId);

        return collect($channels)
            ->filter(fn($channel) => $channel['type'] === 0) // 0 is text channel
            ->values()
            ->toArray();
    }

    /**
     * Fetch all roles from the guild.
     */
    public function getGuildRoles(string $guildId): array
    {
        return $this->discordApiTask->getRoles($guildId);
    }

    /**
     * Send a new raid announcement or update an existing one.
     */
    public function sendOrUpdateRaidAnnouncement(RaidEvent $event): bool
    {
        $channelId = (string) $event->static->discord_channel_id;

        if (empty($channelId)) {
            return false;
        }

        $rosterData = $this->attendanceService->getGroupedRoster($event);
        $payload = DiscordMessageBuilder::buildRaidMessage($event, $rosterData);

        // Add Mention if configured
        $automation = $event->static->automation_settings ?? [];
        if (!empty($automation['ping_role_id'])) {
            $payload['content'] = "<@&{$automation['ping_role_id']}>";
        }

        if (!$event->discord_message_id) {
            $response = $this->discordApiTask->sendMessage($channelId, $payload);
            if ($response && isset($response['id'])) {
                $event->update(['discord_message_id' => $response['id']]);
                return true;
            }
        } else {
            $response = $this->discordApiTask->updateMessage($channelId, $event->discord_message_id, $payload);
            if ($response) {
                return true;
            }
        }

        return false;
    }
}
