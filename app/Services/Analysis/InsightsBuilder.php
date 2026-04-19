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
                    $c = (int) ($p['failures'] ?? $p['failure_count'] ?? $p['count'] ?? 0);
                    $total += $c;
                    if ($c > $topCount) {
                        $topCount = $c;
                        $topPlayer = $p['name'] ?? null;
                    }
                }
                if ($total === 0 && empty($mf['players'])) continue;

                $name = $mf['mechanic_name'] ?? $mf['mechanic'] ?? $mf['name'] ?? 'mechanic';
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

    /**
     * Build a per-player performance summary — RAW aggregated data without any scoring,
     * tiering, or ordering. The AI decides composite scores and tiers using raid-specific
     * context (which mechanic was actually costly on a kill pull vs trivial on a filler trash
     * wipe, for example) that a static PHP formula cannot see.
     *
     * Output: flat list, one entry per player, tank/healer/dps role tagged but not sorted.
     *
     * @return array<int, array>
     */
    public function buildPlayerPerformanceSummary(array $encounters, array $perPlayerData): array
    {
        $mechFailures = $this->aggregateMechanicFailuresDetailed($encounters);
        $deathsByBoss = $this->aggregateDeathsByBoss($perPlayerData);

        $out = [];
        foreach ($perPlayerData as $name => $entry) {
            $avoidableTotal = 0;
            foreach ($entry['avoidable_damage_taken'] ?? [] as $ad) {
                $avoidableTotal += (int) ($ad['total'] ?? $ad['amount'] ?? 0);
            }

            $interruptsTotal = 0;
            foreach ($entry['interrupt_contribution'] ?? [] as $count) {
                $interruptsTotal += (int) $count;
            }

            $out[] = [
                'name'            => $name,
                'class'           => (string) ($entry['class'] ?? ''),
                'spec'            => (string) ($entry['spec'] ?? ''),
                'role'            => $this->normalizeRole($entry['role'] ?? 'dps'),
                'ilvl'            => $entry['ilvl'] ?? null,

                'parse_pct'       => $entry['parse_today_pct'] ?? $entry['parse_pct'] ?? null,
                'dps'             => $entry['dps'] ?? null,
                'hps'             => $entry['hps'] ?? null,
                'hps_rank'        => $entry['hps_rank'] ?? null,
                'overheal_pct'    => $entry['overheal_pct'] ?? null,
                'add_damage_pct'  => $entry['add_damage_pct'] ?? null,

                'rotation_score'           => $entry['rotation_score']['score'] ?? null,
                'rotation_abilities_passing'  => $entry['rotation_score']['abilities_passing'] ?? null,
                'rotation_abilities_checked' => $entry['rotation_score']['abilities_checked'] ?? null,
                'rotation_issues_count'    => count($entry['rotation_issues'] ?? []),
                'rotation_issues_major'    => count(array_filter(
                    $entry['rotation_issues'] ?? [],
                    fn($i) => ($i['severity'] ?? '') === 'major'
                )),

                // Attendance — how many encounters the player actually participated in.
                // Lets the AI correctly judge benched players (low casts reflect missed
                // fights, not poor play).
                'encounters_participated' => $entry['rotation_analysis']['encounters_participated'] ?? null,
                'encounters_total'        => $entry['rotation_analysis']['total_encounters'] ?? null,
                'participated_seconds'    => $entry['rotation_analysis']['participated_seconds'] ?? null,

                'total_deaths'            => (int) ($entry['total_deaths'] ?? 0),
                'deaths_by_boss'          => $deathsByBoss[$name] ?? [],
                'avoidable_damage_total'  => $avoidableTotal,
                'interrupts_total'        => $interruptsTotal,

                'mechanic_failures_breakdown' => $mechFailures[$name] ?? [],
            ];
        }

        return $out;
    }

    /**
     * Per-player mechanic failure breakdown — grouped by boss/mechanic with severity and count.
     *
     * @return array<string, array<int, array{boss:string,mechanic:string,severity:string,count:int}>>
     */
    private function aggregateMechanicFailuresDetailed(array $encounters): array
    {
        $out = [];
        foreach ($encounters as $enc) {
            $boss = (string) ($enc['boss'] ?? '?');
            foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                $mechanic = (string) ($mf['mechanic_name'] ?? $mf['mechanic'] ?? $mf['name'] ?? '?');
                $severity = $this->normalizeSeverity($mf['severity'] ?? 'minor');
                foreach ($mf['players'] ?? [] as $p) {
                    $pname = $p['name'] ?? null;
                    if (!$pname) continue;
                    $count = (int) ($p['failures'] ?? $p['failure_count'] ?? $p['count'] ?? 0);
                    if ($count <= 0) continue;
                    $out[$pname][] = [
                        'boss'     => $boss,
                        'mechanic' => $mechanic,
                        'severity' => $severity,
                        'count'    => $count,
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * @return array<string, array<string, int>>  [playerName => [bossName => deathCount]]
     */
    private function aggregateDeathsByBoss(array $perPlayerData): array
    {
        $out = [];
        foreach ($perPlayerData as $name => $entry) {
            foreach ($entry['death_details'] ?? [] as $d) {
                $boss = (string) ($d['boss'] ?? '?');
                $out[$name][$boss] = ($out[$name][$boss] ?? 0) + 1;
            }
        }
        return $out;
    }

    // ─── CATEGORY A: SMART ANALYTICS FROM EXISTING DATA ────────────────────────

    /**
     * A1 — Death-to-mechanic correlation. For each death, attribute it to a tracked
     * mechanic (when killing_blow_guid matches a mechanic's ability_ids). Aggregates
     * per-player into avoidable vs unavoidable buckets.
     *
     * Mutates per_player_data[name]['death_attribution'].
     */
    public function correlateDeathsToMechanics(array $encounters, array &$perPlayerData, array $logData): void
    {
        // Build map: ability_id → {boss, mechanic_name, severity}
        $abilityToMech = [];
        foreach ($encounters as $enc) {
            $boss = $enc['boss'] ?? '?';
            foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                $mechName = $mf['mechanic_name'] ?? $mf['mechanic'] ?? $mf['name'] ?? 'unknown';
                foreach ((array) ($mf['ability_ids'] ?? []) as $aid) {
                    $aid = (int) $aid;
                    if ($aid <= 0) continue;
                    $abilityToMech[$aid] = [
                        'boss'     => $boss,
                        'mechanic' => $mechName,
                        'severity' => $this->normalizeSeverity($mf['severity'] ?? 'minor'),
                    ];
                }
            }
        }

        $deathsByPlayer = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) {
                    $name = $d['player'] ?? null;
                    if (!$name) continue;
                    $deathsByPlayer[$name][] = $d + ['boss' => $bossName];
                }
            }
        }

        foreach ($perPlayerData as $name => &$entry) {
            $deaths = $deathsByPlayer[$name] ?? [];
            if (empty($deaths)) continue;

            $byMechanic = [];
            $mechanicAttributed = 0;
            $unattributed = 0;

            foreach ($deaths as $d) {
                $guid = $d['killing_blow_guid'] ?? null;
                if ($guid && isset($abilityToMech[(int) $guid])) {
                    $hit = $abilityToMech[(int) $guid];
                    $key = "{$hit['boss']}::{$hit['mechanic']}";
                    $byMechanic[$key] = ($byMechanic[$key] ?? 0) + 1;
                    $mechanicAttributed++;
                } else {
                    $unattributed++;
                }
            }

            // Sort by count desc, take top 3
            arsort($byMechanic);
            $topMechs = array_slice($byMechanic, 0, 3, true);

            $entry['death_attribution'] = [
                'total_deaths'              => count($deaths),
                'mechanic_attributed'       => $mechanicAttributed,
                'unattributed'              => $unattributed,
                'top_mechanic_killers'      => $topMechs, // ["boss::mechanic" => count]
            ];
        }
    }

    /**
     * A2 — Detect cascade death clusters. A cluster = 3+ deaths within `windowSec` seconds
     * of each other in the same fight. These are typically wipe-trigger moments.
     *
     * Mutates encounters[i]['death_clusters'].
     */
    public function detectDeathClusters(array &$encounters, array $logData, int $windowSec = 10, int $minClusterSize = 3): void
    {
        $deathsByBoss = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) {
                    $deathsByBoss[$bossName][] = $d;
                }
            }
        }

        foreach ($encounters as &$enc) {
            $bossName = $enc['boss'] ?? null;
            $deaths = $deathsByBoss[$bossName] ?? [];
            if (count($deaths) < $minClusterSize) continue;

            // Group by fight_id, sort by relative ms, scan for clusters
            $byFight = [];
            foreach ($deaths as $d) {
                $fid = $d['fight_id'] ?? null;
                $tMs = $d['time_ms_relative'] ?? null;
                if ($fid === null || $tMs === null) continue;
                $byFight[$fid][] = ['ms' => $tMs, 'player' => $d['player'] ?? '?'];
            }

            $clusters = [];
            foreach ($byFight as $fid => $events) {
                usort($events, fn($a, $b) => $a['ms'] <=> $b['ms']);
                $i = 0;
                while ($i < count($events)) {
                    $start = $events[$i]['ms'];
                    $clusterMembers = [$events[$i]];
                    $j = $i + 1;
                    while ($j < count($events) && ($events[$j]['ms'] - $start) <= ($windowSec * 1000)) {
                        $clusterMembers[] = $events[$j];
                        $j++;
                    }
                    if (count($clusterMembers) >= $minClusterSize) {
                        $startSec = (int) round($start / 1000);
                        $endSec = (int) round(end($clusterMembers)['ms'] / 1000);
                        $clusters[] = [
                            'fight_id'      => $fid,
                            'window_start_s'=> $startSec,
                            'window_end_s'  => $endSec,
                            'span_s'        => $endSec - $startSec,
                            'death_count'   => count($clusterMembers),
                            'players'       => array_values(array_unique(array_column($clusterMembers, 'player'))),
                        ];
                        $i = $j;
                    } else {
                        $i++;
                    }
                }
            }

            if (!empty($clusters)) {
                // Sort clusters by death count desc, top 5
                usort($clusters, fn($a, $b) => $b['death_count'] <=> $a['death_count']);
                $enc['death_clusters'] = array_slice($clusters, 0, 5);
            }
        }
        unset($enc);
    }

    /**
     * A3 — Within-raid learning detection. For each encounter with 5+ pulls, analyze the
     * boss_pct trend across attempts. Are wipes getting closer to kill (improving) or
     * stagnating? Flag players whose deaths concentrate in early pulls (learning fast).
     *
     * Mutates encounters[i]['learning_trend'].
     */
    public function analyzeWithinRaidLearning(array &$encounters, array $logData): void
    {
        $deathsByBossFight = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) {
                    $fid = $d['fight_id'] ?? null;
                    if ($fid === null) continue;
                    $deathsByBossFight[$bossName][$fid][] = $d['player'] ?? '?';
                }
            }
        }

        foreach ($encounters as &$enc) {
            $pulls = $enc['pull_by_pull'] ?? [];
            if (count($pulls) < 5) continue;

            // Compute team boss_pct trend (smoothed): mean of first 1/3 vs last 1/3
            $third = max(1, (int) floor(count($pulls) / 3));
            $firstThird = array_slice($pulls, 0, $third);
            $lastThird  = array_slice($pulls, -$third);

            $firstAvg = $this->avgBossPct($firstThird);
            $lastAvg  = $this->avgBossPct($lastThird);

            $bossDelta = $firstAvg !== null && $lastAvg !== null ? round($firstAvg - $lastAvg, 1) : null;

            $verdict = 'plateau';
            if ($bossDelta !== null) {
                if ($bossDelta >= 10) $verdict = 'team_learning';     // wipe% dropped 10+ pts
                elseif ($bossDelta <= -10) $verdict = 'team_regressing';
                elseif (count(array_filter($pulls, fn($p) => ($p['outcome'] ?? '') === 'kill')) > 0) $verdict = 'killed_during_session';
            }

            // Per-player: who died only in early pulls (recovered)?
            $bossName = $enc['boss'] ?? '?';
            $deathsByFight = $deathsByBossFight[$bossName] ?? [];
            $perPlayerDeathPulls = [];
            $fidToPullIdx = [];
            foreach ($pulls as $idx => $p) {
                // pull_by_pull doesn't carry fight_id; reverse-engineer via $logData['phase_summary']
            }
            // Fallback: just compute count of deaths per pull index using phase_summary tries order
            $tries = $logData['phase_summary'][$bossName] ?? [];
            foreach ($tries as $i => $t) {
                $fid = $t['fight_id'];
                foreach ($deathsByFight[$fid] ?? [] as $playerName) {
                    $perPlayerDeathPulls[$playerName][] = $i + 1;
                }
            }

            $recovered = [];
            $persistent = [];
            $totalPulls = count($pulls);
            foreach ($perPlayerDeathPulls as $pname => $pullIdxList) {
                if (count($pullIdxList) < 2) continue;
                $maxPullIdx = max($pullIdxList);
                if ($maxPullIdx <= $totalPulls / 2 && count($pullIdxList) >= 2) {
                    $recovered[] = $pname; // all deaths in early half
                } elseif (count($pullIdxList) >= max(3, $totalPulls / 3)) {
                    $persistent[] = $pname; // dying throughout
                }
            }

            $enc['learning_trend'] = [
                'pulls_total'      => $totalPulls,
                'first_third_avg_boss_pct' => $firstAvg,
                'last_third_avg_boss_pct'  => $lastAvg,
                'boss_pct_delta'   => $bossDelta,
                'verdict'          => $verdict,
                'recovered_players'  => array_values(array_unique($recovered)),
                'persistent_failers' => array_values(array_unique($persistent)),
            ];
        }
        unset($enc);
    }

    private function avgBossPct(array $pulls): ?float
    {
        $vals = array_filter(array_column($pulls, 'boss_pct'), fn($v) => $v !== null);
        if (empty($vals)) return null;
        return round(array_sum($vals) / count($vals), 1);
    }

    /**
     * A4 — Killer ability identification. Across deaths in each encounter, rank the abilities
     * by victims claimed. Fastest way to know "X is wiping us".
     *
     * Mutates encounters[i]['killer_abilities'].
     */
    public function identifyKillerAbilities(array &$encounters, array $logData): void
    {
        $deathsByBoss = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) $deathsByBoss[$bossName][] = $d;
            }
        }

        foreach ($encounters as &$enc) {
            $bossName = $enc['boss'] ?? null;
            $deaths = $deathsByBoss[$bossName] ?? [];
            if (count($deaths) < 3) continue;

            $byAbility = [];
            foreach ($deaths as $d) {
                $ab = $d['killing_blow'] ?? 'Unknown';
                if ($ab === 'Unknown' || $ab === 'Unknown Ability') continue;
                $byAbility[$ab]['kill_count'] = ($byAbility[$ab]['kill_count'] ?? 0) + 1;
                $byAbility[$ab]['victims'][] = $d['player'] ?? '?';
            }
            if (empty($byAbility)) continue;

            $rows = [];
            foreach ($byAbility as $ability => $data) {
                $rows[] = [
                    'ability'     => $ability,
                    'kill_count'  => $data['kill_count'],
                    'unique_victims' => count(array_unique($data['victims'])),
                    'top_victims' => array_slice(array_count_values($data['victims']), 0, 3, true),
                ];
            }
            usort($rows, fn($a, $b) => $b['kill_count'] <=> $a['kill_count']);
            $enc['killer_abilities'] = array_slice($rows, 0, 5);
        }
        unset($enc);
    }

    /**
     * A5 — Mechanic specialists & weakest links. For each mechanic, identify players who
     * had ZERO failures (specialists) and players in the worst quartile (weak).
     *
     * Mutates encounters[i]['mechanic_specialists'].
     */
    public function identifyMechanicSpecialists(array &$encounters): void
    {
        foreach ($encounters as &$enc) {
            $byMech = [];
            foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                $mechName = $mf['mechanic_name'] ?? $mf['mechanic'] ?? $mf['name'] ?? null;
                if (!$mechName) continue;

                $players = $mf['players'] ?? [];
                if (count($players) < 2) continue; // need at least 2 samples to rank

                $counts = [];
                foreach ($players as $p) {
                    $name = $p['name'] ?? null;
                    if (!$name) continue;
                    $counts[$name] = (int) ($p['failures'] ?? $p['failure_count'] ?? $p['count'] ?? 0);
                }
                if (empty($counts)) continue;

                // Sort, find max, identify worst (>= 75th percentile)
                arsort($counts);
                $values = array_values($counts);
                $maxCount = max($values);
                $p75 = $values[(int) floor(count($values) * 0.25)] ?? $maxCount;
                $struggled = [];
                foreach ($counts as $name => $c) {
                    if ($c >= $p75 && $c >= 2) $struggled[] = ['name' => $name, 'failures' => $c];
                }

                $byMech[$mechName] = [
                    'severity'        => $mf['severity'] ?? 'minor',
                    'total_failures'  => array_sum($counts),
                    'unique_failers'  => count($counts),
                    'top_strugglers'  => array_slice($struggled, 0, 3),
                ];
            }
            if (!empty($byMech)) $enc['mechanic_specialists'] = $byMech;
        }
        unset($enc);
    }

    /**
     * A6 — Fatigue curve. For encounters with 6+ pulls, compare deaths/outcomes between
     * first and last third of pulls. Indicates raid lead should call a break.
     *
     * Mutates encounters[i]['fatigue_signal'].
     */
    public function analyzeFatigueCurves(array &$encounters, array $logData): void
    {
        $deathsByBossFight = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) {
                    $fid = $d['fight_id'] ?? null;
                    if ($fid === null) continue;
                    $deathsByBossFight[$bossName][$fid] = ($deathsByBossFight[$bossName][$fid] ?? 0) + 1;
                }
            }
        }

        foreach ($encounters as &$enc) {
            $bossName = $enc['boss'] ?? null;
            $tries = $logData['phase_summary'][$bossName] ?? [];
            $pullsCount = count($tries);
            if ($pullsCount < 6) continue;

            $third = (int) floor($pullsCount / 3);
            $firstThird = array_slice($tries, 0, $third);
            $lastThird  = array_slice($tries, -$third);

            $firstDeaths = 0;
            $lastDeaths = 0;
            foreach ($firstThird as $t) $firstDeaths += $deathsByBossFight[$bossName][$t['fight_id']] ?? 0;
            foreach ($lastThird  as $t) $lastDeaths  += $deathsByBossFight[$bossName][$t['fight_id']] ?? 0;

            $firstAvg = $third > 0 ? round($firstDeaths / $third, 1) : 0;
            $lastAvg  = $third > 0 ? round($lastDeaths  / $third, 1) : 0;
            $delta = $lastAvg - $firstAvg;

            $verdict = 'fresh';
            if ($delta >= 4) $verdict = 'severe_fatigue';
            elseif ($delta >= 2) $verdict = 'fatigue_visible';
            elseif ($delta <= -2) $verdict = 'momentum_building'; // getting better

            $enc['fatigue_signal'] = [
                'pulls_total'              => $pullsCount,
                'first_third_avg_deaths'   => $firstAvg,
                'last_third_avg_deaths'    => $lastAvg,
                'delta'                    => round($delta, 1),
                'verdict'                  => $verdict,
            ];
        }
        unset($enc);
    }

    /**
     * A7 — Carry / core / building player labels. Composite check across multiple metrics.
     *
     * Mutates per_player_data[name]['raid_role_label'].
     */
    public function identifyCarryPlayers(array &$perPlayerData): void
    {
        foreach ($perPlayerData as $name => &$entry) {
            $parse = (int) ($entry['parse_today_pct'] ?? $entry['parse_pct'] ?? 0);
            $rotation = (int) ($entry['rotation_score']['score'] ?? 0);
            $deaths = (int) ($entry['total_deaths'] ?? 0);
            $rotIssues = count($entry['rotation_issues'] ?? []);

            // Role-weighted; deaths intentionally omitted (progression nights inflate
            // everyone's deaths). If parse is 0/null (rankings fetch missed), fall back
            // to rotation-only judgment.
            $role = $this->normalizeRole($entry['role'] ?? 'dps');
            $parseAvailable = $parse > 0;
            $label = null;

            if ($role === 'tank') {
                if ($rotation >= 75 && $rotIssues <= 2) $label = 'carry';
                elseif ($rotation >= 55) $label = 'core';
                elseif ($rotation >= 35) $label = 'building';
            } elseif ($role === 'healer') {
                if ($parseAvailable) {
                    if ($parse >= 70 && $rotation >= 65 && $rotIssues <= 2) $label = 'carry';
                    elseif ($parse >= 50 && $rotation >= 45) $label = 'core';
                    elseif ($parse >= 30) $label = 'building';
                } else {
                    if ($rotation >= 75 && $rotIssues <= 2) $label = 'carry';
                    elseif ($rotation >= 55) $label = 'core';
                    elseif ($rotation >= 35) $label = 'building';
                }
            } else {
                // DPS
                if ($parseAvailable) {
                    if ($parse >= 75 && $rotation >= 65 && $rotIssues <= 2) $label = 'carry';
                    elseif ($parse >= 50 && $rotation >= 50) $label = 'core';
                    elseif ($parse >= 30) $label = 'building';
                } else {
                    if ($rotation >= 80 && $rotIssues <= 2) $label = 'carry';
                    elseif ($rotation >= 60) $label = 'core';
                    elseif ($rotation >= 40) $label = 'building';
                }
            }

            if ($label !== null) {
                $entry['raid_role_label'] = $label;
            }
        }
    }

    /**
     * A8 — Phase participation. For each player on each boss, compute typical phase reached
     * before death. Compares player's average to the team's average phase progression.
     *
     * Uses encounter.phase_progression + per_player death_details.
     * Mutates per_player_data[name]['phase_participation_by_boss'].
     */
    public function analyzePhaseParticipation(array $encounters, array &$perPlayerData, array $logData): void
    {
        // Build phase ordering per boss (which phase comes after which)
        $phaseOrderByBoss = [];
        foreach ($encounters as $enc) {
            $bossName = $enc['boss'] ?? null;
            if (!$bossName) continue;
            $phases = [];
            foreach ($enc['phase_progression'] ?? [] as $pp) {
                $name = $pp['phase'] ?? null;
                if ($name && !in_array($name, $phases, true)) $phases[] = $name;
            }
            $phaseOrderByBoss[$bossName] = $phases;
        }

        // Walk all deaths, infer phase from death_details (we don't have phase per death,
        // but pull_by_pull last_phase + death_count gives us a rough proxy: deaths in
        // pulls that ended in P1 are P1 deaths). Use pull-by-pull mapping.
        $deathsByPlayer = [];
        foreach ($logData['deaths'] ?? [] as $bossName => $byTry) {
            foreach ($byTry as $tryDeaths) {
                if (!is_array($tryDeaths)) continue;
                foreach ($tryDeaths as $d) {
                    $name = $d['player'] ?? null;
                    if (!$name) continue;
                    $deathsByPlayer[$name][$bossName][] = $d['fight_id'] ?? null;
                }
            }
        }

        // Build fight_id → last_phase map per boss
        $fightLastPhase = [];
        foreach ($logData['phase_summary'] ?? [] as $bossName => $tries) {
            foreach ($tries as $t) {
                $fightLastPhase[$bossName][$t['fight_id']] = $t['last_phase'] ?? null;
            }
        }

        foreach ($perPlayerData as $name => &$entry) {
            $byBoss = [];
            foreach ($deathsByPlayer[$name] ?? [] as $bossName => $fightIds) {
                $phaseCounts = [];
                foreach ($fightIds as $fid) {
                    $ph = $fightLastPhase[$bossName][$fid] ?? null;
                    if ($ph) $phaseCounts[$ph] = ($phaseCounts[$ph] ?? 0) + 1;
                }
                if (!empty($phaseCounts)) $byBoss[$bossName] = $phaseCounts;
            }
            if (!empty($byBoss)) {
                $entry['deaths_by_phase_by_boss'] = $byBoss;
            }
        }
    }

    private function normalizeRole(string $role): string
    {
        $r = strtolower($role);
        if (in_array($r, ['tank', 'healer'], true)) return $r;
        if (in_array($r, ['melee_dps', 'ranged_dps', 'dps'], true)) return 'dps';
        return 'dps';
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
