<?php

namespace App\Services;

use App\Jobs\ProcessRaidAnalysisJob;
use App\Models\StaticGroup;
use App\Models\TacticalReport;

class LogAnalysisService
{
    /**
     * Process manual log submission.
     *
     * @param string $wclUrl
     * @param StaticGroup $static
     * @return TacticalReport|null
     */
    public function submitManualLog(string $wclUrl, StaticGroup $static): ?TacticalReport
    {
        $reportId = $this->extractReportId($wclUrl);

        if (!$reportId) {
            return null;
        }

        $report = TacticalReport::create([
            'static_id' => $static->id,
            'wcl_report_id' => $reportId,
            'title' => 'Manual Log Analysis',
        ]);

        ProcessRaidAnalysisJob::dispatch($report);

        return $report;
    }

    /**
     * Extract the 16-character Report ID from the URL.
     *
     * @param string $url
     * @return string|null
     */
    protected function extractReportId(string $url): ?string
    {
        preg_match('/reports\/([a-zA-Z0-9]{16})/', $url, $matches);
        return $matches[1] ?? null;
    }
}
