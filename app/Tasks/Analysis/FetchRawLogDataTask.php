<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\TacticalReport;

class FetchRawLogDataTask
{
    /**
     * Fetch raw log data from the TacticalReport model.
     */
    public function run(string $wclReportId): array
    {
        $report = TacticalReport::where('wcl_report_id', $wclReportId)->first();

        if (!$report || !$report->raw_data) {
            return [];
        }

        // If raw_data is already a casted array, return it.
        // Otherwise, decode it if it's a string.
        if (is_array($report->raw_data)) {
            return $report->raw_data;
        }

        return json_decode($report->raw_data, true) ?? [];
    }
}
