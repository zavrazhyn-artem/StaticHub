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
    public function executeScheduleGeneration(StaticGroup $static, int $weeksAhead = 4): void
    {
        $raidDays = $static->getRaidDaysArray();

        if (empty($raidDays) || empty($static->raid_start_time)) {
            return;
        }

        $timezone = $static->timezone ?? 'UTC';

        $events = $this->calculateEventTimestamps(
            $raidDays,
            $static->raid_start_time,
            $static->raid_end_time,
            $timezone,
            $weeksAhead
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

            $allUpcomingRaids = $allUpcomingRaids->concat($upcomingRaids->toArray());
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
     */
    public function calculateEventTimestamps(array $raidDays, string $startTime, ?string $endTime, string $timezone, int $weeksAhead): array
    {
        $dayMap = [
            'mon' => Carbon::MONDAY, 'tue' => Carbon::TUESDAY, 'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY, 'fri' => Carbon::FRIDAY, 'sat' => Carbon::SATURDAY, 'sun' => Carbon::SUNDAY,
        ];

        $timestamps = [];

        foreach ($raidDays as $day) {
            $dayLower = strtolower($day);
            if (!isset($dayMap[$dayLower])) {
                continue;
            }

            $targetDay = $dayMap[$dayLower];

            for ($i = 0; $i < $weeksAhead; $i++) {
                $currentDate = Carbon::today($timezone)->addWeeks($i);
                $date = $currentDate->isDayOfWeek($targetDay) ? $currentDate : $currentDate->next($targetDay);

                $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime, $timezone)->setTimezone('UTC');
                $end = null;

                if ($endTime) {
                    $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime, $timezone);
                    $startInTimezone = $start->copy()->setTimezone($timezone);
                    if ($end->lessThan($startInTimezone)) {
                        $end->addDay();
                    }
                    $end->setTimezone('UTC');
                }

                $timestamps[] = ['start' => $start, 'end' => $end];
            }
        }

        return $timestamps;
    }
}
