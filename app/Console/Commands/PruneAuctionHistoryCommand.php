<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily prune for price_snapshots — keep only the last N days (default 7) of
 * auction history. The table grows ~1M rows / month from the hourly auction
 * sync, but only the LATEST price per item is read by ConsumableService.
 * Historical data is dead weight; aggressive pruning is safe.
 *
 * DELETE is chunked (10k rows at a time, ~100ms apart) so we don't hold a
 * long lock on the table during peak hours. Total runtime for a one-time
 * cleanup of 12M rows is ~2-3 minutes.
 *
 * Requires the index on created_at added by the matching migration —
 * without it the DELETE degrades to a full table scan (~10+ min, locks).
 */
class PruneAuctionHistoryCommand extends Command
{
    protected $signature = 'auctions:prune
        {--keep=7 : Number of days of history to retain}
        {--chunk=10000 : Rows to delete per batch}
        {--dry-run : Report what would be deleted without touching the table}';

    protected $description = 'Delete price_snapshots older than --keep days. Run daily.';

    public function handle(): int
    {
        $keepDays = max(1, (int) $this->option('keep'));
        $chunk    = max(100, (int) $this->option('chunk'));
        $dryRun   = (bool) $this->option('dry-run');

        $cutoff = now()->subDays($keepDays);

        $totalEligible = (int) DB::table('price_snapshots')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($totalEligible === 0) {
            $this->info("Nothing to prune — no rows older than {$cutoff->toDateTimeString()} ({$keepDays} days).");
            return self::SUCCESS;
        }

        $this->info("Found {$totalEligible} rows older than {$cutoff->toDateTimeString()} ({$keepDays} days).");

        if ($dryRun) {
            $this->warn('Dry run — no rows deleted.');
            return self::SUCCESS;
        }

        $deleted = 0;
        $start = microtime(true);

        do {
            $batchDeleted = DB::table('price_snapshots')
                ->where('created_at', '<', $cutoff)
                ->limit($chunk)
                ->delete();

            $deleted += $batchDeleted;

            if ($batchDeleted > 0) {
                // Brief pause so concurrent inserts (auction sync runs hourly)
                // and reads (ConsumableService) get fair access to the table.
                usleep(100_000);
            }
        } while ($batchDeleted > 0);

        $elapsed = round(microtime(true) - $start, 1);

        $this->info("Deleted {$deleted} rows in {$elapsed}s.");

        return self::SUCCESS;
    }
}
