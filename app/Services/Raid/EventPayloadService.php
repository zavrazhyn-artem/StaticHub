<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\CharacterStaticSpec;
use App\Models\Character;
use App\Models\Event;
use App\Models\RaidAttendance;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Models\StaticGroup;

class EventPayloadService
{
    public function __construct(
        protected RaidAttendanceService $attendanceService,
        protected EncounterRosterService $encounterRosterService,
        protected BossPlannerService $bossPlannerService,
    ) {}

    /**
     * Build the boss planner URL for the event's static group.
     */
    public function buildBossPlannerUrl(Event $event): string
    {
        return route('statics.boss-planner');
    }

    /**
     * Build payload for displaying a raid event.
     */
    public function buildEventShowPayload(Event $event, User $user): array
    {
        $event->load('static');

        $userCharacters = $this->fetchUserCharactersInStatic($user->id, $event->static_id);
        $userCharacters->loadMissing('statics');

        /** @var RaidAttendance|null $currentAttendance */
        $currentAttendance = $event->getUserAttendance($user->id);

        $selectedCharacterId = $this->resolveSelectedCharacterId(
            $user,
            $userCharacters,
            $currentAttendance,
            $event->static_id
        );

        $rosterData = $this->attendanceService->getGroupedRoster($event);

        $tz = $event->timezone ?? $event->static?->timezone ?? 'UTC';
        $event->start_time_formatted = $event->start_time->copy()->setTimezone($tz)->format('H:i');
        $event->end_time_formatted = $event->end_time?->copy()->setTimezone($tz)->format('H:i');
        $event->start_time_date = $event->start_time->copy()->setTimezone($tz)->format('Y-m-d');

        $aiAnalysis = $event->ai_analysis;
        $event->ai_analysis_html = [
            'strategy' => Str::markdown($aiAnalysis['strategy'] ?? $aiAnalysis['Overall Strategy & Execution'] ?? 'No strategy data available.'),
            'wipes' => Str::markdown($aiAnalysis['wipes'] ?? $aiAnalysis['Major Wipe Reasons'] ?? 'No wipe data available.'),
            'individual' => Str::markdown($aiAnalysis['individual'] ?? $aiAnalysis['Individual Highlights/Issues'] ?? 'No individual data available.'),
        ];

        $allAttendanceSpecs = RaidAttendance::where('event_id', $event->id)
            ->whereNotNull('spec_id')
            ->pluck('spec_id', 'character_id');

        $specModels = Specialization::whereIn('id', $allAttendanceSpecs->values()->unique())
            ->get()
            ->keyBy('id');

        // Pre-load all main specs for all characters appearing in the roster (one query).
        $allRosterCharIds = collect($rosterData['mainRoster'])->flatten()
            ->merge($rosterData['absentRoster'])
            ->merge($userCharacters)
            ->pluck('id')
            ->unique();

        $mainSpecMap = CharacterStaticSpec::whereIn('character_id', $allRosterCharIds)
            ->where('static_id', $event->static_id)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        $enhanceRoster = function ($characters) use ($allAttendanceSpecs, $specModels, $mainSpecMap) {
            return collect($characters)->map(function ($char) use ($allAttendanceSpecs, $specModels, $mainSpecMap) {
                $char->setAttribute('class_icon_url', $char->getClassIconUrl());

                $spec = null;
                $rsvpSpecId = $allAttendanceSpecs->get($char->id);
                if ($rsvpSpecId) {
                    $spec = $specModels->get($rsvpSpecId);
                }
                if (!$spec) {
                    $spec = $mainSpecMap->get($char->id)?->specialization;
                }

                $char->setAttribute('main_spec', $spec ? [
                    'id'       => $spec->id,
                    'name'     => $spec->name,
                    'role'     => $spec->role,
                    'icon_url' => $spec->icon_url,
                ] : null);

                $char->setAttribute('assigned_role', $spec?->role ?? 'rdps');
                return $char;
            });
        };

        $mainRosterEnhanced = collect($rosterData['mainRoster'])->map(function ($roleGroup) use ($enhanceRoster) {
            return $enhanceRoster($roleGroup);
        })->toArray();

        $absentRosterEnhanced = $enhanceRoster($rosterData['absentRoster']);
        $userCharactersEnhanced = $enhanceRoster($userCharacters);

        // Pre-load all specs for user's characters in one query.
        $userCharIds = $userCharacters->pluck('id');
        $allUserSpecRecords = CharacterStaticSpec::whereIn('character_id', $userCharIds)
            ->where('static_id', $event->static_id)
            ->with('specialization')
            ->get()
            ->groupBy('character_id');

        $characterSpecs = $userCharacters->mapWithKeys(function ($char) use ($allUserSpecRecords) {
            $specRecords = $allUserSpecRecords->get($char->id, collect());

            return [$char->id => $specRecords->filter(fn ($r) => $r->specialization)
                ->map(fn ($r) => [
                    'id'       => $r->specialization->id,
                    'name'     => $r->specialization->name,
                    'role'     => $r->specialization->role,
                    'icon_url' => $r->specialization->icon_url,
                    'is_main'  => (bool) $r->is_main,
                ])->values()];
        });

        $encounterData = $this->encounterRosterService->buildEncounterRosterPayload($event);
        $plannerData = $this->bossPlannerService->buildPlannerPayload($event->static_id, $event->id);
        $planningStats = $this->encounterRosterService->calculatePlanningStats($event->static_id, $event->id);
        $bossPlannerUrl = $this->buildBossPlannerUrl($event);

        // Build buff/utility coverage from present roster classes
        $buffConfig = config('wow_buffs');
        $roleLimits = $buffConfig['role_limits'] ?? [];

        // All user alts in this static (for character swap feature)
        $allStaticAlts = $this->buildAllUserAlts($event->static_id, $resolvedCharacters ?? collect());

        // Weekly raid lockout data per character
        $weeklyRaidData = $this->buildWeeklyRaidData($allRosterCharIds);

        // Bench history: count how many of last N raids each character was benched
        $benchHistory = $this->buildBenchHistory($event->static_id, $event->id);

        return [
            'event'               => $event,
            'mainRoster'          => $mainRosterEnhanced,
            'absentRoster'        => $absentRosterEnhanced,
            'userCharacters'      => $userCharactersEnhanced,
            'currentAttendance'   => $currentAttendance,
            'selectedCharacterId' => $selectedCharacterId,
            'characterSpecs'      => $characterSpecs,
            'encounters'          => $encounterData['encounters'],
            'encounterRosters'    => $encounterData['encounterRosters'],
            'plannerData'         => $plannerData,
            'planningStats'       => $planningStats,
            'bossPlannerUrl'      => $bossPlannerUrl,
            'buffConfig'          => [
                'buffs_debuffs' => $buffConfig['buffs_debuffs'] ?? [],
                'utility'       => $buffConfig['utility'] ?? [],
            ],
            'roleLimits'          => $roleLimits[$event->difficulty] ?? $roleLimits['mythic'],
            'allStaticAlts'       => $allStaticAlts,
            'weeklyRaidData'      => $weeklyRaidData,
            'benchHistory'        => $benchHistory,
            'splitAssignments'    => $this->buildSplitAssignments($event),
        ];
    }

    /**
     * Fetch all user characters belonging to a specific static group.
     */
    private function fetchUserCharactersInStatic(int $userId, int $staticId): Collection
    {
        return Character::query()
            ->belongingToUserInStatic($userId, $staticId)
            ->get();
    }

    /**
     * Determine the default selected character for the RSVP form.
     */
    private function resolveSelectedCharacterId(User $user, Collection $userCharacters, ?RaidAttendance $currentAttendance, int $staticId): ?int
    {
        if ($currentAttendance) {
            return $currentAttendance->character_id;
        }

        if ($userCharacters->isEmpty()) {
            return null;
        }

        $mainCharacter = $userCharacters->first(
            fn ($char) => $char->statics->contains(fn ($s) => $s->pivot->role === 'main')
        );
        return $mainCharacter ? $mainCharacter->id : $userCharacters->first()->id;
    }

    /**
     * Get all split assignments for an event: { character_id: split_group }
     */
    private function buildSplitAssignments(Event $event): array
    {
        return RaidAttendance::where('event_id', $event->id)
            ->whereNotNull('split_group')
            ->pluck('split_group', 'character_id')
            ->toArray();
    }

    /**
     * Build all user alts in this static, keyed by user_id.
     * Each user maps to an array of their characters with specs.
     */
    private function buildAllUserAlts(int $staticId, Collection $resolvedCharacters): array
    {
        $userIds = User::query()
            ->whereHas('statics', fn ($q) => $q->where('statics.id', $staticId))
            ->pluck('id');

        $characters = Character::query()
            ->whereIn('user_id', $userIds)
            ->whereHas('statics', fn ($q) => $q->where('statics.id', $staticId))
            ->with(['statics' => fn ($q) => $q->where('statics.id', $staticId)])
            ->get();

        $specMap = CharacterStaticSpec::whereIn('character_id', $characters->pluck('id'))
            ->where('static_id', $staticId)
            ->with('specialization')
            ->get()
            ->groupBy('character_id');

        return $characters->groupBy('user_id')
            ->map(fn (Collection $chars) => $chars->map(function (Character $c) use ($specMap) {
                $specs = $specMap->get($c->id, collect());
                $mainSpec = $specs->firstWhere('is_main', true)?->specialization;

                return [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'playable_class' => $c->playable_class,
                    'item_level'     => $c->equipped_item_level ?? $c->item_level,
                    'avatar_url'     => $c->avatar_url,
                    'role'           => $c->statics->first()?->pivot->role ?? 'alt',
                    'main_spec'      => $mainSpec ? [
                        'id'       => $mainSpec->id,
                        'name'     => $mainSpec->name,
                        'role'     => $mainSpec->role,
                        'icon_url' => $mainSpec->icon_url,
                    ] : null,
                    'specs' => $specs->filter(fn ($r) => $r->specialization)->map(fn ($r) => [
                        'id'       => $r->specialization->id,
                        'name'     => $r->specialization->name,
                        'role'     => $r->specialization->role,
                        'icon_url' => $r->specialization->icon_url,
                        'is_main'  => (bool) $r->is_main,
                    ])->values(),
                ];
            })->values())
            ->toArray();
    }

    /**
     * Build weekly raid lockout data for characters.
     * Returns { character_id => { "Instance Name" => [ { name, LFR, N, H, M } ] } }
     */
    private function buildWeeklyRaidData(Collection $characterIds): array
    {
        return Character::whereIn('id', $characterIds)
            ->whereNotNull('character_weekly_data')
            ->pluck('character_weekly_data', 'id')
            ->map(fn ($data) => is_string($data) ? json_decode($data, true) : $data)
            ->map(fn ($data) => $data['raids'] ?? [])
            ->filter()
            ->toArray();
    }

    /**
     * Build bench history: count how many of the last 5 raids each character was absent/benched.
     */
    private function buildBenchHistory(int $staticId, int $currentEventId): array
    {
        $recentEventIds = Event::query()
            ->forStatic($staticId)
            ->where('start_time', '<', now())
            ->where('id', '!=', $currentEventId)
            ->orderByDesc('start_time')
            ->limit(5)
            ->pluck('id');

        if ($recentEventIds->isEmpty()) {
            return [];
        }

        $totalEvents = $recentEventIds->count();

        return RaidAttendance::whereIn('event_id', $recentEventIds)
            ->whereIn('status', ['absent', 'tentative'])
            ->groupBy('character_id')
            ->selectRaw('character_id, COUNT(*) as bench_count')
            ->pluck('bench_count', 'character_id')
            ->map(fn (int $count) => [
                'bench_count'  => $count,
                'total_events' => $totalEvents,
            ])
            ->toArray();
    }
}
