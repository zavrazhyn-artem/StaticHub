<?php

namespace App\Services\StaticGroup;

use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;

use App\Enums\StaticGroup\SyncType;
use App\Helpers\SyncIntervalHelper;
use App\Models\StaticGroup;
use App\Models\Event;
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
        $nextRaid = Event::query()->nextRaid($static->id);

        // 2. Roster Summary
        $roleCounts = $this->rosterService->getRoleCounts($static->id);

        // 3. Consumables
        $consumables = $this->consumableService->buildConsumablesPayload($static);

        // 4. Weekly Schedule (Next 3 events)
        $weeklySchedule = Event::query()->weeklySchedule($static->id, 3);

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

        // 6. Sync Data — includes per-service interval so the widget can render
        //    an accurate countdown without knowing the plan tier on the frontend.
        $tier = $static->plan_tier ?? 'free';
        $syncData = [
            'bnet' => [
                'last_synced_at'   => $static->bnet_last_synced_at?->toIso8601String(),
                'interval_minutes' => SyncIntervalHelper::getIntervalInMinutes($tier, SyncType::BNET),
            ],
            'rio' => [
                'last_synced_at'   => $static->rio_last_synced_at?->toIso8601String(),
                'interval_minutes' => SyncIntervalHelper::getIntervalInMinutes($tier, SyncType::RIO),
            ],
            'wcl' => [
                'last_synced_at'   => $static->wcl_last_synced_at?->toIso8601String(),
                'interval_minutes' => SyncIntervalHelper::getIntervalInMinutes($tier, SyncType::WCL),
            ],
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

    /**
     * Build the fully-formatted payload for the dashboard Vue component.
     *
     * @param StaticGroup $static
     * @return array
     */
    public function buildDashboardViewPayload(StaticGroup $static): array
    {
        $data = $this->getDashboardData($static);

        $nextRaid = $data['nextRaid'];
        $recipes  = $data['recipes'] ?? collect();
        $weeklySchedule = $data['weeklySchedule'];

        return [
            'nextRaid' => $nextRaid ? [
                'timestamp'     => $nextRaid->start_time->timestamp,
                'date'          => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('l, M d'),
                'time'          => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('H:i'),
                'discordPosted' => (bool) $nextRaid->discord_message_id,
            ] : null,
            'roleCounts'     => $data['roleCounts'],
            'taxStatus'      => $data['taxStatus'],
            'taxDescription' => $data['taxDescription'],
            'targetTax'      => CurrencyHelper::formatGold($data['targetTax'], false),
            'weeklyStatus'   => $data['weeklyStatus'] ?? [],
            'paidCount'      => collect($data['weeklyStatus'] ?? [])->where('is_paid', true)->count(),
            'weekRange'      => now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d'),
            'reserves'       => CurrencyHelper::formatGold($data['reserves']),
            'autonomy'       => $data['autonomy'],
            'weeklyCost'     => $data['weeklyCost'],
            'raidDays'       => count($static->raid_days ?? ['wed', 'thu', 'sun']),
            'recipes'        => $recipes->map(fn($r) => [
                'icon'     => $r->display_icon_url,
                'name'     => $r->name,
                'quantity' => $r->quantity ?? $r->default_quantity,
            ])->values()->toArray(),
            'weeklySchedule' => $weeklySchedule->map(fn($e) => [
                'id'        => $e->id,
                'month'     => $e->start_time->setTimezone($static->timezone)->format('M'),
                'day'       => $e->start_time->setTimezone($static->timezone)->format('d'),
                'dayOfWeek' => $e->start_time->setTimezone($static->timezone)->format('D'),
                'time'      => $e->start_time->setTimezone($static->timezone)->format('H:i'),
                'rsvpCount' => $e->rsvp_count,
            ])->values()->toArray(),
            'syncData'      => $data['syncData'] ?? (object) [],
            'tickInterval'  => config('sync.widget_tick_ms', 1000),
            'routes' => [
                'settings'  => route('statics.settings.schedule', $static->id),
                'calendar'  => route('schedule.index'),
                'treasury'  => route('statics.treasury', $static->id),
                'eventShow' => route('schedule.event.show', '__ID__'),
            ],
        ];
    }
}
