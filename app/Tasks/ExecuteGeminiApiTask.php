<?php

declare(strict_types=1);

namespace App\Tasks;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Task to execute requests against the Gemini API.
 */
class ExecuteGeminiApiTask
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key');
        $this->baseUrl = (string) config(
            'services.gemini.base_url',
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
        );
    }

    /**
     * Run the API request and return the text response.
     */
    public function run(array $payload): string
    {
        $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error: ' . $response->body());
            throw new \Exception('AI Analysis failed: ' . $response->body());
        }

        $result = $response->json();

        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'AI Analysis could not be generated.';
    }
}
