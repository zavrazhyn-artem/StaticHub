<?php

namespace App\Console\Commands;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use App\Services\DiscordMessageService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-discord-automations')]
#[Description('Process automated Discord raid announcements and reminders')]
class ProcessDiscordAutomations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DiscordMessageService $discordService)
    {
        $this->processPostNext($discordService);
        $this->processReminders($discordService);
    }

    /**
     * Logic A: Post next scheduled raid immediately after the current one ends.
     */
    protected function processPostNext(DiscordMessageService $discordService)
    {
        $this->info("Checking for raids that just ended...");

        // Find raids that ended in the last 30 minutes
        $recentlyEndedRaids = RaidEvent::where('end_time', '<=', now())
            ->where('end_time', '>', now()->subMinutes(30))
            ->get();

        foreach ($recentlyEndedRaids as $raid) {
            $static = $raid->static;
            $automation = $static->automation_settings ?? [];

            if (!empty($automation['post_next_after_raid'])) {
                $this->line("Static [{$static->name}] has 'Post Next' enabled.");

                // Find the next upcoming raid for this static
                $nextRaid = RaidEvent::where('static_id', $static->id)
                    ->where('start_time', '>', $raid->end_time)
                    ->whereNull('discord_message_id')
                    ->orderBy('start_time', 'asc')
                    ->first();

                if ($nextRaid) {
                    $this->info("Posting next raid for static: {$static->name} (Event: {$nextRaid->title})");
                    $discordService->sendOrUpdateRaidAnnouncement($nextRaid);
                }
            }
        }
    }

    /**
     * Logic B: Send a reminder/post if not already posted X hours before start.
     */
    protected function processReminders(DiscordMessageService $discordService)
    {
        $this->info("Checking for pre-raid reminders...");

        // We'll iterate through all statics that have a reminder configured
        $staticsWithReminders = StaticGroup::whereNotNull('automation_settings')->get()
            ->filter(fn($static) => !empty($static->automation_settings['reminder_hours_before']));

        foreach ($staticsWithReminders as $static) {
            $hoursBefore = (int) $static->automation_settings['reminder_hours_before'];

            // Find raids starting within the next X hours that haven't been posted to Discord yet
            $upcomingRaids = RaidEvent::where('static_id', $static->id)
                ->where('start_time', '<=', now()->addHours($hoursBefore))
                ->where('start_time', '>', now())
                ->whereNull('discord_message_id')
                ->get();

            foreach ($upcomingRaids as $raid) {
                $this->info("Reminder: Posting upcoming raid for static: {$static->name} (Event: {$raid->title})");
                $discordService->sendOrUpdateRaidAnnouncement($raid);
            }
        }
    }
}
