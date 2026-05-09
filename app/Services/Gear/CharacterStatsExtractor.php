<?php

declare(strict_types=1);

namespace App\Services\Gear;

/**
 * Normalizes Blizzard's /character/statistics + /character profile payload
 * into a flat DTO ready for the Gear-tab stats panel.
 *
 * Returns null when statistics data is missing — the panel will show a
 * "stats not available" placeholder.
 */
final class CharacterStatsExtractor
{
    /**
     * @param ?array $statistics — services_raw_data.bnet_statistics
     * @param ?array $profile    — services_raw_data.bnet_profile (for ilvl)
     * @param ?string $role      — Specialization.role (tank|heal|mdps|rdps)
     * @return null|array{
     *   item_level:int,
     *   attributes:array<int,array{label:string,value:int,is_main?:bool}>,
     *   enhancements:array<int,array{label:string,value:float,unit:string}>
     * }
     */
    public function extract(?array $statistics, ?array $profile, ?string $role): ?array
    {
        if (! is_array($statistics) || empty($statistics)) {
            return null;
        }

        $itemLevel = (int) ($profile['equipped_item_level'] ?? $profile['average_item_level'] ?? 0);

        return [
            'item_level'   => $itemLevel,
            'attributes'   => $this->extractAttributes($statistics),
            'enhancements' => $this->extractEnhancements($statistics, $role),
        ];
    }

    private function extractAttributes(array $s): array
    {
        $strength = (int) ($s['strength']['effective'] ?? 0);
        $agility  = (int) ($s['agility']['effective'] ?? 0);
        $intellect = (int) ($s['intellect']['effective'] ?? 0);

        // Main stat = whichever has the highest effective value. Works for every
        // spec without needing class→stat mapping.
        $candidates = [
            ['Strength', $strength],
            ['Agility', $agility],
            ['Intellect', $intellect],
        ];
        usort($candidates, fn ($a, $b) => $b[1] <=> $a[1]);
        [$mainLabel, $mainValue] = $candidates[0];

        return [
            ['label' => $mainLabel, 'value' => $mainValue, 'is_main' => true],
            ['label' => 'Stamina', 'value' => (int) ($s['stamina']['effective'] ?? 0)],
            ['label' => 'Armor', 'value' => (int) ($s['armor']['effective'] ?? 0)],
        ];
    }

    private function extractEnhancements(array $s, ?string $role): array
    {
        $rows = [
            ['label' => 'Critical Strike', 'value' => $this->pick($s, 'melee_crit.value'), 'unit' => '%'],
            ['label' => 'Haste',           'value' => $this->pick($s, 'melee_haste.value'), 'unit' => '%'],
            ['label' => 'Mastery',         'value' => $this->pick($s, 'mastery.value'), 'unit' => '%'],
            ['label' => 'Versatility',     'value' => $this->pick($s, 'versatility_damage_done_bonus'), 'unit' => '%'],
            ['label' => 'Leech',           'value' => $this->pick($s, 'lifesteal.value'), 'unit' => '%'],
        ];

        if ($role === 'tank') {
            $rows[] = ['label' => 'Avoidance', 'value' => $this->pickPercent($s, 'avoidance'), 'unit' => '%'];
            $rows[] = ['label' => 'Dodge',     'value' => $this->pickPercent($s, 'dodge'), 'unit' => '%'];

            $parry = $this->pickPercent($s, 'parry');
            if ($parry > 0) {
                $rows[] = ['label' => 'Parry', 'value' => $parry, 'unit' => '%'];
            }

            $block = $this->pickPercent($s, 'block');
            if ($block > 0) {
                $rows[] = ['label' => 'Block', 'value' => $block, 'unit' => '%'];
            }
        }

        return array_map(fn ($r) => [
            'label' => $r['label'],
            'value' => round((float) $r['value'], 2),
            'unit'  => $r['unit'],
        ], $rows);
    }

    private function pick(array $s, string $path): float
    {
        $value = $s;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return 0.0;
            }
            $value = $value[$key];
        }
        return (float) $value;
    }

    /**
     * Some Blizzard stat blocks (notably `avoidance`, `parry` for non-blockers)
     * leave `.value` empty and only populate `.rating_bonus`. Fall through.
     */
    private function pickPercent(array $s, string $key): float
    {
        $block = $s[$key] ?? null;
        if (! is_array($block)) {
            return 0.0;
        }
        return (float) ($block['value'] ?? $block['rating_bonus'] ?? 0);
    }
}
