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
     * Build the preprocessing prompt for Flash model (data restructuring).
     * Combines: system prompt + relevant boss tactics + raw WCL log data + localization.
     */
    public static function buildPreprocessingPrompt(string $wclJsonData, array $localization, array $bossNames): string
    {
        $promptPath = resource_path('prompts/gemini_data_preprocessing.txt');

        if (!file_exists($promptPath)) {
            Log::error("Preprocessing prompt file missing: {$promptPath}");
            throw new \Exception("Preprocessing prompt file missing: {$promptPath}");
        }

        $systemPrompt = file_get_contents($promptPath);

        // Load relevant boss tactics
        $tactics = self::loadBossTactics($bossNames);

        $prompt = $systemPrompt;

        if (!empty($tactics)) {
            $prompt .= "\n\n=== BOSS TACTICS ===\n" . $tactics;
        }

        if (!empty($localization)) {
            $prompt .= "\n\n=== LOCALIZATION ===\n" . json_encode($localization, JSON_UNESCAPED_UNICODE);
        }

        $prompt .= "\n\n=== RAW LOG DATA ===\n" . $wclJsonData;

        return $prompt;
    }

    /**
     * Load boss tactics files for the given boss names.
     * Maps boss names from WCL (e.g. "Imperator Averzian") to tactic filenames (e.g. "imperator-averzian.md").
     */
    private static function loadBossTactics(array $bossNames): string
    {
        $tacticsDir = resource_path('tactics');
        $allTactics = '';

        foreach ($bossNames as $bossName) {
            $slug = self::bossNameToSlug($bossName);
            $filePath = $tacticsDir . '/' . $slug . '.md';

            if (file_exists($filePath)) {
                $allTactics .= "\n--- TACTICS: {$bossName} ---\n";
                $allTactics .= file_get_contents($filePath);
                $allTactics .= "\n";
            } else {
                Log::warning("No tactics file found for boss: {$bossName} (tried: {$filePath})");
            }
        }

        return $allTactics;
    }

    /**
     * Convert a boss name from WCL format to a tactics filename slug.
     * "Imperator Averzian" → "imperator-averzian"
     * "Chimaerus, the Undreamt God" → "chimaerus-the-undreamt-god"
     * "Vaelgor & Ezzorak" → "vaelgor-and-ezzorak"
     * "Belo'ren Child of Al'ar" → "beloren-child-of-alar"
     */
    private static function bossNameToSlug(string $bossName): string
    {
        $slug = strtolower($bossName);
        $slug = str_replace('&', 'and', $slug);
        $slug = str_replace("'", '', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Build the report generation prompt for Pro model.
     * Takes the structured analysis JSON from Flash and produces final HTML reports.
     */
    public static function buildReportGenerationPrompt(string $preprocessedJsonData): string
    {
        $promptPath = resource_path('prompts/gemini_report_generation.txt');

        if (!file_exists($promptPath)) {
            Log::error("Report generation prompt file missing: {$promptPath}");
            throw new \Exception("Report generation prompt file missing: {$promptPath}");
        }

        $systemPrompt = file_get_contents($promptPath);

        return $systemPrompt . "\n\n=== PRE-ANALYZED DATA ===\n" . $preprocessedJsonData;
    }

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
