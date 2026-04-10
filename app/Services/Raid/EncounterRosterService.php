<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\Character;
use App\Models\CharacterStaticSpec;
use App\Models\Event;
use App\Models\EventEncounterRoster;
use App\Models\RaidAttendance;
use App\Models\Specialization;
use Illuminate\Support\Collection;

class EncounterRosterService
{
    /**
     * Get per-encounter roster for an event, grouped by encounter slug.
     */
    public function getEncounterRosters(Event $event): array
    {
        $rosters = EventEncounterRoster::query()
            ->forEventGroupedByEncounter($event->id);

        return $rosters->groupBy('encounter_slug')
            ->map(fn (Collection $group) => [
                'selected' => $group->where('selection_status', 'selected')
                    ->sortBy('position_order')->values(),
                'queued' => $group->where('selection_status', 'queued')
                    ->sortBy('position_order')->values(),
                'benched' => $group->where('selection_status', 'benched')
                    ->sortBy('position_order')->values(),
            ])
            ->toArray();
    }

    /**
     * Assign a character to an encounter (selected/queued/benched).
     */
    public function assignCharacter(
        int $eventId,
        string $encounterSlug,
        int $characterId,
        string $status = 'selected',
        int $order = 0,
    ): EventEncounterRoster {
        return EventEncounterRoster::query()
            ->upsertAssignment($eventId, $encounterSlug, $characterId, $status, $order);
    }

    /**
     * Remove a character from an encounter roster.
     */
    public function removeCharacter(int $eventId, string $encounterSlug, int $characterId): void
    {
        EventEncounterRoster::query()
            ->removeAssignment($eventId, $encounterSlug, $characterId);
    }

    /**
     * Bulk update encounter roster from frontend payload.
     * Expects: [{ encounter_slug, character_id, selection_status, position_order }]
     */
    public function bulkUpdateEncounterRoster(Event $event, array $assignments): void
    {
        EventEncounterRoster::where('event_id', $event->id)->delete();

        $records = array_map(fn (array $a) => [
            'event_id' => $event->id,
            'encounter_slug' => $a['encounter_slug'],
            'character_id' => $a['character_id'],
            'selection_status' => $a['selection_status'] ?? 'selected',
            'position_order' => $a['position_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $assignments);

        if (!empty($records)) {
            EventEncounterRoster::insert($records);
        }
    }

    /**
     * Calculate planning stats for characters in a static.
     * Returns selection percentage and count per character.
     */
    public function calculatePlanningStats(int $staticId, ?int $beforeEventId = null): Collection
    {
        $query = Event::query()->forStatic($staticId)
            ->where('start_time', '<', now());

        if ($beforeEventId) {
            $query->where('id', '<', $beforeEventId);
        }

        $pastEventIds = $query->pluck('id');

        if ($pastEventIds->isEmpty()) {
            return collect();
        }

        $totalEvents = $pastEventIds->count();

        $attendanceCounts = RaidAttendance::whereIn('event_id', $pastEventIds)
            ->whereIn('status', ['present', 'late'])
            ->groupBy('character_id')
            ->selectRaw('character_id, COUNT(*) as selected_count')
            ->pluck('selected_count', 'character_id');

        return $attendanceCounts->map(fn (int $count) => [
            'selected_count' => $count,
            'total_events' => $totalEvents,
            'percentage' => round(($count / $totalEvents) * 100, 1),
        ]);
    }

    /**
     * Build the full encounter roster payload for the event show page.
     */
    public function buildEncounterRosterPayload(Event $event): array
    {
        $rosters = $this->getEncounterRosters($event);
        $encounters = $this->getOrderedEncounters($event);

        // Pre-load specs for all characters in encounter rosters
        $allCharacterIds = EventEncounterRoster::query()
            ->forEvent($event->id)
            ->pluck('character_id')
            ->unique();

        $mainSpecMap = CharacterStaticSpec::whereIn('character_id', $allCharacterIds)
            ->where('static_id', $event->static_id)
            ->where('is_main', true)
            ->with('specialization')
            ->get()
            ->keyBy('character_id');

        // Enhance roster entries with spec/role info
        foreach ($rosters as $slug => &$groups) {
            foreach (['selected', 'queued', 'benched'] as $groupKey) {
                $groups[$groupKey] = $groups[$groupKey]->map(function ($entry) use ($mainSpecMap) {
                    $character = $entry->character;
                    $spec = $mainSpecMap->get($character->id)?->specialization;

                    return [
                        'id' => $entry->id,
                        'character_id' => $character->id,
                        'character_name' => $character->name,
                        'class_name' => $character->playable_class,
                        'class_icon_url' => $character->getClassIconUrl(),
                        'selection_status' => $entry->selection_status,
                        'position_order' => $entry->position_order,
                        'spec' => $spec ? [
                            'id' => $spec->id,
                            'name' => $spec->name,
                            'role' => $spec->role,
                            'icon_url' => $spec->icon_url,
                        ] : null,
                        'role' => $spec?->role ?? 'rdps',
                    ];
                })->values()->toArray();
            }
        }

        return [
            'encounters' => $encounters,
            'encounterRosters' => $rosters,
        ];
    }

    /**
     * Get ordered encounter list for an event, respecting custom order.
     */
    private function getOrderedEncounters(Event $event): array
    {
        $raidInstances = config('wow_season.current_raid_instances', []);
        $encounterBosses = config('wow_season.encounter_bosses', []);
        $allBosses = [];

        foreach ($raidInstances as $instanceName => $bosses) {
            foreach ($bosses as $bossName) {
                $slug = \Illuminate\Support\Str::slug($bossName);
                $bossData = $encounterBosses[$slug] ?? [];
                $portraits = array_map(
                    fn (int $id) => "/images/raidplan/portraits/{$id}.png",
                    $bossData['portraits'] ?? []
                );

                $allBosses[] = [
                    'slug' => $slug,
                    'name' => $bossName,
                    'instance' => $instanceName,
                    'portrait' => $portraits[0] ?? null,
                ];
            }
        }

        if (!empty($event->encounter_order)) {
            $orderMap = array_flip($event->encounter_order);
            usort($allBosses, function ($a, $b) use ($orderMap) {
                $posA = $orderMap[$a['slug']] ?? 999;
                $posB = $orderMap[$b['slug']] ?? 999;
                return $posA <=> $posB;
            });
        }

        return $allBosses;
    }
}
