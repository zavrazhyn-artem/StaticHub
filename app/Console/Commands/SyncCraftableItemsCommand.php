<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Gear\CraftableItemSyncService;
use Illuminate\Console\Command;

/**
 * `php artisan blastr:sync-craftables` — pulls every gear-producing
 * profession's recipes from BNet and seeds equippable crafted items
 * into the season_items catalog with is_craftable=true.
 *
 * Idempotent: re-running upserts existing rows. Run after a season
 * patch that introduces new crafted items.
 */
final class SyncCraftableItemsCommand extends Command
{
    protected $signature = 'blastr:sync-craftables {--dry-run : Show what would be upserted without writing}';

    protected $description = 'Seed craftable equipment from Blizzard profession recipes into season_items.';

    public function __construct(
        private readonly CraftableItemSyncService $sync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Walking gear-producing professions...');
        $start = microtime(true);

        $stats = $this->sync->sync(function (string $line) {
            $this->line($line);
        });

        $elapsed = number_format(microtime(true) - $start, 1);

        $this->newLine();
        $this->info("Done in {$elapsed}s");
        $this->table(
            ['Recipes seen', 'Equipment found', 'Upserted', 'Skipped', 'Errors'],
            [[$stats['seen'], $stats['equipment'], $stats['upserted'], $stats['skipped'], $stats['errors']]],
        );

        return self::SUCCESS;
    }
}
