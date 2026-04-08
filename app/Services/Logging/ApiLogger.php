<?php

declare(strict_types=1);

namespace App\Services\Logging;

use App\Models\ApiUsageLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class ApiLogger
{
    public function logApiCall(
        string $service,
        string $endpoint,
        string $method,
        Response $response,
        float $startTime,
        ?array $metadata = null,
    ): void {
        $serviceConfig = config("api_tracking.services.{$service}");

        if (!$serviceConfig || !$serviceConfig['enabled']) {
            return;
        }

        $isSuccess = $response->successful();

        if ($isSuccess && !$serviceConfig['log_success']) {
            return;
        }

        if (!$isSuccess && !$serviceConfig['log_errors']) {
            return;
        }

        try {
            ApiUsageLog::create([
                'service' => $service,
                'endpoint' => $this->truncateEndpoint($endpoint),
                'method' => strtoupper($method),
                'status_code' => $response->status(),
                'response_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
                'rate_limit_remaining' => $this->parseHeader($response, 'X-RateLimit-Remaining'),
                'rate_limit_limit' => $this->parseHeader($response, 'X-RateLimit-Limit'),
                'rate_limit_reset_at' => $this->parseResetHeader($response),
                'error_message' => $isSuccess ? null : mb_substr($response->body(), 0, 1000),
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to log API call', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function parseHeader(Response $response, string $header): ?int
    {
        $value = $response->header($header);

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function parseResetHeader(Response $response): ?\Carbon\Carbon
    {
        $value = $response->header('X-RateLimit-Reset');

        if ($value === null || $value === '') {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp((int) $value);
    }

    private function truncateEndpoint(string $endpoint): string
    {
        return mb_substr($endpoint, 0, 255);
    }
}
