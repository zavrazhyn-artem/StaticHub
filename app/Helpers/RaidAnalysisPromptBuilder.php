<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Helper to build prompts and payloads for AI-driven raid analysis.
 */
class RaidAnalysisPromptBuilder
{
    /**
     * Build the massive system prompt for the Raid AI Analyst.
     *
     * @throws \Exception
     */
    public static function buildSystemPrompt(): string
    {
        $promptPath = resource_path('prompts/gemini_raid_analysis.txt');

        if (!file_exists($promptPath)) {
            Log::error("Raid analysis prompt file missing: {$promptPath}");
            throw new \Exception("Raid analysis prompt file missing: {$promptPath}");
        }

        return file_get_contents($promptPath);
    }

    /**
     * Build the final payload array required by the Gemini API.
     */
    public static function buildPayload(array $logData): array
    {
        $prompt = self::buildSystemPrompt() . "\n\nLog Data:\n" . json_encode($logData);

        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
    }
}
