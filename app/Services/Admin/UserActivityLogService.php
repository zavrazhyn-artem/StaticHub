<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\UserActivityLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class UserActivityLogService
{
    public const REDIS_KEY = 'user_activity_queue';
    public const REDIS_CONNECTION = 'default';

    public function record(array $payload): void
    {
        try {
            Redis::connection(self::REDIS_CONNECTION)
                ->lpush(self::REDIS_KEY, json_encode($payload, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            Log::warning('UserActivityLogService::record failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function flushToDatabase(int $max = 500): int
    {
        $rows = $this->drainBatch($max);

        if ($rows === []) {
            return 0;
        }

        return UserActivityLog::query()->bulkInsert($rows);
    }

    private function drainBatch(int $max): array
    {
        $redis = Redis::connection(self::REDIS_CONNECTION);
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $raw = $redis->rpop(self::REDIS_KEY);
            if ($raw === null || $raw === false) {
                break;
            }

            $decoded = json_decode((string) $raw, true);
            if (! is_array($decoded)) {
                Log::warning('UserActivityLogService: malformed payload discarded', ['raw' => $raw]);
                continue;
            }

            $rows[] = $decoded;
        }

        return $rows;
    }
}
