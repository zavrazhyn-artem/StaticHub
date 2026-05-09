<?php

declare(strict_types=1);

namespace App\Builders;

use App\Models\OAuthDeviceCode;
use Illuminate\Database\Eloquent\Builder;

class OAuthDeviceCodeBuilder extends Builder
{
    public function findByDeviceCodeHash(string $hash): ?OAuthDeviceCode
    {
        return $this->where('device_code_hash', $hash)->first();
    }

    public function findPendingByUserCode(string $userCode): ?OAuthDeviceCode
    {
        return $this
            ->where('user_code', $userCode)
            ->whereNull('approved_at')
            ->whereNull('denied_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function expired(): self
    {
        return $this->where('expires_at', '<=', now());
    }

    public function pending(): self
    {
        return $this
            ->whereNull('approved_at')
            ->whereNull('denied_at');
    }

    public function purgeExpired(): int
    {
        return $this->expired()->delete();
    }
}
