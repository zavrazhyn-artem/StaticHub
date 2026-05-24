<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Resolves a per-pull "intent cutoff" — the moment a wipe was called, after
 * which deaths are not mistakes. Source-agnostic by design: Phase 1a feeds it
 * from the anchor death + WipeDetector terminal-abandon; Phase 2 will add addon
 * markers and manual overrides at higher priority.
 *
 * Source priority (Phase 1a subset): anchor+cascade > cascade_only > anchor_only(low).
 * Kills are never cut.
 */
class IntentCutoffResolver
{
    private const ANCHOR_NEAR_MS = 5000;
    private const AUTO_APPLY_CONFIDENCE = ['high', 'medium'];

    public function __construct(private readonly WipeDetector $wipeDetector) {}

    /**
     * @param array<int,array> $deaths     deaths for ONE fight (each has player, time_ms_relative)
     * @param array            $fightMeta  ['outcome','boss_pct','duration_s','fight_id']
     * @param list<string>     $anchorNames
     * @return array{cutoff_ms:int,source:string,confidence:string}|null
     */
    public function resolve(array $deaths, array $fightMeta, array $anchorNames): ?array
    {
        if (($fightMeta['outcome'] ?? 'wipe') === 'kill') {
            return null;
        }

        $durationMs = (int) (($fightMeta['duration_s'] ?? 0) * 1000);
        $bossPct = isset($fightMeta['boss_pct']) ? (float) $fightMeta['boss_pct'] : null;

        $terminal = $this->wipeDetector->detectTerminalAbandon($deaths, $bossPct, $durationMs);
        $anchorDeath = $this->earliestAnchorDeath($deaths, $anchorNames);

        if ($terminal !== null) {
            $near = $anchorDeath !== null && $anchorDeath >= ($terminal - self::ANCHOR_NEAR_MS);
            return [
                'cutoff_ms'  => $terminal,
                'source'     => $near ? 'anchor+cascade' : 'cascade_only',
                'confidence' => $near ? 'high' : 'medium',
            ];
        }

        if ($anchorDeath !== null) {
            // Anchor died but no corroborating cascade — low confidence.
            // Recorded for Phase 2 review; NOT auto-applied in Phase 1a.
            return [
                'cutoff_ms'  => $anchorDeath,
                'source'     => 'anchor_only',
                'confidence' => 'low',
            ];
        }

        return null;
    }

    /**
     * Partition grouped deaths at each wiped fight's intent cutoff. Post-cutoff
     * deaths move from 'deaths' into 'excluded_deaths'. Kills and low-confidence
     * (anchor_only) resolutions are left untouched in Phase 1a.
     *
     * @param array $deathsGrouped  [bossName][try_N][] => death (already WipeDetector-tagged)
     * @param array $phaseSummary   [bossName][] => ['fight_id','outcome','boss_pct','duration_s', ...]
     * @param list<string> $anchorNames
     * @return array{deaths:array, excluded_deaths:array, intent_cutoffs:array}
     */
    public function partition(array $deathsGrouped, array $phaseSummary, array $anchorNames): array
    {
        $metaByFight = [];
        foreach ($phaseSummary as $bossTries) {
            if (!is_array($bossTries)) continue;
            foreach ($bossTries as $t) {
                if (isset($t['fight_id'])) $metaByFight[$t['fight_id']] = $t;
            }
        }

        $excluded = [];
        $cutoffs = [];

        foreach ($deathsGrouped as $bossName => $byTry) {
            foreach ($byTry as $tryKey => $deaths) {
                if (empty($deaths)) continue;

                // All deaths in a fight share fight_id (see WipeDetector::tagFight).
                $fightId = $deaths[0]['fight_id'] ?? null;
                $meta = ($fightId !== null) ? ($metaByFight[$fightId] ?? []) : [];

                $res = $this->resolve($deaths, $meta, $anchorNames);
                if ($res === null || !in_array($res['confidence'], self::AUTO_APPLY_CONFIDENCE, true)) {
                    continue;
                }

                $cutoffMs = $res['cutoff_ms'];
                $kept = [];
                $cut = [];
                foreach ($deaths as $d) {
                    $t = $d['time_ms_relative'] ?? null;
                    if ($t !== null && (int) $t >= $cutoffMs) {
                        $cut[] = $d;
                    } else {
                        $kept[] = $d;
                    }
                }

                if (empty($cut)) continue;

                $deathsGrouped[$bossName][$tryKey] = $kept;
                $excluded[$bossName][$tryKey] = $cut;
                if ($fightId !== null) {
                    $cutoffs[$fightId] = [
                        'cutoff_ms'      => $cutoffMs,
                        'source'         => $res['source'],
                        'confidence'     => $res['confidence'],
                        'excluded_count' => count($cut),
                    ];
                }
            }
        }

        return [
            'deaths'          => $deathsGrouped,
            'excluded_deaths' => $excluded,
            'intent_cutoffs'  => $cutoffs,
        ];
    }

    private function earliestAnchorDeath(array $deaths, array $anchorNames): ?int
    {
        if (empty($anchorNames)) return null;
        $set = array_flip($anchorNames);
        $earliest = null;
        foreach ($deaths as $d) {
            if (!isset($set[$d['player'] ?? ''])) continue;
            $t = $d['time_ms_relative'] ?? null;
            if ($t === null) continue;
            $t = (int) $t;
            if ($earliest === null || $t < $earliest) $earliest = $t;
        }
        return $earliest;
    }
}
