<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\TacticalReport;

class SaveAiAnalysisToReportTask
{
    /**
     * Save the AI analysis results to a tactical report.
     */
    public function run(TacticalReport $report, string $analysis): void
    {
        $report->update(['ai_analysis' => $analysis]);
    }
}
