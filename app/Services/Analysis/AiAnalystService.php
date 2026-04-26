<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\GeminiResponseFormatter;
use App\Models\AiChatMessage;
use App\Models\PersonalTacticalReport;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Models\User;

class AiAnalystService
{
    /** How many recent messages to send as context to the AI */
    private const HISTORY_WINDOW = 6;

    /** Chat session length in seconds after activation. */
    public const CHAT_TTL_SECONDS = 1800;

    public function __construct(
        private readonly GeminiService        $geminiService,
        private readonly WclService           $wclService,
        private readonly RaidPayloadStorage   $payloadStorage,
    ) {}

    /**
     * One-shot chat activation: builds an explicit Gemini cache from the
     * persisted raid payload, sets the activation window, returns the
     * timestamp when the chat will expire.
     *
     * Throws on misuse (already activated, no payload file, cache create fail).
     */
    public function activateChat(TacticalReport $report): \Carbon\Carbon
    {
        if ($report->chat_activated_at !== null) {
            throw new \DomainException('Chat already activated for this report.');
        }

        $payload = $this->payloadStorage->read($report->id);
        if ($payload === null) {
            throw new \DomainException('Raid payload no longer available — chat cannot be activated.');
        }

        $cache = $this->geminiService->createRaidCache($payload, self::CHAT_TTL_SECONDS);
        if (!$cache || empty($cache['cache_id'])) {
            throw new \RuntimeException('Failed to create Gemini cache for chat.');
        }

        $now = now();
        $report->update([
            'gemini_cache_id'         => $cache['cache_id'],
            'gemini_cache_expires_at' => $cache['expires_at'] ? \Carbon\Carbon::parse($cache['expires_at']) : null,
            'chat_activated_at'       => $now,
            'chat_active_until'       => $now->copy()->addSeconds(self::CHAT_TTL_SECONDS),
        ]);

        return $report->chat_active_until;
    }

    /**
     * Get the character IDs belonging to a user within a static group.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getUserCharacterIdsInStatic(StaticGroup $static, int $userId): \Illuminate\Support\Collection
    {
        return $static->characters()
            ->where('characters.user_id', $userId)
            ->pluck('characters.id');
    }

    /**
     * Full log analysis for leaders/officers — uses cached context if available.
     */
    public function analyze(int $reportId, string $message, User $user, StaticGroup $static): string
    {
        $report = TacticalReport::query()->findWithRoster($reportId);

        // Build user identity for the AI
        $userIdentity = $this->buildUserIdentity($user, $static, 'leader');

        $history = $this->loadHistory($reportId, $user->id);

        if ($report->isChatActive()) {
            $reply = $this->chatWithCache($report, $message, $userIdentity, $history);
        } else {
            // Fallback: send full log data (legacy path)
            $context = $this->wclService->getLogSummary($report->wcl_report_id, $report->getRosterCharacterNames());
            $context['user_identity'] = $userIdentity;
            $reply = $this->geminiService->analyzeLog($message, $context, $history);
        }

        $this->saveExchange($reportId, $user->id, $message, $reply);

        return $reply;
    }

    /**
     * Personal report analysis for members — uses cached context if available.
     *
     * @param int[] $characterIds All character IDs the user has in the static.
     */
    public function analyzePersonal(int $reportId, string $message, array $characterIds, User $user, StaticGroup $static): string
    {
        $report = TacticalReport::query()->findWithRoster($reportId);

        $personalReport = PersonalTacticalReport::where('tactical_report_id', $reportId)
            ->whereIn('character_id', $characterIds)
            ->first();

        // Build user identity for the AI
        $userIdentity = $this->buildUserIdentity($user, $static, 'member');

        $history = $this->loadHistory($reportId, $user->id);

        if ($report->isChatActive()) {
            // For members, add personal report restriction to the prompt
            $enrichedMessage = "IMPORTANT: This user is a regular member. Only discuss THEIR personal data. "
                . "Do not reveal other players' performance or names.\n\n"
                . "Their personal report:\n" . ($personalReport?->content ?? 'No personal report available.')
                . "\n\nUser question: " . $message;

            $reply = $this->chatWithCache($report, $enrichedMessage, $userIdentity, $history);
        } else {
            // Fallback: personal context only (legacy path)
            $context = [
                'mode'            => 'personal',
                'personal_report' => $personalReport?->content ?? null,
                'user_identity'   => $userIdentity,
            ];
            $reply = $this->geminiService->analyzeLog($message, $context, $history);
        }

        $this->saveExchange($reportId, $user->id, $message, $reply);

        return $reply;
    }

    /**
     * Check if chat is available for a given report (within the manual
     * activation window).
     */
    public function isChatAvailable(TacticalReport $report): bool
    {
        return $report->isChatActive();
    }

    /**
     * Chat using Gemini cached context.
     */
    private function chatWithCache(TacticalReport $report, string $message, array $userIdentity, array $history): string
    {
        $identityBlock = json_encode($userIdentity, JSON_UNESCAPED_UNICODE);

        $prompt = "=== USER IDENTITY ===\n{$identityBlock}\n\n";

        if (!empty($history)) {
            $prompt .= "=== CONVERSATION HISTORY ===\n";
            foreach ($history as $msg) {
                $label = $msg['role'] === 'user' ? '[User]' : '[Assistant]';
                $prompt .= "{$label}: {$msg['text']}\n";
            }
            $prompt .= "\n";
        }

        $chatSystemPromptPath = resource_path('prompts/gemini_chat_analyst.txt');
        $chatSystemPrompt = file_exists($chatSystemPromptPath) ? file_get_contents($chatSystemPromptPath) : '';

        $prompt .= "=== INSTRUCTIONS ===\n{$chatSystemPrompt}\n\n"
            . "=== USER MESSAGE ===\n{$message}\n\n"
            . "You are in CHAT MODE. Answer the user's specific question using the cached raid data. "
            . "Do NOT generate a full report. Respond concisely to what was asked. "
            . "Respond in the user's language. Return a JSON object with a 'blocks' array.";

        $url = $this->geminiService->buildModelUrl(
            config('services.gemini.pro_model', 'gemini-2.5-flash')
        );

        $rawResponse = $this->geminiService->executeWithCache(
            $report->gemini_cache_id,
            $prompt,
            $url,
            120,
            true
        );

        return GeminiResponseFormatter::cleanMarkdown($rawResponse);
    }

    /**
     * Build user identity context for AI chat.
     */
    private function buildUserIdentity(User $user, StaticGroup $static, string $accessLevel): array
    {
        $character = $static->characters()
            ->where('characters.user_id', $user->id)
            ->first();

        // Determine role from static membership
        $member = $static->members->first(fn($m) => $m->id === $user->id);
        $role = $member?->pivot?->access_role ?? 'member';

        return [
            'character_name' => $character?->name ?? $user->name,
            'class'          => $character?->playable_class ?? null,
            'spec'           => $character?->active_spec ?? null,
            'access_level'   => $accessLevel,
            'role'           => $role,
            'locale'         => $user->locale ?? 'en',
        ];
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
