<?php

declare(strict_types=1);

namespace App\Helpers;

class WclReportParserHelper
{
    /**
     * Parse deaths, filter out wipe deaths, and group by boss/try.
     *
     * @param array $phaseSummary  Boss → [ fight_id, outcome, ... ] from buildPhaseSummary()
     * @param int   $raidSize      Number of roster players (for wipe detection threshold)
     */
    public static function parseDeaths(
        array $deathsData,
        array $rosterNames = [],
        array $fightStartTimes = [],
        array $phaseSummary = [],
        int   $raidSize = 20
    ): array {
        $entries = $deathsData['entries'] ?? [];

        if (!empty($rosterNames)) {
            $entries = array_filter($entries, fn($d) => in_array($d['name'] ?? '', $rosterNames));
        }

        // Parse all deaths with relative timestamps
        $allDeaths = array_values(array_map(function ($d) use ($fightStartTimes) {
            $fightId      = $d['fight'] ?? null;
            $timestampMs  = $d['timestamp'] ?? null;
            $fightStartMs = $fightId ? ($fightStartTimes[$fightId] ?? null) : null;
            $relativeMs   = ($timestampMs !== null && $fightStartMs !== null)
                ? $timestampMs - $fightStartMs
                : $timestampMs;

            return [
                'player'          => $d['name'] ?? 'Unknown',
                'fight_id'        => $fightId,
                'killing_blow'    => $d['killingBlow']['name'] ?? 'Unknown Ability',
                'time_into_fight' => self::msToFightTime($relativeMs),
                '_relative_ms'    => $relativeMs,
            ];
        }, $entries));

        // Group deaths by fight_id
        $byFight = [];
        foreach ($allDeaths as $death) {
            $byFight[$death['fight_id']][] = $death;
        }

        // Build fight_id → boss name lookup from phase_summary
        $fightBossMap = [];
        $fightTryMap  = [];
        foreach ($phaseSummary as $bossName => $tries) {
            $tryNum = 0;
            foreach ($tries as $try) {
                $tryNum++;
                $fightBossMap[$try['fight_id']] = $bossName;
                $fightTryMap[$try['fight_id']]  = $tryNum;
            }
        }

        // Build fight outcome lookup from phase_summary
        $fightOutcomes = [];
        foreach ($phaseSummary as $tries) {
            foreach ($tries as $try) {
                $fightOutcomes[$try['fight_id']] = $try['outcome'] ?? 'wipe';
            }
        }

        // Filter wipe cascade deaths.
        // Deaths that occur while < 60% of the raid is dead = individual mistakes (keep).
        // Deaths after 60% of the raid is already dead = wipe cascade (discard).
        $cascadeThreshold = (int) ceil($raidSize * 0.6);
        $individualDeaths = [];

        foreach ($byFight as $fightId => $fightDeaths) {
            usort($fightDeaths, fn($a, $b) => ($a['_relative_ms'] ?? 0) <=> ($b['_relative_ms'] ?? 0));

            $deadCount = 0;
            foreach ($fightDeaths as $death) {
                $deadCount++;
                if ($deadCount > $cascadeThreshold) {
                    break; // 60%+ dead — rest is wipe cascade
                }
                $individualDeaths[] = $death + ['fight_id' => $fightId];
            }
        }

        // Group by boss → try_N, strip internal fields
        $grouped = [];
        foreach ($individualDeaths as $death) {
            $fightId  = $death['fight_id'];
            $bossName = $fightBossMap[$fightId] ?? 'Unknown';
            $tryNum   = $fightTryMap[$fightId] ?? 1;
            $tryKey   = "try_{$tryNum}";

            unset($death['_relative_ms']);
            unset($death['fight_id']);

            $grouped[$bossName][$tryKey][] = $death;
        }

        return $grouped;
    }

    public static function parseInterrupts(array $interruptEntries, array $rosterNames = []): array
    {
        return array_map(function ($int) use ($rosterNames) {
            $interrupters = [];
            foreach ($int['details'] ?? [] as $detail) {
                if (!empty($rosterNames) && !in_array($detail['name'], $rosterNames)) continue;
                $interrupters[$detail['name']] = $detail['total'];
            }
            return [
                'enemy_ability'     => $int['name'] ?? 'Unknown',
                'total_interrupted' => $int['spellsInterrupted'] ?? 0,
                'total_missed'      => $int['spellsCompleted'] ?? 0,
                'interrupted_by'    => $interrupters,
            ];
        }, $interruptEntries);
    }

    public static function parseDamageTaken(array $damageEntries, array $rosterNames = []): array
    {
        $abilityDamageMap = [];

        foreach ($damageEntries as $playerData) {
            if (($playerData['type'] ?? '') === 'NPC') continue;
            $playerName = $playerData['name'] ?? 'Unknown';

            if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

            foreach ($playerData['abilities'] ?? [] as $ability) {
                $abilityName = $ability['name'] ?? 'Unknown';
                if (in_array($abilityName, ['Melee', 'Attack', 'Auto Attack', 'Stagger', 'Burning Rush'])) continue;

                if (!isset($abilityDamageMap[$abilityName])) {
                    $abilityDamageMap[$abilityName] = ['total_damage_to_raid' => 0, 'victims' => []];
                }
                $abilityDamageMap[$abilityName]['total_damage_to_raid'] += $ability['total'] ?? 0;
                $abilityDamageMap[$abilityName]['victims'][$playerName] =
                    ($abilityDamageMap[$abilityName]['victims'][$playerName] ?? 0) + ($ability['total'] ?? 0);
            }
        }

        $cleanDamageTaken = [];
        foreach ($abilityDamageMap as $abilityName => $data) {
            arsort($data['victims']);
            $cleanDamageTaken[] = [
                'ability'             => $abilityName,
                'total_damage_to_raid' => $data['total_damage_to_raid'],
                'biggest_victims'     => array_slice($data['victims'], 0, 3, true),
            ];
        }

        usort($cleanDamageTaken, fn($a, $b) => $b['total_damage_to_raid'] <=> $a['total_damage_to_raid']);

        return $cleanDamageTaken;
    }

    public static function parseCastsAndConsumables(array $castEntries, array $rosterNames = []): array
    {
        $castsSummary     = [];
        $cleanConsumables = [];
        $ignoredAbilities = ['Melee', 'Auto Attack', 'Shoot', 'Wand', 'Attack'];

        foreach ($castEntries as $abilityData) {
            $abilityName = $abilityData['name'] ?? 'Unknown';
            if (in_array($abilityName, $ignoredAbilities)) continue;

            $isConsumable = self::isConsumableAbility($abilityName, $abilityData['abilityIcon'] ?? '');

            // WCL viewBy: Ability returns player data in subentries (complete) or sources (top 5 only)
            $actors       = $abilityData['subentries'] ?? $abilityData['entries'] ?? $abilityData['details'] ?? $abilityData['sources'] ?? [];
            $useActorName = isset($abilityData['subentries']);

            foreach ($actors as $actor) {
                $playerName = $useActorName ? ($actor['actorName'] ?? $actor['name'] ?? 'Unknown') : ($actor['name'] ?? 'Unknown');
                $totalCasts = $actor['total'] ?? 0;

                if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

                if ($isConsumable) {
                    $cleanConsumables[$playerName][$abilityName] =
                        ($cleanConsumables[$playerName][$abilityName] ?? 0) + $totalCasts;
                }

                $castsSummary[$playerName][$abilityName] =
                    ($castsSummary[$playerName][$abilityName] ?? 0) + $totalCasts;
            }
        }

        return [
            'casts'       => $castsSummary,
            'consumables' => $cleanConsumables,
        ];
    }

    /**
     * Detect consumable abilities by icon pattern and name keywords.
     * Alchemy items use inv_*alchemy*|inv_potion* icons; Healthstones use warlock_-healthstone/bloodstone.
     */
    private static function isConsumableAbility(string $name, string $icon): bool
    {
        $iconLower = strtolower($icon);
        if (str_contains($iconLower, 'alchemy') || str_contains($iconLower, 'potion') || str_contains($iconLower, 'healthstone') || str_contains($iconLower, 'bloodstone')) {
            return true;
        }

        return stripos($name, 'Potion') !== false || stripos($name, 'Healthstone') !== false;
    }

    public static function calculatePerformanceMetrics(
        array $damageEntries,
        array $healingEntries,
        int   $raidDuration,
        array $rosterNames = []
    ): array {
        $performanceMetrics = [];

        $dpsList = [];
        foreach ($damageEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $dpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];
            $performanceMetrics[$entry['name']] = [
                'dps'        => (int) round($entry['total'] / $raidDuration),
                'dps_rank'   => 0,
                'ilvl'       => $entry['itemLevel'] ?? null,
                'percentile' => $entry['rankPercent'] ?? null,
            ];
        }

        usort($dpsList, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
        foreach ($dpsList as $index => $item) {
            if (isset($performanceMetrics[$item['name']])) {
                $performanceMetrics[$item['name']]['dps_rank'] = $index + 1;
            }
        }

        $hpsList = [];
        foreach ($healingEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($entry['name'], $rosterNames)) continue;

            $hpsList[] = ['name' => $entry['name'], 'total' => $entry['total']];

            if (!isset($performanceMetrics[$entry['name']])) {
                $performanceMetrics[$entry['name']] = [];
            }

            $total    = $entry['total'] ?? 0;
            $overheal = $entry['overheal'] ?? 0;
            $performanceMetrics[$entry['name']]['hps']              = (int) round($total / $raidDuration);
            $performanceMetrics[$entry['name']]['hps_rank']         = 0;
            $performanceMetrics[$entry['name']]['overheal_pct']     = $total > 0
                ? round($overheal / ($total + $overheal) * 100, 1)
                : null;

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

    public static function parseDispels(array $dispelEntries, array $rosterNames = []): array
    {
        $playerDispels = [];

        foreach ($dispelEntries as $spellData) {
            foreach ($spellData['details'] ?? [] as $detail) {
                $playerName = $detail['name'] ?? 'Unknown';

                if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

                $playerDispels[$playerName] = ($playerDispels[$playerName] ?? 0) + ($detail['total'] ?? 0);
            }
        }

        arsort($playerDispels);

        return $playerDispels;
    }

    public static function calculateRaidDuration(array $fights): int
    {
        $duration = 0;
        foreach ($fights as $fight) {
            $duration += ($fight['endTime'] ?? 0) - ($fight['startTime'] ?? 0);
        }
        return (int) max(1, $duration / 1000);
    }

    /**
     * Parse playerDetails response into a compact per-player map.
     * Actual API path: $raw['data']['playerDetails'] → { dps: [...], tanks: [...], healers: [...] }
     * Each player has: name, type, specs, minItemLevel, maxItemLevel, potionUse, combatantInfo
     * combatantInfo has: stats, talentTree, gear, specIDs, factionID
     */
    public static function parsePlayerDetails(mixed $raw, array $rosterNames = []): array
    {
        // Unwrap nested path: raw → data.playerDetails → { dps, tanks, healers }
        $byRole = null;
        if (isset($raw['data']['playerDetails'])) {
            $byRole = $raw['data']['playerDetails'];
        } elseif (isset($raw['playerDetails'])) {
            $byRole = $raw['playerDetails'];
        } elseif (is_array($raw) && isset($raw['dps'])) {
            $byRole = $raw;
        }

        if (!is_array($byRole)) {
            return [];
        }

        $result = [];

        foreach (['dps', 'healers', 'tanks'] as $role) {
            foreach ($byRole[$role] ?? [] as $player) {
                $name = $player['name'] ?? null;
                if (!$name) continue;
                if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

                $combatantInfo = $player['combatantInfo'] ?? [];
                $gear          = $combatantInfo['gear'] ?? [];

                // Trinkets are at slot index 12 and 13
                $trinkets = [];
                foreach ($gear as $item) {
                    if (in_array($item['slot'] ?? -1, [12, 13]) && isset($item['name'])) {
                        $trinkets[] = ['name' => $item['name'], 'ilvl' => $item['itemLevel'] ?? null];
                    }
                }

                // Average item level from gear slots (exclude shirt=3, tabard=17, empty/cosmetic ilvl ≤ 1)
                $itemLevels = array_filter(
                    array_map(fn($i) => !in_array($i['slot'] ?? -1, [3, 17]) ? ($i['itemLevel'] ?? null) : null, $gear),
                    fn($ilvl) => $ilvl !== null && $ilvl > 1
                );
                $avgIlvl = !empty($itemLevels)
                    ? round(array_sum($itemLevels) / count($itemLevels), 1)
                    : ($player['maxItemLevel'] ?? null);

                // Stats summary (Crit, Haste, Mastery, Vers)
                $stats = [];
                foreach (['Crit', 'Haste', 'Mastery', 'Versatility'] as $stat) {
                    if (isset($combatantInfo['stats'][$stat]['min'])) {
                        $stats[$stat] = $combatantInfo['stats'][$stat]['min'];
                    }
                }

                // Spec name from specs array: [{ spec: "Havoc", count: 1 }]
                $specName = $player['specs'][0]['spec'] ?? null;

                $result[$name] = [
                    'role'        => $role,
                    'class'       => $player['type'] ?? null,
                    'spec'        => $specName,
                    'avg_ilvl'    => $avgIlvl,
                    'trinkets'    => $trinkets,
                    'stats'       => $stats,
                    'spec_ids'    => $combatantInfo['specIDs'] ?? [],
                ];
            }
        }

        return $result;
    }

    /**
     * Parse rankings response into per-player parse percentiles.
     * Returns: [ playerName => [ 'parse_pct' => float, 'today_pct' => float, 'role' => string, 'spec' => string ] ]
     */
    public static function parseRankings(mixed $rankingsRaw, array $rosterNames = []): array
    {
        if (!is_array($rankingsRaw)) {
            return [];
        }

        $result = [];
        $fights = is_array($rankingsRaw['data'] ?? null) ? $rankingsRaw['data'] : [$rankingsRaw];

        foreach ($fights as $fight) {
            $roles = $fight['roles'] ?? [];
            foreach (['tanks', 'healers', 'dps'] as $role) {
                foreach ($roles[$role]['characters'] ?? [] as $char) {
                    $name = $char['name'] ?? null;
                    if (!$name) continue;
                    if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

                    // Keep best parse across multiple fights
                    $existing = $result[$name]['parse_pct'] ?? 0;
                    $current  = $char['rankPercent'] ?? $char['todayPercent'] ?? 0;
                    if ($current > $existing) {
                        $result[$name] = [
                            'role'      => $role,
                            'spec'      => $char['spec'] ?? null,
                            'parse_pct' => round((float)($char['rankPercent'] ?? 0), 1),
                            'today_pct' => round((float)($char['todayPercent'] ?? 0), 1),
                        ];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Parse Buffs table into top raid-wide aura uptime percentages.
     * WCL Buffs table returns aggregate auras (not per-player) with structure:
     *   data.{ auras: [{ name, totalUptime, totalUses, bands }], totalTime }
     * Returns top auras sorted by uptime %, filtered to ≥5% uptime.
     */
    public static function parseBuffUptime(array $buffsData, int $totalDurationMs, array $rosterNames = []): array
    {
        $totalTime = $buffsData['totalTime'] ?? $totalDurationMs;
        if ($totalTime <= 0) {
            return [];
        }

        $result = [];
        foreach ($buffsData['auras'] ?? [] as $aura) {
            $uptime   = $aura['totalUptime'] ?? 0;
            $uptimePct = round($uptime / $totalTime * 100, 1);
            if ($uptimePct < 5 || $uptimePct >= 98) continue;

            $result[$aura['name']] = [
                'uptime_pct'  => $uptimePct,
                'total_uses'  => $aura['totalUses'] ?? 0,
            ];
        }

        // Sort by uptime descending
        uasort($result, fn($a, $b) => $b['uptime_pct'] <=> $a['uptime_pct']);

        return $result;
    }

    /**
     * Extract consumable buffs (flasks, food, augment runes) from aggregate Buffs data.
     * Returns: [ buffName => [ 'uptime_pct' => float, 'avg_players_per_fight' => float ] ]
     */
    public static function parseConsumableBuffs(array $buffsData, int $totalDurationMs, int $fightCount): array
    {
        $totalTime = $buffsData['totalTime'] ?? $totalDurationMs;
        if ($totalTime <= 0 || $fightCount <= 0) {
            return [];
        }

        $consumableKeywords = ['Flask', 'Phial', 'Well Fed', 'Hearty', 'Augmentation', 'Vantus Rune'];
        $result = [];

        foreach ($buffsData['auras'] ?? [] as $aura) {
            $name = $aura['name'] ?? '';
            $isConsumable = false;
            foreach ($consumableKeywords as $keyword) {
                if (stripos($name, $keyword) !== false) {
                    $isConsumable = true;
                    break;
                }
            }
            if (!$isConsumable) continue;

            $uptime   = $aura['totalUptime'] ?? 0;
            $uses     = $aura['totalUses'] ?? 0;

            $result[$name] = [
                'uptime_pct'           => round($uptime / $totalTime * 100, 1),
                'avg_players_per_fight' => round($uses / $fightCount, 1),
            ];
        }

        uasort($result, fn($a, $b) => $b['uptime_pct'] <=> $a['uptime_pct']);

        return $result;
    }

    /**
     * Parse Debuffs table into per-debuff application counts and uptime.
     * Returns: [ debuffName => [ 'total_uptime_pct' => float, 'applied_by' => [ playerName => count ] ] ]
     */
    public static function parseDebuffUptime(array $debuffsData, int $totalDurationMs, array $rosterNames = []): array
    {
        if ($totalDurationMs <= 0) {
            return [];
        }

        $result = [];

        foreach ($debuffsData['auras'] ?? $debuffsData['entries'] ?? [] as $aura) {
            $auraName = $aura['name'] ?? 'Unknown';
            $uptime   = $aura['totalUptime'] ?? 0;
            $appliedBy = [];

            foreach ($aura['details'] ?? [] as $detail) {
                $playerName = $detail['name'] ?? 'Unknown';
                if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;
                $appliedBy[$playerName] = $detail['total'] ?? 0;
            }

            $result[$auraName] = [
                'total_uptime_pct' => round($uptime / $totalDurationMs * 100, 1),
                'applied_by'       => $appliedBy,
            ];
        }

        return $result;
    }

    /**
     * Parse Resources table into per-player resource waste (overcap) summary.
     * Returns: [ playerName => [ 'resource' => string, 'generated' => int, 'wasted' => int, 'waste_pct' => float ] ]
     */
    public static function parseResources(array $resourcesData, array $rosterNames = []): array
    {
        $result = [];

        foreach ($resourcesData['entries'] ?? [] as $player) {
            $name = $player['name'] ?? null;
            if (!$name) continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

            $generated = $player['total'] ?? 0;
            $wasted    = $player['totalReduced'] ?? $player['wasted'] ?? 0;

            if ($generated <= 0) continue;

            $result[$name] = [
                'resource'   => $player['resourceName'] ?? $player['type'] ?? 'Unknown',
                'generated'  => $generated,
                'wasted'     => $wasted,
                'waste_pct'  => round($wasted / ($generated + $wasted) * 100, 1),
            ];
        }

        arsort($result);

        return $result;
    }

    /**
     * Build per-fight phase summary: boss name → fight # → phase where fight ended.
     * Uses report.phases for phase name lookup.
     */
    public static function buildPhaseSummary(array $raidFights, array $reportPhases): array
    {
        // Build lookup: encounterID → [ phaseIndex => phaseName ]
        $phaseNames = [];
        foreach ($reportPhases as $encounterPhase) {
            $encId = $encounterPhase['encounterID'] ?? 0;
            foreach ($encounterPhase['phases'] ?? [] as $phase) {
                $phaseNames[$encId][$phase['id']] = [
                    'name'           => $phase['name'] ?? "Phase {$phase['id']}",
                    'isIntermission' => $phase['isIntermission'] ?? false,
                ];
            }
        }

        $summary = [];
        foreach ($raidFights as $fight) {
            $boss     = $fight['name'] ?? 'Unknown';
            $encId    = $fight['encounterID'] ?? 0;
            $lastPhase = $fight['lastPhase'] ?? null;
            $isInter   = $fight['lastPhaseIsIntermission'] ?? false;
            $outcome   = ($fight['kill'] ?? false) ? 'kill' : 'wipe';
            $bossPct   = $fight['bossPercentage'] ?? null;
            $durationS = (int) round((($fight['endTime'] ?? 0) - ($fight['startTime'] ?? 0)) / 1000);

            // Skip accidental pulls / commanded wipes (short fight + boss barely damaged)
            if ($outcome === 'wipe' && $durationS < 30 && $bossPct !== null && $bossPct >= 90) {
                continue;
            }

            $phaseName = null;
            if ($lastPhase !== null) {
                $phaseName = $phaseNames[$encId][$lastPhase]['name']
                    ?? ($isInter ? "Intermission {$lastPhase}" : "Phase {$lastPhase}");
            }

            $summary[$boss][] = [
                'fight_id'   => $fight['id'],
                'outcome'    => $outcome,
                'last_phase' => $phaseName,
                'duration_s' => $durationS,
                'boss_pct'   => $bossPct,
            ];
        }

        return $summary;
    }

    /**
     * Calculate total fight time in seconds and per-fight durations for CPM support.
     */
    public static function buildFightDurations(array $raidFights): array
    {
        $totalMs = 0;
        $perFight = [];

        foreach ($raidFights as $fight) {
            $durationMs = ($fight['endTime'] ?? 0) - ($fight['startTime'] ?? 0);
            $totalMs += $durationMs;
            $perFight[$fight['id']] = (int) round($durationMs / 1000);
        }

        return [
            'total_seconds'    => (int) round($totalMs / 1000),
            'per_fight_seconds' => $perFight,
        ];
    }

    /**
     * Convert a millisecond timestamp (relative to fight start) to "M:SS" string.
     */
    private static function msToFightTime(?int $ms): ?string
    {
        if ($ms === null) return null;
        $seconds = (int) round($ms / 1000);
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
