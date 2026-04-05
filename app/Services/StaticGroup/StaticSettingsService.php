<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Helpers\CurrencyHelper;
use App\Models\StaticGroup;
use App\Services\Discord\DiscordWebhookService;
use App\Services\Raid\RaidScheduleService;
use App\Tasks\StaticGroup\ResolveDiscordGuildContextTask;
use App\Tasks\StaticGroup\UpdateStaticSettingsTask;

class StaticSettingsService
{
    public function __construct(
        protected ResolveDiscordGuildContextTask $resolveDiscordGuildContextTask,
        protected UpdateStaticSettingsTask       $updateStaticSettingsTask,
        protected RaidScheduleService            $raidScheduleService,
        protected DiscordWebhookService          $discordWebhookService
    ) {}

    public function buildScheduleSettingsPayload(StaticGroup $static): array
    {
        $timezones = timezone_identifiers_list();

        $clientId    = config('services.discord.client_id');
        $redirectUri = urlencode(config('services.discord.redirect', ''));
        $discordInviteUrl = "https://discord.com/oauth2/authorize?client_id={$clientId}&permissions=117760&response_type=code&redirect_uri={$redirectUri}&integration_type=0&scope=bot+applications.commands+guilds";

        return [
            'static'          => $static,
            'timezones'       => $timezones,
            'discordInviteUrl' => $discordInviteUrl,
        ];
    }

    public function buildDiscordSettingsPayload(StaticGroup $static): array
    {
        $context = $this->resolveDiscordGuildContextTask->run($static->discord_guild_id);

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
        $this->updateStaticSettingsTask->run($static, $data);
    }

    public function executeUpdateScheduleSettings(StaticGroup $static, array $data, bool $postNextAfterRaid): void
    {
        if (isset($data['automation_settings'])) {
            $data['automation_settings']['post_next_after_raid'] = $postNextAfterRaid;
        }

        if (isset($data['weekly_tax_per_player'])) {
            $data['weekly_tax_per_player'] = CurrencyHelper::goldToCopper((int) $data['weekly_tax_per_player']);
        }

        $this->updateStaticSettingsTask->run($static, $data);
        $this->raidScheduleService->executeScheduleGeneration($static);
    }

    public function executeUpdateDiscordSettings(StaticGroup $static, array $data): ?array
    {
        $this->updateStaticSettingsTask->run($static, $data);

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
}
