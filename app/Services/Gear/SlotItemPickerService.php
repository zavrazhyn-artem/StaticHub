<?php

declare(strict_types=1);

namespace App\Services\Gear;

use App\Exceptions\GearListException;
use App\Models\GearList;
use App\Models\SeasonItem;
use App\Models\Specialization;

/**
 * Read service that builds the eligible-items payload for the slot picker
 * modal. Authorization happens upstream (controller verifies the list belongs
 * to a character the user owns).
 */
final class SlotItemPickerService
{
    /**
     * Returns the items + currently-eligible track/level dropdown options for
     * a (gearList, slot) pair. Filters by the list's character class and the
     * list's spec role.
     *
     * @return array{
     *   slot: string,
     *   class_name: string,
     *   spec_role: string,
     *   items: list<array<string,mixed>>,
     *   tracks: list<array{name:string, max:int, ilvls:array<int,int>, default_bonus_id:int}>
     * }
     */
    public function buildPayload(GearList $list, string $slot): array
    {
        $character = $list->character;
        $spec = $list->specialization
            ?? Specialization::query()->find($list->spec_id);

        $className = (string) ($character->playable_class ?? '');
        $role      = $this->roleLabel($spec?->role);

        $items = SeasonItem::query()
            ->forSeason(SeasonItem::CURRENT_SEASON_SLUG)
            ->forSlot($slot)
            ->forClassAndRole($className, $role)
            ->orderBy('name')
            ->get([
                'id', 'name', 'icon', 'inventory_type', 'armor_type', 'weapon_type',
                'role', 'stats', 'source_type', 'source_slug', 'encounter_slug',
                'encounter_id', 'boss_name', 'is_tier',
            ]);

        return [
            'slot'       => $slot,
            'class_name' => $className,
            'class_id'   => GearViewService::classId($className),
            'spec_id'    => $spec?->id,
            'spec_role'  => $role,
            'items'      => $items->map(fn (SeasonItem $i) => [
                'id'             => $i->id,
                'name'           => $i->name,
                'icon'           => $i->icon,
                'inventory_type' => $i->inventory_type,
                'armor_type'     => $i->armor_type,
                'weapon_type'    => $i->weapon_type,
                'role'           => $i->role,
                'stats'          => $i->stats,
                'source_type'    => $i->source_type,
                'source_slug'    => $i->source_slug,
                'source_label'   => SeasonItem::displaySource($i->source_slug),
                'boss_name'      => $i->boss_name,
                'encounter_id'   => $i->encounter_id,
                'is_tier'        => $i->is_tier,
            ])->all(),
            'tracks' => $this->trackOptions(),
        ];
    }

    /**
     * Group the upgrade-track config into per-track rows the modal can render
     * as two cascading dropdowns (track → level).
     *
     * @return list<array{name:string, max:int, ilvls:array<int,int>, default_bonus_id:int, level_to_bonus_id:array<int,int>}>
     */
    public function trackOptions(): array
    {
        $tracks = (array) config('wow_season.item_upgrade_tracks', []);
        $byTrack = [];
        foreach ($tracks as $bonusId => $info) {
            $name = (string) ($info['track'] ?? '');
            if ($name === '') continue;
            $byTrack[$name] ??= ['name' => $name, 'max' => 0, 'ilvls' => [], 'level_to_bonus_id' => []];
            $level = (int) ($info['level'] ?? 0);
            $byTrack[$name]['max'] = max($byTrack[$name]['max'], (int) ($info['max'] ?? 0));
            $byTrack[$name]['ilvls'][$level] = (int) ($info['ilvl'] ?? 0);
            $byTrack[$name]['level_to_bonus_id'][$level] = (int) $bonusId;
        }

        $order = ['Myth', 'Hero', 'Champion', 'Veteran', 'Adventurer', 'Explorer'];
        $result = [];
        foreach ($order as $name) {
            if (! isset($byTrack[$name])) continue;
            $t = $byTrack[$name];
            ksort($t['ilvls']);
            ksort($t['level_to_bonus_id']);
            // Default = top level of the track
            $defaultBonusId = $t['level_to_bonus_id'][$t['max']] ?? 0;
            $result[] = [
                'name'              => $t['name'],
                'max'               => $t['max'],
                'ilvls'             => $t['ilvls'],
                'level_to_bonus_id' => $t['level_to_bonus_id'],
                'default_bonus_id'  => $defaultBonusId,
            ];
        }
        return $result;
    }

    /**
     * Resolve a (track, level) pair to its (bonus_id, ilvl). Throws if either
     * arg is unknown.
     *
     * @return array{0:int, 1:int}
     */
    public function resolveTrackLevel(string $track, int $level): array
    {
        foreach ($this->trackOptions() as $opt) {
            if ($opt['name'] !== $track) continue;
            $bonusId = $opt['level_to_bonus_id'][$level] ?? null;
            $ilvl    = $opt['ilvls'][$level] ?? null;
            if ($bonusId === null || $ilvl === null) {
                throw GearListException::trackLevelOutOfRange($track, $level);
            }
            return [$bonusId, $ilvl];
        }
        throw GearListException::unknownTrack($track);
    }

    private function roleLabel(?string $rawRole): string
    {
        return match (strtolower((string) $rawRole)) {
            'tank'   => 'Tank',
            'healer' => 'Healer',
            default  => 'DPS',
        };
    }
}
