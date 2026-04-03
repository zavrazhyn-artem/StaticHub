<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\RaidEvent;
use Carbon\Carbon;

class MatchRaidToWclLogTask
{
    /**
     * Match a raid event to a WCL log based on a 1-hour time window.
     */
    public function run(array $logs, RaidEvent $raid): ?array
    {
        return collect($logs)->first(function (array $log) use ($raid) {
            $logStart = Carbon::createFromTimestampMs($log['startTime']);
            // Allow 1 hour window
            return $logStart->between($raid->start_time->subHour(), $raid->start_time->addHour());
        });
    }
}
