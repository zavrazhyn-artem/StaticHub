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
    /**
     * @param int      $staticId
     * @param string[] $difficulties
     * @param string|null $dateFrom
     * @param string|null $dateTo
     */
    public function run(int $staticId, array $difficulties = [], ?string $dateFrom = null, ?string $dateTo = null): LengthAwarePaginator
    {
        return TacticalReport::query()
            ->forStatic($staticId, $difficulties, $dateFrom, $dateTo)
            ->paginate(9);
    }
}
