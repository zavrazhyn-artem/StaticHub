<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\WclParserHelper;
use App\Jobs\Analysis\ProcessRaidAnalysisJob;
use App\Models\StaticGroup;
use App\Models\TacticalReport;

class LogAnalysisService
{
    /**
     * Process manual log submission.
     */
    public function processManualLogSubmission(string $wclUrl, StaticGroup $static): ?TacticalReport
    {
        $reportId = WclParserHelper::extractReportIdFromUrl($wclUrl);

        if (!$reportId) {
            return null;
        }

        $report = $this->createReportRecord($static, $reportId);

        $this->dispatchAnalysisJob($report);

        return $report;
    }

    /**
     * Create a new TacticalReport record.
     */
    private function createReportRecord(StaticGroup $static, string $reportId): TacticalReport
    {
        return TacticalReport::create([
            'static_id' => $static->id,
            'wcl_report_id' => $reportId,
            'title' => 'Manual Log Analysis',
        ]);
    }

    /**
     * Dispatch the raid analysis job.
     */
    private function dispatchAnalysisJob(TacticalReport $report): void
    {
        ProcessRaidAnalysisJob::dispatch($report);
    }
}
