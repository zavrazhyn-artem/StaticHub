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

class EventPayloadService
{
    public function __construct(
        protected RaidAttendanceService $attendanceService
    ) {}

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

        $event->start_time_formatted = $event->start_time->format('H:i');
        $event->end_time_formatted = $event->end_time?->format('H:i');
        $event->start_time_date = $event->start_time->format('Y-m-d');

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

        return [
            'event'               => $event,
            'mainRoster'          => $mainRosterEnhanced,
            'absentRoster'        => $absentRosterEnhanced,
            'userCharacters'      => $userCharactersEnhanced,
            'currentAttendance'   => $currentAttendance,
            'selectedCharacterId' => $selectedCharacterId,
            'characterSpecs'      => $characterSpecs,
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
}
