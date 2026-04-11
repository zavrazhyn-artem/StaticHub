<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Raid\BossAbilityTimingService;
use Illuminate\Console\Command;

class SeedSeasonCooldownsCommand extends Command
{
    protected $signature = 'cooldowns:seed-season
        {--season= : Season identifier; defaults to current season from config}
        {--zone= : WCL zone ID; defaults to wcl_zone_id from wow_season config}
        {--encounter= : Only seed this encounter slug (e.g. imperator-averzian)}';

    protected $description = 'Auto-discover all encounters in a WCL zone and seed boss ability timings for normal/heroic/mythic from each difficulty\'s top speed kill.';

    public function handle(BossAbilityTimingService $service): int
    {
        $season = (string) ($this->option('season') ?: config('wow_season.current_season'));
        $zoneId = (int) ($this->option('zone') ?: config('wow_season.wcl_zone_id'));
        $only = $this->option('encounter');

        if (!$zoneId) {
            $this->error('No WCL zone ID configured. Set wow_season.wcl_zone_id or pass --zone=N.');
            return self::FAILURE;
        }

        $this->line("Seeding season <info>{$season}</info> from WCL zone <info>{$zoneId}</info>" . ($only ? " (filter: {$only})" : '') . '...');
        $this->newLine();

        try {
            $report = $service->seedSeasonGlobal($season, $zoneId, $only);
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $totalAbilities = 0;
        $totalIcons = 0;
        $totalErrors = 0;

        foreach ($report['encounters'] as $enc) {
            $this->line("<comment>{$enc['name']}</comment> ({$enc['slug']})");
            foreach ($enc['difficulties'] as $diff => $info) {
                $status = $info['status'] ?? 'unknown';
                if ($status === 'ok') {
                    $this->line("  ✓ <info>{$diff}</info>: {$info['abilities']} abilities, {$info['icons']} icons (source: {$info['source']})");
                    $totalAbilities += $info['abilities'];
                    $totalIcons += $info['icons'];
                } elseif ($status === 'no_rankings') {
                    $this->warn("  · {$diff}: no rankings yet");
                } else {
                    $this->error("  ✗ {$diff}: {$info['message']}");
                    $totalErrors++;
                }
            }
        }

        $this->newLine();
        $encCount = count($report['encounters']);
        $this->info("Done — {$encCount} encounters processed, {$totalAbilities} abilities upserted, {$totalIcons} icons downloaded, {$totalErrors} errors.");
        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
