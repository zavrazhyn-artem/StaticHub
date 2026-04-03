<?php

declare(strict_types=1);

namespace App\Tasks\Raid;

use App\Models\RaidEvent;
use App\Models\StaticGroup;
use Illuminate\Database\Eloquent\Collection;

class FetchRaidsNeedingRemindersTask
{
    /**
     * Fetch all upcoming raids for statics that have reminders enabled.
     * Eager loads the static group to prevent N+1 queries.
     *
     * @return Collection<int, RaidEvent>
     */
    public function run(): Collection
    {
        $staticsWithReminders = StaticGroup::whereNotNull('automation_settings')
            ->get()
            ->filter(fn($static) => !empty($static->automation_settings['reminder_hours_before']));

        $allUpcomingRaids = new Collection();

        foreach ($staticsWithReminders as $static) {
            $hoursBefore = (int) $static->automation_settings['reminder_hours_before'];

            $upcomingRaids = RaidEvent::with('static')
                ->where('static_id', $static->id)
                ->where('start_time', '<=', now()->addHours($hoursBefore))
                ->where('start_time', '>', now())
                ->whereNull('discord_message_id')
                ->get();

            $allUpcomingRaids = $allUpcomingRaids->concat($upcomingRaids->toArray());
        }

        return $allUpcomingRaids;
    }
}
