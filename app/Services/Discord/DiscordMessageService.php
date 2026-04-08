<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Helpers\DiscordMessageBuilder;
use App\Models\Event;
use App\Services\Raid\RaidAttendanceService;
use App\Services\Discord\DiscordApiService;

class DiscordMessageService
{
    public function __construct(
        private readonly RaidAttendanceService $attendanceService,
        private readonly DiscordApiService $discordApiTask
    ) {
    }

    private function postNewMessage(Event $event, string $channelId, array $payload): bool
    {
        $response = $this->discordApiTask->sendMessage($channelId, $payload);

        if ($response && isset($response['id'])) {
            $event->update(['discord_message_id' => $response['id']]);
            return true;
        }

        return false;
    }

    /**
     * Send a test message to a channel via the bot and return detailed result.
     */
    public function sendTestMessageToChannel(string $channelId): array
    {
        $payload = DiscordMessageBuilder::buildChannelTestPayload();

        return $this->discordApiTask->sendMessageDetailed($channelId, $payload);
    }

    /**
     * Delete a message from a channel via the bot.
     */
    public function deleteChannelMessage(string $channelId, string $messageId): bool
    {
        return $this->discordApiTask->deleteMessage($channelId, $messageId);
    }

    /**
     * Fetch all guilds the bot is in.
     */
    public function getGuildsTheBotIsIn(): array
    {
        return $this->discordApiTask->getGuilds();
    }

    /**
     * Fetch bot guilds filtered to only those where the given Discord user is a member.
     */
    public function getGuildsForMember(string $discordUserId): array
    {
        $botGuilds = $this->discordApiTask->getGuilds();

        return collect($botGuilds)
            ->filter(fn(array $guild) => $this->discordApiTask->getGuildMember($guild['id'], $discordUserId) !== null)
            ->values()
            ->toArray();
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
     * Delete a raid announcement from Discord and clear the stored message ID.
     */
    public function deleteRaidAnnouncement(Event $event): bool
    {
        if (!$event->discord_message_id) {
            return true;
        }

        $channelId = (string) $event->static->discord_channel_id;

        if (empty($channelId)) {
            return false;
        }

        $deleted = $this->discordApiTask->deleteMessage($channelId, $event->discord_message_id);

        if ($deleted) {
            $event->update(['discord_message_id' => null]);
        }

        return $deleted;
    }

    /**
     * Send a new raid announcement or update an existing one.
     */
    public function sendOrUpdateRaidAnnouncement(Event $event): bool
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
            return $this->postNewMessage($event, $channelId, $payload);
        }

        $response = $this->discordApiTask->updateMessage($channelId, $event->discord_message_id, $payload);

        if ($response !== false) {
            return (bool) $response;
        }

        // 404 — message was deleted; clear stored ID and post a fresh one
        $event->update(['discord_message_id' => null]);

        return $this->postNewMessage($event, $channelId, $payload);
    }
}
