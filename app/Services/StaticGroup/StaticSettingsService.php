<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\CurrencyHelper;
use App\Models\StaticGroup;
use App\Services\Discord\DiscordMessageService;
use App\Services\Discord\DiscordWebhookService;
use App\Services\Raid\RaidScheduleService;
use Carbon\Carbon;

class StaticSettingsService
{
    public function __construct(
        protected DiscordMessageService $discordMessageService,
        protected RaidScheduleService   $raidScheduleService,
        protected DiscordWebhookService $discordWebhookService
    ) {}

    public function buildScheduleSettingsPayload(StaticGroup $static): array
    {
        $timezones = timezone_identifiers_list();

        $clientId    = config('services.discord.client_id');
        $redirectUri = urlencode(config('services.discord.redirect', ''));
        $discordInviteUrl = "https://discord.com/oauth2/authorize?client_id={$clientId}&permissions=117760&response_type=code&redirect_uri={$redirectUri}&integration_type=0&scope=bot+applications.commands+guilds";

        $scheduleData = [
            'static_name'           => $static->name,
            'raid_days'             => is_array($static->raid_days)
                ? $static->raid_days
                : (json_decode($static->raid_days, true) ?? []),
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
        $context = $this->resolveDiscordGuildContext($static->discord_guild_id);

        $clientId    = config('services.discord.client_id');
        $redirectUri = urlencode(config('services.discord.redirect', ''));
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

    public function executeUpdateLogsSettings(StaticGroup $static, array $data): void
    {
        $this->updateSettings($static, $data);
    }

    public function executeUpdateScheduleSettings(StaticGroup $static, array $data, bool $postNextAfterRaid): void
    {
        if (isset($data['automation_settings'])) {
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

        $this->updateSettings($static, $data);
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
        $static->update($data);
    }

    /**
     * Resolve Discord guild context including bot guilds, channels, and roles.
     *
     * @param string|null $savedGuildId
     * @return array
     */
    public function resolveDiscordGuildContext(?string $savedGuildId): array
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
