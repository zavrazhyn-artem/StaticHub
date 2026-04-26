<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportFeedbackRequest;
use App\Jobs\Analysis\NotifyCriticalFeedbackJob;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Services\Analysis\ReportFeedbackService;
use Illuminate\Http\JsonResponse;

class ReportFeedbackController extends Controller
{
    public function __construct(
        private readonly ReportFeedbackService $feedbackService,
    ) {}

    /**
     * Return the form payload — existing feedback (if any), tag pools, and
     * whether the chat-rating widget should appear.
     *
     * `$static` is injected positionally by ResolveCurrentStatic middleware —
     * keep it as the first parameter to match every other controller in this
     * route group (StaticLogsController, etc.).
     */
    public function show(StaticGroup $static, TacticalReport $report): JsonResponse
    {
        $this->authorizeView($static, $report);
        $userId = (int) auth()->id();

        return response()->json($this->feedbackService->buildFormPayload($userId, $report));
    }

    /**
     * Upsert feedback for the authenticated user on this report.
     */
    public function store(StoreReportFeedbackRequest $request, StaticGroup $static, TacticalReport $report): JsonResponse
    {
        $this->authorizeView($static, $report);
        $userId = (int) auth()->id();

        $feedback = $this->feedbackService->upsertFeedback($userId, $report, $request->validated());

        // Real-time alert on critical ratings so we can react before the
        // admin dashboard is checked. No-op when webhook URL isn't set.
        if ($feedback->report_rating <= 2) {
            NotifyCriticalFeedbackJob::dispatch($feedback->id);
        }

        return response()->json([
            'ok'      => true,
            'feedback' => [
                'report_rating' => $feedback->report_rating,
                'chat_rating'   => $feedback->chat_rating,
                'liked_tags'    => $feedback->liked_tags ?? [],
                'disliked_tags' => $feedback->disliked_tags ?? [],
                'comment'       => $feedback->comment,
                'submitted_at'  => $feedback->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Feedback eligibility mirrors report visibility. The middleware already
     * confirmed auth + verified + has-static; we just check the report belongs
     * to the user's currently-resolved static, plus they're a member or owner.
     * Owners aren't always present in the `static_user` pivot, so we check both.
     */
    private function authorizeView(StaticGroup $static, TacticalReport $report): void
    {
        if ($report->static_id !== $static->id) {
            abort(404);
        }

        $user = auth()->user();
        $allowed = $user && (
            $static->hasMember($user->id)
            || (int) $static->owner_id === (int) $user->id
        );

        abort_unless($allowed, 403);
    }
}
