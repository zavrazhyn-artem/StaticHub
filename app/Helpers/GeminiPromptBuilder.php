<?php

declare(strict_types=1);

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class GeminiPromptBuilder
{
    /**
     * Build the prompt for tactical analysis from a resource file.
     *
     * @param string $wclJsonData
     * @return string
     * @throws \Exception
     */
    public static function buildTacticalAnalysisPrompt(string $wclJsonData): string
    {
        $promptPath = resource_path('prompts/gemini_raid_analysis.txt');

        if (!file_exists($promptPath)) {
            Log::error("Prompt file missing: {$promptPath}");
            throw new \Exception("Prompt file missing: {$promptPath}");
        }

        $systemPrompt = file_get_contents($promptPath);

        return $systemPrompt . "\n\nLog Data:\n" . $wclJsonData;
    }

    /**
     * Build the prompt for the real-time AI analyst chat.
     *
     * @param string $userMessage
     * @param array $rawLogData
     * @return string
     */
    public static function buildChatPrompt(string $userMessage, array $rawLogData): string
    {
        $systemInstruction = "You are an expert World of Warcraft Raid Leader. Analyze this raw combat log and answer the user's question with tactical precision. Use clean text formatting, and highlight key abilities or damage numbers if necessary.";

        $prompt = "System Instruction: {$systemInstruction}\n\n";
        $prompt .= "Raw Log Data (JSON): " . json_encode($rawLogData) . "\n\n";
        $prompt .= "User Question: {$userMessage}\n";
        $prompt .= "Response: HTML formated message for chat bar 240px width without \`\`\`html\`\`\` tags.";

        return $prompt;
    }
}
