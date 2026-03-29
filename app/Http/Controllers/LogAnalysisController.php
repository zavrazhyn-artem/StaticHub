<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRaidAnalysisJob;
use App\Models\TacticalReport;
use App\Models\StaticGroup;
use Illuminate\Http\Request;

class LogAnalysisController extends Controller
{
    public function storeManual(Request $request, StaticGroup $static)
    {
        $request->validate([
            'wcl_url' => ['required', 'url', 'regex:/warcraftlogs\.com\/reports\/([a-zA-Z0-9]{16})/']
        ]);

        // Regex Extraction: Extract the 16-character Report ID from the URL
        preg_match('/reports\/([a-zA-Z0-9]{16})/', $request->wcl_url, $matches);
        $reportId = $matches[1] ?? null;

        if (!$reportId) {
            return back()->with('error', 'Invalid Warcraft Logs URL. Could not extract Report ID.');
        }

        // Create a new TacticalReport record linked to the current Static
        $report = TacticalReport::create([
            'static_id' => $static->id,
            'wcl_report_id' => $reportId,
            'title' => 'Manual Log Analysis', // Default title, will be updated by Job
        ]);

        // Dispatch an asynchronous Job, passing the new TacticalReport model
        ProcessRaidAnalysisJob::dispatch($report);

        return redirect()->route('statics.logs.index', $static)
            ->with('success', 'Log submitted for analysis. It will appear here shortly.');
    }
}
