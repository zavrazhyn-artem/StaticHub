<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Models\AiChatMessage;
use App\Models\ReportFeedback;
use App\Models\TacticalReport;

/**
 * Owns business logic for AI-report feedback (rating + tag pools + comment).
 *
 * The set of canonical tags is defined here rather than in the database so a
 * future tag rename only touches code (no data migration). Translations live
 * in lang files keyed by the tag slug.
 */
class ReportFeedbackService
{
    /**
     * Positive tag pool — shown when rating >= 4 (with collapsable variant
     * available at lower ratings so users can still mark what worked).
     */
    public const LIKED_TAGS = [
        'accurate_data',
        'actionable',
        'per_pull_value',
        'good_tone',
        'spec_aware',
        'comprehensive',
        'boss_timeline',
    ];

    /**
     * Negative tag pool — shown when rating <= 4. The `wrong_spec_advice` tag
     * does NOT need a follow-up question for the user's character — we already
     * own that context via PersonalTacticalReport → character.
     */
    public const DISLIKED_TAGS = [
        'inaccurate_data',
        'hallucinations',
        'missing_context',
        'too_long',
        'tone',
        'wrong_spec_advice',
        'generic',
        'repetitive',
    ];

    /**
     * Return the existing feedback for a viewer, or null if none yet.
     */
    public function getFeedbackForUser(int $userId, int $reportId): ?ReportFeedback
    {
        return ReportFeedback::query()->findByUserAndReport($userId, $reportId);
    }

    /**
     * Build the payload the Vue card needs to render itself: existing feedback
     * (if any), the canonical tag pools, and a flag indicating whether the
     * chat-rating widget should appear (only shown when the user has actually
     * exchanged messages with the chat for this report).
     */
    public function buildFormPayload(int $userId, TacticalReport $report): array
    {
        $existing = $this->getFeedbackForUser($userId, $report->id);

        $hasChatHistory = AiChatMessage::query()
            ->where('tactical_report_id', $report->id)
            ->where('user_id', $userId)
            ->exists();

        return [
            'tactical_report_id' => $report->id,
            'show_chat_rating'   => $hasChatHistory,
            'liked_tag_pool'     => self::LIKED_TAGS,
            'disliked_tag_pool'  => self::DISLIKED_TAGS,
            'existing'           => $existing ? [
                'report_rating' => $existing->report_rating,
                'chat_rating'   => $existing->chat_rating,
                'liked_tags'    => $existing->liked_tags ?? [],
                'disliked_tags' => $existing->disliked_tags ?? [],
                'comment'       => $existing->comment,
                'submitted_at'  => $existing->updated_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * Upsert a user's feedback. Returns the saved row.
     *
     * Tags are filtered against the canonical pools — anything outside the
     * allowed list is silently dropped so a stale frontend can't poison
     * future aggregates with arbitrary strings.
     */
    public function upsertFeedback(int $userId, TacticalReport $report, array $input): ReportFeedback
    {
        $likedTags = array_values(array_intersect(
            (array) ($input['liked_tags'] ?? []),
            self::LIKED_TAGS
        ));
        $dislikedTags = array_values(array_intersect(
            (array) ($input['disliked_tags'] ?? []),
            self::DISLIKED_TAGS
        ));

        return ReportFeedback::query()->upsertForUser($userId, $report->id, [
            'report_rating' => (int) $input['report_rating'],
            'chat_rating'   => isset($input['chat_rating']) ? (int) $input['chat_rating'] : null,
            'liked_tags'    => $likedTags ?: null,
            'disliked_tags' => $dislikedTags ?: null,
            'comment'       => $this->normaliseComment($input['comment'] ?? null),
        ]);
    }

    private function normaliseComment(?string $comment): ?string
    {
        if ($comment === null) return null;
        $trimmed = trim($comment);
        return $trimmed === '' ? null : mb_substr($trimmed, 0, 2000);
    }

    /**
     * Build the data the admin feedback dashboard needs.
     * Splits aggregates into "all-time", "last 30 days", and the bucketed
     * weekly timeseries — admins need to spot trends, not just totals.
     */
    public function buildAdminDashboardPayload(): array
    {
        $allTime  = ReportFeedback::query()->aggregateGlobal();
        $last30   = ReportFeedback::query()->aggregateGlobal(now()->subDays(30));
        $timeseries = ReportFeedback::query()->weeklyTimeseries(12);

        // Tag mix split by rating polarity — drives prompt-tuning priorities.
        $tagsPositive = ReportFeedback::query()->tagBreakdownByRating('positive', now()->subDays(30));
        $tagsNegative = ReportFeedback::query()->tagBreakdownByRating('negative', now()->subDays(30));

        // Recent low-rating feedback (≤3) for the queue of "things to fix".
        $criticalRecent = ReportFeedback::query()
            ->withRatingAtMost(3)
            ->with(['user:id,name', 'tacticalReport:id,title,wcl_report_id,static_id'])
            ->latest()
            ->limit(20)
            ->get();

        $byVersion = ReportFeedback::query()->aggregateByPromptVersion();

        return [
            'all_time'        => $allTime,
            'last_30_days'    => $last30,
            'weekly'          => $timeseries,
            'tags_positive'   => $tagsPositive,
            'tags_negative'   => $tagsNegative,
            'critical_recent' => $criticalRecent,
            'by_version'      => $byVersion,
            'current_version' => (string) config('ai_report.prompt_version', 'v1'),
            'tag_labels'      => $this->tagLabelMap(),
        ];
    }

    /**
     * Slug → human-readable label mapping for templates that don't have access
     * to Vue's translation helper. Localised on the admin side via __().
     */
    public function tagLabelMap(): array
    {
        return [
            // Liked
            'accurate_data'     => __('Accurate data'),
            'actionable'        => __('Actionable advice'),
            'per_pull_value'    => __('Per-pull breakdown valuable'),
            'good_tone'         => __('Good coaching tone'),
            'spec_aware'        => __('Understands spec'),
            'comprehensive'     => __('Comprehensive'),
            'boss_timeline'     => __('Boss timing helpful'),
            // Disliked
            'inaccurate_data'   => __('Inaccurate numbers'),
            'hallucinations'    => __('Hallucinations'),
            'missing_context'   => __('Missing context'),
            'too_long'          => __('Too long'),
            'tone'              => __('Tone'),
            'wrong_spec_advice' => __('Wrong spec advice'),
            'generic'           => __('Generic'),
            'repetitive'        => __('Repetitive'),
        ];
    }
}
