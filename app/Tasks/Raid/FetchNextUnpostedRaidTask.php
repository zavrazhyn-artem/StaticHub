<?php

declare(strict_types=1);

namespace App\Tasks\Raid;

use App\Models\RaidEvent;
use Carbon\Carbon;

class FetchNextUnpostedRaidTask
{
    /**
     * Fetch the next upcoming raid for a static that hasn't been posted yet.
     *
     * @param int $staticId
     * @param Carbon $afterTime
     * @return RaidEvent|null
     */
    public function run(int $staticId, Carbon $afterTime): ?RaidEvent
    {
        return RaidEvent::where('static_id', $staticId)
            ->where('start_time', '>', $afterTime)
            ->whereNull('discord_message_id')
            ->orderBy('start_time', 'asc')
            ->first();
    }
}
