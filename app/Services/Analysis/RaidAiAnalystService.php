<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\RaidAnalysisPromptBuilder;
use App\Tasks\ExecuteGeminiApiTask;

/**
 * Service to orchestrate AI-driven raid log analysis.
 */
class RaidAiAnalystService
{
    public function __construct(
        private readonly ExecuteGeminiApiTask $executeGeminiApiTask
    ) {
    }

    /**
     * Analyze raid combat log data using AI.
     */
    public function analyzeLog(array $logData): string
    {
        $payload = RaidAnalysisPromptBuilder::buildPayload($logData);

        return $this->executeGeminiApiTask->run($payload);
    }
}
