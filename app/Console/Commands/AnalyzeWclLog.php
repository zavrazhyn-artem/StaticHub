<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\WclParserHelper;
use App\Jobs\Analysis\ProcessRaidAnalysisJob;
use App\Models\StaticGroup;
use App\Models\TacticalReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:analyze-wcl {url : WCL report URL or report ID} {--static= : Static group ID (defaults to first static)} {--force : Re-run analysis even if report already exists}')]
#[Description('Run AI raid analysis synchronously for a WCL log (local dev — bypasses queue entirely).')]
class AnalyzeWclLog extends Command
{
    public function handle(): int
    {
        $input = (string) $this->argument('url');

        $reportId = WclParserHelper::extractReportIdFromUrl($input) ?? trim($input);

        if ($reportId === '' || !preg_match('/^[A-Za-z0-9]+$/', $reportId)) {
            $this->error("Could not extract WCL report ID from input: {$input}");
            return self::FAILURE;
        }

        $staticOpt = $this->option('static');
        if ($staticOpt !== null) {
            $static = StaticGroup::query()->find((int) $staticOpt);
            if (!$static) {
                $this->error("Static group #{$staticOpt} not found.");
                return self::FAILURE;
            }
        } else {
            $static = StaticGroup::query()->orderBy('id')->first();
            if (!$static) {
                $this->error('No static groups exist. Pass --static=<id> or seed one.');
                return self::FAILURE;
            }
            $this->info("Using static #{$static->id} ({$static->name}) — pass --static=<id> to override.");
        }

        $report = TacticalReport::query()
            ->where('static_id', $static->id)
            ->where('wcl_report_id', $reportId)
            ->first();

        if ($report && !$this->option('force')) {
            $this->info("Report already exists (id={$report->id}). Re-running analysis. Use --force=false to skip in future.");
        } elseif (!$report) {
            $report = TacticalReport::create([
                'static_id'     => $static->id,
                'wcl_report_id' => $reportId,
                'title'         => 'CLI Analysis',
            ]);
            $this->info("Created tactical report id={$report->id} for WCL log {$reportId}.");
        } else {
            $this->info("Forcing re-analysis of existing report id={$report->id}.");
        }

        $this->info('Starting Gemini analysis pipeline — this can take 5-10 minutes…');
        $start = microtime(true);

        ProcessRaidAnalysisJob::dispatchSync($report);

        $elapsed = number_format(microtime(true) - $start, 1);
        $this->info("Done in {$elapsed}s. Report id={$report->id}.");

        return self::SUCCESS;
    }
}
