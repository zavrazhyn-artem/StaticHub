<?php

namespace App\Console\Commands;

use App\Helpers\WclReportParserHelper;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'app:test';

    public function __construct(private WclService $wclService)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $reportId = 'pZ8FVMHfCjQPLByY';

        $this->info('Testing getLogSummary with fixes for report: ' . $reportId);

        $logData = $this->wclService->getLogSummary($reportId);

        if (isset($logData['error'])) {
            $this->error($logData['error']);
            return;
        }

        // --- has_kills flag ---
        $this->info('');
        $this->info('=== has_kills: ' . ($logData['has_kills'] ? 'YES' : 'NO') . ' ===');

        // --- Player Details (ilvl fix check) ---
        $this->info('');
        $this->info('=== PLAYER DETAILS — ilvl + consumables ===');
        foreach ($logData['player_details'] ?? [] as $name => $d) {
            $trinkets = implode(', ', array_map(fn($t) => "{$t['name']}({$t['ilvl']})", $d['trinkets'] ?? []));
            $consumables = !empty($d['consumables'])
                ? implode(', ', array_map(fn($v, $k) => "{$k}×{$v}", $d['consumables'], array_keys($d['consumables'])))
                : 'NONE';
            $this->line("  {$name}: ilvl={$d['avg_ilvl']} role={$d['role']} spec={$d['spec']}");
            $this->line("    trinkets: [{$trinkets}]");
            $this->line("    consumables: {$consumables}");
        }

        // --- Performance Metrics (parse % check) ---
        $this->info('');
        $this->info('=== PERFORMANCE METRICS ===');
        foreach ($logData['performance_metrics'] ?? [] as $name => $m) {
            $parse = isset($m['parse_pct']) ? "parse={$m['parse_pct']}%" : 'parse=N/A';
            $ilvl = $m['ilvl'] ?? '?';
            $this->line("  {$name}: dps=" . ($m['dps'] ?? 0) . " hps=" . ($m['hps'] ?? 0) . " ilvl={$ilvl} {$parse} spec={$m['spec']}");
        }

        // --- Consumables Used (top-level) ---
        $this->info('');
        $this->info('=== CONSUMABLES_USED (top-level) ===');
        foreach ($logData['consumables_used'] ?? [] as $name => $items) {
            $list = implode(', ', array_map(fn($v, $k) => "{$k}×{$v}", $items, array_keys($items)));
            $this->line("  {$name}: {$list}");
        }

        // --- Consumable Buffs (flasks, food, runes) ---
        $this->info('');
        $this->info('=== CONSUMABLE BUFFS (raid-wide) ===');
        foreach ($logData['consumable_buffs'] ?? [] as $name => $data) {
            $this->line("  {$name}: uptime={$data['uptime_pct']}% avg_players/fight={$data['avg_players_per_fight']}");
        }

        // Save full payload
        $outputPath = storage_path('logs/fixed_payload.json');
        file_put_contents($outputPath, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('');
        $this->info('Full payload saved to: ' . $outputPath);
        $this->info('Size: ' . round(filesize($outputPath) / 1024, 1) . ' KB');
    }
}
