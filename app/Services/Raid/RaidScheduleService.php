<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Helpers\RaidDateHelper;
use App\Models\RaidEvent;
use App\Models\StaticGroup;
use Carbon\Carbon;

class RaidScheduleService
{
    /**
     * Orchestrate the generation of upcoming raid events for a static.
     *
     * @param StaticGroup $static
     * @param int $weeksAhead
     * @return void
     */
    public function executeScheduleGeneration(StaticGroup $static, int $weeksAhead = 4): void
    {
        $raidDays = $static->getRaidDaysArray();

        if (empty($raidDays) || empty($static->raid_start_time)) {
            return;
        }

        $timezone = $static->timezone ?? 'UTC';

        $events = RaidDateHelper::calculateEventTimestamps(
            $raidDays,
            $static->raid_start_time,
            $static->raid_end_time,
            $timezone,
            $weeksAhead
        );

        foreach ($events as $eventData) {
            $this->persistEvent($static, $eventData['start'], $eventData['end']);
        }
    }

    /**
     * Persist or update a single raid event in the database.
     *
     * @param StaticGroup $static
     * @param Carbon $startTime
     * @param Carbon|null $endTime
     * @return void
     */
    private function persistEvent(StaticGroup $static, Carbon $startTime, ?Carbon $endTime): void
    {
        RaidEvent::updateOrCreate([
            'static_id' => $static->id,
            'start_time' => $startTime,
        ], [
            'end_time' => $endTime,
            'description' => 'Auto-generated raid session.',
        ]);
    }
}
