<?php

declare(strict_types=1);

namespace App\Jobs\Analysis;

use App\Helpers\DiscordWebhookBuilder;
use App\Models\Event;
use App\Models\TacticalReport;
use App\Services\Analysis\WclService;
use App\Services\Discord\DiscordWebhookService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchPostRaidLogsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public function __construct(
        private readonly int $eventId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(WclService $wclService, DiscordWebhookService $webhookService): void
    {
        $event = Event::with('static')->find($this->eventId);

        if (!$event || $event->ai_analysis_done) {
            return;
        }

        $static = $event->static;
        $guildId = (int) $static->wcl_guild_id;

        if (!$guildId) {
            return;
        }

        try {
            $matchedReports = $this->fetchAndMatchLogs($wclService, $event, $guildId);

            if (empty($matchedReports)) {
                $this->notifyNoLogFound($webhookService, $event, $static);
                $event->update(['ai_analysis_done' => true]);
                return;
            }

            $this->processMatchedReports($matchedReports, $event, $static);
            $event->update(['ai_analysis_done' => true]);

        } catch (\Exception $e) {
            Log::error("FetchPostRaidLogsJob failed: {$e->getMessage()}", [
                'event_id' => $this->eventId,
            ]);
            throw $e;
        }
    }

    /**
     * Fetch guild reports from WCL and match to the event's time window.
     */
    private function fetchAndMatchLogs(WclService $wclService, Event $event, int $guildId): array
    {
        // Search window: 2 hours before event start → 2 hours after event end
        $searchStart = $event->start_time->copy()->subHours(2)->getTimestampMs();
        $searchEnd   = ($event->end_time ?? $event->start_time->copy()->addHours(4))->copy()->addHours(2)->getTimestampMs();

        $reports = $wclService->getGuildReports($guildId, (float) $searchStart, (float) $searchEnd);

        if (empty($reports)) {
            return [];
        }

        // Match: report startTime must be within ±1 hour of event start_time
        $eventStartMs = $event->start_time->getTimestampMs();

        return array_filter($reports, function (array $report) use ($eventStartMs) {
            $reportStart = (float) $report['startTime'];
            return abs($reportStart - $eventStartMs) <= 3600000; // 1 hour in ms
        });
    }

    /**
     * Create TacticalReports and dispatch analysis jobs for matched logs.
     */
    private function processMatchedReports(array $reports, Event $event, $static): void
    {
        foreach ($reports as $report) {
            $wclReportId = $report['code'];

            // Skip already-processed reports
            if (TacticalReport::query()->findByWclReportId($wclReportId)) {
                continue;
            }

            $tacticalReport = TacticalReport::create([
                'static_id'      => $static->id,
                'event_id'       => $event->id,
                'wcl_report_id'  => $wclReportId,
                'title'          => $report['title'] ?? 'Raid Analysis',
            ]);

            ProcessRaidAnalysisJob::dispatch($tacticalReport);

            Log::info("Dispatched analysis for WCL report {$wclReportId}", [
                'event_id' => $event->id,
                'report_id' => $tacticalReport->id,
            ]);
        }
    }

    /**
     * Send Discord webhook notification that no log was found.
     */
    private function notifyNoLogFound(DiscordWebhookService $webhookService, Event $event, $static): void
    {
        $raidDate = $event->start_time->format('M d, Y @ H:i');
        $manualUploadUrl = route('statics.logs.index');

        $payload = DiscordWebhookBuilder::buildNoLogFoundPayload($raidDate, $manualUploadUrl);
        $webhookService->sendNotification($static, $payload);

        Log::info("No WCL log found for event {$event->id}, sent Discord notification.");
    }
}
