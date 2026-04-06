<?php

declare(strict_types=1);

namespace App\Services\Discord;

use App\Models\Event;
use App\Services\Raid\RaidScheduleService;

class DiscordAutomationService
{
    public function __construct(
        private readonly DiscordMessageService $discordMessageService,
        private readonly RaidScheduleService $raidScheduleService,
    ) {
    }

    /**
     * Mark raids as started when start_time has passed.
     *
     * @return array<int, string>
     */
    public function executeRaidStartedAutomations(): array
    {
        $raids = Event::with('static')
            ->where('raid_started', false)
            ->where('start_time', '<=', now())
            ->where('raid_over', false)
            ->get();

        $results = [];

        foreach ($raids as $raid) {
            $raid->update(['raid_started' => true]);
            $results[] = "Marked raid as started: Event ID {$raid->id}";

            // Update Discord message to remove interactive buttons
            if ($raid->discord_message_id) {
                $this->discordMessageService->sendOrUpdateRaidAnnouncement($raid);
                $results[] = "Updated Discord message (removed buttons): Event ID {$raid->id}";
            }
        }

        return $results;
    }

    /**
     * Process raids that just ended:
     * - Mark them as raid_over
     * - Delete old Discord message
     * - Post next raid if post_next_after_raid is enabled
     *
     * @return array<int, string>
     */
    public function executePostNextAutomations(): array
    {
        $recentlyEndedRaids = $this->raidScheduleService->getRecentlyEndedRaids();
        $results = [];

        foreach ($recentlyEndedRaids as $raid) {
            $raid->update(['raid_over' => true]);
            $results[] = "Marked raid as over: Event ID {$raid->id}";

            $static = $raid->static;
            $automation = $static->automation_settings ?? [];

            if (!empty($automation['post_next_after_raid'])) {
                // Delete the ended raid's Discord message
                $this->discordMessageService->deleteRaidAnnouncement($raid);
                $results[] = "Deleted Discord message for ended raid: Event ID {$raid->id}";

                // Post the next raid
                $nextRaid = $this->raidScheduleService->getNextUnpostedRaid($static->id, $raid->end_time);

                if ($nextRaid) {
                    $this->discordMessageService->sendOrUpdateRaidAnnouncement($nextRaid);
                    $results[] = "Posted next raid for static: {$static->name} (Event ID: {$nextRaid->id})";
                }
            } elseif (!empty($automation['reminder_hours_before'])) {
                // reminder_hours_before mode: just delete the ended raid's message
                $this->discordMessageService->deleteRaidAnnouncement($raid);
                $results[] = "Deleted Discord message for ended raid (reminder mode): Event ID {$raid->id}";
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
        $upcomingRaids = $this->raidScheduleService->getRaidsNeedingReminders();
        $results = [];

        foreach ($upcomingRaids as $raid) {
            $static = $raid->static;
            $this->discordMessageService->sendOrUpdateRaidAnnouncement($raid);
            $results[] = "Reminder: Posted upcoming raid for static: {$static->name} (Event ID: {$raid->id})";
        }

        return $results;
    }
}
