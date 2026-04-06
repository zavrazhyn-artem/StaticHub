<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\Event;
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

        $grid = $this->generateGrid($dates['start'], $month, $events);

        return [
            'grid' => $grid,
            'mappedGrid' => $this->formatGridForVue($grid),
            'current_date' => $currentDate,
            'prev_month' => $currentDate->copy()->subMonth(),
            'next_month' => $currentDate->copy()->addMonth(),
        ];
    }

    /**
     * Format the raw grid data into the structure the Vue component expects.
     */
    public function formatGridForVue(array $grid): array
    {
        return collect($grid)->map(fn($day) => [
            'day_number'       => $day['date']->format('j'),
            'formatted_date'   => $day['date']->format('Y-m-d'),
            'is_today'         => $day['is_today'],
            'is_current_month' => $day['is_current_month'],
            'events'           => collect($day['events'])->map(fn($event) => [
                'id'               => $event->id,
                'show_url'         => route('schedule.event.show', $event),
                'update_url'       => route('schedule.event.update', $event),
                'start_time'       => $event->start_time->toIso8601String(),
                'end_time'         => $event->end_time?->toIso8601String(),
                'description'      => $event->description,
                'characters_count' => $event->characters_count ?? 0,
            ])->values()->all(),
        ])->values()->all();
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
        return Event::query()
            ->forStatic($staticId)
            ->betweenDates($startDate, $endDate)
            ->withCount('characters')
            ->get()
            ->groupBy(fn (Event $event) => $event->start_time->format('Y-m-d'));
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
