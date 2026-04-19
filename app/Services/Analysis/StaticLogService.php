<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AiChatMessage;
use App\Models\Character;
use App\Models\Event;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class StaticLogService
{

    /**
     * Get paginated logs for a static group.
     */
    /**
     * @param string[] $difficulties
     */
    public function getPaginatedLogs(StaticGroup $static, array $difficulties = [], ?string $dateFrom = null, ?string $dateTo = null): LengthAwarePaginator
    {
        return TacticalReport::query()
            ->forStatic($static->id, $difficulties, $dateFrom, $dateTo)
            ->paginate(9);
    }

    /**
     * Get the character context for a user in a report.
     */
    public function getUserCharacterForReport(User $user, StaticGroup $static, TacticalReport $report): ?Character
    {
        $character = Character::query()->findUserCharacterInReport($user->id, $static->id, $report->id);

        if (!$character) {
            $character = $user->getMainCharacterForStatic($static->id);
        }

        return $character;
    }

    /**
     * Get raw log data from the database.
     */
    public function getRawLogData(string $wclReportId): array
    {
        $report = TacticalReport::where('wcl_report_id', $wclReportId)->first();

        if (!$report || !$report->raw_data) {
            return [];
        }

        if (is_array($report->raw_data)) {
            return $report->raw_data;
        }

        return json_decode($report->raw_data, true) ?? [];
    }

    /**
     * Save the AI analysis results to a tactical report.
     */
    public function saveAiAnalysis(TacticalReport $report, string $analysis): void
    {
        $report->update(['ai_analysis' => $analysis]);
    }

    /**
     * Match a raid event to a WCL log based on a 1-hour time window.
     */
    public function matchRaidToWclLog(array $logs, Event $raid): ?array
    {
        return collect($logs)->first(function (array $log) use ($raid) {
            $logStart = Carbon::createFromTimestampMs($log['startTime']);
            return $logStart->between($raid->start_time->subHour(), $raid->start_time->addHour());
        });
    }

    /**
     * Build the fully-formatted payload for the log show Vue component.
     */
    public function buildLogShowPayload(StaticGroup $static, TacticalReport $report, ?User $user): array
    {
        // Resolve user character context
        $userCharacter = $user
            ? $this->getUserCharacterForReport($user, $static, $report)
            : null;

        // Prefer structured blocks; fall back to legacy HTML/markdown for older reports.
        $aiBlocks = $report->ai_blocks ?: null;
        $aiHtml = null;
        if (!$aiBlocks && $report->ai_analysis) {
            $rawText = $report->ai_analysis;
            if (Str::startsWith($rawText, '"') && Str::endsWith($rawText, '"')) {
                $rawText = json_decode($rawText);
            }
            $cleanText = str_replace(['\n', '\r'], ["\n", "\r"], $rawText);
            $aiHtml = Str::markdown($cleanText);
        }

        // Execution metrics (placeholder values)
        $executionRank   = rand(60, 95);
        $avoidableDamage = rand(10, 40);
        $executionMetrics = [
            [
                'label'     => 'Execution Rank',
                'value'     => $executionRank,
                'color'     => 'text-amber-500',
                'bar_class' => 'bg-amber-500 shadow-[0_0_10px_rgba(245,158,11,0.5)]',
                'note'      => null,
            ],
            [
                'label'     => 'Avoidable DMG',
                'value'     => $avoidableDamage,
                'color'     => 'text-error',
                'bar_class' => 'bg-error shadow-[0_0_10px_rgba(239,68,68,0.5)]',
                'note'      => 'Compared to regional average',
            ],
        ];

        // Personal report
        $canViewGlobalReport = $user ? $user->can('canViewGlobalReport', $static) : false;
        $canUseAiChat        = $user ? $static->hasMember($user->id) : false;

        $authUserReport = $userCharacter
            ? $report->personalReports()->where('character_id', $userCharacter->id)->first()
            : null;

        $personalData = null;
        if ($authUserReport) {
            $char = $authUserReport->character;
            $personalData = [
                'id'             => $authUserReport->id,
                'blocks'         => $authUserReport->ai_blocks ?: null,
                'html'           => $authUserReport->ai_blocks ? null : ($authUserReport->content ? Str::markdown($authUserReport->content) : null),
                'char_name'      => $char->name,
                'char_class'     => $char->playable_class,
                'char_class_css' => strtolower(str_replace(' ', '-', $char->playable_class)),
            ];
        }

        // All roster personal reports for leaders/officers
        $rosterReports = [];
        $rosterMembers = [];
        if ($canViewGlobalReport) {
            $allPersonalReports = $report->personalReports()->with('character')->get();

            $rosterReports = $allPersonalReports->mapWithKeys(function ($pr) {
                return [$pr->id => [
                    'blocks' => $pr->ai_blocks ?: null,
                    'html'   => $pr->ai_blocks ? null : ($pr->content ? Str::markdown($pr->content) : null),
                ]];
            })->all();

            $rosterMembers = $allPersonalReports->map(function ($pr) {
                $char = $pr->character;
                return [
                    'id'        => $pr->id,
                    'name'      => $char->name,
                    'character' => [
                        'name'           => $char->name,
                        'playable_class' => $char->playable_class,
                        'avatar_url'     => $char->avatar_url,
                    ],
                ];
            })->sortBy('name')->values()->all();
        }

        $reportData = [
            'id'                => $report->id,
            'title'             => $report->title ?? 'Manual Log Analysis',
            'wcl_report_id'     => $report->wcl_report_id,
            'created_at'        => $report->created_at->format('F d, Y @ H:i'),
            'has_ai_analysis'   => (bool) ($aiBlocks || $report->ai_analysis),
            'ai_blocks'         => $aiBlocks,
            'ai_html'           => $aiHtml,
            'duration_hours'    => $report->event
                ? $report->event->start_time->diffInHours($report->event->end_time)
                : null,
            'wcl_url'           => 'https://www.warcraftlogs.com/reports/' . $report->wcl_report_id,
            'model'              => $report->model,
            'chat_available'     => $report->isCacheActive(),
            'chat_expires_at'    => $report->gemini_cache_expires_at?->toIso8601String(),
            'execution_metrics'  => $executionMetrics,
        ];

        // Chat history
        $chatHistory = $user
            ? AiChatMessage::where('tactical_report_id', $report->id)
                ->where('user_id', $user->id)
                ->orderBy('created_at')
                ->get(['role', 'content', 'created_at'])
                ->toArray()
            : [];

        return [
            'reportData'         => $reportData,
            'personalData'       => $personalData,
            'rosterReports'      => $rosterReports,
            'rosterMembers'      => $rosterMembers,
            'canViewGlobalReport' => $canViewGlobalReport,
            'canUseAiChat'       => $canUseAiChat,
            'chatHistory'        => $chatHistory,
            'logsIndexUrl'       => route('statics.logs.index'),
            'abilityIndex'       => app(AbilityNameIndex::class)->all(),
        ];
    }

    /**
     * Persist a tactical report for a matched WCL log.
     */
    public function persistTacticalReport(array $matchedLog, Event $raid): TacticalReport
    {
        return TacticalReport::updateOrCreate(
            ['wcl_report_id' => $matchedLog['code']],
            [
                'static_id' => $raid->static_id,
                'event_id' => $raid->id,
                'title' => $matchedLog['title'] ?? 'Raid Analysis',
            ]
        );
    }

    /**
     * Fetch raids that ended recently and don't have a tactical report.
     */
    public function getPendingAnalysisRaids(int $hours = 6): Collection
    {
        return Event::where('end_time', '>=', Carbon::now()->subHours($hours))
            ->where('end_time', '<=', Carbon::now())
            ->whereDoesntHave('tacticalReport')
            ->with('static')
            ->get();
    }

    /**
     * Build the formatted payload for the logs index Vue component.
     */
    public function buildLogsIndexPayload(StaticGroup $static, LengthAwarePaginator $logs): array
    {
        $logsData = array_map(fn($log) => [
            'id'           => $log->id,
            'title'        => $log->title,
            'difficulties' => $log->difficulties ?? [],
            'date'         => $log->created_at->format('M d, Y'),
            'has_ai'       => (bool) ($log->ai_blocks || $log->ai_analysis),
            'url'          => route('statics.logs.show', $log),
        ], $logs->items());

        return $logsData;
    }
}
