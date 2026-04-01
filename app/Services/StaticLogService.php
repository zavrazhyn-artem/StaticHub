<?php

namespace App\Services;

use App\Models\Character;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;

class StaticLogService
{
    /**
     * Get paginated logs for a static group.
     */
    public function getPaginatedLogs(StaticGroup $static, ?string $difficulty = null): LengthAwarePaginator
    {
        return TacticalReport::query()
            ->forStatic($static->id, $difficulty)
            ->paginate(12);
    }

    /**
     * Get the character context for a user in a report.
     */
    public function getUserCharacterForReport(User $user, StaticGroup $static, TacticalReport $report): ?Character
    {
        $character = Character::query()->findUserCharacterInReport($user->id, $static->id, $report->id);

        if (!$character) {
            $character = $user->characters()->first();
        }

        return $character;
    }

    /**
     * Get raw log data from the filesystem.
     */
    public function getRawLogData(string $wclReportId): array
    {
        $logPath = storage_path("logs/wcl_debug_{$wclReportId}.json");

        return File::exists($logPath)
            ? json_decode(File::get($logPath), true) ?? []
            : [];
    }
}
