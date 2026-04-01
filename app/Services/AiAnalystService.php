<?php

namespace App\Services;

use App\Models\TacticalReport;

class AiAnalystService
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly WclService    $wclService
    ) {}

    /**
     * Analyze log data for a specific report and message.
     *
     * @param int $reportId
     * @param string $message
     * @return string
     */
    public function analyze(int $reportId, string $message): string
    {
        $report = TacticalReport::query()->findWithRoster($reportId);
        $rosterNames = $this->getRosterNames($report);
        $logData = $this->wclService->getLogSummary($report->wcl_report_id, $rosterNames);

        return $this->geminiService->analyzeLog($message, $logData);
    }

    /**
     * Get roster names from the report's static group.
     *
     * @param TacticalReport $report
     * @return array
     */
    protected function getRosterNames(TacticalReport $report): array
    {
        return $report->staticGroup->characters->pluck('name')->toArray();
    }
}
