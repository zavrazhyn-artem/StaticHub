<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EventBuilder extends Builder
{
    public function forStatic(int $staticId): self
    {
        return $this->where('static_id', $staticId);
    }

    public function betweenDates(Carbon $startDate, Carbon $endDate): self
    {
        return $this->whereBetween('start_time', [$startDate, $endDate]);
    }

    public function nextRaid(int $staticId): ?\App\Models\Event
    {
        return $this->where('static_id', $staticId)
            ->where('start_time', '>', now())
            ->orderBy('start_time', 'asc')
            ->first();
    }

    public function weeklySchedule(int $staticId, int $limit = 7): \Illuminate\Database\Eloquent\Collection
    {
        return $this->where('static_id', $staticId)
            ->where('start_time', '>', now())
            ->withCount(['attendances as rsvp_count' => function ($query) {
                $query->whereIn('status', ['present', 'late', 'tentative']);
            }])
            ->orderBy('start_time', 'asc')
            ->limit($limit)
            ->get();
    }

    public function findById(int $id): ?\App\Models\Event
    {
        return $this->find($id);
    }

    public function unpostedForStatic(int $staticId, \Carbon\Carbon $afterTime): ?\App\Models\Event
    {
        return $this->where('static_id', $staticId)
            ->where('start_time', '>', $afterTime)
            ->whereNull('discord_message_id')
            ->orderBy('start_time', 'asc')
            ->first();
    }

    public function recentlyEnded(int $minutes = 5): \Illuminate\Database\Eloquent\Collection
    {
        return $this->with('static')
            ->where('end_time', '<=', now())
            ->where('end_time', '>', now()->subMinutes($minutes))
            ->where('raid_over', false)
            ->get();
    }

    public function pendingAnalysis(int $hours = 6): \Illuminate\Database\Eloquent\Collection
    {
        return $this->where('end_time', '>=', \Carbon\Carbon::now()->subHours($hours))
            ->where('end_time', '<=', \Carbon\Carbon::now())
            ->whereDoesntHave('tacticalReport')
            ->with('static')
            ->get();
    }

    public function existsForStaticOnDay(int $staticId, \Carbon\Carbon $dayStart, \Carbon\Carbon $dayEnd): bool
    {
        return $this->where('static_id', $staticId)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->exists();
    }
}
