<?php

namespace App\Console\Commands;

use App\Models\RaidEvent;
use App\Models\TacticalReport;
use App\Services\WclService;
use App\Services\RaidAiAnalyst;
use App\Services\DiscordMessageService;
use Carbon\Carbon;

#[Signature('app:process-log-analysis')]
#[Description('Analyze recent raid logs using AI')]
class ProcessLogAnalysis extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WclService $wclService, RaidAiAnalyst $aiAnalyst, DiscordMessageService $discordService)
    {
        $this->info('Starting log analysis process...');

        // Find raids that ended in the last 6 hours and don't have analysis yet
        $raids = RaidEvent::where('end_time', '>=', Carbon::now()->subHours(6))
            ->where('end_time', '<=', Carbon::now())
            ->whereDoesntHave('tacticalReport')
            ->get();

        foreach ($raids as $raid) {
            $this->info("Processing raid: {$raid->title}");
            $static = $raid->static;

            if (!$static->wcl_guild_id) {
                $this->warn("Skipping raid: No WCL Guild ID configured for static {$static->name}");
                continue;
            }

            // 1. Fetch latest logs for the static
            $logs = $wclService->fetchLatestGuildLogs(
                $static->wcl_guild_id,
                $static->wcl_region ?? 'eu',
                $static->wcl_realm ?? $static->server
            );

            // 2. Match log to raid event based on time
            // We look for a log that started around the same time as the raid event
            $matchedLog = collect($logs)->first(function ($log) use ($raid) {
                $logStart = Carbon::createFromTimestampMs($log['startTime']);
                // Allow 1 hour window
                return $logStart->between($raid->start_time->subHour(), $raid->start_time->addHour());
            });

            if (!$matchedLog) {
                $this->warn("No matching WCL log found for raid: {$raid->title}");
                continue;
            }

            // 3. Create or update TacticalReport
            $report = TacticalReport::updateOrCreate(
                ['wcl_report_id' => $matchedLog['code']],
                [
                    'static_id' => $static->id,
                    'raid_event_id' => $raid->id,
                    'title' => $matchedLog['title'] ?? $raid->title
                ]
            );

            // 4. Get log summary
            $this->info("Fetching log summary for report: {$matchedLog['code']}");
            $logSummary = $wclService->getLogSummary($matchedLog['code']);

            // 5. Trigger AI analysis
            $this->info("Generating AI analysis...");
            try {
                $analysis = $aiAnalyst->analyzeLog($logSummary);
                $report->update(['ai_analysis' => $analysis]);
                $this->info("Analysis stored for report: {$report->wcl_report_id}");

                // 6. Update Discord message
                if ($raid->discord_message_id) {
                    $discordService->sendOrUpdateRaidAnnouncement($raid);
                }
            } catch (\Exception $e) {
                $this->error("Failed to analyze log: " . $e->getMessage());
            }
        }

        $this->info('Log analysis process completed.');
    }
}
