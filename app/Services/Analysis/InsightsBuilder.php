<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Aggregates secondary insights on top of TacticalDataAnalyzer's structured output:
 *
 *   - rotation_score (per player): 0-100 composite from rotation_analysis checks
 *   - focus_next_session: top-5 prescriptive items (mechanic failures + rotation majors)
 *   - pre_pull_readiness: flask/food/augment coverage with names missing
 *   - highlight_reel: positive callouts (0 deaths, high parse, progression kill)
 *
 * Called from TacticalDataAnalyzer::analyze() after RotationAnalyzer runs.
 */
class InsightsBuilder
{
    public const SEVERITY_ORDER = ['critical' => 3, 'major' => 2, 'minor' => 1];

    /** Assign rotation_score to each eligible player in-place. */
    public function attachRotationScores(array &$perPlayerData): void
    {
        foreach ($perPlayerData as &$entry) {
            $checks = $entry['rotation_analysis']['checks'] ?? [];
            if (empty($checks)) continue;

            $ratios = [];
            $passing = 0;
            foreach ($checks as $c) {
                $rec = $c['recommended_pct'] ?? 80;
                $eff = $c['efficiency_pct'] ?? 0;
                if ($rec <= 0) continue;
                $ratios[] = min($eff / $rec, 1.0);
                if (($c['status'] ?? 'passing') === 'passing') $passing++;
            }
            if (empty($ratios)) continue;

            $score = (int) round((array_sum($ratios) / count($ratios)) * 100);
            $entry['rotation_score'] = [
                'score'              => $score,
                'label'              => $this->scoreLabel($score),
                'abilities_checked'  => count($checks),
                'abilities_passing'  => $passing,
            ];
        }
    }

    private function scoreLabel(int $score): string
    {
        if ($score >= 95) return 'Elite';
        if ($score >= 85) return 'Strong';
        if ($score >= 70) return 'Solid';
        if ($score >= 50) return 'Needs Work';
        return 'Critical';
    }

    /**
     * Top 5 priority items for the next raid session. Mixes mechanic failures,
     * rotation majors, and consumable gaps by severity.
     *
     * @return array<int, array{title:string,detail:string,severity:string,source:string}>
     */
    public function buildFocusNextSession(array $encounters, array $perPlayerData, array $consumableAudit): array
    {
        $items = [];

        // 1. Mechanic failures — pull the top critical entries across encounters
        foreach ($encounters as $enc) {
            foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                $severity = $this->normalizeSeverity($mf['severity'] ?? 'minor');
                if ($severity === 'minor') continue;

                $total = 0;
                $topPlayer = null;
                $topCount = 0;
                foreach ($mf['players'] ?? [] as $p) {
                    $c = (int) ($p['failure_count'] ?? $p['count'] ?? 0);
                    $total += $c;
                    if ($c > $topCount) {
                        $topCount = $c;
                        $topPlayer = $p['name'] ?? null;
                    }
                }
                if ($total === 0 && empty($mf['players'])) continue;

                $name = $mf['mechanic'] ?? $mf['name'] ?? 'mechanic';
                $boss = $enc['boss'] ?? '';
                $detail = $total > 0
                    ? "{$total} failures across the raid on {$boss}" . ($topPlayer ? " (worst: {$topPlayer} x{$topCount})." : ".")
                    : ($mf['tactic_rule'] ?? $mf['description'] ?? "Recurring issue on {$boss}.");

                $items[] = [
                    'title'    => "{$name} — {$boss}",
                    'detail'   => $detail,
                    'severity' => $severity,
                    'source'   => 'mechanic_failure',
                    'rank'     => self::SEVERITY_ORDER[$severity] * 100 + $total,
                ];
            }
        }

        // 2. Rotation issues — major-severity only, aggregated per player
        foreach ($perPlayerData as $name => $entry) {
            foreach ($entry['rotation_issues'] ?? [] as $ri) {
                if (($ri['severity'] ?? 'minor') !== 'major') continue;
                $items[] = [
                    'title'    => "{$name} — {$ri['ability']} rotation",
                    'detail'   => $ri['issue'],
                    'severity' => 'major',
                    'source'   => 'rotation',
                    'rank'     => self::SEVERITY_ORDER['major'] * 100,
                ];
            }
        }

        // 3. Consumable gaps — single summary item if any category is below 80% coverage
        $consumableShort = $this->consumableGapSummary($consumableAudit);
        if ($consumableShort) {
            $items[] = [
                'title'    => 'Consumable Coverage Gap',
                'detail'   => $consumableShort,
                'severity' => 'major',
                'source'   => 'consumables',
                'rank'     => self::SEVERITY_ORDER['major'] * 100 - 10,
            ];
        }

        // Rank + top 5
        usort($items, fn($a, $b) => $b['rank'] <=> $a['rank']);
        $top = array_slice($items, 0, 5);
        return array_map(fn($i) => [
            'title'    => $i['title'],
            'detail'   => $i['detail'],
            'severity' => $i['severity'],
            'source'   => $i['source'],
        ], $top);
    }

    private function consumableGapSummary(array $consumableAudit): ?string
    {
        $raidSize = $consumableAudit['raid_size'] ?? 0;
        if ($raidSize === 0) return null;

        $perPlayer = $consumableAudit['per_player'] ?? null;
        if (!$perPlayer) return null;

        $shortCategories = [];
        foreach (['flask_coverage' => 'flask', 'food_coverage' => 'food', 'augment_rune_coverage' => 'augment rune'] as $k => $label) {
            $buffs = $consumableAudit[$k] ?? [];
            if (empty($buffs)) continue;
            $avg = array_sum(array_column($buffs, 'avg_players_per_fight'));
            if ($raidSize > 0 && $avg < $raidSize * 0.8) {
                $shortCategories[] = "{$label} (" . (int) round($avg) . "/{$raidSize})";
            }
        }
        if (empty($shortCategories)) return null;
        return "Categories below 80% coverage: " . implode(', ', $shortCategories) . ".";
    }

    /**
     * @return array{
     *   flask: array{coverage_pct:int,missing:array<int,string>},
     *   food: array{coverage_pct:int,missing:array<int,string>},
     *   augment_rune: array{coverage_pct:int,missing:array<int,string>}
     * }|null
     */
    public function buildPrePullReadiness(array $consumableAudit): ?array
    {
        $raidSize = $consumableAudit['raid_size'] ?? 0;
        if ($raidSize === 0) return null;

        $perPlayer = $consumableAudit['per_player'] ?? [];
        $playersWithout = $perPlayer['players_without'] ?? [];
        $playersWith    = $perPlayer['players_with'] ?? [];

        $buckets = [
            'flask'        => $consumableAudit['flask_coverage'] ?? [],
            'food'         => $consumableAudit['food_coverage'] ?? [],
            'augment_rune' => $consumableAudit['augment_rune_coverage'] ?? [],
        ];

        $out = [];
        foreach ($buckets as $cat => $buffData) {
            if (empty($buffData)) continue;

            $buffNames = array_keys($buffData);

            // Primary coverage metric: sum avg_players_per_fight across all variants in this bucket.
            // Long-duration pre-pull buffs (flasks) don't emit applybuff events inside fights,
            // so per-player event detection is unreliable. Aggregate counts are authoritative.
            $avgSum = 0.0;
            foreach ($buffData as $d) {
                $avgSum += (float) ($d['avg_players_per_fight'] ?? 0);
            }
            $coveragePct = (int) round(min(100, $avgSum / $raidSize * 100));

            // Identify missing players ONLY when per-player event data is reliable. A player is
            // considered "had the category" if they appear in players_with for ANY variant. We
            // still fall back gracefully if events-based data is absent.
            $missing = [];
            $hadAnyVariant = [];
            $hasReliablePerPlayer = false;
            foreach ($buffNames as $bn) {
                if (isset($playersWith[$bn]) || isset($playersWithout[$bn])) {
                    $hasReliablePerPlayer = true;
                    foreach ($playersWith[$bn] ?? [] as $pn) $hadAnyVariant[$pn] = true;
                }
            }
            if ($hasReliablePerPlayer && $coveragePct < 100) {
                // Collect every player name referenced anywhere in the per-player audit as the
                // raid's player universe (prevents flagging alts / irrelevant accounts).
                $universe = [];
                foreach ($playersWith as $list)    foreach ($list as $pn) $universe[$pn] = true;
                foreach ($playersWithout as $list) foreach ($list as $pn) $universe[$pn] = true;
                foreach (array_keys($universe) as $pn) {
                    if (!isset($hadAnyVariant[$pn])) $missing[] = $pn;
                }
                sort($missing);
            }

            $out[$cat] = [
                'coverage_pct' => $coveragePct,
                'missing'      => $missing,
            ];
        }

        return empty($out) ? null : $out;
    }

    /**
     * Positive, celebration-worthy moments. Max 5 entries.
     *
     * @return array<int, array{type:string,text:string,player:?string}>
     */
    public function buildHighlightReel(array $encounters, array $perPlayerData, array $raidSummary): array
    {
        $highlights = [];

        // 1. Progression kill (kill with multiple prior wipes on this boss)
        foreach ($encounters as $enc) {
            if (($enc['kills'] ?? 0) > 0 && ($enc['wipes'] ?? 0) >= 5) {
                $boss = $enc['boss'];
                $w = $enc['wipes'];
                $highlights[] = [
                    'type'   => 'progression_kill',
                    'text'   => "Progression kill on {$boss} after {$w} wipes — effort paid off.",
                    'player' => null,
                    'rank'   => 1000 + $w,
                ];
            }
        }

        // 2. Zero deaths across the entire raid (activity > 0)
        foreach ($perPlayerData as $name => $entry) {
            $deaths = $entry['total_deaths'] ?? 0;
            $active = ($entry['boss_damage'] ?? 0) > 0 || ($entry['hps'] ?? 0) > 0 || ($entry['dps'] ?? 0) > 0;
            if ($deaths === 0 && $active) {
                $highlights[] = [
                    'type'   => 'zero_deaths',
                    'text'   => "{$name}: 0 deaths across the session — flawless survival.",
                    'player' => $name,
                    'rank'   => 500,
                ];
            }
        }

        // 3. High parse (>=90 today_pct OR overall parse_pct)
        foreach ($perPlayerData as $name => $entry) {
            $parse = max(
                (int) ($entry['parse_today_pct'] ?? 0),
                (int) ($entry['parse_pct'] ?? 0)
            );
            if ($parse >= 90) {
                $highlights[] = [
                    'type'   => 'high_parse',
                    'text'   => "{$name}: parse {$parse} — top-tier performance for spec.",
                    'player' => $name,
                    'rank'   => 400 + $parse,
                ];
            }
        }

        // 4. Elite rotation score (>=95)
        foreach ($perPlayerData as $name => $entry) {
            $score = $entry['rotation_score']['score'] ?? null;
            if ($score !== null && $score >= 95) {
                $highlights[] = [
                    'type'   => 'rotation_elite',
                    'text'   => "{$name}: rotation score {$score}/100 — near-perfect ability usage.",
                    'player' => $name,
                    'rank'   => 300 + $score,
                ];
            }
        }

        usort($highlights, fn($a, $b) => $b['rank'] <=> $a['rank']);
        $top = array_slice($highlights, 0, 5);
        return array_map(fn($h) => [
            'type'   => $h['type'],
            'text'   => $h['text'],
            'player' => $h['player'],
        ], $top);
    }

    private function normalizeSeverity(string $s): string
    {
        $s = strtolower($s);
        return match ($s) {
            'high', 'critical'   => 'critical',
            'medium', 'major'    => 'major',
            'low', 'minor'       => 'minor',
            default              => 'minor',
        };
    }
}
