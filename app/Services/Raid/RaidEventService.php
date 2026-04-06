<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\CharacterStaticSpec;
use App\Models\RaidEvent;
use App\Models\Specialization;
use App\Models\User;
use App\Models\Character;
use App\Models\RaidAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RaidEventService
{
    public function __construct(
        protected RaidAttendanceService $attendanceService
    ) {}

    /**
     * Action: Build payload for displaying a raid event.
     */
    public function buildEventShowPayload(RaidEvent $event, User $user): array
    {
        $event->load('static');

        $userCharacters = $this->fetchUserCharactersInStatic($user->id, $event->static_id);

        // ОСЬ ТЕ ЩО МИ ЗАБУЛИ: Примусово вантажимо зв'язок зі статіками, щоб fallback не спрацьовував!
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

        // Prepare event data for Vue
        $event->start_time_formatted = $event->start_time->format('H:i');
        $event->end_time_formatted = $event->end_time?->format('H:i');
        $event->start_time_date = $event->start_time->format('Y-m-d');

        $aiAnalysis = $event->ai_analysis;
        $event->ai_analysis_html = [
            'strategy' => Str::markdown($aiAnalysis['strategy'] ?? $aiAnalysis['Overall Strategy & Execution'] ?? 'No strategy data available.'),
            'wipes' => Str::markdown($aiAnalysis['wipes'] ?? $aiAnalysis['Major Wipe Reasons'] ?? 'No wipe data available.'),
            'individual' => Str::markdown($aiAnalysis['individual'] ?? $aiAnalysis['Individual Highlights/Issues'] ?? 'No individual data available.'),
        ];

        // Load all attendance spec_ids for this event in one query (keyed by character_id).
        $allAttendanceSpecs = RaidAttendance::where('raid_event_id', $event->id)
            ->whereNotNull('spec_id')
            ->pluck('spec_id', 'character_id');

        // Pre-load all Specialization objects we'll need (avoid N+1).
        $specModels = Specialization::whereIn('id', $allAttendanceSpecs->values()->unique())
            ->get()
            ->keyBy('id');

        // Enhance character data with class icon URL, main spec and role for Vue.
        // Priority: spec chosen for this raid (from attendance) > main spec in static.
        $enhanceRoster = function ($characters) use ($event, $allAttendanceSpecs, $specModels) {
            return collect($characters)->map(function ($char) use ($event, $allAttendanceSpecs, $specModels) {
                $char->setAttribute('class_icon_url', $char->getClassIconUrl());

                $spec = null;

                $rsvpSpecId = $allAttendanceSpecs->get($char->id);
                if ($rsvpSpecId) {
                    $spec = $specModels->get($rsvpSpecId);
                }

                if (!$spec) {
                    $spec = $char->getMainSpecInStatic($event->static_id);
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

        $mainRosterEnhanced = collect($rosterData['mainRoster'])->map(function($roleGroup) use ($enhanceRoster) {
            return $enhanceRoster($roleGroup);
        })->toArray();

        $absentRosterEnhanced = $enhanceRoster($rosterData['absentRoster']);
        $userCharactersEnhanced = $enhanceRoster($userCharacters);

        // Спеки доступні для кожного персонажа в цьому статику (для RsvpModal)
        $characterSpecs = $userCharacters->mapWithKeys(function ($char) use ($event) {
            $specs = $char->specsInStatic($event->static_id);
            $mainSpecRecord = CharacterStaticSpec::where('character_id', $char->id)
                ->where('static_id', $event->static_id)
                ->where('is_main', true)
                ->value('spec_id');

            return [$char->id => $specs->map(fn ($spec) => [
                'id'       => $spec->id,
                'name'     => $spec->name,
                'role'     => $spec->role,
                'icon_url' => $spec->icon_url,
                'is_main'  => $spec->id === $mainSpecRecord,
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
     * Action: Process RSVP for a raid event.
     */
    public function executeRsvp(RaidEvent $event, User $user, array $data): bool
    {
        Log::info('RSVP Request processing', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'data' => $data
        ]);

        $character = $this->validateAndFetchRsvpCharacter($data, $user->id, $event->static_id);

        if (!$character) {
            Log::warning('RSVP failed: character not found or not in static', [
                'character_id' => $data['character_id'] ?? null,
                'user_id' => $user->id,
                'static_id' => $event->static_id
            ]);
            return false;
        }

        $event->clearUserAttendance($user->id);

        $specId = isset($data['spec_id']) ? (int) $data['spec_id'] : null;

        $this->attendanceService->updateAttendance(
            $event,
            $character,
            $data['status'],
            $data['comment'] ?? null,
            $specId
        );

        Log::info('RSVP success', [
            'event_id' => $event->id,
            'character_id' => $character->id
        ]);

        return true;
    }

    /**
     * Task: Fetch all user characters belonging to a specific static group.
     */
    private function fetchUserCharactersInStatic(int $userId, int $staticId): Collection
    {
        return Character::query()
            ->belongingToUserInStatic($userId, $staticId)
            ->get();
    }

    /**
     * Task: Determine the default selected character for the RSVP form.
     */
    private function resolveSelectedCharacterId(
        User $user,
        Collection $userCharacters,
        ?RaidAttendance $currentAttendance,
        int $staticId
    ): ?int {
        if ($currentAttendance) {
            return $currentAttendance->character_id;
        }

        if ($userCharacters->isEmpty()) {
            return null;
        }

        // Use the model method from User refactoring
        $mainCharacter = $user->getMainCharacterForStatic($staticId);

        return $mainCharacter ? $mainCharacter->id : $userCharacters->first()->id;
    }

    /**
     * Task: Validate if the selected character belongs to the user and the static.
     */
    private function validateAndFetchRsvpCharacter(array $data, int $userId, int $staticId): ?Character
    {
        if (empty($data['character_id'])) {
            return null;
        }

        return Character::query()->findForRsvp(
            (int) $data['character_id'],
            $userId,
            $staticId
        );
    }
}
