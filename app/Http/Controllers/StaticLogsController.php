<?php

namespace App\Http\Controllers;

use App\Models\StaticGroup;
use App\Models\TacticalReport;
use Illuminate\Http\Request;

class StaticLogsController extends Controller
{
    public function index(StaticGroup $static, Request $request)
    {
        $query = TacticalReport::where('static_id', $static->id)
            ->orderBy('created_at', 'desc');

        if ($request->filled('difficulty')) {
            $query->where('title', 'like', '%' . $request->difficulty . '%');
        }

        $logs = $query->paginate(12);

        return view('statics.logs.index', compact('static', 'logs'));
    }

    public function show(StaticGroup $static, TacticalReport $report)
    {
        if ($report->static_id !== $static->id) {
            abort(404);
        }

        $userCharacter = null;
        if (auth()->check()) {
            $userCharacter = auth()->user()->characters()
                ->select('characters.*')
                ->join('character_static', 'characters.id', '=', 'character_static.character_id')
                ->where('character_static.static_id', $static->id)
                ->join('personal_tactical_reports', 'characters.id', '=', 'personal_tactical_reports.character_id')
                ->where('personal_tactical_reports.tactical_report_id', $report->id)
                ->first();

            if (!$userCharacter) {
                $userCharacter = auth()->user()->characters()->first();
            }
        }

        return view('statics.logs.show', compact('static', 'report', 'userCharacter'));
    }
}
