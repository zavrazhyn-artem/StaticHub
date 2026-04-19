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
                'player'            => $d['name'] ?? 'Unknown',
                'fight_id'          => $fightId,
                'killing_blow'      => $d['killingBlow']['name'] ?? 'Unknown Ability',
                'killing_blow_guid' => $d['killingBlow']['guid'] ?? null,
                'time_into_fight'   => self::msToFightTime($relativeMs),
                '_relative_ms'      => $relativeMs,
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

        // Group by boss → try_N. Keep fight_id + relative_ms for downstream phase bucketing.
        $grouped = [];
        foreach ($individualDeaths as $death) {
            $fightId  = $death['fight_id'];
            $bossName = $fightBossMap[$fightId] ?? 'Unknown';
            $tryNum   = $fightTryMap[$fightId] ?? 1;
            $tryKey   = "try_{$tryNum}";

            $death['time_ms_relative'] = $death['_relative_ms'] ?? null;
            unset($death['_relative_ms']);

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
                $abilityGuid = $ability['guid'] ?? null;
                if (in_array($abilityName, ['Melee', 'Attack', 'Auto Attack', 'Stagger', 'Burning Rush'])) continue;

                if (!isset($abilityDamageMap[$abilityName])) {
                    $abilityDamageMap[$abilityName] = ['guid' => $abilityGuid, 'total_damage_to_raid' => 0, 'victims' => []];
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
                'ability'              => $abilityName,
                'ability_guid'         => $data['guid'],
                'total_damage_to_raid' => $data['total_damage_to_raid'],
                'biggest_victims'      => array_slice($data['victims'], 0, 5, true),
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

            // Walk WCL nested subentries to find actual player actors (actorName / actorType).
            // Multi-school spells (Frostbolt etc.) wrap actors inside a second-level subentries[].
            $actors = self::flattenCastActors($abilityData);

            foreach ($actors as $actor) {
                $playerName = $actor['actorName'] ?? null;
                $actorType  = $actor['actorType'] ?? null;
                $totalCasts = $actor['total'] ?? 0;

                if (!$playerName || !$actorType) continue;
                if (in_array($actorType, ['NPC', 'Pet', 'Boss', 'Unknown'], true)) continue;
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
     * Recursively walk WCL ability entry's `subentries` to collect leaf actor records
     * (those with `actorName` + `actorType` fields).
     */
    private static function flattenCastActors(array $node): array
    {
        if (isset($node['actorName']) && isset($node['actorType'])) {
            return [$node];
        }
        $children = $node['subentries'] ?? [];
        if (empty($children)) return [];
        $out = [];
        foreach ($children as $c) {
            if (!is_array($c)) continue;
            $out = array_merge($out, self::flattenCastActors($c));
        }
        return $out;
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

    /**
     * Parse DamageDone entries (per-player) into boss vs add damage breakdown.
     * Uses each player's `targets` array from standard DamageDone table.
     *
     * Returns: [
     *   'adds'       => [ addName => ['total' => int, 'top_sources' => [playerName => int]] ],
     *   'per_player' => [ playerName => ['boss_damage' => int, 'add_damage' => int, 'add_pct' => float] ]
     * ]
     */
    public static function parseTargetDamage(array $damageDoneEntries, array $rosterNames = []): array
    {
        $perPlayer = [];
        $addTotals = []; // addName => [playerName => damage]

        foreach ($damageDoneEntries as $entry) {
            if (($entry['type'] ?? '') === 'NPC') continue;
            $playerName = $entry['name'] ?? '';
            if (!empty($rosterNames) && !in_array($playerName, $rosterNames)) continue;

            $bossDmg = 0;
            $addDmg = 0;

            foreach ($entry['targets'] ?? [] as $target) {
                $targetName = $target['name'] ?? '';
                $targetType = $target['type'] ?? '';
                $dmg = $target['total'] ?? 0;

                if ($targetType === 'Boss') {
                    $bossDmg += $dmg;
                } elseif ($targetType === 'NPC') {
                    $addDmg += $dmg;
                    $addTotals[$targetName][$playerName] = ($addTotals[$targetName][$playerName] ?? 0) + $dmg;
                }
            }

            $total = $bossDmg + $addDmg;
            $perPlayer[$playerName] = [
                'boss_damage' => $bossDmg,
                'add_damage'  => $addDmg,
                'add_pct'     => $total > 0 ? round($addDmg / $total * 100, 1) : 0,
            ];
        }

        // Build add summary sorted by total damage
        $adds = [];
        foreach ($addTotals as $addName => $sources) {
            arsort($sources);
            $adds[$addName] = [
                'total'       => array_sum($sources),
                'top_sources' => array_slice($sources, 0, 5, true),
            ];
        }
        uasort($adds, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'adds'       => $adds,
            'per_player' => $perPlayer,
        ];
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
        $aggregated = [];

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

            // Aggregate duplicate buff names (WCL splits by spell ID)
            if (!isset($aggregated[$name])) {
                $aggregated[$name] = ['uptime' => 0, 'uses' => 0];
            }
            $aggregated[$name]['uptime'] = max($aggregated[$name]['uptime'], $aura['totalUptime'] ?? 0);
            $aggregated[$name]['uses'] += $aura['totalUses'] ?? 0;
        }

        $result = [];
        foreach ($aggregated as $name => $data) {
            $result[$name] = [
                'uptime_pct'            => round($data['uptime'] / $totalTime * 100, 1),
                'avg_players_per_fight' => round($data['uses'] / $fightCount, 1),
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
     * Per-player damage-done top abilities. Returns top N (default 8) abilities for each player
     * with absolute damage and percentage of player's total.
     *
     * @return array<string, array<int, array{ability:string,total:int,pct:float,type:?int}>>
     */
    public static function parseDamageDoneBreakdown(array $damageDoneData, array $rosterNames = [], int $topN = 8): array
    {
        $out = [];
        foreach ($damageDoneData['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (!$name) continue;
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

            $playerTotal = (int) ($entry['total'] ?? 0);
            if ($playerTotal <= 0) continue;

            $abilities = [];
            foreach ($entry['abilities'] ?? [] as $a) {
                $aTotal = (int) ($a['total'] ?? 0);
                if ($aTotal <= 0) continue;
                $abilities[] = [
                    'ability' => (string) ($a['name'] ?? '?'),
                    'total'   => $aTotal,
                    'pct'     => round($aTotal / $playerTotal * 100, 1),
                    'type'    => $a['type'] ?? null,
                ];
            }
            usort($abilities, fn($a, $b) => $b['total'] <=> $a['total']);
            $out[$name] = array_slice($abilities, 0, $topN);
        }
        return $out;
    }

    /**
     * Per-player damage-taken top sources (the abilities that hit them hardest).
     *
     * @return array<string, array<int, array{ability:string,total:int,pct:float,hit_count:?int}>>
     */
    public static function parseDamageTakenBreakdown(array $damageTakenData, array $rosterNames = [], int $topN = 8): array
    {
        $out = [];
        foreach ($damageTakenData['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (!$name) continue;
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

            $playerTotal = (int) ($entry['total'] ?? 0);
            if ($playerTotal <= 0) continue;

            $abilities = [];
            foreach ($entry['abilities'] ?? [] as $a) {
                $aTotal = (int) ($a['total'] ?? 0);
                if ($aTotal <= 0) continue;
                $abilities[] = [
                    'ability'   => (string) ($a['name'] ?? '?'),
                    'total'     => $aTotal,
                    'pct'       => round($aTotal / $playerTotal * 100, 1),
                    'hit_count' => $a['hitCount'] ?? null,
                ];
            }
            usort($abilities, fn($a, $b) => $b['total'] <=> $a['total']);
            $out[$name] = array_slice($abilities, 0, $topN);
        }
        return $out;
    }

    /**
     * Per-healer top heal targets — who you healed the most (and what was overheal).
     * Distinguishes "tank healer" vs "raid healer" play patterns.
     *
     * @return array<string, array<int, array{target:string,total:int,pct:float,target_role:?string}>>
     */
    public static function parseHealTargets(array $healingData, array $rosterNames = [], array $playerDetails = [], int $topN = 5): array
    {
        // Build roleByName lookup
        $roleByName = [];
        foreach ($playerDetails as $name => $d) {
            $role = $d['role'] ?? null;
            if ($role) $roleByName[$name] = in_array($role, ['tanks', 'tank'], true) ? 'tank'
                : (in_array($role, ['healers', 'healer'], true) ? 'healer' : 'dps');
        }

        $out = [];
        foreach ($healingData['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (!$name) continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;
            if (($entry['type'] ?? '') !== 'NPC' && ($roleByName[$name] ?? '') !== 'healer') continue;

            $playerTotal = (int) ($entry['total'] ?? 0);
            if ($playerTotal <= 0) continue;

            $targets = [];
            foreach ($entry['targets'] ?? [] as $t) {
                $tName = $t['name'] ?? '';
                $tTotal = (int) ($t['total'] ?? 0);
                if ($tTotal <= 0) continue;
                $targets[] = [
                    'target'      => $tName,
                    'total'       => $tTotal,
                    'pct'         => round($tTotal / $playerTotal * 100, 1),
                    'target_role' => $roleByName[$tName] ?? null,
                ];
            }
            usort($targets, fn($a, $b) => $b['total'] <=> $a['total']);
            $out[$name] = array_slice($targets, 0, $topN);
        }
        return $out;
    }

    /**
     * Per-healer top healing abilities with overheal %.
     *
     * @return array<string, array<int, array{ability:string,total:int,pct:float,overheal_pct:float}>>
     */
    public static function parseHealingBreakdown(array $healingData, array $rosterNames = [], int $topN = 6): array
    {
        $out = [];
        foreach ($healingData['entries'] ?? [] as $entry) {
            $name = $entry['name'] ?? null;
            if (!$name) continue;
            if (($entry['type'] ?? '') === 'NPC') continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;

            $playerTotal = (int) ($entry['total'] ?? 0);
            if ($playerTotal <= 0) continue;

            $abilities = [];
            foreach ($entry['abilities'] ?? [] as $a) {
                $aTotal = (int) ($a['total'] ?? 0);
                if ($aTotal <= 0) continue;
                $aOver = (int) ($a['overheal'] ?? 0);
                $aRaw  = $aTotal + $aOver;
                $abilities[] = [
                    'ability'      => (string) ($a['name'] ?? '?'),
                    'total'        => $aTotal,
                    'pct'          => round($aTotal / $playerTotal * 100, 1),
                    'overheal_pct' => $aRaw > 0 ? round($aOver / $aRaw * 100, 1) : 0.0,
                ];
            }
            usort($abilities, fn($a, $b) => $b['total'] <=> $a['total']);
            $out[$name] = array_slice($abilities, 0, $topN);
        }
        return $out;
    }

    /**
     * Bucket deaths into phase windows for a single encounter.
     *
     * @param array $deaths            Death events with `time` field (ms relative to fight start)
     * @param array $phaseTransitions  [{ id, startTime }] from fight data — startTime is relative ms
     * @return array<string, int>      [phaseName => deathCount]
     */
    public static function bucketDeathsByPhase(array $deaths, array $phaseTransitions, array $phaseNamesById = []): array
    {
        if (empty($phaseTransitions)) return [];

        // Sort phase transitions by startTime ascending
        usort($phaseTransitions, fn($a, $b) => ($a['startTime'] ?? 0) <=> ($b['startTime'] ?? 0));

        $buckets = [];
        foreach ($deaths as $d) {
            $t = $d['time'] ?? $d['timestamp'] ?? null;
            if ($t === null) continue;

            $phase = null;
            foreach ($phaseTransitions as $pt) {
                if ($t >= ($pt['startTime'] ?? 0)) {
                    $phase = $pt['id'];
                } else {
                    break;
                }
            }
            if ($phase === null) continue;
            $name = $phaseNamesById[$phase] ?? "Phase {$phase}";
            $buckets[$name] = ($buckets[$name] ?? 0) + 1;
        }
        return $buckets;
    }

    /**
     * Parse cooldown cast events into per-player per-ability timing data.
     * Returns timestamps + idle gap analysis to evaluate cooldown discipline.
     *
     * @param array $events    Cast events with `timestamp`, `sourceID`, `abilityGameID`, `fight`
     * @param array $actorMap  ActorID → playerName
     * @param array $abilityNames  AbilityID → name lookup
     * @param array $cooldownsByAbility  AbilityID → cooldown_seconds (from spec baselines)
     * @param array $fightDurations  fightId → duration_seconds
     *
     * @return array<string, array<string, array{
     *   ability_id:int, used:int, max_possible:int, idle_seconds:int, casts:array<int,int>
     * }>>  Outer: playerName, inner: abilityName.
     */
    public static function parseCooldownEvents(
        array $events,
        array $actorMap,
        array $abilityNames,
        array $cooldownsByAbility,
        array $fightDurations
    ): array {
        // Group casts by (player, ability), collecting timestamps in seconds (relative to fight start).
        $casts = [];
        foreach ($events as $e) {
            if (($e['type'] ?? '') !== 'cast') continue;
            $sourceId = $e['sourceID'] ?? null;
            $abilityId = $e['abilityGameID'] ?? null;
            $time = $e['timestamp'] ?? null;
            $fightId = $e['fight'] ?? null;
            if ($sourceId === null || $abilityId === null || $time === null) continue;
            if (!isset($cooldownsByAbility[$abilityId])) continue;

            $playerName = $actorMap[$sourceId] ?? null;
            if (!$playerName) continue;

            $casts[$playerName][$abilityId][] = [
                'time_s'   => (int) round($time / 1000),
                'fight_id' => $fightId,
            ];
        }

        $totalDurationSeconds = (int) array_sum($fightDurations);
        $out = [];

        foreach ($casts as $playerName => $byAbility) {
            foreach ($byAbility as $abilityId => $castList) {
                $cd = (float) $cooldownsByAbility[$abilityId];
                if ($cd <= 0) continue;

                $abilityName = $abilityNames[$abilityId] ?? "Spell {$abilityId}";
                $used = count($castList);
                $maxPossible = (int) floor($totalDurationSeconds / $cd);

                // Idle time must be computed WITHIN a fight window only (between-fight gaps
                // are not "holds"). Group by fight_id, sort chronologically, sum (gap - cd).
                $byFight = [];
                foreach ($castList as $c) {
                    $byFight[$c['fight_id'] ?? 0][] = $c['time_s'];
                }
                $idleSeconds = 0;
                foreach ($byFight as $times) {
                    sort($times);
                    for ($i = 1; $i < count($times); $i++) {
                        $gap = $times[$i] - $times[$i - 1];
                        $excess = $gap - (int) $cd;
                        if ($excess > 0) $idleSeconds += $excess;
                    }
                }

                $out[$playerName][$abilityName] = [
                    'ability_id'   => (int) $abilityId,
                    'used'         => $used,
                    'max_possible' => $maxPossible,
                    'idle_seconds' => $idleSeconds,
                    'cast_times_s' => array_column($castList, 'time_s'),
                ];
            }
        }

        return $out;
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
     * Parse debuff stack events into per-player max stack statistics.
     * Useful for analyzing tank swap mechanics where stack count indicates swap failure.
     *
     * @param array $events    Events from getDebuffStackEvents
     * @param array $actorMap  Actor ID → name map (for resolving targetID to player name)
     * @return array [
     *   'max_stacks_per_player' => [playerName => max_stack],
     *   'stacks_at_death_per_player' => [playerName => [fight_id => last_stack_before_death]],
     *   'total_applications' => count,
     * ]
     */
    public static function parseDebuffStacks(array $events, array $actorMap, array $deathEvents = []): array
    {
        $maxStacks = [];
        $maxPerFight = []; // [targetID_fightId => max_stack_in_fight]
        $currentStacks = []; // [targetID_fightId => current_stack]
        $applyTimestamps = []; // [targetID_fightId => apply_timestamp]
        $durations = []; // list of ms durations for avg calculation
        $totalApplications = 0;

        foreach ($events as $event) {
            $type = $event['type'] ?? '';
            $targetId = $event['targetID'] ?? null;
            $fightId = $event['fight'] ?? null;
            if (!$targetId || !$fightId) continue;

            $playerName = $actorMap[$targetId] ?? null;
            if (!$playerName) continue;

            $stackKey = "{$targetId}_{$fightId}";

            $timestamp = $event['timestamp'] ?? 0;

            if ($type === 'applydebuff') {
                $currentStacks[$stackKey] = 1;
                $applyTimestamps[$stackKey] = $timestamp;
                $totalApplications++;
            } elseif ($type === 'applydebuffstack') {
                $currentStacks[$stackKey] = $event['stack'] ?? 1;
            } elseif ($type === 'removedebuff') {
                if (isset($applyTimestamps[$stackKey])) {
                    $durations[] = $timestamp - $applyTimestamps[$stackKey];
                    unset($applyTimestamps[$stackKey]);
                }
                $currentStacks[$stackKey] = 0;
            }

            // Track max seen per player across all fights
            $current = $currentStacks[$stackKey] ?? 0;
            if ($current > ($maxStacks[$playerName] ?? 0)) {
                $maxStacks[$playerName] = $current;
            }
            // Track max seen per player per fight (for death correlation)
            if ($current > ($maxPerFight[$stackKey] ?? 0)) {
                $maxPerFight[$stackKey] = $current;
            }
        }

        // Correlate deaths with max stacks seen in that fight.
        // Uses max-in-fight rather than stack-at-exact-death-timestamp because
        // removedebuff sometimes fires milliseconds before death, hiding the real stack count.
        $stacksAtDeath = [];
        foreach ($deathEvents as $death) {
            $deathTargetId = $death['targetID'] ?? $death['id'] ?? null;
            $deathFightId = $death['fight'] ?? null;
            $killingBlow = $death['killingBlow']['name'] ?? '';
            if (!$deathTargetId || !$deathFightId) continue;

            $playerName = $actorMap[$deathTargetId] ?? $death['name'] ?? null;
            if (!$playerName) continue;

            $stackKey = "{$deathTargetId}_{$deathFightId}";
            $maxInFight = $maxPerFight[$stackKey] ?? 0;
            if ($maxInFight <= 0) continue;

            $stacksAtDeath[$playerName][] = [
                'fight_id'     => $deathFightId,
                'max_stacks'   => $maxInFight,
                'killing_blow' => $killingBlow,
            ];
        }

        arsort($maxStacks);

        $avgDurationMs = !empty($durations) ? (int) round(array_sum($durations) / count($durations)) : null;

        // Tank swap timing: for each pair of (removedebuff on tank A → next applydebuff on tank B)
        // Calculate the gap. Short gap (<1s) = crisp swap. Long gap (>3s) = boss sat on same tank too long.
        // Only meaningful if debuff appears on 2+ different players (tank swap mechanic).
        $swapTiming = null;
        if (count($maxStacks) >= 2) {
            $swapTiming = self::calculateSwapTiming($events, $actorMap);
        }

        return [
            'max_stacks_per_player'      => $maxStacks,
            'stacks_at_death_per_player' => $stacksAtDeath,
            'total_applications'         => $totalApplications,
            'avg_duration_ms'            => $avgDurationMs,
            'swap_timing'                => $swapTiming,
        ];
    }

    /**
     * Calculate tank swap gaps from debuff events.
     * A "swap" = removedebuff on tank A immediately followed by applydebuff on tank B within a fight.
     *
     * Returns: [
     *   'avg_gap_ms' => int,
     *   'min_gap_ms' => int,
     *   'max_gap_ms' => int,
     *   'late_swaps' => int (count of swaps with gap > 3000ms),
     *   'total_swaps' => int,
     * ]
     */
    private static function calculateSwapTiming(array $events, array $actorMap): array
    {
        // Group events by fight
        $byFight = [];
        foreach ($events as $e) {
            $fightId = $e['fight'] ?? null;
            if (!$fightId) continue;
            $byFight[$fightId][] = $e;
        }

        $gaps = [];

        foreach ($byFight as $fightId => $fightEvents) {
            usort($fightEvents, fn($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

            $lastRemoval = null; // ['target' => id, 'time' => ts]
            foreach ($fightEvents as $event) {
                $type = $event['type'] ?? '';
                $targetId = $event['targetID'] ?? null;
                $ts = $event['timestamp'] ?? 0;

                if ($type === 'removedebuff') {
                    $lastRemoval = ['target' => $targetId, 'time' => $ts];
                } elseif ($type === 'applydebuff' && $lastRemoval !== null) {
                    if ($targetId !== $lastRemoval['target']) {
                        $gap = $ts - $lastRemoval['time'];
                        if ($gap >= 0 && $gap < 30000) { // sanity cap at 30s
                            $gaps[] = $gap;
                        }
                        $lastRemoval = null;
                    }
                }
            }
        }

        if (empty($gaps)) {
            return [
                'avg_gap_ms' => null,
                'min_gap_ms' => null,
                'max_gap_ms' => null,
                'late_swaps' => 0,
                'total_swaps' => 0,
            ];
        }

        return [
            'avg_gap_ms'  => (int) round(array_sum($gaps) / count($gaps)),
            'min_gap_ms'  => min($gaps),
            'max_gap_ms'  => max($gaps),
            'late_swaps'  => count(array_filter($gaps, fn($g) => $g > 3000)),
            'total_swaps' => count($gaps),
        ];
    }

    /**
     * Parse summon events into add-spawn waves per fight.
     * Groups summon timestamps that are close together (<5s gap) into waves.
     *
     * Returns: [
     *   fight_id => [
     *     'total_summons' => int,
     *     'wave_count' => int,
     *     'waves' => [ ['wave' => 1, 'time_into_fight' => 'M:SS', 'adds_per_ability' => [abilityName => count]] ],
     *     'adds_by_ability' => [abilityName => total_across_waves],
     *   ]
     * ]
     *
     * @param array $events       Summon events
     * @param array $fightStarts  [fight_id => start_timestamp_ms]
     * @param array $abilityMap   Optional [abilityGameID => name] for labeling. If missing, uses ID as string.
     */
    public static function parseSummonWaves(array $events, array $fightStarts = [], array $abilityMap = []): array
    {
        // Group events by fight
        $byFight = [];
        foreach ($events as $e) {
            $fightId = $e['fight'] ?? null;
            if (!$fightId) continue;
            $byFight[$fightId][] = $e;
        }

        $result = [];

        foreach ($byFight as $fightId => $fightEvents) {
            usort($fightEvents, fn($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

            $fightStart = $fightStarts[$fightId] ?? 0;
            $waves = [];
            $currentWave = [];
            $lastTs = null;
            $waveNum = 0;
            $adds_by_ability = [];

            foreach ($fightEvents as $event) {
                $ts = $event['timestamp'] ?? 0;
                $abilityId = $event['abilityGameID'] ?? 0;
                $label = $abilityMap[$abilityId] ?? "ability_{$abilityId}";

                $adds_by_ability[$label] = ($adds_by_ability[$label] ?? 0) + 1;

                // Start new wave if >5s gap OR first event
                if ($lastTs === null || ($ts - $lastTs) > 5000) {
                    if (!empty($currentWave)) {
                        $waves[] = $currentWave;
                    }
                    $waveNum++;
                    $relativeMs = $ts - $fightStart;
                    $currentWave = [
                        'wave' => $waveNum,
                        'time_into_fight' => self::msToFightTime($relativeMs),
                        'adds_per_ability' => [],
                    ];
                }

                $currentWave['adds_per_ability'][$label] =
                    ($currentWave['adds_per_ability'][$label] ?? 0) + 1;

                $lastTs = $ts;
            }

            if (!empty($currentWave)) {
                $waves[] = $currentWave;
            }

            $result[$fightId] = [
                'total_summons'  => count($fightEvents),
                'wave_count'     => count($waves),
                'waves'          => $waves,
                'adds_by_ability' => $adds_by_ability,
            ];
        }

        return $result;
    }

    /**
     * Parse enemy death events to detect orb kill staggering (overlaps within 1s).
     *
     * @param array $deathEvents  Enemy death events
     * @param array $npcMap       [actorID => npcName]
     * @param array $fightStarts  [fight_id => start_timestamp_ms]
     */
    public static function parseOrbStaggering(array $deathEvents, array $npcMap, array $fightStarts = []): array
    {
        $byAddInFight = [];
        foreach ($deathEvents as $e) {
            $tid = $e['targetID'] ?? null;
            $fightId = $e['fight'] ?? null;
            if (!$tid || !$fightId) continue;

            $addName = $npcMap[$tid] ?? "npc_{$tid}";
            $key = "{$addName}|{$fightId}";
            $byAddInFight[$key][] = $e;
        }

        $result = [];

        foreach ($byAddInFight as $key => $events) {
            [$addName, $fightId] = explode('|', $key);
            usort($events, fn($a, $b) => ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0));

            if (!isset($result[$addName])) {
                $result[$addName] = [
                    'total_deaths'      => 0,
                    'overlapping_kills' => 0,
                    'intervals_ms'      => [],
                    'kill_clusters'     => [],
                ];
            }

            $result[$addName]['total_deaths'] += count($events);

            $clusterStart = null;
            $clusterSize = 0;
            $prevTs = null;
            foreach ($events as $event) {
                $ts = $event['timestamp'] ?? 0;
                if ($prevTs !== null) {
                    $interval = $ts - $prevTs;
                    $result[$addName]['intervals_ms'][] = $interval;
                    if ($interval < 1000) {
                        if ($clusterSize === 0) {
                            $clusterStart = $prevTs;
                            $clusterSize = 2;
                        } else {
                            $clusterSize++;
                        }
                    } else {
                        if ($clusterSize >= 2) {
                            $fightStart = $fightStarts[$fightId] ?? 0;
                            $result[$addName]['kill_clusters'][] = [
                                'fight_id'     => (int) $fightId,
                                'time'         => self::msToFightTime($clusterStart - $fightStart),
                                'cluster_size' => $clusterSize,
                            ];
                            $result[$addName]['overlapping_kills'] += $clusterSize;
                            $clusterSize = 0;
                        }
                    }
                }
                $prevTs = $ts;
            }
            if ($clusterSize >= 2) {
                $fightStart = $fightStarts[$fightId] ?? 0;
                $result[$addName]['kill_clusters'][] = [
                    'fight_id'     => (int) $fightId,
                    'time'         => self::msToFightTime($clusterStart - $fightStart),
                    'cluster_size' => $clusterSize,
                ];
                $result[$addName]['overlapping_kills'] += $clusterSize;
            }
        }

        foreach ($result as &$data) {
            $intervals = $data['intervals_ms'];
            $data['avg_interval_ms'] = !empty($intervals) ? (int) round(array_sum($intervals) / count($intervals)) : null;
            $data['min_interval_ms'] = !empty($intervals) ? min($intervals) : null;
            unset($data['intervals_ms']);
        }
        unset($data);

        return $result;
    }

    /**
     * Cross-reference cast events with shield buff windows to detect casts on shielded targets.
     */
    public static function parseShieldedCasts(array $castEvents, array $shieldEvents, array $actorMap = [], array $fightStarts = []): array
    {
        $windows = [];
        foreach ($shieldEvents as $e) {
            $type = $e['type'] ?? '';
            $tid = $e['targetID'] ?? null;
            $instance = $e['targetInstance'] ?? 1;
            $fight = $e['fight'] ?? null;
            $ts = $e['timestamp'] ?? 0;
            if (!$tid || !$fight) continue;

            $key = "{$tid}_{$instance}_{$fight}";
            if ($type === 'applybuff') {
                $windows[$key][] = ['start' => $ts, 'end' => PHP_INT_MAX, 'fight' => $fight];
            } elseif ($type === 'removebuff' && !empty($windows[$key])) {
                $idx = count($windows[$key]) - 1;
                $windows[$key][$idx]['end'] = $ts;
            }
        }

        $total = count($castEvents);
        $onShielded = 0;
        $shieldedDetails = [];

        foreach ($castEvents as $cast) {
            // For enemy casts (boss clones), the caster is sourceID — match it with shield TARGET
            $casterId = $cast['sourceID'] ?? null;
            $casterInstance = $cast['sourceInstance'] ?? 1;
            $fight = $cast['fight'] ?? null;
            $ts = $cast['timestamp'] ?? 0;
            if (!$casterId || !$fight) continue;

            $key = "{$casterId}_{$casterInstance}_{$fight}";
            $wasShielded = false;
            foreach ($windows[$key] ?? [] as $w) {
                if ($ts >= $w['start'] && $ts <= $w['end']) {
                    $wasShielded = true;
                    break;
                }
            }

            if ($wasShielded) {
                $onShielded++;
                $fightStart = $fightStarts[$fight] ?? 0;
                $shieldedDetails[] = [
                    'fight'           => $fight,
                    'time'            => self::msToFightTime($ts - $fightStart),
                    'clone_id'        => $casterId,
                    'clone_instance'  => $casterInstance,
                ];
            }
        }

        return [
            'casts_total'       => $total,
            'casts_on_shielded' => $onShielded,
            'casts_on_clear'    => $total - $onShielded,
            'shielded_details'  => array_slice($shieldedDetails, 0, 10),
        ];
    }

    /**
     * Parse boss cast events with coordinates into positioning timeline.
     */
    public static function parseBossPositions(array $castEvents, array $fightStarts = []): array
    {
        $positions = [];
        $xs = [];
        $ys = [];

        foreach ($castEvents as $cast) {
            $x = $cast['x'] ?? null;
            $y = $cast['y'] ?? null;
            $fight = $cast['fight'] ?? null;
            $ts = $cast['timestamp'] ?? 0;

            if ($x === null || $y === null) continue;
            $xs[] = $x;
            $ys[] = $y;

            $fightStart = $fightStarts[$fight] ?? 0;
            $positions[] = [
                'fight' => $fight,
                'time'  => self::msToFightTime($ts - $fightStart),
                'x'     => $x,
                'y'     => $y,
            ];
        }

        return [
            'casts_with_coords' => count($positions),
            'positions'         => array_slice($positions, 0, 20),
            'avg_x'             => !empty($xs) ? (int) round(array_sum($xs) / count($xs)) : null,
            'avg_y'             => !empty($ys) ? (int) round(array_sum($ys) / count($ys)) : null,
        ];
    }

    /**
     * Correlate debuff removal events with nearby player cast events to extract x,y coords.
     * For each removal timestamp, find the closest cast event (within ±3s) from the same player.
     *
     * @param array $removals       [ {timestamp, targetID (player), fight} ]
     * @param array $castSnapshots  Player cast events with x, y
     * @param array $playerMap      [actorID => name]
     * @param array $fightStarts    [fight_id => start_ms]
     *
     * @return array [
     *   'applications_with_coords' => int,
     *   'applications_total'       => int,
     *   'per_player_avg_distance_from_group' => [playerName => distance_units],
     *   'positions_sample'         => [ {player, fight, time, x, y} ],
     *   'group_center'             => {avg_x, avg_y},
     *   'outlier_count'            => int (positions >30% away from group center)
     * ]
     */
    public static function correlatePlayerCoordsWithEvents(array $removals, array $castSnapshots, array $playerMap, array $fightStarts = []): array
    {
        if (empty($castSnapshots)) {
            return [
                'applications_with_coords' => 0,
                'applications_total'       => count($removals),
                'positions_sample'         => [],
                'group_center'             => null,
                'outlier_count'            => 0,
                'per_player_avg_distance_from_group' => [],
            ];
        }

        // Index cast events by (playerID, fight) for fast lookup
        $castsByPlayer = [];
        foreach ($castSnapshots as $c) {
            $pid = $c['sourceID'] ?? null;
            $fight = $c['fight'] ?? null;
            if (!$pid || !$fight) continue;
            $castsByPlayer[$pid][$fight][] = $c;
        }

        $positions = [];

        foreach ($removals as $r) {
            $ts = $r['timestamp'] ?? 0;
            $pid = $r['targetID'] ?? null;
            $fight = $r['fight'] ?? null;
            if (!$pid || !$fight) continue;

            $candidates = $castsByPlayer[$pid][$fight] ?? [];
            if (empty($candidates)) continue;

            // Find nearest cast event within 3000ms
            $best = null;
            $bestDt = PHP_INT_MAX;
            foreach ($candidates as $c) {
                $dt = abs(($c['timestamp'] ?? 0) - $ts);
                if ($dt < $bestDt && $dt < 3000) {
                    $bestDt = $dt;
                    $best = $c;
                }
            }

            if ($best) {
                $fightStart = $fightStarts[$fight] ?? 0;
                $positions[] = [
                    'player' => $playerMap[$pid] ?? "player_{$pid}",
                    'fight'  => $fight,
                    'time'   => self::msToFightTime($ts - $fightStart),
                    'x'      => $best['x'],
                    'y'      => $best['y'],
                    'match_delta_ms' => $bestDt,
                ];
            }
        }

        // Compute group center (average of all positions)
        if (empty($positions)) {
            return [
                'applications_with_coords' => 0,
                'applications_total'       => count($removals),
                'positions_sample'         => [],
                'group_center'             => null,
                'outlier_count'            => 0,
                'per_player_avg_distance_from_group' => [],
            ];
        }

        $avgX = array_sum(array_column($positions, 'x')) / count($positions);
        $avgY = array_sum(array_column($positions, 'y')) / count($positions);

        // Per-player avg distance from group center
        $byPlayer = [];
        foreach ($positions as $p) {
            $d = sqrt(pow($p['x'] - $avgX, 2) + pow($p['y'] - $avgY, 2));
            $byPlayer[$p['player']][] = $d;
        }
        $perPlayerAvg = [];
        foreach ($byPlayer as $name => $dists) {
            $perPlayerAvg[$name] = (int) round(array_sum($dists) / count($dists));
        }
        arsort($perPlayerAvg);

        // Outliers: positions > 30% above average distance
        $allDists = array_merge(...array_values($byPlayer));
        $avgDist = array_sum($allDists) / count($allDists);
        $outlierThreshold = $avgDist * 1.3;
        $outlierCount = count(array_filter($allDists, fn($d) => $d > $outlierThreshold));

        return [
            'applications_with_coords' => count($positions),
            'applications_total'       => count($removals),
            'positions_sample'         => array_slice($positions, 0, 15),
            'group_center'             => ['avg_x' => (int) $avgX, 'avg_y' => (int) $avgY],
            'outlier_count'            => $outlierCount,
            'per_player_avg_distance_from_group' => $perPlayerAvg,
        ];
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
