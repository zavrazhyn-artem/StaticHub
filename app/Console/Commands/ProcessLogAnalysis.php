<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\AutomatedLogAnalysisService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:process-log-analysis')]
#[Description('Analyze recent raid logs using AI')]
class ProcessLogAnalysis extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AutomatedLogAnalysisService $automatedLogAnalysisService): void
    {
        $this->info('Starting log analysis process...');

        $messages = $automatedLogAnalysisService->executeAutomatedAnalysis();

        foreach ($messages as $message) {
            if (str_starts_with($message, 'WARN:')) {
                $this->warn($message);
            } elseif (str_starts_with($message, 'ERROR:')) {
                $this->error($message);
            } else {
                $this->info($message);
            }
        }

        $this->info('Log analysis process completed.');
    }
}
