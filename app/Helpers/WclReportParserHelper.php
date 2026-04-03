<?php

declare(strict_types=1);

namespace App\Helpers;

class WclReportParserHelper
{
    public static function parseDeaths(array $deathsData): array
    {
        return array_map(fn($d) => [
            'player' => $d['name'] ?? 'Unknown',
            'fight_id' => $d['fight'] ?? null,
            'killing_blow' => $d['killingBlow']['name'] ?? 'Unknown Ability'
        ], $deathsData['entries'] ?? []);
    }

    public static function parseInterrupts(array $interruptEntries): array
    {
        return array_map(function ($int) {
            $interrupters = [];
            foreach ($int['details'] ?? [] as $detail) {
                $interrupters[$detail['name']] = $detail['total'];
            }
            return [
                'enemy_ability' => $int['name'] ?? 'Unknown',
                'total_interrupted' => $int['spellsInterrupted'] ?? 0,
                'total_missed' => $int['spellsCompleted'] ?? 0,
                'interrupted_by' => $interrupters,
            ];
        }, $interruptEntries);
    }

    public static function parseDamageTaken(array $damageEntries): array
    {
        $abilityDamageMap = [];

        foreach ($damageEntries as $playerData) {
            if (($playerData['type'] ?? '') === 'NPC') continue;
            $playerName = $playerData['name'] ?? 'Unknown';

            foreach ($playerData['abilities'] ?? [] as $ability) {
                $abilityName = $ability['name'] ?? 'Unknown';
                if (in_array($abilityName, ['Melee', 'Attack', 'Auto Attack', 'Stagger', 'Burning Rush'])) continue;

                if (!isset($abilityDamageMap[$abilityName])) {
                    $abilityDamageMap[$abilityName] = ['total_damage_to_raid' => 0, 'victims' => []];
                }
                $abilityDamageMap[$abilityName]['total_damage_to_raid'] += $ability['total'] ?? 0;
                $abilityDamageMap[$abilityName]['victims'][$playerName] = ($abilityDamageMap[$abilityName]['victims'][$playerName] ?? 0) + ($ability['total'] ?? 0);
            }
        }

        $cleanDamageTaken = [];
        foreach ($abilityDamageMap as $abilityName => $data) {
            arsort($data['victims']);
            $cleanDamageTaken[] = [
                'ability' => $abilityName,
                'total_damage_to_raid' => $data['total_damage_to_raid'],
                'biggest_victims' => array_slice($data['victims'], 0, 3, true)
            ];
        }

        usort($cleanDamageTaken, fn($a, $b) => $b['total_damage_to_raid'] <=> $a['total_damage_to_raid']);

        return $cleanDamageTaken;
    }

    public static function parseCastsAndConsumables(array $castEntries, array $rosterNames = []): array
    {
        $castsSummary = [];
        $cleanConsumables = [];
        $ignoredAbilities = ['Melee', 'Auto Attack', 'Shoot', 'Wand', 'Attack'];

        foreach ($castEntries as $abilityData) {
            $abilityName = $abilityData['name'] ?? 'Unknown';
            if (in_array($abilityName, $ignoredAbilities)) continue;

            $actors = $abilityData['entries'] ?? $abilityData['details'] ?? $abilityData['sources'] ?? [];

            foreach ($actors as $actor) {
                $playerName = $actor['name'] ?? 'Unknown';
                $totalCasts = $actor['total'] ?? 0;

                if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

                $isPotion = stripos($abilityName, 'Potion') !== false;
                $isHealthstone = $abilityName === 'Healthstone';

                if ($isPotion || $isHealthstone) {
                    if (!isset($cleanConsumables[$playerName])) {
                        $cleanConsumables[$playerName] = [];
                    }
                    $cleanConsumables[$playerName][$abilityName] = ($cleanConsumables[$playerName][$abilityName] ?? 0) + $totalCasts;
                }

                if (!isset($castsSummary[$playerName])) {
                    $castsSummary[$playerName] = [];
                }
                $castsSummary[$playerName][$abilityName] = ($castsSummary[$playerName][$abilityName] ?? 0) + $totalCasts;
            }
        }

        return [
            'casts' => $castsSummary,
            'consumables' => $cleanConsumables,
        ];
    }

    public static function calculatePerformanceMetrics(array $damageEntries, array $healingEntries, int $raidDuration, array $rosterNames = []): array
    {
        $performanceMetrics = [];

        // DPS
        $dpsList = [];
        foreach ($damageEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $dpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];
            $performanceMetrics[$entry['name']] = [
                'dps' => (int) round($entry['total'] / $raidDuration),
                'dps_rank' => 0,
                'percentile' => $entry['rankPercent'] ?? null
            ];
        }

        usort($dpsList, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($dpsList as $index => $item) {
            if (isset($performanceMetrics[$item['name']])) {
                $performanceMetrics[$item['name']]['dps_rank'] = $index + 1;
            }
        }

        // HPS
        $hpsList = [];
        foreach ($healingEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $hpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];
            if (!isset($performanceMetrics[$entry['name']])) {
                $performanceMetrics[$entry['name']] = [];
            }

            $performanceMetrics[$entry['name']]['hps'] = (int) round($entry['total'] / $raidDuration);
            $performanceMetrics[$entry['name']]['hps_rank'] = 0;
            if (!isset($performanceMetrics[$entry['name']]['percentile'])) {
                $performanceMetrics[$entry['name']]['percentile'] = $entry['rankPercent'] ?? null;
            }
        }

        usort($hpsList, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($hpsList as $index => $item) {
            if (isset($performanceMetrics[$item['name']])) {
                $performanceMetrics[$item['name']]['hps_rank'] = $index + 1;
            }
        }

        return $performanceMetrics;
    }

    public static function calculateRaidDuration(array $fights): int
    {
        $duration = 0;
        foreach ($fights as $fight) {
            $duration += ($fight['endTime'] ?? 0) - ($fight['startTime'] ?? 0);
        }
        return (int) max(1, $duration / 1000);
    }
}
