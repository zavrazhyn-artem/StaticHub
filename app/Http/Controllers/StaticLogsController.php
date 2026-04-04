<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Services\Analysis\StaticLogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticLogsController extends Controller
{
    public function __construct(
        private readonly StaticLogService $logService
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

        return view('statics.logs.index', [
            'static'              => $static,
            'logs'                => $logs,
            'currentFromDate'     => $fromDate,
            'currentToDate'       => $toDate,
            'currentDifficulties' => $rawDiffs ?? '',
        ]);
    }

    public function show(StaticGroup $static, TacticalReport $report): View
    {
        if ($report->static_id !== $static->id) {
            abort(404);
        }

        $userCharacter = auth()->check()
            ? $this->logService->getUserCharacterForReport(auth()->user(), $static, $report)
            : null;

        $rawLogData = $this->logService->getRawLogData($report->wcl_report_id);

        return view('statics.logs.show', compact('static', 'report', 'userCharacter', 'rawLogData'));
    }
}
