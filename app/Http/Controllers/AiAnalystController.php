<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiAnalystRequest;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Services\Analysis\AiAnalystService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AiAnalystController extends Controller
{
    public function __construct(
        private readonly AiAnalystService $aiAnalystService
    ) {}

    /**
     * One-shot activation of the AI chat for a tactical report.
     *
     * `$static` is injected positionally by ResolveCurrentStatic middleware —
     * it must precede route-bound models in the signature.
     */
    public function activate(StaticGroup $static, TacticalReport $report): JsonResponse
    {
        Gate::authorize('canActivateReportChat', $static);

        if (!$report->canActivateChat()) {
            return response()->json([
                'error' => $report->chat_activated_at !== null
                    ? 'already_activated'
                    : 'payload_unavailable',
            ], 409);
        }

        try {
            $until = $this->aiAnalystService->activateChat($report);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'activation_failed', 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'chat_active_until' => $until->toIso8601String(),
            'ttl_seconds'       => AiAnalystService::CHAT_TTL_SECONDS,
        ]);
    }

    public function ask(AiAnalystRequest $request): JsonResponse
    {
        $reportId = $request->integer('report_id');
        $message  = $request->input('message');

        $report = TacticalReport::with('staticGroup')->findOrFail($reportId);
        $static = $report->staticGroup;
        $user   = auth()->user();

        // Check if chat is available (cache still active)
        if (!$this->aiAnalystService->isChatAvailable($report)) {
            return response()->json([
                'reply' => json_encode(['blocks' => [
                    ['type' => 'alert', 'level' => 'warning', 'content' => 'Chat session has expired. The AI context for this report is no longer available.'],
                ]]),
            ]);
        }

        // Leaders and officers get full log context
        if ($user->can('canViewGlobalReport', $static)) {
            $reply = $this->aiAnalystService->analyze($reportId, $message, $user, $static);

            return response()->json(['reply' => $reply]);
        }

        // Members get personal report context only
        $characterIds = $this->aiAnalystService->getUserCharacterIdsInStatic($static, $user->id);

        if ($characterIds->isEmpty()) {
            return response()->json([
                'reply' => json_encode(['blocks' => [
                    ['type' => 'alert', 'level' => 'danger', 'content' => 'Your character was not found in this static.'],
                ]]),
            ], 403);
        }

        $reply = $this->aiAnalystService->analyzePersonal($reportId, $message, $characterIds->all(), $user, $static);

        return response()->json(['reply' => $reply]);
    }
}
