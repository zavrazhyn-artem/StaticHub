<?php

declare(strict_types=1);

namespace App\Tasks\Analysis;

use App\Models\TacticalReport;
use Illuminate\Pagination\LengthAwarePaginator;

class FetchPaginatedTacticalReportsTask
{
    /**
     * Get paginated logs for a static group.
     */
    public function run(int $staticId, ?string $difficulty = null): LengthAwarePaginator
    {
        return TacticalReport::query()
            ->forStatic($staticId, $difficulty)
            ->paginate(12);
    }
}
