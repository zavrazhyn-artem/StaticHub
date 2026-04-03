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
        $logs = $this->logService->getPaginatedLogs($static, $request->input('difficulty'));

        return view('statics.logs.index', compact('static', 'logs'));
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
