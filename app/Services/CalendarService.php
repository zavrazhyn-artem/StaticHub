<?php

namespace App\Services;

use App\Models\RaidEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * Build a 42-day month grid.
     */
    public function buildMonthGrid(int $year, int $month, int $staticId): array
    {
        $currentDate = Carbon::create($year, $month, 1);
        $startDate = $currentDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $endDate = $startDate->copy()->addDays(41); // 42 days total

        $events = RaidEvent::query()
            ->forStatic($staticId)
            ->betweenDates($startDate, $endDate)
            ->get()
            ->groupBy(fn (RaidEvent $event) => $event->start_time->format('Y-m-d'));

        $grid = [];
        $tempDate = $startDate->copy();

        for ($i = 0; $i < 42; $i++) {
            $dateString = $tempDate->format('Y-m-d');

            $grid[] = [
                'date' => $tempDate->copy(),
                'is_current_month' => $tempDate->month === $month,
                'is_today' => $tempDate->isToday(),
                'events' => $events->get($dateString, collect()),
            ];

            $tempDate->addDay();
        }

        return [
            'grid' => $grid,
            'current_date' => $currentDate,
            'prev_month' => $currentDate->copy()->subMonth(),
            'next_month' => $currentDate->copy()->addMonth(),
        ];
    }
}
