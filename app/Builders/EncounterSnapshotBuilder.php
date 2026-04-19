<?php

declare(strict_types=1);

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EncounterSnapshotBuilder extends Builder
{
    /**
     * Recent snapshots for a static group's specific boss, newest first.
     * Excludes the snapshot tied to $excludeReportId so the trend window doesn't include
     * the report we're currently generating.
     */
    public function recentForBoss(int $staticId, string $bossName, int $limit = 5, ?int $excludeReportId = null): Collection
    {
        $q = $this->where('static_id', $staticId)
            ->where('boss_name', $bossName)
            ->orderByDesc('raid_date');

        if ($excludeReportId) {
            $q->where(function ($w) use ($excludeReportId) {
                $w->whereNull('tactical_report_id')
                  ->orWhere('tactical_report_id', '!=', $excludeReportId);
            });
        }

        return $q->limit($limit)->get();
    }

    /**
     * Recent snapshots across ALL bosses for a static — newest first.
     * Used to compute raid-wide player trends and progression curves.
     */
    public function recentForStatic(int $staticId, int $limit = 100, ?int $excludeReportId = null): Collection
    {
        $q = $this->where('static_id', $staticId)
            ->orderByDesc('raid_date');

        if ($excludeReportId) {
            $q->where(function ($w) use ($excludeReportId) {
                $w->whereNull('tactical_report_id')
                  ->orWhere('tactical_report_id', '!=', $excludeReportId);
            });
        }

        return $q->limit($limit)->get();
    }

    public function forReport(int $reportId): Collection
    {
        return $this->where('tactical_report_id', $reportId)->get();
    }
}
