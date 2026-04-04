<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\GeminiPromptBuilder;
use App\Helpers\GeminiResponseFormatter;
use App\Tasks\Analysis\ExecuteGeminiRequestTask;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    public function __construct(
        private readonly ExecuteGeminiRequestTask $executeGeminiRequestTask
    ) {}

    /**
     * Legacy method for Raid Analysis Jobs.
     * Uses a prompt from a file and returns cleaned text.
     *
     * @param string $wclJsonData
     * @return string
     * @throws \Exception
     */
    public function analyzeTacticalData(string $wclJsonData): string
    {
        Log::info('Starting AI Tactical Analysis (Manual/Legacy)');

        $prompt = GeminiPromptBuilder::buildTacticalAnalysisPrompt($wclJsonData);
        $rawResponse = $this->executeGeminiRequestTask->run($prompt, 180, 1, true);
        $cleanedText = GeminiResponseFormatter::cleanMarkdown($rawResponse);

        Log::info('AI Tactical Analysis completed successfully');

        return $cleanedText;
    }

    /**
     * New method for real-time AI Analyst chat.
     *
     * @param string $userMessage
     * @param array $rawLogData
     * @return string
     */
    public function analyzeLog(string $userMessage, array $rawLogData): string
    {
        try {
            $prompt = GeminiPromptBuilder::buildChatPrompt($userMessage, $rawLogData);
            $text = $this->executeGeminiRequestTask->run($prompt);

            if (empty($text)) {
                return 'Sorry, I could not generate a response.';
            }

            Log::info('Gemini API Success (analyzeLog)');

            return $text;

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception (analyzeLog)', ['message' => $e->getMessage()]);
            return "Error: " . $e->getMessage();
        }
    }
}
