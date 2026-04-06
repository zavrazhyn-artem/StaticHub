<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\GeminiPromptBuilder;
use App\Helpers\GeminiResponseFormatter;
use App\Helpers\RaidAnalysisPromptBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.key', config('services.gemini.api_key'));
        $this->baseUrl = (string) config(
            'services.gemini.base_url',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
        );
    }

    /**
     * Execute a raw Gemini API request.
     */
    public function executeRequest(string $promptText, int $timeout = 120, int $retries = 3, bool $jsonMode = false): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText],
                    ],
                ],
            ],
        ];

        if ($jsonMode) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        try {
            $response = Http::retry($retries, 1000)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout($timeout)
                ->post($this->baseUrl . '?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                Log::error('Gemini API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Gemini API Error: ' . $response->body());
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (!$text) {
                Log::warning('Gemini API returned empty response', ['response' => $response->json()]);
            }

            return (string) ($text ?? '');
        } catch (\Exception $e) {
            Log::error('Gemini API Exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Execute a raw Gemini API request with a pre-built payload.
     */
    public function executeApiRequest(array $payload): string
    {
        $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error: ' . $response->body());
            throw new \Exception('AI Analysis failed: ' . $response->body());
        }

        $result = $response->json();

        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'AI Analysis could not be generated.';
    }

    /**
     * Analyze tactical data for raid reports.
     */
    public function analyzeTacticalData(string $wclJsonData, array $localization = []): string
    {
        Log::info('Starting AI Tactical Analysis');

        $prompt = GeminiPromptBuilder::buildTacticalAnalysisPrompt($wclJsonData, $localization);
        $rawResponse = $this->executeRequest($prompt, 180, 1, true);
        $cleanedText = GeminiResponseFormatter::cleanMarkdown($rawResponse);

        Log::info('AI Tactical Analysis completed successfully');

        return $cleanedText;
    }

    /**
     * Real-time AI Analyst chat.
     */
    public function analyzeLog(string $userMessage, array $context, array $history = []): string
    {
        try {
            $prompt = GeminiPromptBuilder::buildChatPrompt($userMessage, $context, $history);
            $text = $this->executeRequest($prompt);

            if (empty($text)) {
                return 'Sorry, I could not generate a response.';
            }

            Log::info('Gemini API Success (analyzeLog)');

            return $text;
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception (analyzeLog)', ['message' => $e->getMessage()]);
            return 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Analyze raid combat log data using AI.
     */
    public function analyzeRaidLog(array $logData): string
    {
        $payload = RaidAnalysisPromptBuilder::buildPayload($logData);

        return $this->executeApiRequest($payload);
    }
}
