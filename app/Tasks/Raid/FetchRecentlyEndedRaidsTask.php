<?php

declare(strict_types=1);

namespace App\Tasks\Raid;

use App\Models\RaidEvent;
use Illuminate\Database\Eloquent\Collection;

class FetchRecentlyEndedRaidsTask
{
    /**
     * Fetch raids that ended in the last X minutes.
     * Eager loads the static group to prevent N+1 queries.
     *
     * @param int $minutes
     * @return Collection<int, RaidEvent>
     */
    public function run(int $minutes = 30): Collection
    {
        return RaidEvent::with('static')
            ->where('end_time', '<=', now())
            ->where('end_time', '>', now()->subMinutes($minutes))
            ->get();
    }
}
