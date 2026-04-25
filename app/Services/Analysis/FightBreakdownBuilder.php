<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Builds the per-fight (per-pull) breakdown attached to each encounter as
 * `fights[]`. Each entry is one pull, isolated — so the AI can analyse a
 * specific attempt without bleed from raid-wide aggregates.
 *
 * Output shape per fight:
 *   attempt              int      1-based index within encounter
 *   fight_id             int      WCL fight id
 *   outcome              string   kill | wipe
 *   duration_s           int
 *   boss_hp_end_pct      float
 *   last_phase           ?string
 *   deaths               array    each tagged by WipeDetector
 *   wipe_called_count    int      diagnostic counter
 *   tank_loss_count      int      diagnostic counter
 *   rotation_per_player  map      playerName => list of {ability, eff_pct, …}
 *
 * The AI groups deaths by killing_blow itself — no separate mechanic_failures
 * pass per fight (the encounter-level aggregate already covers that).
 *
 * Constructor deps: RotationAnalyzer (for per-fight cast_efficiency).
 */
class FightBreakdownBuilder
{
    public function __construct(private readonly RotationAnalyzer $rotation) {}

    /**
     * @param array  $tries              Boss's tries[] (from phase_summary)
     * @param array  $deathsByTry        Boss's deaths grouped by try_N (already tagged by WipeDetector)
     * @param array  $perFightCasts      fight_id => playerName => abilityName => count
     * @param array  $perPlayerData      Whole-raid per-player data (used for class+spec lookup)
     */
    public function build(
        array $tries,
        array $deathsByTry,
        array $perFightCasts,
        array $perPlayerData
    ): array {
        $out = [];
        foreach (array_values($tries) as $idx => $t) {
            $attempt = $idx + 1;
            $tryKey = "try_{$attempt}";
            $fightId = $t['fight_id'] ?? null;

            $deaths = $deathsByTry[$tryKey] ?? [];
            [$wipeCalled, $tankLoss] = $this->countSuppressionTags($deaths);

            $duration = (int) ($t['duration_s'] ?? 0);
            $rotation = $this->buildRotationPerPlayer(
                $perFightCasts[$fightId] ?? [],
                $duration,
                $perPlayerData
            );

            $out[] = [
                'attempt'             => $attempt,
                'fight_id'            => $fightId,
                'outcome'             => (string) ($t['outcome'] ?? 'wipe'),
                'duration_s'          => $duration,
                'boss_hp_end_pct'     => isset($t['boss_pct']) ? (float) $t['boss_pct'] : null,
                'last_phase'          => $t['last_phase'] ?? null,
                'deaths'              => $deaths,
                'wipe_called_count'   => $wipeCalled,
                'tank_loss_count'     => $tankLoss,
                'rotation_per_player' => $rotation,
            ];
        }
        return $out;
    }

    /**
     * @return array{0:int,1:int}  [wipe_called_count, tank_loss_count]
     */
    private function countSuppressionTags(array $deaths): array
    {
        $wipe = 0;
        $tank = 0;
        foreach ($deaths as $d) {
            if (!empty($d['suppressed_as_wipe_call'])) $wipe++;
            if (!empty($d['suppressed_as_tank_loss'])) $tank++;
        }
        return [$wipe, $tank];
    }

    /**
     * Run per-fight cast_efficiency for every player who participated in this
     * pull. Uses the same baseline thresholds as the raid-wide RotationAnalyzer
     * — short pulls naturally drop long-CD abilities (the analyzer skips when
     * max_casts < 1).
     *
     * @param array $playersCasts  playerName => abilityName => cast_count
     * @return array<string, array<int, array>>  playerName => rows
     */
    private function buildRotationPerPlayer(array $playersCasts, int $durationSec, array $perPlayerData): array
    {
        if ($durationSec < 30 || empty($playersCasts)) return [];

        $out = [];
        foreach ($playersCasts as $player => $casts) {
            if (!is_array($casts) || empty($casts)) continue;

            $entry = $perPlayerData[$player] ?? [];
            $rows = $this->rotation->evaluatePlayerRotation(
                $entry['class'] ?? null,
                $entry['spec'] ?? null,
                $casts,
                $durationSec
            );
            if (!empty($rows)) {
                $out[$player] = $rows;
            }
        }
        return $out;
    }

}
