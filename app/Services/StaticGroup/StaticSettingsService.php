<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\CurrencyHelper;
use App\Helpers\WclParserHelper;
use App\Models\StaticGroup;
use App\Services\Analysis\WclService;
use App\Services\Discord\DiscordMessageService;
use App\Services\Discord\DiscordWebhookService;
use App\Services\Raid\RaidScheduleService;
use Carbon\Carbon;

class StaticSettingsService
{
    public function __construct(
        protected DiscordMessageService $discordMessageService,
        protected RaidScheduleService   $raidScheduleService,
        protected DiscordWebhookService $discordWebhookService,
        protected WclService            $wclService,
    ) {}

    public function buildScheduleSettingsPayload(StaticGroup $static): array
    {
        $timezones = timezone_identifiers_list();

        $clientId    = config('services.discord.client_id');
        $redirectUri = urlencode(route('discord.bot-invited'));
        $discordInviteUrl = "https://discord.com/oauth2/authorize?client_id={$clientId}&permissions=117760&response_type=code&redirect_uri={$redirectUri}&integration_type=0&scope=bot+applications.commands+guilds";

        $scheduleData = [
            'static_name'           => $static->name,
            'raid_days'             => $static->raid_days ?? [],
            'raid_start_time'       => $static->raid_start_time
                ? Carbon::parse($static->raid_start_time)->format('H:i')
                : '',
            'raid_end_time'         => $static->raid_end_time
                ? Carbon::parse($static->raid_end_time)->format('H:i')
                : '',
            'timezone'              => $static->timezone ?? 'Europe/Paris',
            'weekly_tax_per_player' => (int) (($static->weekly_tax_per_player ?? 0) / 10000),
            'automation_settings'   => $static->automation_settings ?? [],
        ];

        return [
            'static'           => $static,
            'timezones'        => $timezones,
            'discordInviteUrl' => $discordInviteUrl,
            'scheduleData'     => $scheduleData,
        ];
    }

    public function buildDiscordSettingsPayload(StaticGroup $static): array
    {
        $context = $this->resolveDiscordGuildContext($static);

        $clientId    = config('services.discord.client_id');
        $redirectUri = urlencode(route('discord.bot-invited'));
        $discordInviteUrl = "https://discord.com/oauth2/authorize?client_id={$clientId}&permissions=117760&response_type=code&redirect_uri={$redirectUri}&integration_type=0&scope=bot+applications.commands+guilds";

        $webhookChannel = null;
        if (!empty($static->discord_webhook_url)) {
            $webhookChannel = $this->discordWebhookService->resolveWebhookChannel($static->discord_webhook_url);
        }

        return array_merge([
            'static'          => $static,
            'discordInviteUrl' => $discordInviteUrl,
            'webhookChannel'  => $webhookChannel,
        ], $context);
    }

    /**
     * Connect a WCL guild by parsing the URL and fetching info from WCL API.
     *
     * @return array{success: bool, guild: array|null, error: string|null}
     */
    public function connectWclGuild(StaticGroup $static, string $url): array
    {
        $parsed = WclParserHelper::parseGuildUrl($url);

        if (!$parsed) {
            return ['success' => false, 'guild' => null, 'error' => 'invalid_url'];
        }

        try {
            $guildInfo = $parsed['type'] === 'id'
                ? $this->wclService->getGuildInfoById($parsed['guild_id'])
                : $this->wclService->getGuildInfoByName($parsed['name'], $parsed['server'], $parsed['region']);
        } catch (\Exception $e) {
            return ['success' => false, 'guild' => null, 'error' => 'api_error'];
        }

        if (!$guildInfo) {
            return ['success' => false, 'guild' => null, 'error' => 'not_found'];
        }

        $static->update([
            'wcl_guild_id' => $guildInfo['id'],
            'wcl_realm'    => $guildInfo['server_slug'],
            'wcl_region'   => $guildInfo['region_slug'],
        ]);

        return ['success' => true, 'guild' => $guildInfo, 'error' => null];
    }

    /**
     * Disconnect WCL guild from static.
     */
    public function disconnectWclGuild(StaticGroup $static): void
    {
        $automation = $static->automation_settings ?? [];
        $automation['auto_fetch_logs'] = false;

        $static->update([
            'wcl_guild_id'        => null,
            'wcl_realm'           => null,
            'wcl_region'          => null,
            'automation_settings' => $automation,
        ]);
    }

    /**
     * Build the guild info payload for the logs settings page.
     */
    public function buildLogsSettingsPayload(StaticGroup $static): array
    {
        $guildInfo = null;

        if ($static->wcl_guild_id) {
            try {
                $guildInfo = $this->wclService->getGuildInfoById((int) $static->wcl_guild_id);
            } catch (\Exception) {
                // If WCL is down, show cached data
                $guildInfo = [
                    'id'          => (int) $static->wcl_guild_id,
                    'name'        => null,
                    'server_name' => $static->wcl_realm,
                    'server_slug' => $static->wcl_realm,
                    'region_slug' => $static->wcl_region,
                    'region_name' => strtoupper($static->wcl_region ?? ''),
                ];
            }
        }

        $automation = $static->automation_settings ?? [];

        return [
            'guildInfo'             => $guildInfo,
            'autoFetchLogs'         => (bool) ($automation['auto_fetch_logs'] ?? false),
            'autoFetchDelayMinutes' => (int) ($automation['auto_fetch_delay_minutes'] ?? config('tactical_logs.auto_fetch_delay_minutes')),
        ];
    }

    public function executeUpdateLogsSettings(StaticGroup $static, array $data): void
    {
        $automationSettings = $static->automation_settings ?? [];

        if (array_key_exists('auto_fetch_logs', $data)) {
            $automationSettings['auto_fetch_logs'] = (bool) $data['auto_fetch_logs'];
            unset($data['auto_fetch_logs']);
        }

        if (array_key_exists('auto_fetch_delay_minutes', $data)) {
            $automationSettings['auto_fetch_delay_minutes'] = (int) ($data['auto_fetch_delay_minutes']
                ?? config('tactical_logs.auto_fetch_delay_minutes'));
            unset($data['auto_fetch_delay_minutes']);
        }

        $data['automation_settings'] = $automationSettings;

        $this->updateSettings($static, $data);
    }

    public function executeUpdateScheduleSettings(StaticGroup $static, array $data, bool $postNextAfterRaid): void
    {
        if (isset($data['automation_settings'])) {
            $existing = $static->automation_settings ?? [];
            $data['automation_settings'] = array_merge($existing, $data['automation_settings']);
            $data['automation_settings']['post_next_after_raid'] = $postNextAfterRaid;

            // Mutual exclusivity: only one automation mode can be active
            if ($postNextAfterRaid) {
                $data['automation_settings']['reminder_hours_before'] = null;
            } elseif (!empty($data['automation_settings']['reminder_hours_before'])) {
                $data['automation_settings']['post_next_after_raid'] = false;
            }
        }

        if (isset($data['weekly_tax_per_player'])) {
            $data['weekly_tax_per_player'] = CurrencyHelper::goldToCopper((int) $data['weekly_tax_per_player']);
        }

        // Filter out null values to support partial updates
        $data = array_filter($data, fn ($v) => $v !== null);

        $this->updateSettings($static, $data);

        // Regeneration wipes all future autogen events (flagged) and recreates them
        // from the fresh settings — single source of truth, no duplicates.
        $this->raidScheduleService->executeScheduleGeneration($static);
    }

    public function executeUpdateDiscordSettings(StaticGroup $static, array $data): ?array
    {
        $this->updateSettings($static, $data);

        // If webhook URL was updated, resolve and return channel info.
        if (isset($data['discord_webhook_url']) && !empty($data['discord_webhook_url'])) {
            return $this->discordWebhookService->resolveWebhookChannel($data['discord_webhook_url']);
        }

        return null;
    }

    public function executeChannelTest(StaticGroup $static): array
    {
        if (empty($static->discord_channel_id)) {
            return ['success' => false, 'error' => 'No announcement channel configured.'];
        }

        return $this->discordMessageService->sendTestMessageToChannel($static->discord_channel_id);
    }

    public function executeChannelMessageDelete(StaticGroup $static, string $messageId): bool
    {
        if (empty($static->discord_channel_id)) {
            return false;
        }

        return $this->discordMessageService->deleteChannelMessage($static->discord_channel_id, $messageId);
    }

    public function executeNotificationChannelTest(StaticGroup $static): array
    {
        if (empty($static->notification_channel_id)) {
            return ['success' => false, 'error' => 'No notification channel configured.'];
        }

        return $this->discordMessageService->sendTestMessageToChannel($static->notification_channel_id);
    }

    public function executeNotificationChannelMessageDelete(StaticGroup $static, string $messageId): bool
    {
        if (empty($static->notification_channel_id)) {
            return false;
        }

        return $this->discordMessageService->deleteChannelMessage($static->notification_channel_id, $messageId);
    }

    public function executeWebhookTest(StaticGroup $static): array
    {
        $messageId = $this->discordWebhookService->sendTestMessage($static);

        return [
            'success'    => $messageId !== null,
            'message_id' => $messageId,
        ];
    }

    public function executeWebhookMessageDelete(StaticGroup $static, string $messageId): bool
    {
        return $this->discordWebhookService->deleteMessage($static, $messageId);
    }

    /**
     * Update static group settings.
     *
     * @param StaticGroup $static
     * @param array $data
     * @return void
     */
    public function updateSettings(StaticGroup $static, array $data): void
    {
        if (isset($data['automation_settings'])) {
            $data['automation_settings'] = array_merge(
                $static->automation_settings ?? [],
                $data['automation_settings'],
            );
        }

        $static->update($data);
    }

    /**
     * Resolve Discord guild context including bot guilds, channels, and roles.
     * Guilds are filtered to only those where the static's leader is a member.
     */
    public function resolveDiscordGuildContext(StaticGroup $static): array
    {
        $ownerDiscordId = $static->owner?->discord_id;

        $botGuilds = $ownerDiscordId
            ? $this->discordMessageService->getGuildsForMember($ownerDiscordId)
            : [];

        $discordGuildId = $static->discord_guild_id;

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
