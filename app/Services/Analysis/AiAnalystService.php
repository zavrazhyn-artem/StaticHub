<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AiChatMessage;
use App\Models\PersonalTacticalReport;
use App\Models\TacticalReport;

class AiAnalystService
{
    /** How many recent messages to send as context to the AI */
    private const HISTORY_WINDOW = 6;

    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly WclService    $wclService
    ) {}

    /**
     * Full log analysis for leaders/officers.
     */
    public function analyze(int $reportId, string $message, int $userId): string
    {
        $report  = TacticalReport::query()->findWithRoster($reportId);
        $context = $this->wclService->getLogSummary($report->wcl_report_id, $report->getRosterCharacterNames());
        $history = $this->loadHistory($reportId, $userId);

        $reply = $this->geminiService->analyzeLog($message, $context, $history);

        $this->saveExchange($reportId, $userId, $message, $reply);

        return $reply;
    }

    /**
     * Personal report analysis for members.
     *
     * @param int[] $characterIds All character IDs the user has in the static.
     */
    public function analyzePersonal(int $reportId, string $message, array $characterIds, int $userId): string
    {
        $personalReport = PersonalTacticalReport::where('tactical_report_id', $reportId)
            ->whereIn('character_id', $characterIds)
            ->first();

        $context = [
            'mode'            => 'personal',
            'personal_report' => $personalReport?->content ?? null,
        ];

        $history = $this->loadHistory($reportId, $userId);

        $reply = $this->geminiService->analyzeLog($message, $context, $history);

        $this->saveExchange($reportId, $userId, $message, $reply);

        return $reply;
    }

    /**
     * Load last N messages as compact text history for AI context.
     * Returns: [ ['role' => 'user'|'assistant', 'text' => string], ... ]
     */
    private function loadHistory(int $reportId, int $userId): array
    {
        return AiChatMessage::where('tactical_report_id', $reportId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'text' => $msg->toHistoryText(),
            ])
            ->all();
    }

    /**
     * Persist one user message + one assistant reply.
     */
    private function saveExchange(int $reportId, int $userId, string $userMessage, string $assistantReply): void
    {
        AiChatMessage::insert([
            [
                'tactical_report_id' => $reportId,
                'user_id'            => $userId,
                'role'               => 'user',
                'content'            => $userMessage,
                'created_at'         => now(),
            ],
            [
                'tactical_report_id' => $reportId,
                'user_id'            => $userId,
                'role'               => 'assistant',
                'content'            => $assistantReply,
                'created_at'         => now(),
            ],
        ]);
    }
}
