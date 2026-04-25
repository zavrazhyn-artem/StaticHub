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

    /**
     * Sum of transaction amounts per day for the last `$days` days.
     * Returns oldest → newest, e.g. ['2026-04-19' => +5000, ..., '2026-04-25' => -2000].
     * Days with no activity are still present with 0.
     */
    public function dailyDeltasForLastDays(int $days = 7): array
    {
        $from = now()->copy()->subDays($days - 1)->startOfDay();

        $rows = $this->whereDate('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $key = now()->copy()->subDays($i)->format('Y-m-d');
            $result[$key] = (int) ($rows[$key] ?? 0);
        }
        return $result;
    }
}
