<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Services\Discord\DiscordMessageService;
use App\Tasks\Analysis\FetchPendingAnalysisRaidsTask;
use App\Tasks\Analysis\MatchRaidToWclLogTask;
use App\Tasks\Analysis\PersistTacticalReportTask;
use App\Tasks\Analysis\SaveAiAnalysisToReportTask;
use Throwable;

class AutomatedLogAnalysisService
{
    public function __construct(
        private readonly FetchPendingAnalysisRaidsTask $fetchPendingAnalysisRaidsTask,
        private readonly MatchRaidToWclLogTask $matchRaidToWclLogTask,
        private readonly PersistTacticalReportTask $persistTacticalReportTask,
        private readonly SaveAiAnalysisToReportTask $saveAiAnalysisToReportTask,
        private readonly WclService $wclService,
        private readonly RaidAiAnalystService $aiAnalyst,
        private readonly DiscordMessageService $discordMessageService
    ) {}

    /**
     * Orchestrate the automated analysis of recent raids.
     *
     * @return string[]
     */
    public function executeAutomatedAnalysis(): array
    {
        $messages = [];
        $raids = $this->fetchPendingAnalysisRaidsTask->run();

        foreach ($raids as $raid) {
            try {
                $static = $raid->static;

                if (!$static->wcl_guild_id) {
                    $messages[] = "WARN: Skipping raid (ID: {$raid->id}): No WCL Guild ID configured for static '{$static->name}'";
                    continue;
                }

                $logs = $this->wclService->fetchLatestGuildLogs(
                    $static->wcl_guild_id,
                    $static->wcl_region ?? 'eu',
                    $static->wcl_realm ?? $static->server
                );

                $matchedLog = $this->matchRaidToWclLogTask->run($logs, $raid);

                if (!$matchedLog) {
                    $messages[] = "WARN: No matching WCL log found for raid (ID: {$raid->id})";
                    continue;
                }

                $report = $this->persistTacticalReportTask->run($matchedLog, $raid);
                $logSummary = $this->wclService->getLogSummary($matchedLog['code']);

                try {
                    $analysis = $this->aiAnalyst->analyzeLog($logSummary);
                    $this->saveAiAnalysisToReportTask->run($report, $analysis);

                    if ($raid->discord_message_id) {
                        $this->discordMessageService->sendOrUpdateRaidAnnouncement($raid);
                    }

                    $messages[] = "INFO: Successfully processed analysis for raid (ID: {$raid->id})";
                } catch (Throwable $e) {
                    $messages[] = "ERROR: Failed to analyze log for raid (ID: {$raid->id}): " . $e->getMessage();
                }
            } catch (Throwable $e) {
                $messages[] = "ERROR: General error processing raid (ID: {$raid->id}): " . $e->getMessage();
            }
        }

        return $messages;
    }
}
