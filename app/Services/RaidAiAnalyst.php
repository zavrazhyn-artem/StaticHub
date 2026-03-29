<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RaidAiAnalyst
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function analyzeLog(array $logData)
    {
        $systemPrompt = <<<'PROMPT'
You are a high-level Mythic Raid Strategist. Your mission is to transform raw combat log data into a biting, professional, and actionable tactical review.

CRITICAL RULE 1: You MUST translate your final generated output entirely into the English language.
CRITICAL RULE 2: NEVER GUESS. If specific data is missing, state "Data missing".
CRITICAL RULE 3: Structure the report into two distinct parts: GLOBAL STRATEGIC REVIEW and INDIVIDUAL TACTICAL DOSSIERS.

CONTEXT:
Ignore Mythic+ (M+) data. Focus on Raid encounters.
Roster: [ROSTER_LIST_PLACEHOLDER]

---
PART I: GLOBAL STRATEGIC REVIEW (For Raid Leader)
Format: ### GLOBAL MISSION REPORT
- EXECUTIVE SUMMARY: Provide a 2-sentence professional overview of the raid's primary failure point.
- CRITICAL FAILURE ANALYSIS: Connect the data. (e.g., "The high number of missed interrupts on [Spell] directly correlates with the massive damage taken from [Ability], leading to wipes at X%").
- STRATEGIC DIRECTIVE: Provide 2 specific, high-level tactical adjustments for the next session.

---
PART II: INDIVIDUAL TACTICAL DOSSIERS (For Each Player)
Format: ### PERSONAL REPORT: [PlayerName]
For each player found in the log:
- COMBAT STATUS: A brief sentence summarizing their survival and mechanical contribution.
- FATAL ERRORS: List the most frequent or critical abilities that killed them.
- AVOIDABLE DAMAGE PERFORMANCE: Analyze their presence in the `major_damage_taken` list. If they are a "top victim," call it out with the specific damage amount.
- INTERRUPT PARTICIPATION: Compare their `interrupted_by` count to the raid's `total_missed`. If they are a class with a short kick CD and their count is low, label this a "Critical Mechanical Failure".
- ACTIONABLE ADVICE: One sharp, direct instruction for this specific player.
PROMPT;

        // We need to clean up logData a bit to not exceed token limits, although Gemini Flash has a large window.
        // We'll focus on the most relevant parts.
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nLog Data:\n" . json_encode($logData)]
                    ]
                ]
            ]
        ];

        $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error: ' . $response->body());
            throw new \Exception('AI Analysis failed: ' . $response->body());
        }

        $result = $response->json();

        // Extract the text from Gemini response
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'AI Analysis could not be generated.';
    }
}
