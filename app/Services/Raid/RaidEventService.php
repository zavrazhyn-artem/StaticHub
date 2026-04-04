<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\RaidEvent;
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

        // Enhance character data with class icon URL and guaranteed role for Vue
        $enhanceRoster = function($characters) use ($event, $currentAttendance) {
            return collect($characters)->map(function($char) use ($event, $currentAttendance) {
                $char->setAttribute('class_icon_url', $char->getClassIconUrl());

                $role = null;

                // 1. НАЙВИЩИЙ ПРІОРИТЕТ: Якщо це персонаж, яким ми зараз приєднані, беремо роль прямо з RSVP
                if ($currentAttendance && $currentAttendance->character_id == $char->id) {
                    $role = $currentAttendance->role ?? $currentAttendance->combat_role ?? null;
                }

                // 2. Якщо ролі ще немає, шукаємо в pivot таблиці (працює для учасників ростеру)
                if (!$role && isset($char->pivot)) {
                    $role = $char->pivot->role ?? $char->pivot->combat_role ?? null;
                }

                // 3. Якщо все ще немає, беремо дефолтну роль зі статіка (тепер вона 100% завантажена)
                if (!$role && $char->relationLoaded('statics')) {
                    $staticRecord = $char->statics->firstWhere('id', $event->static_id);
                    $role = $staticRecord->pivot->combat_role ?? null;
                }

                // 4. Зберігаємо атрибут (тепер до mdps дійде тільки якщо людина взагалі без ролей)
                $char->setAttribute('assigned_role', $role ?? 'mdps');

                return $char;
            });
        };

        $mainRosterEnhanced = collect($rosterData['mainRoster'])->map(function($roleGroup) use ($enhanceRoster) {
            return $enhanceRoster($roleGroup);
        })->toArray();

        $absentRosterEnhanced = $enhanceRoster($rosterData['absentRoster']);
        $userCharactersEnhanced = $enhanceRoster($userCharacters);

        return [
            'event' => $event,
            'mainRoster' => $mainRosterEnhanced,
            'absentRoster' => $absentRosterEnhanced,
            'userCharacters' => $userCharactersEnhanced,
            'currentAttendance' => $currentAttendance,
            'selectedCharacterId' => $selectedCharacterId,
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

        $this->attendanceService->updateAttendance(
            $event,
            $character,
            $data['status'],
            $data['comment'] ?? null
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
