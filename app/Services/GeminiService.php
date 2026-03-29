<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
    }

    public function analyzeTacticalData(string $wclJsonData): string
    {
        // Читаємо промпт прямо з файлу
        $promptPath = resource_path('prompts/gemini_raid_analysis.txt');
        if (!file_exists($promptPath)) {
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

        // Збільшений таймаут, щоб не відвалювалося
        $response = Http::timeout(120)->post($this->baseUrl . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            throw new \Exception('AI Analysis failed: ' . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/```\s*$/i', '', $text);

        return trim($text);
    }
}
