<?php

namespace App\Services\StaticGroup;

use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;
use App\Services\Roster\InstanceDataService;

use App\Enums\StaticGroup\SyncType;
use App\Helpers\SyncIntervalHelper;
use App\Models\StaticGroup;
use App\Models\Event;
use App\Helpers\CurrencyHelper;

class DashboardService
{
    public function __construct(
        protected RosterService       $rosterService,
        protected ConsumableService   $consumableService,
        protected TreasuryService     $treasuryService,
        protected InstanceDataService $instanceDataService
    ) {}

    /**
     * Get all data for the dashboard.
     */
    public function getDashboardData(StaticGroup $static): array
    {
        $nextRaid       = Event::query()->nextRaid($static->id);
        $roleCounts     = $this->rosterService->getRoleCounts($static->id);
        $consumables    = $this->consumableService->buildConsumablesPayload($static);
        $weeklySchedule = Event::query()->weeklySchedule($static->id, 3);

        $treasuryData  = $this->treasuryService->buildTreasuryIndexPayload($static);
        $reserves      = $treasuryData['reserves'];
        $autonomy      = $treasuryData['autonomy'];
        $weeklyStatus  = $treasuryData['weeklyStatus'];

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

        $raidProgression = $this->getStaticRaidProgression($static);

        return array_merge(
            compact(
                'static',
                'nextRaid',
                'roleCounts',
                'weeklySchedule',
                'reserves',
                'autonomy',
                'weeklyStatus',
                'syncData',
                'raidProgression'
            ),
            $consumables
        );
    }

    /**
     * Aggregate cumulative raid boss kills across the static's roster.
     * A boss counts as killed at a difficulty if >= 60% of the roster has killed it.
     */
    public function getStaticRaidProgression(StaticGroup $static): array
    {
        $characters = $static->characters()->with('serviceRawData')->get();
        $rosterSize = $characters->count();

        if ($rosterSize === 0) {
            return [];
        }

        $threshold   = (int) ceil($rosterSize * 0.6);
        $difficulties = ['LFR', 'N', 'H', 'M'];
        $counters    = []; // [instance][bossName][difficulty] => kill count

        $this->instanceDataService->setRegion($static->region ?? 'eu');

        foreach ($characters as $character) {
            $raidData = $character->serviceRawData?->bnet_raid ?? [];
            if ($raidData === []) {
                continue;
            }

            $charProgression = $this->instanceDataService->resolveRaids($raidData);
            if ($charProgression === null) {
                continue;
            }

            foreach ($charProgression as $instance => $bosses) {
                foreach ($bosses as $boss) {
                    foreach ($difficulties as $diff) {
                        if ($boss[$diff] ?? false) {
                            $counters[$instance][$boss['name']][$diff]
                                = ($counters[$instance][$boss['name']][$diff] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        $result = [];
        $instances = config('wow_season.current_raid_instances', []);

        foreach ($instances as $instanceName => $configBosses) {
            $bosses = [];
            foreach ($configBosses as $bossName) {
                $best = null;
                foreach ($difficulties as $diff) {
                    $count = $counters[$instanceName][$bossName][$diff] ?? 0;
                    if ($count >= $threshold) {
                        $best = $diff;
                    }
                }
                $bosses[] = [
                    'name'       => $bossName,
                    'difficulty' => $best,
                ];
            }
            $result[] = [
                'instance' => $instanceName,
                'bosses'   => $bosses,
            ];
        }

        return $result;
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
            'weeklyStatus'   => $data['weeklyStatus'] ?? [],
            'paidCount'      => collect($data['weeklyStatus'] ?? [])->where('is_paid', true)->count(),
            'weekRange'      => now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d'),
            'reserves'       => CurrencyHelper::formatGold($data['reserves']),
            'autonomy'       => $data['autonomy'],
            'raidDays'       => count($static->raid_days ?? ['wed', 'thu', 'sun']),
            'recipes'        => $recipes->map(fn($r) => [
                'icon'     => $r->display_icon_url,
                'name'     => $r->name,
                'quantity' => $r->quantity ?? $r->default_quantity,
            ])->values()->toArray(),
            'roleCounts'      => $data['roleCounts'],
            'raidProgression' => $data['raidProgression'],
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
