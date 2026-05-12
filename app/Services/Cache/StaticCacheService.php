<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

class StaticCacheService
{
    private const DEFAULT_TTL = 600;

    public function rememberForStatic(int $staticId, string $key, ?int $ttl, Closure $callback): mixed
    {
        return Cache::tags([$this->staticTag($staticId)])->remember(
            $key,
            $ttl ?? self::DEFAULT_TTL,
            $callback
        );
    }

    public function flushStatic(int $staticId): void
    {
        Cache::tags([$this->staticTag($staticId)])->flush();
    }

    public function rememberGlobal(string $key, ?int $ttl, Closure $callback): mixed
    {
        return Cache::tags(['global'])->remember(
            $key,
            $ttl ?? self::DEFAULT_TTL,
            $callback
        );
    }

    public function flushGlobal(): void
    {
        Cache::tags(['global'])->flush();
    }

    private function staticTag(int $staticId): string
    {
        return "static:{$staticId}";
    }
}
