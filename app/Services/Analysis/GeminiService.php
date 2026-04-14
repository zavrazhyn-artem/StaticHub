<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\GeminiPromptBuilder;
use App\Helpers\GeminiResponseFormatter;
use App\Helpers\RaidAnalysisPromptBuilder;
use App\Services\Logging\AiRequestLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $apiKey;
    private string $baseUrl;
    private string $flashModel;
    private string $proModel;

    private const BASE_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const CACHE_API_URL = 'https://generativelanguage.googleapis.com/v1beta/cachedContents';

    public function __construct(
        private readonly AiRequestLogger $aiLogger,
    ) {
        $this->apiKey = (string) config('services.gemini.key', config('services.gemini.api_key'));
        $this->baseUrl = (string) config(
            'services.gemini.base_url',
            self::BASE_API_URL . 'gemini-2.5-flash:generateContent'
        );
        $this->flashModel = (string) config('services.gemini.flash_model', 'gemini-2.5-flash');
        $this->proModel = (string) config('services.gemini.pro_model', 'gemini-2.5-flash');
    }

    public function buildModelUrl(string $model): string
    {
        return self::BASE_API_URL . $model . ':generateContent';
    }

    /**
     * Create a Gemini cached context with preprocessed data + tactics.
     * Returns the cache ID (cachedContents/xxx) or null on failure.
     *
     * @param string $contextContent  The content to cache (preprocessed data + tactics)
     * @param string $model           Model to bind the cache to
     * @param int    $ttlSeconds      Cache TTL in seconds (default 3 hours)
     * @return array{cache_id: string, expires_at: string}|null
     */
    public function createCachedContext(string $contextContent, string $model, int $ttlSeconds = 10800): ?array
    {
        $payload = [
            'model' => "models/{$model}",
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $contextContent],
                    ],
                ],
            ],
            'ttl' => "{$ttlSeconds}s",
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(30)
                ->post(self::CACHE_API_URL . '?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                Log::error('Gemini cache creation failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $cacheId = $data['name'] ?? null;
            $expireTime = $data['expireTime'] ?? null;

            if (!$cacheId) {
                Log::error('Gemini cache creation returned no ID', ['response' => $data]);
                return null;
            }

            Log::info('Gemini cached context created', [
                'cache_id'   => $cacheId,
                'model'      => $model,
                'ttl'        => $ttlSeconds,
                'expires_at' => $expireTime,
            ]);

            return [
                'cache_id'   => $cacheId,
                'expires_at' => $expireTime,
            ];
        } catch (\Exception $e) {
            Log::error('Gemini cache creation exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Execute a Gemini request using a cached context.
     * The cached context provides the data, this call only sends the user prompt.
     */
    public function executeWithCache(string $cacheId, string $promptText, string $modelUrl, int $timeout = 300, bool $jsonMode = false): string
    {
        $payload = [
            'cachedContent' => $cacheId,
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $promptText],
                    ],
                ],
            ],
        ];

        if ($jsonMode) {
            $payload['generationConfig'] = ['responseMimeType' => 'application/json'];
        }

        $startTime = microtime(true);
        $modelName = 'unknown';
        if (preg_match('#/models/([^/:]+)#', $modelUrl, $matches)) {
            $modelName = $matches[1];
        }

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout($timeout)
                ->post($modelUrl . '?key=' . $this->apiKey, $payload);

            if ($response->failed()) {
                Log::error('Gemini cached request failed', [
                    'model'    => $modelName,
                    'cache_id' => $cacheId,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
                ]);

                $this->aiLogger->logRequest(
                    'gemini', $modelName, $modelUrl,
                    $response->json(), $startTime, 'error', $response->body()
                );

                throw new \Exception("Gemini cached request failed ({$modelName}): " . $response->body());
            }

            $responseData = $response->json();
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            $this->aiLogger->logRequest(
                'gemini', $modelName, $modelUrl,
                $responseData, $startTime, 'success'
            );

            return (string) ($text ?? '');
        } catch (\Exception $e) {
            Log::error('Gemini cached request exception', ['model' => $modelName, 'message' => $e->getMessage()]);
            throw $e;
        }
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

        $startTime = microtime(true);

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

                $this->aiLogger->logRequest(
                    'gemini', $this->extractModelName(), $this->baseUrl,
                    $response->json(), $startTime, 'error', $response->body()
                );

                throw new \Exception('Gemini API Error: ' . $response->body());
            }

            $responseData = $response->json();
            $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

            $this->aiLogger->logRequest(
                'gemini', $this->extractModelName(), $this->baseUrl,
                $responseData, $startTime, 'success'
            );

            if (!$text) {
                Log::warning('Gemini API returned empty response', ['response' => $responseData]);
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
        $startTime = microtime(true);
        $response = Http::post($this->baseUrl . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            Log::error('Gemini API Error: ' . $response->body());

            $this->aiLogger->logRequest(
                'gemini', $this->extractModelName(), $this->baseUrl,
                $response->json(), $startTime, 'error', $response->body()
            );

            throw new \Exception('AI Analysis failed: ' . $response->body());
        }

        $result = $response->json();

        $this->aiLogger->logRequest(
            'gemini', $this->extractModelName(), $this->baseUrl,
            $result, $startTime, 'success'
        );

        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'AI Analysis could not be generated.';
    }

    /**
     * Analyze tactical data for raid reports (legacy single-model path).
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
     * Stage 1: Preprocess raw WCL data using Flash model.
     * Cross-references log data with boss tactics to produce structured analysis JSON.
     *
     * @param string $wclJsonData   Raw WCL log data as JSON string
     * @param array  $localization  Raid leader + participant locales
     * @param array  $bossNames     Boss names from phase_summary keys
     * @return string  Structured analysis JSON from Flash
     */
    public function preprocessWithFlash(string $wclJsonData, array $localization, array $bossNames): string
    {
        Log::info('Stage 1: Flash preprocessing started', ['bosses' => $bossNames]);

        $prompt = GeminiPromptBuilder::buildPreprocessingPrompt($wclJsonData, $localization, $bossNames);
        $url = $this->buildModelUrl($this->flashModel);

        $rawResponse = $this->executeRequestWithModel($prompt, $url, 300, 1, true);
        $cleanedText = GeminiResponseFormatter::cleanMarkdown($rawResponse);

        Log::info('Stage 1: Flash preprocessing completed');

        return $cleanedText;
    }

    /**
     * Execute a request against a specific model URL (for multi-model pipeline).
     * Retries on 503/429 with exponential backoff before giving up.
     */
    public function executeRequestWithModel(string $promptText, string $modelUrl, int $timeout = 120, int $maxRetries = 3, bool $jsonMode = false): string
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

        $modelName = 'unknown';
        if (preg_match('#/models/([^/:]+)#', $modelUrl, $matches)) {
            $modelName = $matches[1];
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $startTime = microtime(true);

            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout($timeout)
                    ->post($modelUrl . '?key=' . $this->apiKey, $payload);

                // Retry on 503 (overloaded) or 429 (rate limit)
                if (in_array($response->status(), [503, 429]) && $attempt < $maxRetries) {
                    $backoff = $attempt * 15;
                    Log::warning("Gemini {$modelName} returned {$response->status()}, retrying in {$backoff}s (attempt {$attempt}/{$maxRetries})");
                    sleep($backoff);
                    continue;
                }

                if ($response->failed()) {
                    Log::error('Gemini API request failed', [
                        'model' => $modelName,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    $this->aiLogger->logRequest(
                        'gemini', $modelName, $modelUrl,
                        $response->json(), $startTime, 'error', $response->body()
                    );

                    throw new \Exception("Gemini API Error ({$modelName}): " . $response->body());
                }

                $responseData = $response->json();
                $text = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

                $this->aiLogger->logRequest(
                    'gemini', $modelName, $modelUrl,
                    $responseData, $startTime, 'success'
                );

                if (!$text) {
                    Log::warning('Gemini API returned empty response', ['model' => $modelName, 'response' => $responseData]);
                }

                return (string) ($text ?? '');
            } catch (\Exception $e) {
                $lastException = $e;
                Log::error('Gemini API Exception', ['model' => $modelName, 'attempt' => $attempt, 'message' => $e->getMessage()]);

                // Don't retry on non-retryable errors
                if (!str_contains($e->getMessage(), '503') && !str_contains($e->getMessage(), '429')) {
                    throw $e;
                }

                if ($attempt < $maxRetries) {
                    $backoff = $attempt * 15;
                    Log::warning("Retrying after exception in {$backoff}s (attempt {$attempt}/{$maxRetries})");
                    sleep($backoff);
                }
            }
        }

        throw $lastException ?? new \Exception("Gemini API Error ({$modelName}): All {$maxRetries} retries exhausted");
    }

    /**
     * Stage 2: Generate final report using Pro model.
     * Takes the structured analysis JSON from Flash and produces the final HTML report.
     *
     * @param string $preprocessedJson  Structured analysis JSON from Flash stage
     * @return string  Final report JSON with title, main, and per-player HTML
     */
    public function generateReportWithPro(string $preprocessedJson): string
    {
        Log::info('Stage 2: Pro report generation started');

        $prompt = GeminiPromptBuilder::buildReportGenerationPrompt($preprocessedJson);
        $url = $this->buildModelUrl($this->proModel);

        $rawResponse = $this->executeRequestWithModel($prompt, $url, 300, 3, true);
        $cleanedText = GeminiResponseFormatter::cleanMarkdown($rawResponse);

        Log::info('Stage 2: Pro report generation completed');

        return $cleanedText;
    }

    /**
     * Two-stage tactical analysis pipeline with context caching:
     * 1. Flash preprocessing → structured analysis JSON
     * 2. Create Gemini cached context (preprocessed data for Pro model)
     * 3. Pro report generation using cached context
     *
     * @return array{response: string, model: string, cache_id: string|null, cache_expires_at: string|null}
     */
    public function analyzeTacticalDataTwoStage(string $wclJsonData, array $localization, array $bossNames): array
    {
        // Stage 1: Flash preprocessing
        $preprocessedJson = $this->preprocessWithFlash($wclJsonData, $localization, $bossNames);

        // Validate Flash output
        $parsed = json_decode($preprocessedJson, true);
        if (!$parsed || !is_array($parsed)) {
            Log::error('Flash preprocessing returned invalid JSON, falling back to legacy single-model');
            return [
                'response'         => $this->analyzeTacticalData($wclJsonData, $localization),
                'model'            => $this->flashModel . ' (legacy fallback)',
                'cache_id'         => null,
                'cache_expires_at' => null,
            ];
        }

        // Stage 2: Create cached context for Pro model (DATA ONLY — no prompt instructions)
        // Cache contains preprocessed analysis + raw supplementary data for chat queries.
        // Prompts are sent per-request so the same cache works for report generation and chat.
        $rawData = json_decode($wclJsonData, true);
        $supplementaryData = json_encode([
            'casts_summary'  => $rawData['casts_summary'] ?? [],
            'interrupts'     => $rawData['interrupts'] ?? [],
            'player_details' => $rawData['player_details'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        $cacheContent = "You are a WoW Mythic Raid AI Analyst. Below is pre-analyzed raid combat data "
            . "that has been cross-referenced with boss tactics, plus raw supplementary data "
            . "for detailed queries. Use this data to answer questions "
            . "and generate reports as instructed in each request.\n\n"
            . "=== PRE-ANALYZED RAID DATA ===\n" . $preprocessedJson
            . "\n\n=== RAW SUPPLEMENTARY DATA (for chat queries) ===\n" . $supplementaryData;

        $cache = $this->createCachedContext($cacheContent, $this->proModel, 10800);

        $cacheId = $cache['cache_id'] ?? null;
        $cacheExpiresAt = $cache['expires_at'] ?? null;

        // Stage 3: Pro report generation (using cache if available, with Flash fallback)
        try {
            if ($cacheId) {
                Log::info('Stage 3: Pro report generation with cached context', ['cache_id' => $cacheId]);
                $reportPrompt = GeminiPromptBuilder::buildReportGenerationPrompt($preprocessedJson);
                // Replace data in prompt with reference to cache (prompt already has instructions)
                $reportInstructions = file_get_contents(resource_path('prompts/gemini_report_generation.txt'));
                $url = $this->buildModelUrl($this->proModel);
                $rawResponse = $this->executeWithCache(
                    $cacheId,
                    $reportInstructions . "\n\nGenerate the report now using the PRE-ANALYZED RAID DATA from the cached context. Output strictly raw JSON.",
                    $url,
                    300,
                    true
                );
                $response = GeminiResponseFormatter::cleanMarkdown($rawResponse);
                Log::info('Stage 3: Pro report generation with cache completed');
            } else {
                Log::warning('Cache creation failed, generating report without cache');
                $response = $this->generateReportWithPro($preprocessedJson);
            }

            return [
                'response'         => $response,
                'model'            => $this->proModel,
                'cache_id'         => $cacheId,
                'cache_expires_at' => $cacheExpiresAt,
            ];
        } catch (\Exception $e) {
            Log::warning('Pro model unavailable, falling back to Flash for report generation', [
                'error' => $e->getMessage(),
            ]);
            return [
                'response'         => $this->generateReportWithFlashFallback($preprocessedJson),
                'model'            => $this->flashModel . ' (pro fallback)',
                'cache_id'         => $cacheId,
                'cache_expires_at' => $cacheExpiresAt,
            ];
        }
    }

    /**
     * Fallback: Generate report using Flash model when Pro is unavailable.
     */
    private function generateReportWithFlashFallback(string $preprocessedJson): string
    {
        Log::info('Stage 2 fallback: Flash report generation started');

        $prompt = GeminiPromptBuilder::buildReportGenerationPrompt($preprocessedJson);
        $url = $this->buildModelUrl($this->flashModel);

        $rawResponse = $this->executeRequestWithModel($prompt, $url, 300, 1, true);
        $cleanedText = GeminiResponseFormatter::cleanMarkdown($rawResponse);

        Log::info('Stage 2 fallback: Flash report generation completed');

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

    private function extractModelName(): string
    {
        if (preg_match('#/models/([^/:]+)#', $this->baseUrl, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
