<?php

declare(strict_types=1);

namespace App\Jobs\Analysis;

use App\Helpers\DiscordWebhookBuilder;
use App\Models\ReportFeedback;
use App\Services\Discord\DiscordWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Posts a Discord embed to the configured admin channel when a user submits
 * AI-report feedback with rating ≤ 2. Lets us see and react to dissatisfied
 * users in real time instead of waiting for an admin to check the dashboard.
 *
 * No-op when DISCORD_FEEDBACK_WEBHOOK_URL is unset.
 */
class NotifyCriticalFeedbackJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(
        private readonly int $feedbackId,
    ) {
        $this->onQueue('default');
    }

    public function handle(DiscordWebhookService $webhookService): void
    {
        $webhookUrl = (string) config('services.discord.feedback_webhook_url');
        if (empty($webhookUrl)) {
            return; // not configured — silent skip
        }

        $feedback = ReportFeedback::with(['user:id,name', 'tacticalReport:id,title,wcl_report_id,static_id'])
            ->find($this->feedbackId);

        if (!$feedback || !$feedback->tacticalReport) {
            Log::info('NotifyCriticalFeedbackJob: feedback or report missing', ['id' => $this->feedbackId]);
            return;
        }

        $report = $feedback->tacticalReport;
        $reportUrl = rtrim((string) config('app.url'), '/') . '/logs/' . $report->id;

        $payload = DiscordWebhookBuilder::buildCriticalFeedbackPayload([
            'rating'        => (int) $feedback->report_rating,
            'chat_rating'   => $feedback->chat_rating ? (int) $feedback->chat_rating : null,
            'disliked_tags' => (array) ($feedback->disliked_tags ?? []),
            'comment'       => $feedback->comment,
            'user_name'     => $feedback->user?->name ?? 'Anonymous',
            'report_title'  => $report->title ?: $report->wcl_report_id,
            'report_url'    => $reportUrl,
        ]);

        $webhookService->sendWebhook($webhookUrl, $payload);
    }
}
