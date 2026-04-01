<?php

namespace App\Http\Controllers;

use App\Models\TacticalReport;
use App\Services\GeminiService;
use App\Services\WclService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AiAnalystController extends Controller
{
    protected GeminiService $geminiService;
    protected WclService $wclService;

    public function __construct(
        GeminiService $geminiService,
        WclService $wclService,
    )
    {
        $this->geminiService = $geminiService;
        $this->wclService = $wclService;
    }

    /**
     * Ask Gemini to analyze the log data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
            'report_id' => 'required|string',
        ]);

        $message = $request->input('message');
        $reportId = $request->input('report_id');

        // Отримуємо тактичний звіт за його ID в БД
        $report = TacticalReport::findOrFail($reportId);

        // Отримуємо ростер з пов'язаної статичної групи
        $rosterNames = $report->staticGroup->characters->pluck('name')->toArray();

        // Завантажуємо звіт з WCL (або з кешу/файлу, як це робить WclService)
        $logData = $this->wclService->getLogSummary($report->wcl_report_id, $rosterNames);

        $result = $this->geminiService->analyzeLog($message, $logData);

        return response()->json(['reply' => $result]);
    }
}
