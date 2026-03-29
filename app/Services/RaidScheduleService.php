<?php

namespace App\Services;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RaidScheduleService
{
    /**
     * Generate upcoming raid events for a static.
     */
    public function generateUpcomingEvents(StaticGroup $static, int $weeksAhead = 4): void
    {
        if (empty($static->raid_days) || empty($static->raid_start_time)) {
            return;
        }

        $raidDays = is_string($static->raid_days) ? json_decode($static->raid_days, true) : $static->raid_days;
        $timezone = $static->timezone ?? 'UTC';

        if (!is_array($raidDays)) {
            return;
        }

        $dayMap = [
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
            'sun' => Carbon::SUNDAY,
        ];

        foreach ($raidDays as $day) {
            if (!isset($dayMap[strtolower($day)])) continue;

            $targetDay = $dayMap[strtolower($day)];

            for ($i = 0; $i < $weeksAhead; $i++) {
                $currentDate = Carbon::today($timezone)->addWeeks($i);
                $date = $currentDate->isDayOfWeek($targetDay)
                    ? $currentDate
                    : $currentDate->next($targetDay);

                $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $static->raid_start_time, $timezone)->setTimezone('UTC');
                $endTime = null;

                if ($static->raid_end_time) {
                    $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $static->raid_end_time, $timezone);

                    // If end time is before start time, it likely means it crosses over to the next day
                    if ($endTime->lessThan($startTime->copy()->setTimezone($timezone))) {
                        $endTime->addDay();
                    }

                    $endTime->setTimezone('UTC');
                }

                RaidEvent::updateOrCreate([
                    'static_id' => $static->id,
                    'start_time' => $startTime,
                ], [
                    'end_time' => $endTime,
                    'title' => 'Mythic Progression',
                    'description' => 'Auto-generated raid session.',
                ]);
            }
        }
    }
}
