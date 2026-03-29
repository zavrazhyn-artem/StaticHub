<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class RaidEventBuilder extends Builder
{
    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function betweenDates(Carbon $startDate, Carbon $endDate): self
    {
        return $this->whereBetween('start_time', [$startDate, $endDate]);
    }
}
