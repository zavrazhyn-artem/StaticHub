<?php

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Models\TacticalReport;
use App\Jobs\ProcessRaidAnalysisJob;
use Illuminate\Console\Command;

class AnalyzeRaidCommand extends Command
{
    // Команда приймає URL. ID статіка за замовчуванням 1 (зміни, якщо треба)
    protected $signature = 'wcl:analyze {url} {static_id=1}';
    protected $description = 'Analyze a WCL report synchronously without queue workers';

    public function handle()
    {
        $url = $this->argument('url');
        $staticId = $this->argument('static_id');

        preg_match('/reports\/([a-zA-Z0-9]{16})/', $url, $matches);
        $reportId = $matches[1] ?? null;

        if (!$reportId) {
            $this->error('Invalid URL. Could not extract Report ID.');
            return;
        }

        $static = StaticGroup::find($staticId);
        if (!$static) {
            $this->error("Static group with ID {$staticId} not found.");
            return;
        }

        $this->info("Creating report record for ID: {$reportId}...");

        $report = TacticalReport::create([
            'static_id' => $static->id,
            'wcl_report_id' => $reportId,
            'title' => 'Console AI Analysis',
        ]);

        $this->info('Sending data to WCL and Gemini (this may take up to 60 seconds)...');

        try {
            // dispatchSync запускає джобу ПРЯМО ТУТ, не відправляючи її в Redis/чергу
            ProcessRaidAnalysisJob::dispatchSync($report);

            $this->info("✅ Done! Report successfully analyzed and saved to DB (TacticalReport ID: {$report->id})");

        } catch (\Exception $e) {
            $this->error("Failed: " . $e->getMessage());
        }
    }
}
