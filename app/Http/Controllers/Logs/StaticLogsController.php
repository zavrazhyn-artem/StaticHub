<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;

use App\Http\Requests\LogAnalysisRequest;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Services\Analysis\LogAnalysisService;
use App\Services\Analysis\StaticLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticLogsController extends Controller
{
    public function __construct(
        private readonly StaticLogService $logService,
        private readonly LogAnalysisService $logAnalysisService,
    ) {}

    public function index(StaticGroup $static, Request $request): View
    {
        $fromDate   = $request->input('from_date');
        $toDate     = $request->input('to_date');
        $rawDiffs   = $request->input('difficulties'); // e.g. "Mythic,Heroic"

        $difficulties = $rawDiffs
            ? array_map('strtolower', array_filter(array_map('trim', explode(',', $rawDiffs))))
            : [];

        $logs = $this->logService->getPaginatedLogs($static, $difficulties, $fromDate, $toDate);
        $logsData = $this->logService->buildLogsIndexPayload($static, $logs);

        $cooldownState = $this->logAnalysisService->getManualLogCooldownState($static);

        return view('statics.logs.index', [
            'static'              => $static,
            'logs'                => $logs,
            'logsData'            => $logsData,
            'currentFromDate'     => $fromDate,
            'currentToDate'       => $toDate,
            'currentDifficulties' => $rawDiffs ?? '',
            'cooldownState'       => $cooldownState,
        ]);
    }

    public function show(StaticGroup $static, TacticalReport $report): View
    {
        if ($report->static_id !== $static->id) {
            abort(404);
        }

        $payload = $this->logService->buildLogShowPayload($static, $report, auth()->user());

        return view('statics.logs.show', array_merge(
            ['static' => $static, 'report' => $report],
            $payload
        ));
    }

    /**
     * Store manual log analysis request.
     */
    public function storeManual(LogAnalysisRequest $request, StaticGroup $static): RedirectResponse
    {
        $cooldownState = $this->logAnalysisService->getManualLogCooldownState($static);

        if ($cooldownState['on_cooldown']) {
            return back()->with('error', __('Manual log upload is on cooldown. Please wait before submitting again.'));
        }

        $report = $this->logAnalysisService->processManualLogSubmission(
            $request->input('wcl_url'),
            $static
        );

        if (!$report) {
            return back()->with('error', __('Invalid Warcraft Logs URL. Could not extract Report ID.'));
        }

        return redirect()->route('statics.logs.index', $static)
            ->with('success', __('Log submitted for analysis. It will appear here shortly.'));
    }
}
