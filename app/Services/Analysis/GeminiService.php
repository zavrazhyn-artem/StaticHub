<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\GeminiPromptBuilder;
use App\Helpers\GeminiResponseFormatter;
use App\Helpers\RaidAnalysisPromptBuilder;
use App\Services\Logging\AiRequestLogger;
use Illuminate\Http\Client\Pool;
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
    public function executeWithCache(string $cacheId, string $promptText, string $modelUrl, int $timeout = 300, bool $jsonMode = false, int $maxOutputTokens = 65536): string
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
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
            ],
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
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
            $finishReason = $responseData['candidates'][0]['finishReason'] ?? null;

            $this->aiLogger->logRequest(
                'gemini', $modelName, $modelUrl,
                $responseData, $startTime, 'success'
            );

            if ($finishReason === 'MAX_TOKENS') {
                Log::warning('Gemini cached request hit MAX_TOKENS — output truncated', [
                    'model'             => $modelName,
                    'cache_id'          => $cacheId,
                    'output_length'     => strlen((string) $text),
                    'max_output_tokens' => $maxOutputTokens,
                ]);

                throw new \Exception(
                    "Gemini cached request truncated by MAX_TOKENS ({$modelName}, limit={$maxOutputTokens}). "
                    . "Increase maxOutputTokens or shrink the prompt/output schema."
                );
            }

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
     * Generate the human-readable raid report from the deterministic PHP-preprocessed JSON.
     * Wraps the existing generateReportWithPro path with cache creation.
     *
     * @param string $preprocessedJson  Output of TacticalDataAnalyzer::analyze()
     * @return array{response: string, model: string, cache_id: string|null, cache_expires_at: string|null}
     */
    public function generateReportFromPreprocessed(string $preprocessedJson, ?string $supplementaryJson = null): array
    {
        // Cache the preprocessed data so the chat (and any retries) can reuse without re-uploading
        $cacheContent = "You are a WoW Mythic Raid AI Analyst. Below is pre-analyzed raid combat data "
            . "produced by a deterministic PHP analyzer plus raw supplementary log data for chat queries. "
            . "Use the PRE-ANALYZED section for failures/performance summaries; use the RAW SUPPLEMENTARY "
            . "section for per-player details (cast counts, buff uptimes, dispel counts, gear, "
            . "consumables, etc.) when the user asks specific stats questions.\n\n"
            . "=== PRE-ANALYZED RAID DATA ===\n" . $preprocessedJson;

        if ($supplementaryJson) {
            $cacheContent .= "\n\n=== RAW SUPPLEMENTARY DATA (for chat queries) ===\n" . $supplementaryJson;
        }

        $cache = $this->createCachedContext($cacheContent, $this->proModel, 10800);
        $cacheId = $cache['cache_id'] ?? null;
        $cacheExpiresAt = $cache['expires_at'] ?? null;

        try {
            if ($cacheId) {
                Log::info('Generating report with cached context', ['cache_id' => $cacheId]);
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
            Log::warning('Pro report generation failed, falling back to Flash', ['error' => $e->getMessage()]);
            return [
                'response'         => $this->generateReportWithFlashFallback($preprocessedJson),
                'model'            => $this->flashModel . ' (pro fallback)',
                'cache_id'         => $cacheId,
                'cache_expires_at' => $cacheExpiresAt,
            ];
        }
    }

    /**
     * Create the raid-wide Gemini cache that both main + per-player generation will reuse.
     * Returns the cache descriptor or null on failure.
     *
     * @return array{cache_id: string, expires_at: ?string}|null
     */
    public function createRaidCache(string $preprocessedJson, ?string $supplementaryJson = null, int $ttlSeconds = 10800): ?array
    {
        $cacheContent = "You are a WoW Mythic Raid AI Analyst. Below is pre-analyzed raid combat data "
            . "produced by a deterministic PHP analyzer plus raw supplementary log data for chat queries. "
            . "Use the PRE-ANALYZED section for failures/performance summaries; use the RAW SUPPLEMENTARY "
            . "section for per-player details (cast counts, buff uptimes, dispel counts, gear, "
            . "consumables, etc.) when the user asks specific stats questions.\n\n"
            . "=== PRE-ANALYZED RAID DATA ===\n" . $preprocessedJson;

        if ($supplementaryJson) {
            $cacheContent .= "\n\n=== RAW SUPPLEMENTARY DATA (for chat queries) ===\n" . $supplementaryJson;
        }

        return $this->createCachedContext($cacheContent, $this->proModel, $ttlSeconds);
    }

    /**
     * Generate ONLY the raid-wide `main` report + title via a single Gemini call.
     * Uses the cached context created by `createRaidCache`.
     *
     * @return array{title: string, main: array<int, array>}
     */
    public function generateMainReportBlocks(string $cacheId): array
    {
        $instructions = file_get_contents(resource_path('prompts/gemini_main_report.txt'));
        $url = $this->buildModelUrl($this->proModel);

        Log::info('Generating main report blocks', ['cache_id' => $cacheId]);

        // Bypass executeWithCache so we can inspect the FULL raw response on
        // failure — useful when Gemini returns truncated JSON without flagging
        // MAX_TOKENS. We dump the entire candidate payload to storage/logs so
        // we can see finishReason, parts[] structure, usage metadata, etc.
        $payload = [
            'cachedContent' => $cacheId,
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $instructions . "\n\nGenerate the raid-wide main report now. Output strictly raw JSON."]],
            ]],
            'generationConfig' => [
                'maxOutputTokens'  => 65536,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(300)
            ->post($url . '?key=' . $this->apiKey, $payload);

        if ($response->failed()) {
            Log::error('Main report HTTP failed', [
                'status' => $response->status(),
                'body'   => mb_substr((string) $response->body(), 0, 1000),
            ]);
            throw new \Exception('Main report HTTP failed (' . $response->status() . '): ' . mb_substr((string) $response->body(), 0, 500));
        }

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? [];
        $finishReason = $candidate['finishReason'] ?? null;
        $usage = $data['usageMetadata'] ?? null;

        // Gemini 3 may emit multiple parts (thoughts + answer). Concatenate ALL
        // text-bearing parts so we don't accidentally pick a thinking-only part
        // and miss the JSON. Skip parts flagged as `thought`.
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (!empty($part['thought'])) continue;
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        Log::info('Main report API response stats', [
            'finish_reason'   => $finishReason,
            'parts_count'     => count($candidate['content']['parts'] ?? []),
            'text_length'     => strlen($text),
            'usage'           => $usage ? [
                'thoughts_tokens' => $usage['thoughtsTokenCount'] ?? null,
                'candidates'      => $usage['candidatesTokenCount'] ?? null,
                'total'           => $usage['totalTokenCount'] ?? null,
            ] : null,
        ]);

        if ($finishReason === 'MAX_TOKENS') {
            $this->dumpMainReportFailure('max_tokens', $data, $text);
            throw new \Exception("Main report truncated by MAX_TOKENS at {$usage['candidatesTokenCount']}/65536 tokens. Output length: " . strlen($text) . ' chars.');
        }

        $cleaned = GeminiResponseFormatter::cleanMarkdown($text);
        $decoded = json_decode($cleaned, true);

        // Gemini 3 sometimes appends trailing garbage (extra `}`, partial
        // duplicate object) after a valid JSON response. Retry decoding
        // against the first balanced JSON object in the text.
        if (!is_array($decoded)) {
            $extracted = GeminiResponseFormatter::extractFirstJsonObject($cleaned);
            if ($extracted !== $cleaned) {
                $decoded = json_decode($extracted, true);
                if (is_array($decoded)) {
                    Log::info('Main report JSON recovered via balanced-brace extraction', [
                        'original_length'  => strlen($cleaned),
                        'extracted_length' => strlen($extracted),
                        'trimmed_chars'    => strlen($cleaned) - strlen($extracted),
                    ]);
                }
            }
        }

        if (!is_array($decoded)) {
            $this->dumpMainReportFailure('invalid_json', $data, $text);
            throw new \Exception(
                'Main report returned invalid JSON. '
                . 'finish_reason=' . ($finishReason ?? 'null')
                . ', text_length=' . strlen($text)
                . ', json_error=' . json_last_error_msg()
                . '. Full raw response dumped to storage/logs/. Preview: '
                . mb_substr($text, 0, 300)
            );
        }

        return [
            'title' => (string) ($decoded['title'] ?? 'Raid Analysis'),
            'main'  => is_array($decoded['main'] ?? null) ? $decoded['main'] : [],
        ];
    }

    /**
     * Dump the full Gemini response to storage/logs for post-mortem diagnosis.
     * Truncation patterns vary across model versions, and the only reliable way
     * to know what actually happened is to inspect the raw candidate payload.
     */
    private function dumpMainReportFailure(string $reason, array $data, string $text): void
    {
        $timestamp = date('Y-m-d_His');
        $dumpPath = storage_path("logs/main_report_failure_{$reason}_{$timestamp}.json");
        @file_put_contents($dumpPath, json_encode([
            'reason'      => $reason,
            'timestamp'   => $timestamp,
            'response'    => $data,
            'text_length' => strlen($text),
            'text'        => $text,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::warning('Main report failure dumped', ['path' => $dumpPath, 'reason' => $reason]);
    }

    /**
     * Generate personal report blocks for each player IN PARALLEL (batched).
     * Uses Http::pool for concurrency, batched to `$concurrency` to respect rate limits.
     * Players that fail with 429 (RESOURCE_EXHAUSTED) are retried after the suggested
     * backoff window — the API includes "Please retry in Xs" in the message body.
     *
     * @param string   $cacheId
     * @param string[] $playerNames   Player names to generate for
     * @param int      $concurrency   Max parallel requests per batch (default 3 — keeps
     *                                 the 2M-input-tokens-per-minute paid quota safe even
     *                                 when each call counts the full cached context)
     * @return array<string, array<int, array>>  [playerName => blocks[]]
     */
    public function generatePlayerReportBlocks(string $cacheId, array $playerNames, int $concurrency = 3): array
    {
        $instructions = file_get_contents(resource_path('prompts/gemini_player_report.txt'));
        $url = $this->buildModelUrl($this->proModel);
        $results = [];
        $pendingRetry = []; // [name => attempts] for players that hit 429

        foreach (array_chunk($playerNames, $concurrency) as $batchIndex => $batch) {
            Log::info('Generating player reports batch', [
                'batch'   => $batchIndex + 1,
                'size'    => count($batch),
                'players' => $batch,
            ]);

            $batchResults = $this->dispatchPlayerBatch($cacheId, $url, $instructions, $batch);
            foreach ($batchResults as $name => $entry) {
                if ($entry['status'] === 'ok') {
                    $results[$name] = $entry['blocks'];
                } elseif ($entry['status'] === 'rate_limited') {
                    $pendingRetry[$name] = ['attempts' => 0, 'retry_after' => $entry['retry_after']];
                } else {
                    $results[$name] = [];
                }
            }
        }

        // Retry players that hit 429. Up to 3 total attempts each.
        $maxRetries = 3;
        while (!empty($pendingRetry)) {
            // Wait for the longest suggested retry window in this batch.
            $waitSeconds = max(array_column($pendingRetry, 'retry_after'));
            // Cap at 90s so we don't sit forever on a runaway response; quota windows
            // typically reset within a minute.
            $waitSeconds = max(5, min(90, $waitSeconds + 2));
            Log::warning("Player report 429 — sleeping {$waitSeconds}s before retry", [
                'players' => array_keys($pendingRetry),
            ]);
            sleep($waitSeconds);

            $retryNames = array_keys($pendingRetry);
            $stillPending = [];

            foreach (array_chunk($retryNames, $concurrency) as $retryBatch) {
                $retryResults = $this->dispatchPlayerBatch($cacheId, $url, $instructions, $retryBatch);
                foreach ($retryResults as $name => $entry) {
                    if ($entry['status'] === 'ok') {
                        $results[$name] = $entry['blocks'];
                    } elseif ($entry['status'] === 'rate_limited' && $pendingRetry[$name]['attempts'] + 1 < $maxRetries) {
                        $stillPending[$name] = [
                            'attempts'    => $pendingRetry[$name]['attempts'] + 1,
                            'retry_after' => $entry['retry_after'],
                        ];
                    } else {
                        Log::error('Player report giving up after retries', ['player' => $name]);
                        $results[$name] = [];
                    }
                }
            }

            $pendingRetry = $stillPending;
        }

        return $results;
    }

    /**
     * Dispatch a single parallel batch of per-player Gemini calls.
     *
     * Returns an array per player: [
     *   'status'      => 'ok' | 'rate_limited' | 'failed',
     *   'blocks'      => array (only when status === 'ok'),
     *   'retry_after' => int seconds (only when status === 'rate_limited'),
     * ]
     *
     * @param string[] $playerNames
     * @return array<string, array{status:string, blocks?:array, retry_after?:int}>
     */
    private function dispatchPlayerBatch(string $cacheId, string $url, string $instructions, array $playerNames): array
    {
        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn($name) => $pool->as($name)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(300)
                ->post($url . '?key=' . $this->apiKey, [
                    'cachedContent' => $cacheId,
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $instructions . "\n\nTARGET_PLAYER: {$name}\n\nGenerate the personal report blocks for this player. Output strictly raw JSON of shape { \"blocks\": [...] }.",
                        ]],
                    ]],
                    'generationConfig' => [
                        'maxOutputTokens'  => 65536,
                        'responseMimeType' => 'application/json',
                    ],
                ]),
            $playerNames
        ));

        $out = [];
        foreach ($responses as $name => $response) {
            if (!is_object($response) || !method_exists($response, 'successful')) {
                Log::error('Player report generation failed (no response object)', ['player' => $name]);
                $out[$name] = ['status' => 'failed'];
                continue;
            }

            if (!$response->successful()) {
                $status = $response->status();
                $body   = (string) $response->body();

                if ($status === 429) {
                    $retryAfter = $this->parseRetryAfter($body);
                    Log::warning('Player report rate-limited (429)', [
                        'player'      => $name,
                        'retry_after' => $retryAfter,
                    ]);
                    $out[$name] = ['status' => 'rate_limited', 'retry_after' => $retryAfter];
                    continue;
                }

                Log::error('Player report generation failed', [
                    'player' => $name,
                    'status' => $status,
                    'body'   => mb_substr($body, 0, 500),
                ]);
                $out[$name] = ['status' => 'failed'];
                continue;
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $finishReason = $data['candidates'][0]['finishReason'] ?? null;

            if ($finishReason === 'MAX_TOKENS') {
                Log::warning('Player report hit MAX_TOKENS — output truncated', [
                    'player'        => $name,
                    'output_length' => strlen($text),
                ]);
                $out[$name] = ['status' => 'failed'];
                continue;
            }

            $cleaned = GeminiResponseFormatter::cleanMarkdown((string) $text);
            $decoded = json_decode($cleaned, true);

            // Gemini 3 sometimes appends trailing garbage after the closing
            // `}` of a valid JSON response. Retry against the first balanced
            // JSON object in the text before declaring failure.
            if (!is_array($decoded)) {
                $extracted = GeminiResponseFormatter::extractFirstJsonObject($cleaned);
                if ($extracted !== $cleaned) {
                    $decoded = json_decode($extracted, true);
                    if (is_array($decoded)) {
                        Log::info('Player report JSON recovered via balanced-brace extraction', [
                            'player'        => $name,
                            'trimmed_chars' => strlen($cleaned) - strlen($extracted),
                        ]);
                    }
                }
            }

            if (!is_array($decoded)) {
                $textLen = strlen((string) $text);
                $tail = $textLen > 0 ? mb_substr((string) $text, -200) : '';
                $usage = $data['usageMetadata'] ?? null;
                Log::warning('Player report invalid JSON', [
                    'player'        => $name,
                    'finish_reason' => $finishReason,
                    'output_length' => $textLen,
                    'json_error'    => json_last_error_msg(),
                    'usage'         => $usage ? [
                        'thoughts_tokens' => $usage['thoughtsTokenCount'] ?? null,
                        'candidates'      => $usage['candidatesTokenCount'] ?? null,
                        'total'           => $usage['totalTokenCount'] ?? null,
                    ] : null,
                    'preview_head'  => mb_substr((string) $text, 0, 200),
                    'preview_tail'  => $tail,
                ]);
                $out[$name] = ['status' => 'failed'];
                continue;
            }

            $blocks = $decoded['blocks'] ?? $decoded;
            $out[$name] = [
                'status' => 'ok',
                'blocks' => is_array($blocks) && array_is_list($blocks) ? $blocks : [],
            ];
        }

        return $out;
    }

    /**
     * Extract the suggested retry-after seconds from a Gemini 429 response body.
     * The API embeds "Please retry in 45.412s" or similar in the message string.
     */
    private function parseRetryAfter(string $body): int
    {
        if (preg_match('/retry in ([\d.]+)\s*s/i', $body, $m)) {
            return (int) ceil((float) $m[1]);
        }

        return 30; // sensible default — most quota windows reset within ~60s
    }

    /**
     * Generate the block-based structured raid report from the deterministic PHP-preprocessed JSON.
     * Returns a Gemini response whose JSON shape is: { title, main: [blocks], <PlayerName>: [blocks], ... }.
     *
     * @return array{response: string, model: string, cache_id: string|null, cache_expires_at: string|null}
     */
    public function generateBlocksFromPreprocessed(string $preprocessedJson, ?string $supplementaryJson = null): array
    {
        $cacheContent = "You are a WoW Mythic Raid AI Analyst. Below is pre-analyzed raid combat data "
            . "produced by a deterministic PHP analyzer plus raw supplementary log data for chat queries. "
            . "Use the PRE-ANALYZED section for failures/performance summaries; use the RAW SUPPLEMENTARY "
            . "section for per-player details (cast counts, buff uptimes, dispel counts, gear, "
            . "consumables, etc.) when the user asks specific stats questions.\n\n"
            . "=== PRE-ANALYZED RAID DATA ===\n" . $preprocessedJson;

        if ($supplementaryJson) {
            $cacheContent .= "\n\n=== RAW SUPPLEMENTARY DATA (for chat queries) ===\n" . $supplementaryJson;
        }

        $cache = $this->createCachedContext($cacheContent, $this->proModel, 10800);
        $cacheId = $cache['cache_id'] ?? null;
        $cacheExpiresAt = $cache['expires_at'] ?? null;

        $reportInstructions = file_get_contents(resource_path('prompts/gemini_report_generation_blocks.txt'));
        $url = $this->buildModelUrl($this->proModel);

        try {
            if ($cacheId) {
                Log::info('Generating block report with cached context', ['cache_id' => $cacheId]);
                $rawResponse = $this->executeWithCache(
                    $cacheId,
                    $reportInstructions . "\n\nGenerate the block-based report now using the PRE-ANALYZED RAID DATA from the cached context. Output strictly raw JSON matching the documented block schema.",
                    $url,
                    300,
                    true
                );
            } else {
                Log::warning('Cache creation failed, generating block report without cache');
                $fullPrompt = $reportInstructions . "\n\n=== PRE-ANALYZED RAID DATA ===\n" . $preprocessedJson;
                $rawResponse = $this->executeRequestWithModel($fullPrompt, $url, 300, 3, true);
            }

            $response = GeminiResponseFormatter::cleanMarkdown($rawResponse);

            return [
                'response'         => $response,
                'model'            => $this->proModel,
                'cache_id'         => $cacheId,
                'cache_expires_at' => $cacheExpiresAt,
            ];
        } catch (\Exception $e) {
            Log::error('Block report generation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Execute a request against a specific model URL (for multi-model pipeline).
     * Retries on 503/429 with exponential backoff before giving up.
     */
    public function executeRequestWithModel(string $promptText, string $modelUrl, int $timeout = 120, int $maxRetries = 3, bool $jsonMode = false, int $maxOutputTokens = 32768): string
    {
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText],
                    ],
                ],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
            ],
        ];

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
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
                $finishReason = $responseData['candidates'][0]['finishReason'] ?? null;

                $this->aiLogger->logRequest(
                    'gemini', $modelName, $modelUrl,
                    $responseData, $startTime, 'success'
                );

                if (!$text) {
                    Log::warning('Gemini API returned empty response', ['model' => $modelName, 'response' => $responseData]);
                }

                if ($finishReason === 'MAX_TOKENS') {
                    Log::warning('Gemini API hit MAX_TOKENS — output truncated', [
                        'model' => $modelName,
                        'output_length' => strlen((string) $text),
                    ]);
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
