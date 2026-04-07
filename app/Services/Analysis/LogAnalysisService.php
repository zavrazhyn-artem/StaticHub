<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\WclParserHelper;
use App\Jobs\Analysis\ProcessRaidAnalysisJob;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use Carbon\Carbon;

class LogAnalysisService
{
    /**
     * Get the manual log cooldown in minutes for the current tier.
     */
    public function getCooldownMinutes(): int
    {
        return (int) config('tactical_logs.manual_cooldown_minutes.free', 60);
    }

    /**
     * Get cooldown state for a static group.
     *
     * @return array{on_cooldown: bool, remaining_seconds: int, cooldown_minutes: int}
     */
    public function getManualLogCooldownState(StaticGroup $static): array
    {
        $cooldownMinutes = $this->getCooldownMinutes();
        $lastManual = TacticalReport::query()->latestManualForStatic($static->id);

        $onCooldown = false;
        $remainingSeconds = 0;

        if ($lastManual) {
            $cooldownEndsAt = $lastManual->created_at->addMinutes($cooldownMinutes);
            if (Carbon::now()->lt($cooldownEndsAt)) {
                $onCooldown = true;
                $remainingSeconds = (int) Carbon::now()->diffInSeconds($cooldownEndsAt);
            }
        }

        return [
            'on_cooldown'       => $onCooldown,
            'remaining_seconds' => $remainingSeconds,
            'cooldown_minutes'  => $cooldownMinutes,
        ];
    }

    /**
     * Process manual log submission.
     */
    public function processManualLogSubmission(string $wclUrl, StaticGroup $static): ?TacticalReport
    {
        $reportId = WclParserHelper::extractReportIdFromUrl($wclUrl);

        if (!$reportId) {
            return null;
        }

        $report = $this->createReportRecord($static, $reportId);

        $this->dispatchAnalysisJob($report);

        return $report;
    }

    /**
     * Create a new TacticalReport record.
     */
    private function createReportRecord(StaticGroup $static, string $reportId): TacticalReport
    {
        return TacticalReport::create([
            'static_id' => $static->id,
            'wcl_report_id' => $reportId,
            'title' => 'Manual Log Analysis',
        ]);
    }

    /**
     * Dispatch the raid analysis job.
     */
    private function dispatchAnalysisJob(TacticalReport $report): void
    {
        ProcessRaidAnalysisJob::dispatch($report);
    }
}
