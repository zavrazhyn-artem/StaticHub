<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiAnalystRequest;
use App\Models\TacticalReport;
use App\Services\Analysis\AiAnalystService;
use Illuminate\Http\JsonResponse;

class AiAnalystController extends Controller
{
    public function __construct(
        private readonly AiAnalystService $aiAnalystService
    ) {}

    public function ask(AiAnalystRequest $request): JsonResponse
    {
        $reportId = $request->integer('report_id');
        $message  = $request->input('message');

        $report = TacticalReport::with('staticGroup')->findOrFail($reportId);
        $static = $report->staticGroup;
        $user   = auth()->user();

        // Leaders and officers get full log context
        if ($user->can('canViewGlobalReport', $static)) {
            $reply = $this->aiAnalystService->analyze($reportId, $message, $user->id);

            return response()->json(['reply' => $reply]);
        }

        // Members get personal report context only
        $characterIds = $static->characters()
            ->where('characters.user_id', $user->id)
            ->pluck('characters.id');

        if ($characterIds->isEmpty()) {
            return response()->json([
                'reply' => json_encode(['blocks' => [
                    ['type' => 'alert', 'level' => 'danger', 'content' => 'Your character was not found in this static.'],
                ]]),
            ], 403);
        }

        $reply = $this->aiAnalystService->analyzePersonal($reportId, $message, $characterIds->all(), $user->id);

        return response()->json(['reply' => $reply]);
    }
}
