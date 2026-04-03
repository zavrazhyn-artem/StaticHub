<?php

declare(strict_types=1);

namespace App\Tasks\StaticGroup;

use App\Services\Discord\DiscordMessageService;

class ResolveDiscordGuildContextTask
{
    public function __construct(
        protected DiscordMessageService $discordMessageService
    ) {
    }

    /**
     * Resolve Discord guild context including bot guilds, channels, and roles.
     *
     * @param string|null $savedGuildId
     * @return array
     */
    public function run(?string $savedGuildId): array
    {
        $botGuilds = $this->discordMessageService->getGuildsTheBotIsIn();
        $discordGuildId = $savedGuildId;

        if (empty($discordGuildId) && count($botGuilds) === 1) {
            $discordGuildId = $botGuilds[0]['id'];
        }

        $discordChannels = [];
        $discordRoles = [];

        if ($discordGuildId) {
            $discordChannels = $this->discordMessageService->getGuildChannels($discordGuildId);
            $discordRoles = $this->discordMessageService->getGuildRoles($discordGuildId);
        }

        return [
            'botGuilds' => $botGuilds,
            'discordGuildId' => $discordGuildId,
            'discordChannels' => $discordChannels,
            'discordRoles' => $discordRoles,
        ];
    }
}
