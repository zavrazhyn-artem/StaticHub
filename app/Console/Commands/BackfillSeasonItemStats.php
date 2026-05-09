<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SeasonItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Backfills season_items.real_stats from Wowhead's tooltip endpoint, which
 * is the only public source that returns actual game stat values at a given
 * ilvl + bonus combo (Blizzard's data API ignores both query params and
 * returns stats at a useless template ilvl of 44).
 *
 * We fetch each item once at our anchor (Myth 6/6 = 289 ilvl, bonus 12806).
 * The set-total panel scales these to the user's chosen ilvl with the
 * standard 1.083^((target-base)/15) factor — exact at the anchor, slight
 * approximation elsewhere.
 *
 * Hybrid primary stats ("+N Agility or Intellect" on Druid/Monk gear) are
 * stored as both keys at the same value; the spec's mainstat decides which
 * one counts at aggregation time so the value isn't double-counted.
 */
class BackfillSeasonItemStats extends Command
{
    protected $signature = 'season-items:backfill-stats {--force : refetch even items that already have real_stats} {--limit= : cap how many items to process}';
    protected $description = 'Backfill season_items.real_stats from Wowhead at the Myth 6/6 anchor.';

    private const ANCHOR_ILVL = 289;
    private const ANCHOR_BONUS = 12806;
    private const TOOLTIP_URL = 'https://nether.wowhead.com/tooltip/item/%d?ilvl=%d&bonus=%d&dataEnv=1&locale=0';
    private const REQ_DELAY_MS = 200;

    public function handle(): int
    {
        $query = SeasonItem::query()->orderBy('id');
        if (! $this->option('force')) {
            $query->whereNull('real_stats');
        }
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $items = $query->get();
        $total = $items->count();
        if ($total === 0) {
            $this->info('All season items already have real_stats. Use --force to refetch.');
            return self::SUCCESS;
        }

        $this->info("Backfilling {$total} season items from Wowhead at ilvl " . self::ANCHOR_ILVL . '…');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $okCount = $failCount = 0;

        foreach ($items as $item) {
            $stats = $this->fetchStats($item->id);
            if ($stats === null) {
                $failCount++;
                $bar->advance();
                continue;
            }
            $item->update([
                'real_stats'      => $stats,
                'base_item_level' => self::ANCHOR_ILVL,
            ]);
            $okCount++;
            $bar->advance();
            usleep(self::REQ_DELAY_MS * 1000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Updated: {$okCount}, failed: {$failCount}.");

        return self::SUCCESS;
    }

    /**
     * Fetch + parse the Wowhead tooltip HTML for a single item id at our
     * anchor ilvl/bonus. Returns null on HTTP/parse failure.
     *
     * @return array<string, int>|null
     */
    private function fetchStats(int $itemId): ?array
    {
        try {
            $url = sprintf(self::TOOLTIP_URL, $itemId, self::ANCHOR_ILVL, self::ANCHOR_BONUS);
            $resp = Http::timeout(15)->get($url);
            if ($resp->failed()) return null;
            $tooltip = (string) ($resp->json('tooltip') ?? '');
            return $tooltip === '' ? null : $this->parseStats($tooltip);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Pull the four kinds of stats Wowhead emits:
     *   - hybrid primary:  "+93 [Agility or Intellect]"
     *   - single primary:  "+93 Strength" / Agility / Intellect
     *   - stamina:         "+1,326 Stamina"
     *   - secondaries:     "+<!--rtgN-->86 Haste" etc.
     *
     * Numbers may have thousands separators ("+1,326").
     *
     * @return array<string, int>
     */
    private function parseStats(string $tooltip): array
    {
        $out = [];

        // Hybrid primary — store both stats at full value; the aggregator
        // picks one based on the spec's mainstat to avoid double-counting.
        if (preg_match('/\+([\d,]+)\s+\[(Agility|Strength|Intellect)\s+or\s+(Agility|Strength|Intellect)\]/u', $tooltip, $m)) {
            $value = (int) str_replace(',', '', $m[1]);
            $out[strtolower($m[2])] = $value;
            $out[strtolower($m[3])] = $value;
        }

        // Single primary — only match outside the "[X or Y]" pattern.
        if (preg_match('/\+([\d,]+)\s+(Strength|Agility|Intellect)(?!\s+or)/u', $tooltip, $m)) {
            $value = (int) str_replace(',', '', $m[1]);
            $out[strtolower($m[2])] ??= $value;
        }

        if (preg_match('/\+([\d,]+)\s+Stamina/u', $tooltip, $m)) {
            $out['stamina'] = (int) str_replace(',', '', $m[1]);
        }

        // Secondary stats sit inside <!--rtgN--> markers; capture all four kinds.
        if (preg_match_all('/<!--rtg\d+-->([\d,]+)\s+(Crit|Haste|Mastery|Versatility)/u', $tooltip, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $out[strtolower($m[2])] = (int) str_replace(',', '', $m[1]);
            }
        }

        return $out;
    }
}
