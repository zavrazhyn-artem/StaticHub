<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\WclService;
use Illuminate\Console\Command;

class DiagnoseDebuffEventsCommand extends Command
{
    protected $signature = 'diag:debuff-events {reportId} {fightId} {abilityId}';
    protected $description = 'Dump event-type histogram + first/last few events for a debuff.';

    public function handle(WclService $wcl): int
    {
        $reportId = (string) $this->argument('reportId');
        $fightId = (int) $this->argument('fightId');
        $abilityId = (int) $this->argument('abilityId');

        $events = $wcl->getDebuffStackEvents($reportId, [$fightId], $abilityId);
        $this->line('total events: ' . count($events));

        $counts = [];
        foreach ($events as $e) {
            $t = $e['type'] ?? '?';
            $counts[$t] = ($counts[$t] ?? 0) + 1;
        }
        foreach ($counts as $t => $c) {
            $this->line("  {$t}: {$c}");
        }

        $this->line('--- first 5 ---');
        foreach (array_slice($events, 0, 5) as $e) {
            $this->line(json_encode($e));
        }
        $this->line('--- last 3 ---');
        foreach (array_slice($events, -3) as $e) {
            $this->line(json_encode($e));
        }
        return self::SUCCESS;
    }
}
