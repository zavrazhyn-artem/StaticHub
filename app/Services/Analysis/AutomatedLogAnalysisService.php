<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Services\Discord\DiscordMessageService;
use Throwable;

class AutomatedLogAnalysisService
{
    public function __construct(
        private readonly StaticLogService $staticLogService,
        private readonly WclService $wclService,
        private readonly GeminiService $aiAnalyst,
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
        $raids = $this->staticLogService->getPendingAnalysisRaids();

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

                $matchedLog = $this->staticLogService->matchRaidToWclLog($logs, $raid);

                if (!$matchedLog) {
                    $messages[] = "WARN: No matching WCL log found for raid (ID: {$raid->id})";
                    continue;
                }

                $report = $this->staticLogService->persistTacticalReport($matchedLog, $raid);
                $logSummary = $this->wclService->getLogSummary($matchedLog['code']);

                try {
                    $analysis = $this->aiAnalyst->analyzeRaidLog($logSummary);
                    $this->staticLogService->saveAiAnalysis($report, $analysis);

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
