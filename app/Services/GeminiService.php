<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    // Base URL for older tactical analysis (Job-based)
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
    }

    /**
     * Legacy method for Raid Analysis Jobs.
     * Uses a prompt from a file and returns cleaned text.
     */
    public function analyzeTacticalData(string $wclJsonData): string
    {
        Log::info('Starting AI Tactical Analysis (Manual/Legacy)');

        $promptPath = resource_path('prompts/gemini_raid_analysis.txt');
        if (!file_exists($promptPath)) {
            Log::error("Prompt file missing: {$promptPath}");
            throw new \Exception("Prompt file missing: {$promptPath}");
        }
        $systemPrompt = file_get_contents($promptPath);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\nLog Data:\n" . $wclJsonData]
                    ]
                ]
            ]
        ];

        try {
            $response = Http::timeout(180)->post($this->baseUrl . '?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                Log::error('AI Tactical Analysis failed', ['status' => $response->status(), 'body' => $response->body()]);
                throw new \Exception('AI Analysis failed: ' . $response->body());
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            // Cleaning markdown JSON blocks if present
            if ($text) {
                $text = preg_replace('/^```json\s*/i', '', $text);
                $text = preg_replace('/```\s*$/i', '', $text);
            }

            Log::info('AI Tactical Analysis completed successfully');

            return trim($text ?? '');
        } catch (\Exception $e) {
            Log::error('AI Tactical Analysis Exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * New method for real-time AI Analyst chat.
     */
    public function analyzeLog(string $userMessage, array $rawLogData): string
    {
        $systemInstruction = "You are an expert World of Warcraft Raid Leader. Analyze this raw combat log and answer the user's question with tactical precision. Use clean text formatting, and highlight key abilities or damage numbers if necessary.";

        $prompt = "System Instruction: {$systemInstruction}\n\n";
        $prompt .= "Raw Log Data (JSON): " . json_encode($rawLogData) . "\n\n";
        $prompt .= "User Question: {$userMessage}";

        try {
            $response = Http::retry(3, 1000)->withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout(120)->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            Log::info('Gemini API Response Status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';
                Log::info('Gemini API Success (analyzeLog)');
                return $text;
            }

            Log::error('Gemini API Error (analyzeLog)', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return "Error: Gemini API responded with status " . $response->status();

        } catch (\Exception $e) {
            Log::error('Gemini Service Exception (analyzeLog)', ['message' => $e->getMessage()]);
            return "Error: " . $e->getMessage();
        }
    }
}
