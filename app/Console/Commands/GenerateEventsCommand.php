<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Services\Raid\RaidScheduleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-events')]
#[Description('Generate events for all statics 30 days ahead based on their raid schedule')]
class GenerateEventsCommand extends Command
{
    public function handle(RaidScheduleService $raidScheduleService): void
    {
        $statics = StaticGroup::whereNotNull('raid_days')
            ->whereNotNull('raid_start_time')
            ->get();

        $this->info("Found {$statics->count()} statics with raid schedules.");

        foreach ($statics as $static) {
            $raidDays = $static->getRaidDaysArray();

            if (empty($raidDays)) {
                continue;
            }

            $raidScheduleService->executeScheduleGeneration($static);
            $this->info("Generated events for static: {$static->name}");
        }

        $this->info("Raid event generation complete.");
    }
}
