<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\RaidEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class FetchPendingAnalysisRaidsTask
{
    /**
     * Fetch raids that ended in the last $hours hours and don't have a tactical report.
     */
    public function run(int $hours = 6): Collection
    {
        return RaidEvent::where('end_time', '>=', Carbon::now()->subHours($hours))
            ->where('end_time', '<=', Carbon::now())
            ->whereDoesntHave('tacticalReport')
            ->with('static')
            ->get();
    }
}
