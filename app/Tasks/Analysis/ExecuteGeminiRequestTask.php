<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExecuteGeminiRequestTask
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    }

    /**
     * Execute the Gemini API request.
     *
     * @param string $promptText
     * @param int $timeout
     * @param int $retries
     * @return string
     * @throws \Exception
     */
    public function run(string $promptText, int $timeout = 120, int $retries = 3, bool $jsonMode = false): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText]
                    ]
                ]
            ]
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
}
