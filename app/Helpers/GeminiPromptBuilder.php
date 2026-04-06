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
    public static function buildTacticalAnalysisPrompt(string $wclJsonData, array $localization = []): string
    {
        $promptPath = resource_path('prompts/gemini_raid_analysis.txt');

        if (!file_exists($promptPath)) {
            Log::error("Prompt file missing: {$promptPath}");
            throw new \Exception("Prompt file missing: {$promptPath}");
        }

        $systemPrompt = file_get_contents($promptPath);

        $prompt = $systemPrompt;

        if (!empty($localization)) {
            $prompt .= "\n\nLocalization:\n" . json_encode($localization, JSON_UNESCAPED_UNICODE);
        }

        $prompt .= "\n\nLog Data:\n" . $wclJsonData;

        return $prompt;
    }

    /**
     * Build the prompt for the real-time AI analyst chat.
     *
     * @param string $userMessage
     * @param array $rawLogData
     * @return string
     */
    /**
     * @param array $history  [ ['role' => 'user'|'assistant', 'text' => string], ... ]
     */
    public static function buildChatPrompt(string $userMessage, array $context, array $history = []): string
    {
        $promptPath = resource_path('prompts/gemini_chat_analyst.txt');

        if (!file_exists($promptPath)) {
            Log::error("Chat prompt file missing: {$promptPath}");
            throw new \Exception("Chat prompt file missing: {$promptPath}");
        }

        $systemPrompt = file_get_contents($promptPath);

        $prompt = $systemPrompt
            . "\n\nContext Data (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE);

        if (!empty($history)) {
            $prompt .= "\n\nConversation History (last " . count($history) . " messages):\n";
            foreach ($history as $msg) {
                $label    = $msg['role'] === 'user' ? '[User]' : '[Assistant]';
                $prompt  .= "{$label}: {$msg['text']}\n";
            }
        }

        $prompt .= "\n\nUser Question: " . $userMessage;

        return $prompt;
    }
}
