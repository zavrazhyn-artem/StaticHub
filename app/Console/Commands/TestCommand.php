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
            $parse = isset($m['parse_pct']) ? " parse={$m['parse_pct']}%" : '';
            $overheal = isset($m['overheal_pct']) ? " overheal={$m['overheal_pct']}%" : '';
            $this->line("  {$name}: spec={$m['spec']} dps=" . ($m['dps'] ?? 0) . " hps=" . ($m['hps'] ?? 0) . $parse . $overheal);
        }

        $this->info('--- Fight Durations ---');
        $this->line('  total_seconds: ' . ($logData['fight_durations']['total_seconds'] ?? 0));

        $this->info('--- Phase Summary ---');
        foreach ($logData['phase_summary'] ?? [] as $boss => $fights) {
            foreach ($fights as $f) {
                $this->line("  {$boss} fight#{$f['fight_id']}: {$f['outcome']} phase={$f['last_phase']} dur={$f['duration_s']}s boss_pct={$f['boss_pct']}%");
            }
        }

        $this->info('--- Player Details (' . count($logData['player_details'] ?? []) . ') ---');
        foreach ($logData['player_details'] ?? [] as $name => $d) {
            $trinkets = implode(', ', array_column($d['trinkets'] ?? [], 'name'));
            $this->line("  {$name}: role={$d['role']} spec={$d['spec']} ilvl={$d['avg_ilvl']} trinkets=[{$trinkets}]");
        }

        $this->info('--- Buff Uptime (' . count($logData['buff_uptime'] ?? []) . ' players) ---');
        $this->info('--- Resource Waste (' . count($logData['resource_waste'] ?? []) . ' players) ---');
        foreach ($logData['resource_waste'] ?? [] as $name => $r) {
            $this->line("  {$name}: {$r['resource']} waste={$r['waste_pct']}%");
        }
    }
}
