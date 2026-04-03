<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Tasks\Raid\FetchNextUnpostedRaidTask;
use App\Tasks\Raid\FetchRaidsNeedingRemindersTask;
use App\Tasks\Raid\FetchRecentlyEndedRaidsTask;

class DiscordAutomationService
{
    public function __construct(
        private readonly DiscordMessageService $discordMessageService,
        private readonly FetchRecentlyEndedRaidsTask $fetchRecentlyEndedRaidsTask,
        private readonly FetchNextUnpostedRaidTask $fetchNextUnpostedRaidTask,
        private readonly FetchRaidsNeedingRemindersTask $fetchRaidsNeedingRemindersTask,
    ) {
    }

    /**
     * Post the next scheduled raid for statics that just finished a raid.
     *
     * @return array<int, string>
     */
    public function executePostNextAutomations(): array
    {
        $recentlyEndedRaids = $this->fetchRecentlyEndedRaidsTask->run();
        $results = [];

        foreach ($recentlyEndedRaids as $raid) {
            $static = $raid->static;
            $automation = $static->automation_settings ?? [];

            if (!empty($automation['post_next_after_raid'])) {
                $nextRaid = $this->fetchNextUnpostedRaidTask->run($static->id, $raid->end_time);

                if ($nextRaid) {
                    $this->discordMessageService->sendOrUpdateRaidAnnouncement($nextRaid);
                    $results[] = "Posted next raid for static: {$static->name} (Event: {$nextRaid->title})";
                }
            }
        }

        return $results;
    }

    /**
     * Send reminders for upcoming raids.
     *
     * @return array<int, string>
     */
    public function executePreRaidReminders(): array
    {
        $upcomingRaids = $this->fetchRaidsNeedingRemindersTask->run();
        $results = [];

        foreach ($upcomingRaids as $raid) {
            $static = $raid->static;
            $this->discordMessageService->sendOrUpdateRaidAnnouncement($raid);
            $results[] = "Reminder: Posted upcoming raid for static: {$static->name} (Event: {$raid->title})";
        }

        return $results;
    }
}
