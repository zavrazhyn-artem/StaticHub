<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Character\SyncCharacterItemLevelJob;
use App\Models\Character;
use Illuminate\Console\Command;

/**
 * Daily lightweight refresh of every character's profile (ilvl + active spec)
 * — the data shown on the My Characters page. In-static characters are kept
 * fresh by UnifiedSyncOrchestrator on its own cadence, but characters that
 * never joined a static would otherwise keep whatever they had at OAuth
 * sign-up. Once a day we re-fetch their profile summary from Battle.net so
 * the listing stays current.
 *
 * Each dispatch is a SyncCharacterItemLevelJob which makes a single Bnet API
 * call. Dispatches are spread over a 2-hour window to avoid burning the rate
 * limit on the 'bnet' queue all at once.
 */
class RefreshAllCharactersCommand extends Command
{
    protected $signature = 'characters:refresh-all
        {--non-static-only : Only sync chars that are not in any static (saves API quota when run on top of orchestrator).}';

    protected $description = 'Dispatch lightweight ilvl/spec refresh for every max-level character.';

    /** Spread dispatches across 2 hours = 7200 seconds. */
    private const JITTER_WINDOW_SECONDS = 7200;

    public function handle(): int
    {
        $query = Character::query()->atMaxLevel();

        if ($this->option('non-static-only')) {
            $query->whereDoesntHave('statics');
        }

        $count = 0;
        $query->chunkById(200, function ($chars) use (&$count) {
            foreach ($chars as $character) {
                $delay = now()->addSeconds(random_int(0, self::JITTER_WINDOW_SECONDS));
                SyncCharacterItemLevelJob::dispatch($character)->delay($delay);
                $count++;
            }
        });

        $this->info("Dispatched lightweight profile refresh for {$count} character(s) across a 2-hour window.");

        return self::SUCCESS;
    }
}
