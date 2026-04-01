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

    public function nextRaid(int $staticId): ?\App\Models\RaidEvent
    {
        return $this->where('static_id', $staticId)
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->first();
    }

    public function weeklySchedule(int $staticId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->where('static_id', $staticId)
            ->where('start_time', '>', now())
            ->where('start_time', '<=', now()->addDays(7))
            ->withCount(['attendances as rsvp_count' => function ($query) {
                $query->whereIn('status', ['present', 'late', 'tentative']);
            }])
            ->orderBy('start_time', 'asc')
            ->get();
    }
}
