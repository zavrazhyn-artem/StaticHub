<?php

namespace App\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EventEncounterRosterBuilder extends Builder
{
    public function forEvent(int $eventId): self
    {
        return $this->where('event_id', $eventId);
    }

    public function forEncounter(string $encounterSlug): self
    {
        return $this->where('encounter_slug', $encounterSlug);
    }

    public function selected(): self
    {
        return $this->where('selection_status', 'selected');
    }

    public function queued(): self
    {
        return $this->where('selection_status', 'queued');
    }

    public function benched(): self
    {
        return $this->where('selection_status', 'benched');
    }

    public function forEventGroupedByEncounter(int $eventId): Collection
    {
        return $this->where('event_id', $eventId)
            ->with('character')
            ->orderBy('position_order')
            ->get();
    }

    public function upsertAssignment(int $eventId, string $encounterSlug, int $characterId, string $status, int $order = 0): \App\Models\EventEncounterRoster
    {
        return $this->getModel()::updateOrCreate(
            [
                'event_id' => $eventId,
                'encounter_slug' => $encounterSlug,
                'character_id' => $characterId,
            ],
            [
                'selection_status' => $status,
                'position_order' => $order,
            ]
        );
    }

    public function removeAssignment(int $eventId, string $encounterSlug, int $characterId): int
    {
        return $this->where('event_id', $eventId)
            ->where('encounter_slug', $encounterSlug)
            ->where('character_id', $characterId)
            ->delete();
    }

    /**
     * Character IDs whose every encounter row for this event is benched
     * (i.e. benched across all bosses — no selected/queued rows exist).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function fullyBenchedCharacterIds(int $eventId): \Illuminate\Support\Collection
    {
        return $this->getModel()::query()
            ->where('event_id', $eventId)
            ->where('selection_status', 'benched')
            ->whereNotExists(function ($q) use ($eventId) {
                $q->from('event_encounter_rosters as sub')
                    ->whereColumn('sub.character_id', 'event_encounter_rosters.character_id')
                    ->where('sub.event_id', $eventId)
                    ->where('sub.selection_status', '!=', 'benched');
            })
            ->pluck('character_id')
            ->unique()
            ->values();
    }
}
