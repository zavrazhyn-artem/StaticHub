<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogAnalysisRequest;
use App\Models\StaticGroup;
use App\Services\LogAnalysisService;
use Illuminate\Http\RedirectResponse;

class LogAnalysisController extends Controller
{
    public function __construct(
        protected LogAnalysisService $logAnalysisService
    ) {}

    /**
     * Store manual log analysis request.
     *
     * @param LogAnalysisRequest $request
     * @param StaticGroup $static
     * @return RedirectResponse
     */
    public function storeManual(LogAnalysisRequest $request, StaticGroup $static): RedirectResponse
    {
        $report = $this->logAnalysisService->submitManualLog(
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
