<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Builds a per-death state snapshot from already-fetched WCL events.
 *
 * Output per death:
 *   - dying_player_id, dying_player_name, position {x, y}
 *   - nearby_players[]: friends within 30y at the death timestamp, with their
 *     positions and recent (±5s) cast events
 *   - recent_casts_by_actor[]: per-actor casts in [death-5s, death+1s]
 *   - personal_defensives_available[]: defensive CDs across the roster that
 *     were OFF cooldown at the death moment (last cast > cooldown_s ago, or
 *     never cast in this fight)
 *
 * No new WCL queries — the assembler operates on snapshots already collected
 * by WclService:
 *   - $playerCoordSnapshots from fetchPlayerCoordSnapshots() (now retains
 *     abilityGameID after the recent extension)
 *   - $deathEventCoords from fetchDeathEventCoords()
 *
 * Coordinates are in WCL units (100 units = 1 yard).
 */
class DeathStateAssembler
{
    private const NEARBY_RADIUS_YARDS = 30;
    private const WINDOW_BEFORE_MS = 5_000;
    private const WINDOW_AFTER_MS  = 1_000;

    public function __construct(
        private readonly CombatReferenceLoader $combatRefs,
    ) {}

    /**
     * @param array $deathEventCoords  fetchDeathEventCoords() output
     * @param array $playerCoordSnapshots  fetchPlayerCoordSnapshots() output
     * @param array $playerIdToName  actorID => name
     * @param array $playerNameToClass  name => class (DeathKnight, DemonHunter, ...)
     * @param array $fightStartTimes  fight_id => absolute startTime ms
     * @param array $rosterDeaths  list of parsed deaths with player + fight_id + time_ms_relative
     * @return array<int, array>  death_index => state-snapshot
     */
    public function assemble(
        array $deathEventCoords,
        array $playerCoordSnapshots,
        array $playerIdToName,
        array $playerNameToClass,
        array $fightStartTimes,
        array $rosterDeaths
    ): array {
        $defensivesById = $this->combatRefs->personalDefensivesById();

        // Pre-index event lookups for O(1) per-death access.
        $coordsByFightPlayer = $this->indexCoordsByFightPlayer($deathEventCoords, $playerIdToName);
        $castsByFight = $this->indexCastsByFight($playerCoordSnapshots);

        $out = [];
        foreach ($rosterDeaths as $idx => $death) {
            $fightId = $death['fight_id'] ?? null;
            $playerName = $death['player'] ?? null;
            $relativeMs = $death['time_ms_relative'] ?? null;

            if ($fightId === null || $playerName === null || $relativeMs === null) {
                $out[$idx] = null;
                continue;
            }

            $fightStartMs = $fightStartTimes[$fightId] ?? null;
            $absoluteMs = $fightStartMs !== null ? $fightStartMs + $relativeMs : null;
            if ($absoluteMs === null) {
                $out[$idx] = null;
                continue;
            }

            $dyingCoord = $coordsByFightPlayer[$fightId][$playerName][0] ?? null;
            $nearby = $this->nearbyPlayersAt(
                $absoluteMs,
                $dyingCoord,
                $playerName,
                $castsByFight[$fightId] ?? [],
                $playerIdToName
            );

            $recentCasts = $this->castsInWindow(
                $castsByFight[$fightId] ?? [],
                $absoluteMs - self::WINDOW_BEFORE_MS,
                $absoluteMs + self::WINDOW_AFTER_MS,
                $playerIdToName
            );

            $availableDefensives = $this->defensivesAvailable(
                $absoluteMs,
                $castsByFight[$fightId] ?? [],
                $playerIdToName,
                $playerNameToClass,
                $defensivesById,
                $fightStartMs ?? 0
            );

            $out[$idx] = [
                'dying_player'                  => $playerName,
                'dying_position'                => $dyingCoord
                    ? ['x' => $dyingCoord['x'], 'y' => $dyingCoord['y']]
                    : null,
                'absolute_timestamp_ms'         => $absoluteMs,
                'nearby_players'                => $nearby,
                'recent_casts'                  => $recentCasts,
                'personal_defensives_available' => $availableDefensives,
            ];
        }

        return $out;
    }

    /**
     * @param array $deathEventCoords
     * @param array<int,string> $playerIdToName
     * @return array<int, array<string, list<array>>>  fight_id => playerName => [coord, ...]
     */
    private function indexCoordsByFightPlayer(array $deathEventCoords, array $playerIdToName): array
    {
        $idx = [];
        foreach ($deathEventCoords as $e) {
            $name = $playerIdToName[$e['targetID']] ?? null;
            if ($name === null) continue;
            $idx[$e['fight']][$name][] = $e;
        }
        return $idx;
    }

    /**
     * Re-index cast snapshots by fight for O(1) per-fight scans during death iteration.
     *
     * @return array<int, list<array>>
     */
    private function indexCastsByFight(array $playerCoordSnapshots): array
    {
        $idx = [];
        foreach ($playerCoordSnapshots as $cast) {
            $fight = $cast['fight'] ?? null;
            if ($fight === null) continue;
            $idx[$fight][] = $cast;
        }
        return $idx;
    }

    /**
     * Find friends within $NEARBY_RADIUS_YARDS of the dying player at absolute
     * timestamp. Uses each friend's most recent cast within ±2s as a position
     * proxy — WCL only has positions on cast events, so we approximate.
     *
     * @return list<array{name:string, x:int, y:int, distance_yards:float}>
     */
    private function nearbyPlayersAt(
        int $absoluteMs,
        ?array $dyingCoord,
        string $dyingPlayer,
        array $fightCasts,
        array $playerIdToName
    ): array {
        if (!$dyingCoord) return [];

        $radiusUnits = self::NEARBY_RADIUS_YARDS * 100; // WCL units = 1/100 yard
        $proxyWindow = 2_000;

        // Per-player most recent cast within proxy window
        $latestPerPlayer = [];
        foreach ($fightCasts as $cast) {
            $name = $playerIdToName[$cast['sourceID']] ?? null;
            if (!$name || $name === $dyingPlayer) continue;
            $ts = (int) ($cast['timestamp'] ?? 0);
            if (abs($ts - $absoluteMs) > $proxyWindow) continue;

            if (!isset($latestPerPlayer[$name]) || abs($ts - $absoluteMs) < abs($latestPerPlayer[$name]['timestamp'] - $absoluteMs)) {
                $latestPerPlayer[$name] = $cast;
            }
        }

        $nearby = [];
        foreach ($latestPerPlayer as $name => $cast) {
            $dx = (int) $cast['x'] - (int) $dyingCoord['x'];
            $dy = (int) $cast['y'] - (int) $dyingCoord['y'];
            $distUnits = sqrt($dx * $dx + $dy * $dy);
            $distYards = $distUnits / 100;
            if ($distUnits > $radiusUnits) continue;
            $nearby[] = [
                'name'           => $name,
                'x'              => (int) $cast['x'],
                'y'              => (int) $cast['y'],
                'distance_yards' => round($distYards, 1),
            ];
        }
        usort($nearby, fn($a, $b) => $a['distance_yards'] <=> $b['distance_yards']);
        return $nearby;
    }

    /**
     * @return list<array{actor:string, ability_id:?int, timestamp_ms:int, ms_before_death:int}>
     */
    private function castsInWindow(array $fightCasts, int $startMs, int $endMs, array $playerIdToName): array
    {
        $deathMs = $endMs - self::WINDOW_AFTER_MS;
        $rows = [];
        foreach ($fightCasts as $cast) {
            $ts = (int) ($cast['timestamp'] ?? 0);
            if ($ts < $startMs || $ts > $endMs) continue;
            $name = $playerIdToName[$cast['sourceID']] ?? null;
            if (!$name) continue;
            $rows[] = [
                'actor'           => $name,
                'ability_id'      => $cast['abilityGameID'] ?? null,
                'timestamp_ms'    => $ts,
                'ms_before_death' => $deathMs - $ts,
            ];
        }
        usort($rows, fn($a, $b) => $a['timestamp_ms'] <=> $b['timestamp_ms']);
        return $rows;
    }

    /**
     * For each roster member, list their personal defensives that were OFF
     * cooldown at the death moment (never cast this fight, or last cast was
     * earlier than `cooldown_s` ago).
     *
     * @return list<array{player:string, ability_id:int, ability_name:string, last_cast_s_ago:?float}>
     */
    private function defensivesAvailable(
        int $absoluteMs,
        array $fightCasts,
        array $playerIdToName,
        array $playerNameToClass,
        array $defensivesById,
        int $fightStartMs
    ): array {
        // Build {player => ability_id => last_cast_ms}
        $lastCast = [];
        foreach ($fightCasts as $cast) {
            $name = $playerIdToName[$cast['sourceID']] ?? null;
            if (!$name) continue;
            $abilityId = (int) ($cast['abilityGameID'] ?? 0);
            if (!isset($defensivesById[$abilityId])) continue;
            $ts = (int) ($cast['timestamp'] ?? 0);
            if ($ts > $absoluteMs) continue;
            if (!isset($lastCast[$name][$abilityId]) || $ts > $lastCast[$name][$abilityId]) {
                $lastCast[$name][$abilityId] = $ts;
            }
        }

        $available = [];
        foreach ($playerNameToClass as $name => $class) {
            foreach ($defensivesById as $abilityId => $entry) {
                if (($entry['class'] ?? '') !== $class) continue;
                $cooldownMs = (int) ($entry['cooldown_s'] ?? 60) * 1000;
                $lastMs = $lastCast[$name][$abilityId] ?? null;

                if ($lastMs === null) {
                    // Never cast this fight — treat as available if the fight
                    // has been going long enough (avoids false positives on
                    // CDs that may have been on cooldown from a prior fight).
                    if ($absoluteMs - $fightStartMs < $cooldownMs) continue;
                    $available[] = [
                        'player'          => $name,
                        'ability_id'      => $abilityId,
                        'ability_name'    => (string) ($entry['name'] ?? ''),
                        'last_cast_s_ago' => null,
                    ];
                    continue;
                }

                if (($absoluteMs - $lastMs) >= $cooldownMs) {
                    $available[] = [
                        'player'          => $name,
                        'ability_id'      => $abilityId,
                        'ability_name'    => (string) ($entry['name'] ?? ''),
                        'last_cast_s_ago' => round(($absoluteMs - $lastMs) / 1000, 1),
                    ];
                }
            }
        }

        return $available;
    }
}
