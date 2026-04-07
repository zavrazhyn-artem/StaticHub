<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class TransactionBuilder extends Builder
{
    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function byType(string $type): self
    {
        return $this->where('type', $type);
    }

    public function inPeriod(string $periodKey): self
    {
        return $this->where('period_key', $periodKey);
    }

    public function forUser(int $userId): self
    {
        return $this->where('user_id', $userId);
    }

    public function recent(int $limit = 10): self
    {
        return $this->orderBy('created_at', 'desc')->limit($limit);
    }

    public function latestFirst(): self
    {
        return $this->orderBy('created_at', 'desc');
    }

    public function sumAmount(): int
    {
        return (int) $this->sum('amount');
    }
}
