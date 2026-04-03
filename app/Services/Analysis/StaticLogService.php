<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Models\User;
use App\Tasks\Analysis\FetchPaginatedTacticalReportsTask;
use App\Tasks\Analysis\FetchRawLogDataTask;
use App\Tasks\Analysis\ResolveUserCharacterForReportTask;
use Illuminate\Pagination\LengthAwarePaginator;

class StaticLogService
{
    public function __construct(
        private readonly FetchPaginatedTacticalReportsTask $fetchPaginatedTacticalReportsTask,
        private readonly ResolveUserCharacterForReportTask $resolveUserCharacterForReportTask,
        private readonly FetchRawLogDataTask $fetchRawLogDataTask
    ) {
    }

    /**
     * Get paginated logs for a static group.
     */
    public function getPaginatedLogs(StaticGroup $static, ?string $difficulty = null): LengthAwarePaginator
    {
        return $this->fetchPaginatedTacticalReportsTask->run($static->id, $difficulty);
    }

    /**
     * Get the character context for a user in a report.
     */
    public function getUserCharacterForReport(User $user, StaticGroup $static, TacticalReport $report): ?Character
    {
        return $this->resolveUserCharacterForReportTask->run($user, $static->id, $report->id);
    }

    /**
     * Get raw log data from the database.
     */
    public function getRawLogData(string $wclReportId): array
    {
        return $this->fetchRawLogDataTask->run($wclReportId);
    }
}
