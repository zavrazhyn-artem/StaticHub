<?php

namespace App\Services;

use App\Models\StaticGroup;
use Illuminate\Support\Facades\Auth;

class StaticSettingsService
{
    public function __construct(
        protected DiscordMessageService $discordMessageService,
        protected RaidScheduleService $raidScheduleService,
        protected DiscordService $discordService
    ) {}

    public function getScheduleData(StaticGroup $static): array
    {
        $this->authorize($static);

        $timezones = timezone_identifiers_list();
        $botGuilds = $this->discordMessageService->getGuildsTheBotIsIn();
        $discordGuildId = $static->discord_guild_id;

        if (empty($discordGuildId) && count($botGuilds) === 1) {
            $discordGuildId = $botGuilds[0]['id'];
        }

        $discordChannels = [];
        $discordRoles = [];

        if ($discordGuildId) {
            $discordChannels = $this->discordMessageService->getGuildChannels($discordGuildId);
            $discordRoles = $this->discordMessageService->getGuildRoles($discordGuildId);
        }

        return compact('static', 'timezones', 'discordChannels', 'discordRoles', 'botGuilds', 'discordGuildId');
    }

    public function updateLogs(StaticGroup $static, array $data): void
    {
        $this->authorize($static);
        $static->update($data);
    }

    public function updateSchedule(StaticGroup $static, array $data, bool $postNextAfterRaid): void
    {
        $this->authorize($static);

        if (isset($data['automation_settings'])) {
            $data['automation_settings']['post_next_after_raid'] = $postNextAfterRaid;
        }

        $static->update($data);

        $this->raidScheduleService->generateUpcomingEvents($static);
    }

    public function testDiscordWebhook(StaticGroup $static): bool
    {
        $this->authorize($static);
        return $this->discordService->sendTestMessage();
    }

    public function authorize(StaticGroup $static): void
    {
        if ($static->owner_id !== Auth::id()) {
            abort(403);
        }
    }
}
