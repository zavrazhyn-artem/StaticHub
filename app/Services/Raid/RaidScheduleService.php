<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\Event;
use App\Models\StaticGroup;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RaidScheduleService
{
    /**
     * Orchestrate the generation of upcoming raid events for a static.
     *
     * @param StaticGroup $static
     * @param int $weeksAhead
     * @return void
     */
    public function executeScheduleGeneration(StaticGroup $static): void
    {
        $raidDays = $static->getRaidDaysArray();

        if (empty($raidDays) || empty($static->raid_start_time)) {
            return;
        }

        $timezone = $static->timezone ?? 'UTC';
        $daysAhead = config('raid.schedule_days_ahead', 30);

        $events = $this->calculateEventTimestamps(
            $raidDays,
            $static->raid_start_time,
            $static->raid_end_time,
            $timezone,
            $daysAhead
        );

        foreach ($events as $eventData) {
            $this->persistEvent($static, $eventData['start'], $eventData['end']);
        }
    }

    /**
     * Persist or update a single raid event in the database.
     *
     * @param StaticGroup $static
     * @param Carbon $startTime
     * @param Carbon|null $endTime
     * @return void
     */
    private function persistEvent(StaticGroup $static, Carbon $startTime, ?Carbon $endTime): void
    {
        Event::updateOrCreate([
            'static_id' => $static->id,
            'start_time' => $startTime,
        ], [
            'end_time' => $endTime,
            'description' => 'Auto-generated raid session.',
        ]);
    }

    /**
     * Fetch the next upcoming raid for a static that hasn't been posted yet.
     */
    public function getNextUnpostedRaid(int $staticId, Carbon $afterTime): ?Event
    {
        return Event::where('static_id', $staticId)
            ->where('start_time', '>', $afterTime)
            ->whereNull('discord_message_id')
            ->orderBy('start_time', 'asc')
            ->first();
    }

    /**
     * Fetch all upcoming raids for statics that have reminders enabled.
     * Eager loads the static group to prevent N+1 queries.
     *
     * @return Collection<int, Event>
     */
    public function getRaidsNeedingReminders(): Collection
    {
        $staticsWithReminders = StaticGroup::whereNotNull('automation_settings')
            ->get()
            ->filter(fn($static) => !empty($static->automation_settings['reminder_hours_before']));

        $allUpcomingRaids = new Collection();

        foreach ($staticsWithReminders as $static) {
            $hoursBefore = (int) $static->automation_settings['reminder_hours_before'];

            $upcomingRaids = Event::with('static')
                ->where('static_id', $static->id)
                ->where('start_time', '<=', now()->addHours($hoursBefore))
                ->where('start_time', '>', now())
                ->whereNull('discord_message_id')
                ->get();

            foreach ($upcomingRaids as $raid) {
                $allUpcomingRaids->push($raid);
            }
        }

        return $allUpcomingRaids;
    }

    /**
     * Fetch raids that ended in the last X minutes and are not yet marked as over.
     * Eager loads the static group to prevent N+1 queries.
     *
     * @param int $minutes
     * @return Collection<int, Event>
     */
    public function getRecentlyEndedRaids(int $minutes = 5): Collection
    {
        return Event::with('static')
            ->where('end_time', '<=', now())
            ->where('end_time', '>', now()->subMinutes($minutes))
            ->where('raid_over', false)
            ->get();
    }

    /**
     * Calculate event timestamps based on raid days and start/end times.
     * Only generates events in the future within the given days-ahead horizon.
     */
    public function calculateEventTimestamps(array $raidDays, string $startTime, ?string $endTime, string $timezone, int $daysAhead): array
    {
        $dayMap = [
            'mon' => Carbon::MONDAY, 'tue' => Carbon::TUESDAY, 'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY, 'fri' => Carbon::FRIDAY, 'sat' => Carbon::SATURDAY, 'sun' => Carbon::SUNDAY,
        ];

        $now = Carbon::now($timezone);
        $horizon = Carbon::today($timezone)->addDays($daysAhead);
        $timestamps = [];

        foreach ($raidDays as $day) {
            $dayLower = strtolower($day);
            if (!isset($dayMap[$dayLower])) {
                continue;
            }

            $targetDay = $dayMap[$dayLower];
            $date = Carbon::today($timezone);
            $date = $date->isDayOfWeek($targetDay) ? $date->copy() : $date->copy()->next($targetDay);

            while ($date->lte($horizon)) {
                $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime, $timezone);

                // Skip events that are in the past
                if ($start->gt($now)) {
                    $startUtc = $start->copy()->setTimezone('UTC');
                    $endUtc = null;

                    if ($endTime) {
                        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime, $timezone);
                        if ($end->lessThan($start)) {
                            $end->addDay();
                        }
                        $endUtc = $end->setTimezone('UTC');
                    }

                    $timestamps[] = ['start' => $startUtc, 'end' => $endUtc];
                }

                $date->addWeek();
            }
        }

        return $timestamps;
    }
}
