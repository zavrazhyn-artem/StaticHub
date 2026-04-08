<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class ApiUsageLogBuilder extends Builder
{
    public function forService(string $service): self
    {
        return $this->where('service', $service);
    }

    public function withErrors(): self
    {
        return $this->where('status_code', '>=', 400);
    }

    public function successful(): self
    {
        return $this->where('status_code', '<', 400);
    }

    public function inDateRange(string $from, string $to): self
    {
        return $this->whereBetween('created_at', [$from, $to]);
    }

    public function recent(int $days = 7): self
    {
        return $this->where('created_at', '>=', now()->subDays($days));
    }
}
