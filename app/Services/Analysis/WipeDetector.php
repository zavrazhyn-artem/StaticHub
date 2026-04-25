<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Classifies each player death into a tag the AI can act on:
 *
 *   normal              — individual mistake; counts toward mechanic failures
 *   mechanic_oneshot    — the boss did something that one-shot 5+ players (still
 *                         a real failure: raid didn't soak/dodge); counted as failure
 *   tank_loss_cascade   — main tank died, then 5+ raid members fell to the
 *                         untanked boss within 10s; not the dead players' fault
 *   wipe_called         — RL called /wipe; deaths cluster with diverse killing
 *                         blows, boss HP > 30%; not a real mistake
 *
 * Heuristic — for each fight, find death clusters (≥5 deaths within a 3s window)
 * and classify the cluster:
 *   1. boss_pct (HP at end of pull) ≤ 30%        → normal (legit progression wipe)
 *   2. one killing_blow shared by 80%+ of cluster → mechanic_oneshot
 *   3. main tank died within 10s before cluster   → tank_loss_cascade
 *   4. otherwise                                  → wipe_called
 *
 * Deaths outside a cluster keep tag=normal.
 *
 * Output: enriches each death entry in-place with:
 *   tag                 string  one of the 4 tags above
 *   suppressed_as_wipe_call    bool  true iff tag === 'wipe_called'
 *   suppressed_as_tank_loss    bool  true iff tag === 'tank_loss_cascade'
 *
 * Downstream consumers that count "real failures" should skip deaths where
 * either suppression flag is true. mechanic_oneshot stays counted.
 */
class WipeDetector
{
    private const CLUSTER_WINDOW_MS = 3000;
    private const CLUSTER_MIN_DEATHS = 5;
    private const BOSS_HP_THRESHOLD_PCT = 30.0;
    private const ONESHOT_DOMINANCE = 0.80;
    private const TANK_LOSS_LOOKBACK_MS = 10000;

    /**
     * Tag every death in the grouped deaths structure produced by
     * WclReportParserHelper::parseDeaths().
     *
     * @param array $deathsGrouped   [bossName][try_N][] => death
     * @param array $tries           Phase summary tries — used for boss_pct & tank lookup
     * @param array $tankNames       List of player names known to be tanks
     */
    public function tag(array &$deathsGrouped, array $tries, array $tankNames): void
    {
        $bossPctByFight = $this->indexBossPctByFight($tries);
        $tankSet = array_flip($tankNames);

        foreach ($deathsGrouped as $bossName => &$byTry) {
            foreach ($byTry as $tryKey => &$deaths) {
                $deaths = $this->tagFight($deaths, $bossPctByFight, $tankSet);
            }
            unset($deaths);
        }
        unset($byTry);
    }

    /**
     * @param array<int,array> $deaths     deaths in one fight, may be unsorted
     * @param array<int,float> $bossPctByFight
     * @param array<string,int> $tankSet
     * @return array<int,array> deaths with tags injected
     */
    private function tagFight(array $deaths, array $bossPctByFight, array $tankSet): array
    {
        if (empty($deaths)) return $deaths;

        // Sort chronologically
        usort($deaths, fn($a, $b) => ($a['time_ms_relative'] ?? 0) <=> ($b['time_ms_relative'] ?? 0));

        // Default everyone to normal
        foreach ($deaths as &$d) {
            $d['tag'] = 'normal';
            $d['suppressed_as_wipe_call'] = false;
            $d['suppressed_as_tank_loss'] = false;
        }
        unset($d);

        $clusters = $this->findClusters($deaths);
        if (empty($clusters)) return $deaths;

        // Use the fight_id from the first death (all deaths in a fight share fight_id)
        $fightId = $deaths[0]['fight_id'] ?? null;
        $bossPct = $bossPctByFight[$fightId] ?? null;

        // If boss died (or was within 30%), every cluster is a legit progression wipe.
        if ($bossPct !== null && $bossPct <= self::BOSS_HP_THRESHOLD_PCT) {
            return $deaths;
        }

        foreach ($clusters as $cluster) {
            $tag = $this->classifyCluster($cluster, $deaths, $tankSet);
            if ($tag === 'normal') continue;

            foreach ($cluster as $idx) {
                $deaths[$idx]['tag'] = $tag;
                $deaths[$idx]['suppressed_as_wipe_call'] = ($tag === 'wipe_called');
                $deaths[$idx]['suppressed_as_tank_loss'] = ($tag === 'tank_loss_cascade');
            }
        }

        return $deaths;
    }

    /**
     * Sliding-window cluster finder. Returns clusters as lists of indices into
     * the (already sorted) $deaths array. A death may belong to at most one
     * cluster (the earliest one that contains it).
     *
     * @return array<int, array<int,int>>
     */
    private function findClusters(array $deaths): array
    {
        $clusters = [];
        $n = count($deaths);
        $consumed = [];

        for ($i = 0; $i < $n; $i++) {
            if (isset($consumed[$i])) continue;
            $startMs = $deaths[$i]['time_ms_relative'] ?? null;
            if ($startMs === null) continue;

            $clusterIdx = [$i];
            for ($j = $i + 1; $j < $n; $j++) {
                if (isset($consumed[$j])) continue;
                $tMs = $deaths[$j]['time_ms_relative'] ?? null;
                if ($tMs === null) continue;
                if ($tMs - $startMs > self::CLUSTER_WINDOW_MS) break;
                $clusterIdx[] = $j;
            }

            if (count($clusterIdx) >= self::CLUSTER_MIN_DEATHS) {
                foreach ($clusterIdx as $k) $consumed[$k] = true;
                $clusters[] = $clusterIdx;
            }
        }
        return $clusters;
    }

    /**
     * Classify a single cluster. Inputs:
     *   $cluster  — indices into $deaths
     *   $deaths   — full sorted death list (so we can look back for tank death)
     *   $tankSet  — flipped tank names → 1
     */
    private function classifyCluster(array $cluster, array $deaths, array $tankSet): string
    {
        $size = count($cluster);

        // 1. mechanic_oneshot — single ability dominant
        $blowCounts = [];
        foreach ($cluster as $idx) {
            $guid = $deaths[$idx]['killing_blow_guid'] ?? null;
            $key = $guid !== null ? (string) $guid : (string) ($deaths[$idx]['killing_blow'] ?? 'unknown');
            $blowCounts[$key] = ($blowCounts[$key] ?? 0) + 1;
        }
        $maxShare = max($blowCounts) / $size;
        if ($maxShare >= self::ONESHOT_DOMINANCE) {
            return 'mechanic_oneshot';
        }

        // 2. tank_loss_cascade — tank died within lookback window before cluster start
        if (!empty($tankSet)) {
            $clusterStartMs = $deaths[$cluster[0]]['time_ms_relative'] ?? PHP_INT_MAX;
            $earliestAllowed = $clusterStartMs - self::TANK_LOSS_LOOKBACK_MS;

            foreach ($deaths as $i => $d) {
                if (in_array($i, $cluster, true)) break; // tank death in cluster doesn't count as preceding
                if (!isset($tankSet[$d['player'] ?? ''])) continue;
                $tMs = $d['time_ms_relative'] ?? null;
                if ($tMs !== null && $tMs >= $earliestAllowed && $tMs <= $clusterStartMs) {
                    return 'tank_loss_cascade';
                }
            }
        }

        // 3. wipe_called — diverse killing blows, no tank loss → RL called wipe
        return 'wipe_called';
    }

    /**
     * @return array<int,float>  fight_id => boss_pct (HP at end of pull)
     */
    private function indexBossPctByFight(array $tries): array
    {
        $out = [];
        foreach ($tries as $bossTries) {
            if (!is_array($bossTries)) continue;
            foreach ($bossTries as $t) {
                $fid = $t['fight_id'] ?? null;
                if ($fid === null) continue;
                $out[$fid] = isset($t['boss_pct']) ? (float) $t['boss_pct'] : 0.0;
            }
        }
        return $out;
    }
}
