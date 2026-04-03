<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Services\Raid\RaidScheduleService;

use App\Helpers\CurrencyHelper;
use App\Models\StaticGroup;
use App\Services\Discord\DiscordWebhookService;
use App\Tasks\StaticGroup\ResolveDiscordGuildContextTask;
use App\Tasks\StaticGroup\UpdateStaticSettingsTask;

class StaticSettingsService
{
    public function __construct(
        protected ResolveDiscordGuildContextTask $resolveDiscordGuildContextTask,
        protected UpdateStaticSettingsTask $updateStaticSettingsTask,
        protected RaidScheduleService $raidScheduleService,
        protected DiscordWebhookService $discordWebhookService
    ) {
    }

    /**
     * Build the schedule settings payload.
     *
     * @param StaticGroup $static
     * @return array
     */
    public function buildScheduleSettingsPayload(StaticGroup $static): array
    {
        $context = $this->resolveDiscordGuildContextTask->run($static->discord_guild_id);
        $timezones = timezone_identifiers_list();

        return array_merge([
            'static' => $static,
            'timezones' => $timezones,
        ], $context);
    }

    /**
     * Execute update for logs settings.
     *
     * @param StaticGroup $static
     * @param array $data
     * @return void
     */
    public function executeUpdateLogsSettings(StaticGroup $static, array $data): void
    {
        $this->updateStaticSettingsTask->run($static, $data);
    }

    /**
     * Execute update for schedule settings.
     *
     * @param StaticGroup $static
     * @param array $data
     * @param bool $postNextAfterRaid
     * @return void
     */
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

    /**
     * Execute webhook test.
     *
     * @param StaticGroup $static
     * @return bool
     */
    public function executeWebhookTest(StaticGroup $static): bool
    {
        return $this->discordWebhookService->sendTestMessage();
    }
}
