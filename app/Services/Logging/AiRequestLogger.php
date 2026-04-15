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

            AiRequestLog::create([
                'provider' => $provider,
                'model' => $model,
                'endpoint' => mb_substr($endpoint, 0, 255),
                'input_tokens' => $tokens['input'],
                'output_tokens' => $tokens['output'],
                'total_tokens' => $tokens['total'],
                'cost_estimate' => $this->estimateCost($provider, $tokens),
                'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'status' => $status,
                'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 1000) : null,
                'metadata' => $metadata,
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
            return ['input' => null, 'output' => null, 'total' => null];
        }

        $usage = $responseData['usageMetadata'] ?? [];

        return [
            'input' => $usage['promptTokenCount'] ?? null,
            'output' => $usage['candidatesTokenCount'] ?? null,
            'total' => $usage['totalTokenCount'] ?? null,
        ];
    }

    private function estimateCost(string $provider, array $tokens): ?float
    {
        if ($tokens['input'] === null && $tokens['output'] === null) {
            return null;
        }

        // Gemini pricing per 1M tokens — update as needed
        $rates = [
            'gemini-2.5-flash' => ['input' => 0.15, 'output' => 0.60],
            'gemini-2.5-pro'   => ['input' => 1.25, 'output' => 10.00],
            'gemini-3-flash'   => ['input' => 0.30, 'output' => 2.50],
        ];

        // Try model-specific rate first, fall back to provider default
        $modelName = $tokens['model'] ?? null;
        $rate = $rates[$modelName] ?? $rates['gemini-2.5-flash'] ?? null;

        if (!$rate) {
            return null;
        }

        $inputCost = ($tokens['input'] ?? 0) * $rate['input'] / 1_000_000;
        $outputCost = ($tokens['output'] ?? 0) * $rate['output'] / 1_000_000;

        return round($inputCost + $outputCost, 6);
    }
}
