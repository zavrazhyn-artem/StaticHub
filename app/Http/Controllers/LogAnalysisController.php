<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogAnalysisRequest;
use App\Models\StaticGroup;
use App\Services\Analysis\LogAnalysisService;
use Illuminate\Http\RedirectResponse;

class LogAnalysisController extends Controller
{
    public function __construct(
        protected LogAnalysisService $logAnalysisService
    ) {}

    /**
     * Store manual log analysis request.
     */
    public function storeManual(LogAnalysisRequest $request, StaticGroup $static): RedirectResponse
    {
        $report = $this->logAnalysisService->processManualLogSubmission(
            $request->input('wcl_url'),
            $static
        );

        if (!$report) {
            return back()->with('error', 'Invalid Warcraft Logs URL. Could not extract Report ID.');
        }

        return redirect()->route('statics.logs.index', $static)
            ->with('success', 'Log submitted for analysis. It will appear here shortly.');
    }
}
