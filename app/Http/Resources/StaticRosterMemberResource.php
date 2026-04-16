<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaticRosterMemberResource extends JsonResource
{
    private int $staticId;

    /** Optional snapshot weekly_data keyed by character_id, injected by the controller. */
    private ?array $weeklyOverrides = null;

    public function setStaticId(int $id): static
    {
        $this->staticId = $id;
        return $this;
    }

    public function setWeeklyOverrides(?array $overrides): static
    {
        $this->weeklyOverrides = $overrides;
        return $this;
    }

    public function toArray(Request $request): array
    {
        $characters = $this->characters->filter(
            fn (Character $c) => $c->statics->isNotEmpty()
        );

        $mainCharacter = $characters->first(
            fn (Character $c) => $c->statics->first()?->pivot?->role === 'main'
        );

        $altCharacters = $characters->filter(
            fn (Character $c) => $c->statics->first()?->pivot?->role === 'alt'
        );

        return [
            'id'             => $this->id,
            'name'           => $this->getDisplayName($this->staticId),
            'access_role'    => $this->pivot->access_role  ?? 'member',
            'roster_status'  => $this->pivot->roster_status ?? 'bench',
            'main_character' => $mainCharacter ? $this->formatCharacter($mainCharacter) : null,
            'alts'           => $altCharacters
                ->map(fn (Character $alt) => $this->formatCharacter($alt))
                ->values()
                ->toArray(),
        ];
    }

    /**
     * Merges character_data + character_weekly_data with identity fields.
     * When weekly overrides are set (snapshot view), uses snapshot data instead of live weekly.
     */
    private function formatCharacter(Character $character): array
    {
        $charData   = $character->character_data ?? [];
        $weeklyData = $this->weeklyOverrides[$character->id] ?? $character->character_weekly_data ?? [];

        $mainSpec     = $character->getAttribute('main_spec');
        $mainSpecName = $mainSpec['name'] ?? null;

        // Overlay main spec gear as top-level defaults
        if ($mainSpecName !== null) {
            $charData = $this->overlayMainSpecGear($charData, $mainSpecName);
        }

        // Build available gear specs list for frontend switcher
        $equipmentBySpec = $charData['equipment_by_spec'] ?? [];
        $availableGearSpecs = array_keys($equipmentBySpec);

        return array_merge(
            $charData,
            $weeklyData,
            [
                'id'                  => $character->id,
                'name'                => $character->name,
                'main_spec'           => $mainSpec,
                'all_specs'           => $character->getAttribute('all_specs') ?? [],
                'available_gear_specs' => $availableGearSpecs,
            ]
        );
    }

    private function overlayMainSpecGear(array $charData, string $specName): array
    {
        $equipmentBySpec = $charData['equipment_by_spec'] ?? [];
        $auditBySpec     = $charData['gear_audit_by_spec'] ?? [];
        $ilvlBySpec      = $charData['ilvl_by_spec'] ?? [];

        if (!isset($equipmentBySpec[$specName])) {
            return $charData;
        }

        $charData['equipment']    = $equipmentBySpec[$specName];
        $charData['equipped_ilvl'] = $ilvlBySpec[$specName] ?? $charData['equipped_ilvl'] ?? null;

        $specAudit = $auditBySpec[$specName] ?? [];
        $auditKeys = [
            'missing_enchants_slots',
            'low_quality_enchants_slots',
            'empty_sockets_count',
            'upgrades_missing',
            'sparks_equipped',
            'tier_pieces',
            'tier_ilvls',
            'embellished_items',
            'spark_gear',
        ];

        foreach ($auditKeys as $key) {
            if (array_key_exists($key, $specAudit)) {
                $charData[$key] = $specAudit[$key];
            }
        }

        return $charData;
    }
}
