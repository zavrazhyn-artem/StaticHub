<?php

namespace App\Services;

use App\Models\RaidEvent;
use App\Models\Character;
use App\Models\RaidAttendance;
use Illuminate\Support\Collection;

class RaidAttendanceService
{
    /**
     * Update attendance for a character at a raid event.
     */
    public function updateAttendance(RaidEvent $event, Character $character, string $status, ?string $comment = null): RaidAttendance
    {
        return RaidAttendance::updateOrCreate([
            'raid_event_id' => $event->id,
            'character_id' => $character->id,
        ], [
            'status' => $status,
            'comment' => $comment,
        ]);
    }

    /**
     * Get the roster grouped by combat roles, including pending status for non-RSVP'd members.
     */
    public function getGroupedRoster(RaidEvent $event): array
    {
        // 1. Fetch ALL users belonging to the event's static
        // We need their characters and character_static pivot data
        $staticMembers = $event->static->members()
            ->with(['characters' => function ($query) use ($event) {
                $query->whereHas('statics', function ($q) use ($event) {
                    $q->where('statics.id', $event->static_id);
                })->with(['statics' => function ($q) use ($event) {
                    $q->where('statics.id', $event->static_id);
                }]);
            }])
            ->get();

        // 2. Fetch all existing RaidAttendance records for this specific event
        // Make sure to load the character relationship if we need it
        $attendances = RaidAttendance::where('raid_event_id', $event->id)
            ->get()
            ->keyBy('character_id');

        $roster = new Collection();

        // 3. Loop through each User in the Static
        foreach ($staticMembers as $user) {
            $userAttendance = null;
            $selectedCharacter = null;

            // Check if any of user's characters has an attendance record for this event
            foreach ($user->characters as $char) {
                if ($attendances->has($char->id)) {
                    $userAttendance = $attendances->get($char->id);
                    $selectedCharacter = $char;
                    break;
                }
            }

            if ($userAttendance && $selectedCharacter) {
                // If YES (RSVP exists): Add their specifically chosen Character
                $status = $userAttendance->status;
                $comment = $userAttendance->comment;

                // Use the character object from the user's characters collection (which has static relation loaded)
                $combatRole = 'other';
                foreach ($selectedCharacter->statics as $static) {
                    if ($static->id == $event->static_id) {
                        $combatRole = $static->pivot->combat_role;
                        break;
                    }
                }
            } else {
                // If NO (No RSVP): Find this user's character where role === 'main' for this static
                $mainCharacter = $user->characters->first(function ($char) use ($event) {
                    foreach ($char->statics as $static) {
                        if ($static->id == $event->static_id && $static->pivot->role === 'main') {
                            return true;
                        }
                    }
                    return false;
                });

                if (!$mainCharacter) {
                    // Fallback to first character if no main found
                    $mainCharacter = $user->characters->first();
                }

                if ($mainCharacter) {
                    $selectedCharacter = $mainCharacter;
                    $status = 'pending';
                    $comment = null;

                    $combatRole = 'other';
                    foreach ($selectedCharacter->statics as $static) {
                        if ($static->id == $event->static_id) {
                            $combatRole = $static->pivot->combat_role;
                            break;
                        }
                    }
                }
            }

            if ($selectedCharacter) {
                // Attach attendance data for the view
                $selectedCharacter->setRelation('pivot', new RaidAttendance([
                    'status' => $status,
                    'comment' => $comment,
                    'character_id' => $selectedCharacter->id,
                    'raid_event_id' => $event->id,
                ]));

                $roster->push([
                    'character' => $selectedCharacter,
                    'combat_role' => $combatRole,
                    'status' => $status,
                ]);
            }
        }

        // 4. Split the roster into two distinct collections
        $mainRoster = [
            'tank' => new Collection(),
            'heal' => new Collection(),
            'mdps' => new Collection(),
            'rdps' => new Collection(),
        ];
        $absentRoster = new Collection();

        foreach ($roster as $item) {
            $status = $item['status'];
            $combatRole = $item['combat_role'];
            $character = $item['character'];

            if ($status === 'absent' || $status === 'tentative') {
                $absentRoster->push($character);
            } else {
                // present, pending, or late move to their role columns
                if (isset($mainRoster[$combatRole])) {
                    $mainRoster[$combatRole]->push($character);
                } else {
                    // Fallback to rdps if role is unknown/other
                    $mainRoster['rdps']->push($character);
                }
            }
        }

        return [
            'mainRoster' => $mainRoster,
            'absentRoster' => $absentRoster,
        ];
    }

    /**
     * Get attendees who are not present/late (absent, tentative).
     * @deprecated Use getGroupedRoster()['other'] instead.
     */
    public function getOtherAttendees(RaidEvent $event): Collection
    {
        return $event->characters()
            ->whereIn('raid_attendances.status', ['absent', 'tentative'])
            ->get();
    }
}
