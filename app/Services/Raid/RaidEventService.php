<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\RaidEvent;
use App\Models\User;
use App\Models\Character;
use App\Models\RaidAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        $userCharacters = $this->fetchUserCharactersInStatic($user->id, $event->static_id);

        /** @var RaidAttendance|null $currentAttendance */
        $currentAttendance = $event->getUserAttendance($user->id);

        $selectedCharacterId = $this->resolveSelectedCharacterId(
            $user,
            $userCharacters,
            $currentAttendance,
            $event->static_id
        );

        $rosterData = $this->attendanceService->getGroupedRoster($event);

        return [
            'event' => $event,
            'mainRoster' => $rosterData['mainRoster'],
            'absentRoster' => $rosterData['absentRoster'],
            'userCharacters' => $userCharacters,
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
