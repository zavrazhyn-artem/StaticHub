<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;

class RaidAttendanceBuilder extends Builder
{
    /**
     * Find attendance by event ID and user's character IDs.
     */
    public function forEventAndUser(int $eventId, int $userId): ?object
    {
        return $this->where('event_id', $eventId)
            ->whereIn('character_id', function ($query) use ($userId) {
                $query->select('id')->from('characters')->where('user_id', $userId);
            })
            ->first();
    }

    /**
     * Create attendance for an event.
     */
    public function createForEvent(int $eventId, int $characterId, string $status): object
    {
        return $this->create([
            'event_id' => $eventId,
            'character_id' => $characterId,
            'status' => $status,
        ]);
    }

    /**
     * Scope: for a specific event.
     */
    public function forEvent(int $eventId): self
    {
        return $this->where('event_id', $eventId);
    }
}
