<?php

namespace App\Services\StaticGroup;

use App\Services\StaticGroup\RosterService;
use App\Services\StaticGroup\TreasuryService;
use App\Services\StaticGroup\StaticProgressionService;

use App\Enums\StaticGroup\SyncType;
use App\Helpers\SyncIntervalHelper;
use App\Models\StaticGroup;
use App\Models\Event;
use App\Helpers\CurrencyHelper;

class DashboardService
{
    public function __construct(
        protected RosterService                                 $rosterService,
        protected ConsumableService                             $consumableService,
        protected TreasuryService                               $treasuryService,
        protected StaticProgressionService                      $progressionService,
        protected SidebarPayloadService                         $sidebarPayloadService,
        protected \App\Services\Raid\EventPayloadService        $eventPayloadService,
        protected \App\Services\Roster\InstanceDataService      $instanceDataService,
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

        $treasuryData  = $this->treasuryService->buildTreasuryIndexPayload($static, $consumables);
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
     * Count confirmed (status=present|late) attendees on an event grouped by
     * spec role. Returns shape [tank, heal, mdps, rdps] => int.
     */
    private function countConfirmedRoles(int $eventId): array
    {
        $specMap = config('wow_season.specializations', []);
        $counts = ['tank' => 0, 'heal' => 0, 'mdps' => 0, 'rdps' => 0];

        \App\Models\RaidAttendance::query()
            ->where('event_id', $eventId)
            ->whereIn('status', ['present', 'late'])
            ->whereNotNull('spec_id')
            ->pluck('spec_id')
            ->each(function ($specId) use ($specMap, &$counts) {
                $role = $specMap[$specId] ?? null;
                if ($role && isset($counts[$role])) {
                    $counts[$role]++;
                }
            });

        return $counts;
    }

    /**
     * Compact payload for the "ME" card on the dashboard. Pulls the user's
     * main character + a handful of headline metrics from cached service data.
     */
    public function buildMeCardPayload(?\App\Models\User $user, StaticGroup $static): ?array
    {
        if (! $user) {
            return null;
        }

        $main = $user->getMainCharacterForStatic($static->id);
        if (! $main) {
            return null;
        }

        $main->loadMissing('serviceRawData');
        $raw = $main->serviceRawData;

        $bnetProfile = $raw?->bnet_profile ?? [];
        $bnetMplus   = $raw?->bnet_mplus   ?? [];
        $rio         = $raw?->rio_profile  ?? [];

        // ilvl: same source as the roster — bnet_profile.equipped_item_level
        $ilvl = isset($bnetProfile['equipped_item_level'])
            ? (int) $bnetProfile['equipped_item_level']
            : ($main->item_level ?? $main->equipped_item_level ?? 0);

        // M+ score: same as roster — bnet_mplus.current_mythic_rating.rating
        $score = $this->instanceDataService->resolveMythicRating($bnetMplus);

        // Best key — highest weekly-key level (same source as VaultDataService)
        $bestKey = (int) collect($rio['mythic_plus_weekly_highest_level_runs'] ?? [])
            ->max('mythic_level');

        // Vault: read from cached character_weekly_data (built by RosterCompilerService).
        // Slots unlocked: 3 raid (boss kills 2/4/6) + 3 dungeon (runs 1/4/8) + 3 world (runs 1/4/8) = 9 total.
        $weekly         = $main->character_weekly_data ?? [];
        $raidUnlocked   = count(array_filter($weekly['vault_raid_slots'] ?? [], fn ($s) => $s !== null));
        $dungeonUnlocked = $this->countVaultSlotsFromRuns(count($weekly['vault_weekly_runs'] ?? []));
        $worldUnlocked  = $this->countVaultSlotsFromRuns(count($weekly['vault_world_runs'] ?? []));
        $vaultUnlocked  = $raidUnlocked + $dungeonUnlocked + $worldUnlocked;

        $mainSpec = \App\Models\CharacterStaticSpec::query()
            ->where('character_id', $main->id)
            ->where('static_id', $static->id)
            ->where('is_main', true)
            ->with('specialization')
            ->first();

        return [
            'characterName' => $main->name,
            'playableClass' => $main->playable_class,
            'avatarUrl'     => $main->avatar_url,
            'specName'      => $mainSpec?->specialization?->name,
            'specRole'      => $mainSpec?->specialization?->role,
            'itemLevel'     => $ilvl,
            'mplusScore'    => $score !== null ? (int) round($score) : null,
            'bestKey'       => $bestKey ?: null,
            'vaultText'     => "{$vaultUnlocked} / 9",
            'readinessPct'  => null,
        ];
    }

    /**
     * Compact summary of the most recent AI-analysed log for the dashboard.
     */
    private function buildLastAiReportPayload(StaticGroup $static): ?array
    {
        $report = \App\Models\TacticalReport::query()->latestAnalyzedForStatic($static->id);
        if (! $report) {
            return null;
        }

        $diffMap = ['mythic' => 'M', 'heroic' => 'H', 'normal' => 'N', 'raid_finder' => 'LFR'];
        $diff = collect($report->difficulties ?? [])
            ->map(fn ($d) => $diffMap[strtolower((string) $d)] ?? strtoupper((string) $d))
            ->first();

        return [
            'id'           => $report->id,
            'title'        => $report->title ?: 'WCL ' . $report->wcl_report_id,
            'difficulty'   => $diff,
            'createdAt'    => $report->created_at?->toIso8601String(),
            'createdHuman' => $report->created_at?->diffForHumans(),
            'href'         => route('statics.logs.show', $report->id),
        ];
    }

    /**
     * Running bank balance for the last 7 days (oldest → newest).
     * Returned shape: [
     *   'days'   => ['Mon', 'Tue', ...],         // weekday short labels
     *   'series' => [int, int, ...],            // balance at end of each day
     *   'delta'  => int,                        // total change over the window
     * ]
     */
    private function buildBankSparkline(StaticGroup $static): array
    {
        $deltas      = \App\Models\Transaction::query()->forStatic($static->id)->dailyDeltasForLastDays(7);
        $current     = (int) ($static->treasury_balance ?? 0);
        $totalDelta  = array_sum($deltas);
        $startBalance = $current - $totalDelta;

        $series = [];
        $days   = [];
        $running = $startBalance;
        foreach ($deltas as $day => $delta) {
            $running += $delta;
            $series[] = $running;
            $days[] = \Carbon\Carbon::parse($day)->translatedFormat('D');
        }

        return [
            'days'   => $days,
            'series' => $series,
            'delta'  => $totalDelta,
        ];
    }

    /**
     * Topbar tally: count of bosses cleared at each difficulty (cumulative —
     * a Mythic kill also counts as Heroic and Normal, mirroring in-game UX).
     */
    private function buildProgressionTally(array $progression): array
    {
        $rank   = ['LFR' => 1, 'N' => 2, 'H' => 3, 'M' => 4];
        $totals = ['LFR' => 0, 'N' => 0, 'H' => 0, 'M' => 0, 'total' => 0];

        foreach ($progression as $instance) {
            foreach ($instance['bosses'] ?? [] as $boss) {
                $totals['total']++;
                $diff = $boss['difficulty'] ?? null;
                if (! $diff || ! isset($rank[$diff])) continue;
                $r = $rank[$diff];
                foreach ($rank as $key => $threshold) {
                    if ($r >= $threshold) $totals[$key]++;
                }
            }
        }

        return $totals;
    }

    /**
     * Vault slot ladder for dungeon/world: 1 run = slot 1, 4 = slot 2, 8 = slot 3.
     */
    private function countVaultSlotsFromRuns(int $runCount): int
    {
        $unlocked = 0;
        foreach ([1, 4, 8] as $threshold) {
            if ($runCount >= $threshold) {
                $unlocked++;
            }
        }
        return $unlocked;
    }

    /**
     * Aggregate attendance counts for the composition footer:
     * go = present + late, qm = tentative, no = absent, bench = bench.
     */
    private function countAttendanceStatuses(int $eventId): array
    {
        $rows = \App\Models\RaidAttendance::query()
            ->where('event_id', $eventId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'go'    => (int) (($rows['present'] ?? 0) + ($rows['late'] ?? 0)),
            'qm'    => (int) ($rows['tentative'] ?? 0),
            'no'    => (int) ($rows['absent'] ?? 0),
            'bench' => (int) ($rows['bench'] ?? 0),
        ];
    }

    /**
     * Get persisted raid progression for the static from the database.
     */
    public function getStaticRaidProgression(StaticGroup $static): array
    {
        return $this->progressionService->getProgression($static->id);
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

        $progressionTally = $this->buildProgressionTally($data['raidProgression']);
        $lastAiReport     = $this->buildLastAiReportPayload($static);

        return [
            'staticName'       => $static->name,
            'progressionLabel' => $this->sidebarPayloadService->buildProgressionLabel($static),
            'progressionTally' => $progressionTally,
            'me'               => $this->buildMeCardPayload(auth()->user(), $static),
            'nextRaid' => $nextRaid ? [
                'timestamp'      => $nextRaid->start_time->timestamp,
                'date'           => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('l, M d'),
                'time'           => $nextRaid->start_time->setTimezone($static->timezone)->translatedFormat('H:i'),
                'discordPosted'  => (bool) $nextRaid->discord_message_id,
                'href'           => route('schedule.event.show', $nextRaid->id),
                'confirmedRoles' => $this->countConfirmedRoles($nextRaid->id),
                'statusCounts'   => $this->countAttendanceStatuses($nextRaid->id),
                'rsvpContext'    => auth()->user()
                    ? $this->eventPayloadService->buildRsvpModalPayload($nextRaid, auth()->user())
                    : null,
            ] : null,
            'weeklyStatus'   => $data['weeklyStatus'] ?? [],
            'paidCount'      => collect($data['weeklyStatus'] ?? [])->where('is_paid', true)->count(),
            'weekRange'      => now()->startOfWeek()->format('M d') . ' - ' . now()->endOfWeek()->format('M d'),
            'reserves'       => CurrencyHelper::formatGold($data['reserves']),
            'reservesRaw'    => (int) ($data['reserves'] ?? 0),
            'autonomy'       => $data['autonomy'],
            'bankSparkline'  => $this->buildBankSparkline($static),
            'lastAiReport'   => $lastAiReport,
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
                'settings'  => route('statics.settings.schedule'),
                'calendar'  => route('schedule.index'),
                'treasury'  => route('statics.treasury'),
                'eventShow' => route('schedule.event.show', '__ID__'),
            ],
        ];
    }
}
