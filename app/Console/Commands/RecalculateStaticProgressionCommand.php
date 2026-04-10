<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StaticGroup;
use App\Services\StaticGroup\StaticProgressionService;
use Illuminate\Console\Command;

class RecalculateStaticProgressionCommand extends Command
{
    protected $signature = 'statics:recalculate-progression {staticId?}';

    protected $description = 'Recalculate raid progression for all statics (or a specific one).';

    public function handle(StaticProgressionService $service): int
    {
        $staticId = $this->argument('staticId');

        $query = StaticGroup::withoutGlobalScopes()->whereHas('characters');

        if ($staticId) {
            $query->where('id', $staticId);
        }

        $statics = $query->get();

        if ($statics->isEmpty()) {
            $this->warn('No statics found.');
            return self::SUCCESS;
        }

        foreach ($statics as $static) {
            $new = $service->recalculate($static);
            $this->line("{$static->name} (ID: {$static->id}): {$new} new achievement(s) recorded.");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
