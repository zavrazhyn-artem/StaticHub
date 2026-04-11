<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Raid\BossAbilityTimingService;
use Illuminate\Console\Command;

class SeedBossCooldownsCommand extends Command
{
    protected $signature = 'cooldowns:seed-boss
        {report : WCL report ID (e.g. X2HbZCJaMnTGvQgd)}
        {fight : WCL fight ID within the report (e.g. 14)}
        {encounter : Encounter slug (e.g. imperator-averzian)}
        {--season= : Season identifier; defaults to current season from config}
        {--difficulty=mythic : Difficulty (normal|heroic|mythic)}
        {--static= : Static ID for per-static custom seed (omit for global)}';

    protected $description = 'Seed boss ability timings for one encounter from a WCL report kill.';

    public function handle(BossAbilityTimingService $service): int
    {
        $report = (string) $this->argument('report');
        $fight = (int) $this->argument('fight');
        $encounter = (string) $this->argument('encounter');
        $season = (string) ($this->option('season') ?: $this->defaultSeason());
        $difficulty = (string) $this->option('difficulty');
        $staticId = $this->option('static') ? (int) $this->option('static') : null;

        $scope = $staticId ? "static#{$staticId}" : 'global';
        $this->line("Seeding {$encounter} {$difficulty} ({$season}, {$scope}) from report {$report} fight #{$fight}...");

        try {
            $result = $service->seedFromWclReport($report, $fight, $season, $encounter, $difficulty, $staticId);
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $fightInfo = $result['fight'];
        $duration = gmdate('i:s', $fightInfo['duration_sec']);
        $this->line("Fight: {$fightInfo['name']} ({$duration}, encounter #{$fightInfo['encounter_id']})");
        $this->line('Phases: ' . count($result['phases']));
        $this->line("Abilities upserted: {$result['abilities_upserted']}");
        $this->line("Icons downloaded: {$result['icons_downloaded']}");
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function defaultSeason(): string
    {
        return (string) (config('wow_season.current_season') ?: 'midnight-s1');
    }
}
