<?php

declare(strict_types=1);

namespace App\Helpers;

use Carbon\Carbon;

class RaidDateHelper
{
    /**
     * Calculate event timestamps based on raid days and start/end times.
     *
     * @param array $raidDays Array of days (e.g., ['mon', 'wed'])
     * @param string $startTime Start time (e.g., '20:00')
     * @param string|null $endTime End time (e.g., '23:00')
     * @param string $timezone Timezone of the static
     * @param int $weeksAhead Number of weeks to generate
     * @return array Array of [['start' => Carbon, 'end' => ?Carbon], ...]
     */
    public static function calculateEventTimestamps(
        array $raidDays,
        string $startTime,
        ?string $endTime,
        string $timezone,
        int $weeksAhead
    ): array {
        $dayMap = [
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
            'sun' => Carbon::SUNDAY,
        ];

        $timestamps = [];

        foreach ($raidDays as $day) {
            $dayLower = strtolower($day);
            if (!isset($dayMap[$dayLower])) {
                continue;
            }

            $targetDay = $dayMap[$dayLower];

            for ($i = 0; $i < $weeksAhead; $i++) {
                $currentDate = Carbon::today($timezone)->addWeeks($i);
                $date = $currentDate->isDayOfWeek($targetDay)
                    ? $currentDate
                    : $currentDate->next($targetDay);

                $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime, $timezone)->setTimezone('UTC');
                $end = null;

                if ($endTime) {
                    $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime, $timezone);

                    // If end time is before start time, it likely means it crosses over to the next day
                    // Note: We compare in the static's timezone to detect the rollover correctly
                    $startInTimezone = $start->copy()->setTimezone($timezone);
                    if ($end->lessThan($startInTimezone)) {
                        $end->addDay();
                    }

                    $end->setTimezone('UTC');
                }

                $timestamps[] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return $timestamps;
    }
}
