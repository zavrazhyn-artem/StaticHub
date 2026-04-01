<?php

namespace App\Services;

use App\Models\StaticGroup;
use App\Models\RaidEvent;
use App\Helpers\CurrencyHelper;

class DashboardService
{
    public function __construct(
        protected RosterService     $rosterService,
        protected ConsumableService $consumableService,
        protected TreasuryService   $treasuryService
    ) {}

    /**
     * Get all data for the dashboard.
     *
     * @param StaticGroup $static
     * @return array
     */
    public function getDashboardData(StaticGroup $static): array
    {
        // 1. Next Raid
        $nextRaid = RaidEvent::query()->nextRaid($static->id);

        // 2. Roster Summary
        $roleCounts = $this->rosterService->getRoleCounts($static->id);

        // 3. Consumables
        $consumables = $this->consumableService->getRaidConsumablesData($static);

        // 4. Weekly Schedule (Next 7 days)
        $weeklySchedule = RaidEvent::query()->weeklySchedule($static->id);

        // 5. Treasury Data
        $reserves = $this->treasuryService->getTotalReserves($static);
        $weeklyCost = $consumables['grand_total_weekly_cost'] ?? 0;
        $autonomy = CurrencyHelper::calculateAutonomy($reserves, $weeklyCost);
        $targetTax = $consumables['guild_tax_per_raider'] ?? 0;
        $weeklyStatus = $this->treasuryService->getWeeklyStatus($static, $targetTax);

        // 6. Sync Data
        $syncData = [
            'bnet' => $static->bnet_last_synced_at ? $static->bnet_last_synced_at->toIso8601String() : null,
            'rio' => $static->rio_last_synced_at ? $static->rio_last_synced_at->toIso8601String() : null,
            'wcl' => $static->wcl_last_synced_at ? $static->wcl_last_synced_at->toIso8601String() : null,
        ];

        return array_merge(
            compact(
                'static',
                'nextRaid',
                'roleCounts',
                'weeklySchedule',
                'reserves',
                'autonomy',
                'weeklyCost',
                'targetTax',
                'weeklyStatus',
                'syncData'
            ),
            $consumables
        );
    }
}
