<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class AiRequestLogBuilder extends Builder
{
    public function forProvider(string $provider): self
    {
        return $this->where('provider', $provider);
    }

    public function successful(): self
    {
        return $this->where('status', 'success');
    }

    public function failed(): self
    {
        return $this->where('status', 'error');
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
