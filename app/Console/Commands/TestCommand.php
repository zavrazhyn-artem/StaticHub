<?php

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function __construct(private WclService $wclService)
    {
        parent::__construct();
    }

    public function handle()
    {
        $reportId = 'hk6zqmd7aFcjp2R3';

        $static = StaticGroup::with('characters')->first();
        $rosterNames = $static ? $static->characters->pluck('name')->toArray() : [];

        $this->info('Static: ' . ($static->name ?? 'none'));
        $this->info('Roster (' . count($rosterNames) . '): ' . implode(', ', $rosterNames));
        $this->info('Fetching WCL data...');

        $logData = $this->wclService->getLogSummary($reportId, $rosterNames);

        $outputPath = storage_path('logs/report_data_to_ai.json');
        file_put_contents($outputPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Saved to: ' . $outputPath);
        $this->line('Keys: ' . implode(', ', array_keys($logData)));

        if (isset($logData['boss_summary'])) {
            $this->info('--- Boss Summary ---');
            foreach ($logData['boss_summary'] as $boss => $data) {
                $this->line("  {$boss}: kills={$data['kills']} wipes={$data['wipes']} best_wipe_pct={$data['best_wipe_pct']}%");
            }
        }

        $this->info('--- Players (' . count($logData['players'] ?? []) . ') ---');
        foreach ($logData['players'] ?? [] as $p) {
            $this->line("  {$p['name']} spec={$p['subType']}");
        }

        $this->info('--- Deaths: ' . count($logData['deaths'] ?? []) . ' ---');
        $this->info('--- Interrupts: ' . count($logData['interrupts'] ?? []) . ' ---');
        $this->info('--- Major Damage Taken: ' . count($logData['major_damage_taken'] ?? []) . ' entries ---');
        $this->info('--- Dispels (' . count($logData['dispels'] ?? []) . ' players): ' . implode(', ', array_map(fn($n, $c) => "{$n}={$c}", array_keys($logData['dispels'] ?? []), $logData['dispels'] ?? [])) . ' ---');
        $this->info('--- Consumables: ' . json_encode($logData['consumables_used']) . ' ---');
        $this->info('--- Performance Metrics ---');
        foreach ($logData['performance_metrics'] ?? [] as $name => $m) {
            $this->line("  {$name}: spec={$m['spec']} dps={$m['dps']} hps={$m['hps']} rank={$m['dps_rank']} pct={$m['percentile']}");
        }
    }
}
