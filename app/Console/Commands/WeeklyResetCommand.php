<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\WeeklyResetHelper;
use App\Models\Character;
use App\Models\CharacterWeeklySnapshot;
use App\Models\StaticGroup;
use App\Services\StaticGroup\TreasuryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeeklyResetCommand extends Command
{
    protected $signature = 'weekly:reset {region : Region code (eu, us, kr, tw)}';
    protected $description = 'Snapshot character_weekly_data, process treasury taxes, and reset weekly state.';

    public function handle(TreasuryService $treasury): int
    {
        $region    = WeeklyResetHelper::normalizeRegion($this->argument('region'));
        $periodKey = $this->outgoingPeriodKey($region);

        $this->info("Weekly reset for region [{$region}], archiving period [{$periodKey}]...");

        // ── 1. Character weekly snapshots ────────────────────────────────
        $snapshotCount = $this->snapshotCharacters($region, $periodKey);

        // ── 2. Treasury tax deduction ────────────────────────────────────
        $taxCount = $this->processTreasuryTaxes($region, $treasury);

        $this->info("Archived {$snapshotCount} character snapshots. Processed taxes for {$taxCount} statics.");
        Log::info('Weekly reset completed.', [
            'region'     => $region,
            'period_key' => $periodKey,
            'snapshots'  => $snapshotCount,
            'statics_taxed' => $taxCount,
        ]);

        return self::SUCCESS;
    }

    private function snapshotCharacters(string $region, string $periodKey): int
    {
        $characters = Character::query()
            ->whereHas('realm', fn ($q) => $q->where('region', $region))
            ->whereNotNull('character_weekly_data')
            ->get(['id', 'character_weekly_data']);

        $snapshotCount = 0;

        foreach ($characters->chunk(100) as $chunk) {
            $inserts = [];

            foreach ($chunk as $character) {
                $weeklyData = $character->character_weekly_data;
                if ($weeklyData === null || $weeklyData === []) {
                    continue;
                }

                $inserts[] = [
                    'character_id' => $character->id,
                    'period_key'   => $periodKey,
                    'region'       => $region,
                    'weekly_data'  => json_encode($weeklyData),
                    'created_at'   => now(),
                ];
            }

            if ($inserts !== []) {
                CharacterWeeklySnapshot::query()->upsert(
                    $inserts,
                    ['character_id', 'period_key'],
                    ['weekly_data', 'created_at']
                );
                $snapshotCount += count($inserts);
            }
        }

        Character::query()
            ->whereHas('realm', fn ($q) => $q->where('region', $region))
            ->update(['character_weekly_data' => null]);

        return $snapshotCount;
    }

    private function processTreasuryTaxes(string $region, TreasuryService $treasury): int
    {
        $statics = StaticGroup::withoutGlobalScopes()
            ->where('region', $region)
            ->get();

        foreach ($statics as $static) {
            $treasury->processWeeklyTaxReset($static);
        }

        return $statics->count();
    }

    /**
     * The period key for the week that just ended (before the reset we're processing).
     * We go back 1 hour from reset time to land in the previous period.
     */
    private function outgoingPeriodKey(string $region): string
    {
        $resetTs = WeeklyResetHelper::resetTimestamp($region);

        return WeeklyResetHelper::periodKey($region, $resetTs - 3600);
    }
}
