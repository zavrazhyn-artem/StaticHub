<?php

namespace App\Services;

use App\Models\RaidEvent;
use App\Models\User;
use App\Models\Character;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RaidEventService
{
    public function __construct(
        protected RaidAttendanceService $attendanceService
    ) {}

    public function getShowData(RaidEvent $event, User $user): array
    {
        $userCharacters = Character::query()
            ->belongingToUserInStatic($user->id, $event->static_id)
            ->get();

        $currentAttendance = $event->characters()
            ->where('user_id', $user->id)
            ->first()?->pivot;

        $selectedCharacterId = $this->determineSelectedCharacterId($event, $userCharacters, $currentAttendance);

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

    protected function determineSelectedCharacterId(RaidEvent $event, Collection $userCharacters, $currentAttendance): ?int
    {
        if ($currentAttendance) {
            return $currentAttendance->character_id;
        }

        if ($userCharacters->isEmpty()) {
            return null;
        }

        foreach ($userCharacters as $char) {
            $isMain = $char->statics()
                ->where('statics.id', $event->static_id)
                ->where('character_static.role', 'main')
                ->exists();

            if ($isMain) {
                return $char->id;
            }
        }

        return $userCharacters->first()->id;
    }

    public function handleRsvp(RaidEvent $event, User $user, array $data): bool
    {
        Log::info('RSVP Request processing', [
            'event_id' => $event->id,
            'user_id' => $user->id,
            'data' => $data
        ]);

        $character = Character::query()->findForRsvp(
            $data['character_id'],
            $user->id,
            $event->static_id
        );

        if (!$character) {
            Log::warning('RSVP failed: character not found or not in static', [
                'character_id' => $data['character_id'],
                'user_id' => $user->id,
                'static_id' => $event->static_id
            ]);
            return false;
        }

        $event->characters()->where('user_id', $user->id)->detach();

        $this->attendanceService->updateAttendance(
            $event,
            $character,
            $data['status'],
            $data['comment'] ?? null
        );

        Log::info('RSVP success', ['event_id' => $event->id, 'character_id' => $character->id]);

        return true;
    }
}
