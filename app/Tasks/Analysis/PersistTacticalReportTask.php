<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\RaidEvent;
use App\Models\TacticalReport;

class PersistTacticalReportTask
{
    /**
     * Create or update a tactical report for a matched WCL log.
     */
    public function run(array $matchedLog, RaidEvent $raid): TacticalReport
    {
        return TacticalReport::updateOrCreate(
            ['wcl_report_id' => $matchedLog['code']],
            [
                'static_id' => $raid->static_id,
                'raid_event_id' => $raid->id,
                'title' => $matchedLog['title'] ?? $raid->title
            ]
        );
    }
}
