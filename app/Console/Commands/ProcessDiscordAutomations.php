<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Discord\DiscordAutomationService;
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
    public function handle(DiscordAutomationService $automationService): void
    {
        $this->info("Marking raids as started...");
        $startedMessages = $automationService->executeRaidStartedAutomations();
        foreach ($startedMessages as $message) {
            $this->info($message);
        }

        $this->info("Checking for raids that just ended...");
        $postNextMessages = $automationService->executePostNextAutomations();
        foreach ($postNextMessages as $message) {
            $this->info($message);
        }

        $this->info("Checking for pre-raid reminders...");
        $reminderMessages = $automationService->executePreRaidReminders();
        foreach ($reminderMessages as $message) {
            $this->info($message);
        }
    }
}
