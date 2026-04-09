<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\CharacterStaticSpec;
use App\Models\Event;
use App\Models\Character;
use App\Models\RaidAttendance;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Support\Collection;

class RaidAttendanceService
{
    /**
     * Swap the character on an existing RSVP, or create a tentative one.
     */
    public function swapCharacter(int $eventId, int $userId, int $newCharacterId): void
    {
        $attendance = RaidAttendance::query()->forEventAndUser($eventId, $userId);

        if ($attendance) {
            $attendance->update(['character_id' => $newCharacterId]);
        } else {
            RaidAttendance::query()->createForEvent($eventId, $newCharacterId, 'tentative');
        }
    }

    /**
     * Swap the spec on an existing RSVP.
     */
    public function swapSpec(int $eventId, int $userId, int $specId): void
    {
        $attendance = RaidAttendance::query()->forEventAndUser($eventId, $userId);

        if ($attendance) {
            $attendance->update(['spec_id' => $specId]);
        }
    }

    /**
     * Update attendance for a character at a raid event.
     */
    public function updateAttendance(Event $event, Character $character, string $status, ?string $comment = null, ?int $specId = null): RaidAttendance
    {
        return RaidAttendance::updateOrCreate([
            'event_id' => $event->id,
            'character_id' => $character->id,
        ], [
            'status'  => $status,
            'comment' => $comment,
            'spec_id' => $specId,
        ]);
    }

    /**
     * Get the roster grouped by combat roles, including pending status for non-RSVP'd members.
     */
    public function getGroupedRoster(Event $event): array
    {
        $staticMembers = $this->fetchStaticMembers($event->static_id);
        $attendances = $this->fetchEventAttendances($event->id);

        $resolvedCharacters = new Collection();

        foreach ($staticMembers as $user) {
            $resolved = $this->resolveUserCharacterForEvent($user, $attendances, $event->static_id);

            if ($resolved) {
                /** @var Character $character */
                $character = $resolved['character'];

                $this->attachVirtualAttendance(
                    $character,
                    $event->id,
                    $resolved['status'],
                    $resolved['comment'],
                    $resolved['spec_id'],
                );

                $resolvedCharacters->push($character);
            }
        }

        return $this->categorizeRoster($resolvedCharacters, $event->static_id, $attendances);
    }

    /**
     * Fetch ALL users belonging to the static with their characters loaded.
     */
    private function fetchStaticMembers(int $staticId): Collection
    {
        return User::query()
            ->whereHas('statics', fn ($q) => $q->where('statics.id', $staticId))
            ->with(['characters' => function ($query) use ($staticId) {
                $query->whereHas('statics', fn ($q) => $q->where('statics.id', $staticId))
                    ->with(['statics' => fn ($q) => $q->where('statics.id', $staticId)]);
            }])
            ->get();
    }

    /**
     * Fetch all existing RaidAttendance records for this specific event.
     */
    private function fetchEventAttendances(int $eventId): Collection
    {
        return RaidAttendance::where('event_id', $eventId)
            ->get()
            ->keyBy('character_id');
    }

    /**
     * Determines which character to use for a user in an event.
     */
    private function resolveUserCharacterForEvent(User $user, Collection $attendances, int $staticId): ?array
    {
        $userAttendance = null;
        $selectedCharacter = null;

        // 1. Check if any of user's characters has an attendance record for this event
        foreach ($user->characters as $char) {
            if ($attendances->has($char->id)) {
                $userAttendance = $attendances->get($char->id);
                $selectedCharacter = $char;
                break;
            }
        }

        if ($userAttendance && $selectedCharacter) {
            return [
                'character' => $selectedCharacter,
                'status'    => $userAttendance->status,
                'comment'   => $userAttendance->comment,
                'spec_id'   => $userAttendance->spec_id,
            ];
        }

        // 2. Fallback: Find this user's main character from already-loaded relations
        $mainCharacter = $user->characters->first(
            fn ($char) => $char->statics->first()?->pivot->role === 'main'
        ) ?? $user->characters->first();

        if ($mainCharacter) {
            return [
                'character' => $mainCharacter,
                'status'    => 'pending',
                'comment'   => null,
                'spec_id'   => null,
            ];
        }

        return null;
    }

    /**
     * Isolates the presentation logic of attaching a temporary pivot.
     */
    private function attachVirtualAttendance(
        Character $character,
        int $eventId,
        string $status,
        ?string $comment,
        ?int $specId = null,
    ): void {
        $character->setRelation('pivot', new RaidAttendance([
            'status'        => $status,
            'comment'       => $comment,
            'spec_id'       => $specId,
            'character_id'  => $character->id,
            'event_id' => $eventId,
        ]));
    }

    /**
     * Sorts characters into role groups.
     * Uses the RSVP spec_id (from attendance) when available for role determination,
     * falling back to the character's main spec in the static.
     */
    private function categorizeRoster(
        Collection $resolvedCharacters,
        int $staticId,
        Collection $attendances,
    ): array {
        $mainRoster = [
            'tank' => new Collection(),
            'heal' => new Collection(),
            'mdps' => new Collection(),
            'rdps' => new Collection(),
        ];
        $absentRoster = new Collection();

        // Pre-load all RSVP specializations in one query to avoid N+1.
        $rsvpSpecIds = $attendances->pluck('spec_id')->filter()->unique();
        $rsvpSpecs   = $rsvpSpecIds->isNotEmpty()
            ? Specialization::whereIn('id', $rsvpSpecIds)->get()->keyBy('id')
            : collect();

        // Pre-load all main specs for resolved characters in one query.
        $characterIds = $resolvedCharacters->pluck('id');
        $mainSpecMap = CharacterStaticSpec::whereIn('character_id', $characterIds)
            ->where('static_id', $staticId)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        foreach ($resolvedCharacters as $character) {
            /** @var Character $character */
            $status = $character->pivot->status;

            // Prefer the spec chosen for this specific raid over the static main spec.
            $rsvpSpecId = $attendances->get($character->id)?->spec_id;
            $mainSpecRole = $mainSpecMap->get($character->id)?->specialization?->role ?? 'rdps';
            $combatRole = $rsvpSpecId
                ? ($rsvpSpecs->get($rsvpSpecId)?->role ?? $mainSpecRole)
                : $mainSpecRole;

            if ($status === 'absent' || $status === 'tentative') {
                $character->setAttribute('assigned_role', $combatRole);
                $absentRoster->push($character);
            } else {
                ($mainRoster[$combatRole] ?? $mainRoster['rdps'])->push($character);
            }
        }

        return [
            'mainRoster'   => $mainRoster,
            'absentRoster' => $absentRoster,
        ];
    }
}
