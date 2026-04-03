<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RaidEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarService
{
    /**
     * Build a 42-day month grid (Main Action).
     */
    public function buildMonthGrid(int $year, int $month, int $staticId): array
    {
        $currentDate = Carbon::create($year, $month, 1);
        $dates = $this->calculateDateRange($currentDate);
        $events = $this->fetchEvents($staticId, $dates['start'], $dates['end']);

        return [
            'grid' => $this->generateGrid($dates['start'], $month, $events),
            'current_date' => $currentDate,
            'prev_month' => $currentDate->copy()->subMonth(),
            'next_month' => $currentDate->copy()->addMonth(),
        ];
    }

    /**
     * Task: Calculate start and end dates for a 42-day grid.
     */
    private function calculateDateRange(Carbon $currentDate): array
    {
        $start = $currentDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->addDays(41);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Task: Fetch events within the date range from the Builder.
     */
    private function fetchEvents(int $staticId, Carbon $startDate, Carbon $endDate): Collection
    {
        return RaidEvent::query()
            ->forStatic($staticId)
            ->betweenDates($startDate, $endDate)
            ->get()
            ->groupBy(fn (RaidEvent $event) => $event->start_time->format('Y-m-d'));
    }

    /**
     * Task: Generate the 42-day grid array.
     */
    private function generateGrid(Carbon $startDate, int $month, Collection $events): array
    {
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

        return $grid;
    }
}
