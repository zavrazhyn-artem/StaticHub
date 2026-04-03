<?php

declare(strict_types=1);

namespace App\Services\Analysis;

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
        $report = $this->resolveReport($reportId);
        $logData = $this->fetchWclContext($report);

        return $this->executeAiAnalysis($message, $logData);
    }

    /**
     * Resolve the report from the database.
     *
     * @param int $reportId
     * @return TacticalReport
     */
    private function resolveReport(int $reportId): TacticalReport
    {
        return TacticalReport::query()->findWithRoster($reportId);
    }

    /**
     * Orchestrates the WCL data fetch.
     *
     * @param TacticalReport $report
     * @return array
     */
    private function fetchWclContext(TacticalReport $report): array
    {
        $rosterNames = $report->getRosterCharacterNames();

        return $this->wclService->getLogSummary($report->wcl_report_id, $rosterNames);
    }

    /**
     * Orchestrates the Gemini API call.
     *
     * @param string $message
     * @param array $logData
     * @return string
     */
    private function executeAiAnalysis(string $message, array $logData): string
    {
        return $this->geminiService->analyzeLog($message, $logData);
    }
}
