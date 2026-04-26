<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\AiRequestLog;
use Illuminate\Support\Facades\Log;

class AiRequestLogger
{
    public function logRequest(
        string $provider,
        ?string $model,
        string $endpoint,
        ?array $responseData,
        float $startTime,
        string $status = 'success',
        ?string $errorMessage = null,
        ?array $metadata = null,
    ): void {
        $serviceConfig = config("api_tracking.services.{$provider}");

        if (!$serviceConfig || !$serviceConfig['enabled']) {
            return;
        }

        $isSuccess = $status === 'success';

        if ($isSuccess && !$serviceConfig['log_success']) {
            return;
        }

        if (!$isSuccess && !$serviceConfig['log_errors']) {
            return;
        }

        try {
            $tokens = $this->extractTokenUsage($responseData);
            $tokens['model'] = $model;

            $logMetadata = $metadata ?? [];
            if ($tokens['thoughts'] !== null) {
                $logMetadata['thoughts_tokens'] = $tokens['thoughts'];
            }
            if ($tokens['cached'] !== null) {
                $logMetadata['cached_tokens'] = $tokens['cached'];
            }

            AiRequestLog::create([
                'provider' => $provider,
                'model' => $model,
                'endpoint' => mb_substr($endpoint, 0, 255),
                'input_tokens' => $tokens['input'],
                'output_tokens' => $tokens['output'],
                'total_tokens' => $tokens['total'],
                'cost_estimate' => $this->estimateCost($provider, $tokens),
                'response_time_ms' => max(0, (int) round((microtime(true) - $startTime) * 1000)),
                'status' => $status,
                'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 1000) : null,
                'metadata' => $logMetadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log AI request', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractTokenUsage(?array $responseData): array
    {
        if (!$responseData) {
            return ['input' => null, 'output' => null, 'total' => null, 'thoughts' => null, 'cached' => null];
        }

        $usage = $responseData['usageMetadata'] ?? [];

        return [
            'input'    => $usage['promptTokenCount'] ?? null,
            'output'   => $usage['candidatesTokenCount'] ?? null,
            'total'    => $usage['totalTokenCount'] ?? null,
            'thoughts' => $usage['thoughtsTokenCount'] ?? null,
            'cached'   => $usage['cachedContentTokenCount'] ?? null,
        ];
    }

    private function estimateCost(string $provider, array $tokens): ?float
    {
        if ($tokens['input'] === null && $tokens['output'] === null) {
            return null;
        }

        // Gemini pricing per 1M tokens — update as needed.
        // cache_read = price for tokens served from implicit/explicit prefix cache.
        $rates = [
            'gemini-2.5-flash'              => ['input' => 0.15, 'output' => 0.60,  'cache_read' => 0.0375],
            'gemini-2.5-pro'                => ['input' => 1.25, 'output' => 10.00, 'cache_read' => 0.31],
            'gemini-3-flash'                => ['input' => 0.50, 'output' => 3.00,  'cache_read' => 0.05],
            'gemini-3-flash-preview'        => ['input' => 0.50, 'output' => 3.00,  'cache_read' => 0.05],
            'gemini-3.1-flash-lite-preview' => ['input' => 0.25, 'output' => 1.50,  'cache_read' => 0.025],
            'gemini-3-pro'                  => ['input' => 2.00, 'output' => 12.00, 'cache_read' => 0.20],
        ];

        // Try model-specific rate first, fall back to provider default
        $modelName = $tokens['model'] ?? null;
        $rate = $rates[$modelName] ?? $rates['gemini-2.5-flash'] ?? null;

        if (!$rate) {
            return null;
        }

        // Gemini bills thoughtsTokenCount at the output rate
        $outputTotal = ($tokens['output'] ?? 0) + ($tokens['thoughts'] ?? 0);

        // Split input cost: cached prefix billed at the (much cheaper) cache_read rate,
        // remainder at full input rate. Falls back to full rate if cache_read missing.
        $inputTotal = $tokens['input'] ?? 0;
        $cached = $tokens['cached'] ?? 0;
        $uncached = max(0, $inputTotal - $cached);
        $cacheRate = $rate['cache_read'] ?? $rate['input'];

        $inputCost = ($uncached * $rate['input'] + $cached * $cacheRate) / 1_000_000;
        $outputCost = $outputTotal * $rate['output'] / 1_000_000;

        return round($inputCost + $outputCost, 6);
    }
}
