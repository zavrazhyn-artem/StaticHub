<?php

namespace App\Services;

use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;

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
        $consumables = $this->consumableService->buildConsumablesPayload($static);

        // 4. Weekly Schedule (Next 3 events)
        $weeklySchedule = RaidEvent::query()->weeklySchedule($static->id, 3);

        // 5. Treasury Data
        $treasuryData = $this->treasuryService->buildTreasuryIndexPayload($static);
        $reserves = $treasuryData['reserves'];
        $weeklyCost = $treasuryData['weeklyCost'];
        $autonomy = $treasuryData['autonomy'];
        $targetTax = $treasuryData['targetTax'];
        $weeklyStatus = $treasuryData['weeklyStatus'];
        $taxStatus = $treasuryData['taxStatus'];
        $taxDescription = $treasuryData['taxDescription'];
        $taxClass = $treasuryData['taxClass'];

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
                'taxStatus',
                'taxDescription',
                'taxClass',
                'syncData'
            ),
            $consumables
        );
    }
}
