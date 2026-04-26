<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Deterministic replacement for the old Flash preprocessing stage.
 * Takes raw WCL log data + YAML tactics and produces structured JSON
 * analysis consumed by the report generation model (Pro) or diagnostic agent.
 *
 * Pipeline:
 *   WCL getLogSummary() -> TacticalDataAnalyzer::analyze() -> JSON
 *
 * The output schema mirrors what Flash preprocessing used to emit:
 *   { raid_summary, encounters, per_player_data, consumable_audit, localization }
 */
class TacticalDataAnalyzer
{
    /** @var array<string,array<string,int>> ability => playerName => count */
    private array $interruptsByAbility = [];

    public function __construct(
        private readonly TacticsLoader $tacticsLoader,
        private readonly WclService $wclService,
        private readonly RotationAnalyzer $rotationAnalyzer,
        private readonly InsightsBuilder $insightsBuilder,
        private readonly SpecBaselineLoader $specBaselineLoader,
        private readonly CombatReferenceLoader $combatRefs,
        private readonly BossTimelineLoader $bossTimelineLoader,
        private readonly WipeDetector $wipeDetector,
        private readonly FightBreakdownBuilder $fightBreakdownBuilder,
    ) {}

    /**
     * Run full analysis. Returns preprocessed JSON-ready structure.
     *
     * @param array  $logData       Output of WclService::getLogSummary()
     * @param array  $localization  Raid leader + participant locales
     * @param array  $rosterNames   Optional roster filter (empty array = all players in log)
     */
    public function analyze(array $logData, array $localization = [], array $rosterNames = [], ?string $reportId = null): array
    {
        $difficulty = $this->resolveDifficulty($logData);
        $bossNames = array_keys($logData['phase_summary'] ?? []);

        // Apply roster filter to player list if provided
        $players = $logData['players'] ?? [];
        if (!empty($rosterNames)) {
            $players = array_values(array_filter(
                $players,
                fn($p) => in_array($p['name'] ?? '', $rosterNames)
            ));
        }
        $playerNames = array_column($players, 'name');

        // Tag every death with normal | wipe_called | tank_loss_cascade | mechanic_oneshot.
        // Mutates $logData['deaths'] in-place — downstream consumers (mechanic
        // failure detection, insights) skip entries flagged as suppressed.
        if (isset($logData['deaths'])) {
            $tankNames = $this->extractTankNames($logData['player_details'] ?? []);
            $this->wipeDetector->tag(
                $logData['deaths'],
                $logData['phase_summary'] ?? [],
                $tankNames
            );
        }

        $encounters = [];
        foreach ($bossNames as $bossName) {
            $encounters[] = $this->analyzeEncounter($bossName, $difficulty, $logData, $playerNames, $rosterNames, $reportId);
        }

        // Per-player consumable audit (who used flask/food/augment rune)
        if ($reportId && !empty($players)) {
            $allRaidFightIds = [];
            foreach ($logData['phase_summary'] ?? [] as $bossTries) {
                foreach ($bossTries as $t) {
                    if (isset($t['fight_id'])) $allRaidFightIds[] = $t['fight_id'];
                }
            }
            try {
                $logData['per_player_consumable_audit'] = $this->wclService->getPerPlayerConsumableAudit(
                    $reportId,
                    $allRaidFightIds,
                    $players,
                    $logData['consumable_buffs'] ?? []
                );
            } catch (\Exception $e) {
                // non-fatal
            }
        }

        $raidSummary = $this->buildRaidSummary($logData, $difficulty, $encounters);
        $perPlayerData = $this->buildPerPlayerData($logData, $encounters, $players);

        // Enrich per-player data with rotation_analysis + rotation_issues, AND attach
        // per-encounter rotation breakdowns to encounters[i].player_rotation (by reference).
        $totalDurationSeconds = (int) ($logData['fight_durations']['total_seconds'] ?? 0);
        $this->rotationAnalyzer->apply($perPlayerData, $encounters, $totalDurationSeconds);

        // Attach composite rotation_score (depends on rotation_analysis)
        $this->insightsBuilder->attachRotationScores($perPlayerData);

        $consumableAudit = $this->buildConsumableAudit($logData, count($playerNames));
        if (isset($logData['per_player_consumable_audit'])) {
            $consumableAudit['per_player'] = $logData['per_player_consumable_audit'];
        }

        // Wave 5A: deeper insights from existing data — mutates encounters + per_player_data
        $this->insightsBuilder->correlateDeathsToMechanics($encounters, $perPlayerData, $logData);
        $this->insightsBuilder->detectDeathClusters($encounters, $logData);
        $this->insightsBuilder->identifyKillerAbilities($encounters, $logData);
        $this->insightsBuilder->identifyMechanicSpecialists($encounters);
        $this->insightsBuilder->analyzeWithinRaidLearning($encounters, $logData);
        $this->insightsBuilder->analyzeFatigueCurves($encounters, $logData);
        $this->insightsBuilder->identifyCarryPlayers($perPlayerData);
        $this->insightsBuilder->analyzePhaseParticipation($encounters, $perPlayerData, $logData);

        // Secondary insights (depend on all above being populated)
        $focusNextSession  = $this->insightsBuilder->buildFocusNextSession($encounters, $perPlayerData, $consumableAudit);
        $prePullReadiness  = $this->insightsBuilder->buildPrePullReadiness($consumableAudit);
        $highlightReel     = $this->insightsBuilder->buildHighlightReel($encounters, $perPlayerData, $raidSummary);
        $playerPerformance = $this->insightsBuilder->buildPlayerPerformanceSummary($encounters, $perPlayerData);

        return [
            'raid_summary'                => $raidSummary,
            'encounters'                  => $encounters,
            'per_player_data'             => $perPlayerData,
            'consumable_audit'            => $consumableAudit,
            'focus_next_session'          => $focusNextSession,
            'pre_pull_readiness'          => $prePullReadiness,
            'highlight_reel'              => $highlightReel,
            'player_performance_summary'  => $playerPerformance,
            'localization'                => $localization,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveDifficulty(array $logData): string
    {
        $diffs = $logData['difficulties'] ?? [];
        if (in_array('mythic', $diffs)) return 'mythic';
        if (in_array('heroic', $diffs)) return 'heroic';
        if (in_array('normal', $diffs)) return 'normal';
        return $diffs[0] ?? 'unknown';
    }

    private function analyzeEncounter(string $bossName, string $difficulty, array $logData, array $playerNames, array $rosterNames = [], ?string $reportId = null): array
    {
        $tries = $logData['phase_summary'][$bossName] ?? [];
        $bossFightIds = array_column($tries, 'fight_id');
        $bossFightIdsLookup = array_flip($bossFightIds);

        // Load tactics (by encounter ID if we can find it, else by name)
        $tactics = $this->loadTacticsForBoss($bossName, $logData);

        // Targeted per-mechanic damage lookup (bypasses top-50 filter).
        $targetedDamage = [];
        $bossAdds = [];
        if ($reportId && !empty($bossFightIds)) {
            $abilityIds = [];
            foreach ($tactics['mechanics'] ?? [] as $m) {
                foreach ($m['ability_ids'] ?? [] as $aid) {
                    $abilityIds[] = (int) $aid;
                }
            }
            $abilityIds = array_values(array_unique($abilityIds));
            if (!empty($abilityIds)) {
                $targetedDamage = $this->wclService->getTargetedDamageTakenBatch(
                    $reportId,
                    $bossFightIds,
                    $abilityIds,
                    $rosterNames
                );
            }

            // Per-boss adds (avoids top-N global merge across all bosses)
            $bossAdds = $this->wclService->getBossAdds($reportId, $bossFightIds, $rosterNames);
        }

        // Per-encounter stats (casts/buffs/dispels/interrupts/debuffs/damage scoped to this boss only)
        $perEncounterStats = [];
        $bossDurationMs = 0;
        foreach ($tries as $t) {
            $bossDurationMs += ($t['duration_s'] ?? 0) * 1000;
        }
        if ($reportId && !empty($bossFightIds)) {
            $perEncounterStats = $this->wclService->getPerEncounterStats(
                $reportId,
                $bossFightIds,
                $rosterNames,
                $bossDurationMs,
                $logData['player_details'] ?? []
            );

            // Wave 1: cooldown timing analysis for all major CDs of participating specs
            $cdTimings = $this->collectCooldownTimings(
                $reportId,
                $bossFightIds,
                $logData,
                $tries,
                $rosterNames
            );
            if (!empty($cdTimings)) {
                $perEncounterStats['cooldown_timings'] = $cdTimings;
            }

            // Wave 2: external defensive cooldowns given/received
            $externalCds = $this->collectExternalCooldowns(
                $reportId,
                $bossFightIds,
                $logData
            );
            if (!empty($externalCds)) {
                $perEncounterStats['external_cooldowns'] = $externalCds;
            }

            // Wave 2: per-tank active mitigation uptime
            $tankMitigation = $this->collectTankMitigation(
                $reportId,
                $bossFightIds,
                $logData,
                $bossDurationMs
            );
            if (!empty($tankMitigation)) {
                $perEncounterStats['tank_mitigation'] = $tankMitigation;
            }

            // Wave 4: burst sync analysis (lust drops + personal CD alignment)
            $burstSync = $this->collectBurstSync(
                $reportId,
                $bossFightIds,
                $logData,
                $tries,
                $perEncounterStats['cooldown_timings'] ?? []
            );
            if (!empty($burstSync) && !empty($burstSync['lust_drops'])) {
                $perEncounterStats['burst_sync'] = $burstSync;
            }
        }

        // Wave 1: bucket deaths by phase using existing fight phase transitions
        $phaseDeaths = $this->bucketEncounterDeathsByPhase($logData, $bossName, $tries);

        $kills = count(array_filter($tries, fn($t) => ($t['outcome'] ?? '') === 'kill'));
        $wipes = count($tries) - $kills;
        $bestWipe = null;
        foreach ($tries as $t) {
            if (($t['outcome'] ?? '') === 'wipe' && isset($t['boss_pct'])) {
                if ($bestWipe === null || $t['boss_pct'] < $bestWipe) {
                    $bestWipe = $t['boss_pct'];
                }
            }
        }

        // Per-boss subsets of cross-fight data
        $deaths = $logData['deaths'][$bossName] ?? [];
        $orbStaggering = $this->filterOrbStaggering($logData['orb_staggering'] ?? [], $bossFightIdsLookup);
        $shieldedCasts = $this->filterShieldedCasts($logData['shielded_casts'] ?? [], $bossFightIdsLookup);
        $debuffStacks = $this->filterDebuffStacks($logData['debuff_stacks'] ?? [], $bossFightIdsLookup);

        // Build interrupters map BEFORE mechanic_failures so shield_interrupt case can use it.
        $this->interruptsByAbility = [];
        foreach ($logData['interrupts'] ?? [] as $intr) {
            $this->interruptsByAbility[$intr['enemy_ability'] ?? ''] = $intr['interrupted_by'] ?? [];
        }

        $mechanicFailures = $this->detectMechanicFailures(
            $tactics,
            $deaths,
            $logData['major_damage_taken'] ?? [],
            $debuffStacks,
            $orbStaggering,
            $shieldedCasts,
            $difficulty,
            $targetedDamage,
            $logData['enemy_buff_uptimes'] ?? [],
            // Use per-boss adds (precise). Empty array if this boss has no adds — better
            // than falling back to global top-N which would mix in other bosses' NPCs.
            $bossAdds
        );

        $interruptAnalysis = $this->buildInterruptAnalysis(
            $tactics,
            $logData['interrupts'] ?? [],
            $shieldedCasts,
            $playerNames
        );

        $addPerformance = $this->buildAddPerformance(
            $tactics,
            $bossAdds,
            $orbStaggering
        );

        $tankAnalysis = $this->buildTankAnalysis($tactics, $debuffStacks, $deaths);
        $healingAnalysis = $this->buildHealingAnalysis($tactics, $deaths, $debuffStacks, $logData);

        [$topPerformers, $worstPerformers] = $this->rankPerformers(
            $logData['performance_metrics'] ?? [],
            $deaths,
            $logData['interrupts'] ?? []
        );

        // Scheduled boss-mechanic timeline (same source as the Raid Planner UI).
        // Lets the AI correlate deaths & missed CDs with absolute-time scheduled
        // casts — e.g. "death at 134s vs Primordial Roar at 132s".
        $bossTimeline = $this->loadBossTimelineForEncounter($tactics, $difficulty);

        // Per-fight (per-pull) breakdown — isolates each attempt so the AI can
        // analyse ability usage / deaths / outcome WITHOUT mixing data across
        // pulls. The encounter-level aggregates above stay for raid-wide views.
        $fights = $this->fightBreakdownBuilder->build(
            $tries,
            $logData['deaths'][$bossName] ?? [],
            $logData['per_fight_casts'] ?? [],
            // perPlayerData isn't built yet at this point — pass players for class+spec lookup
            $this->playerSpecLookup($playerNames, $logData)
        );

        // Aggregate death-tag counts so the AI can separate /wipe-call deaths
        // from real mechanical kills in the narrative.
        $deathTagDistribution = $this->buildDeathTagDistribution($logData['deaths'][$bossName] ?? []);

        // Slim debuff-stacks payload — surfaces real max-stack and swap-timing
        // numbers to the AI so it stops hallucinating "you reached 12 stacks"
        // and "17 of 23 swaps were late". Keeps only the fields the AI needs.
        $debuffStacksPayload = $this->buildDebuffStacksPayload($debuffStacks);

        return [
            'boss'              => $bossName,
            'wcl_encounter_id'  => $tactics['wcl_encounter_id'] ?? null,
            'difficulty'        => $difficulty,
            'tries'             => count($tries),
            'kills'             => $kills,
            'wipes'             => $wipes,
            'best_wipe_pct'     => $bestWipe,
            'duration_seconds'  => (int) round($bossDurationMs / 1000),
            'phase_progression' => $this->buildPhaseProgression($tries),
            'phase_deaths'      => $phaseDeaths,
            'pull_by_pull'      => $this->buildPullByPull($tries),
            'mechanic_failures' => $mechanicFailures,
            'interrupt_analysis' => $interruptAnalysis,
            'add_performance'   => $addPerformance,
            'tank_analysis'     => $tankAnalysis,
            'healing_analysis'  => $healingAnalysis,
            'top_performers'    => $topPerformers,
            'worst_performers'  => $worstPerformers,
            'boss_timeline'     => $bossTimeline,
            'fights'            => $fights,
            'death_tag_distribution' => $deathTagDistribution,
            'debuff_stacks'     => $debuffStacksPayload,
            // Per-encounter raw stats (scoped to this boss's fights — replaces global supplementary)
            'player_stats'      => $perEncounterStats,
        ];
    }

    /**
     * Build a slim debuff-stacks payload tuned for AI consumption. Surfaces
     * `max_stacks_per_player`, per-attempt stacks-at-death, and the precomputed
     * swap_timing aggregate (late_swaps, total_swaps, avg_gap_ms) so the AI
     * can quote real numbers instead of inventing them in tank coaching prose.
     *
     * Strips the verbose `total_applications` / `avg_duration_ms` fields that
     * don't add coaching value.
     */
    private function buildDebuffStacksPayload(array $debuffStacks): array
    {
        $out = [];
        foreach ($debuffStacks as $debuffName => $data) {
            $entry = [];

            if (!empty($data['max_stacks_per_player'])) {
                $entry['max_stacks_per_player'] = $data['max_stacks_per_player'];
            }
            if (!empty($data['stacks_at_death_per_player'])) {
                $entry['stacks_at_death_per_player'] = $data['stacks_at_death_per_player'];
            }
            if (!empty($data['swap_timing']) && ($data['swap_timing']['total_swaps'] ?? 0) > 0) {
                $entry['swap_timing'] = [
                    'late_swaps'  => $data['swap_timing']['late_swaps'] ?? 0,
                    'total_swaps' => $data['swap_timing']['total_swaps'] ?? 0,
                    'avg_gap_ms'  => $data['swap_timing']['avg_gap_ms'] ?? null,
                    'max_gap_ms'  => $data['swap_timing']['max_gap_ms'] ?? null,
                ];
            }

            if (!empty($entry)) {
                $out[$debuffName] = $entry;
            }
        }
        return $out;
    }

    /**
     * Count deaths per WipeDetector tag so the AI can write "of 14 wipes,
     * 9 were /wipe-calls — 5 real mechanical wipes". Includes a 'real_failures'
     * convenience total for the narrative.
     *
     * @param array $deathsByTry  $logData['deaths'][bossName] — array keyed by try number
     */
    private function buildDeathTagDistribution(array $deathsByTry): array
    {
        $counts = [
            'normal' => 0,
            'mechanic_oneshot' => 0,
            'tank_loss_cascade' => 0,
            'wipe_called' => 0,
        ];

        foreach ($deathsByTry as $deaths) {
            if (!is_array($deaths)) continue;
            foreach ($deaths as $d) {
                $tag = $d['tag'] ?? 'normal';
                if (!isset($counts[$tag])) $tag = 'normal';
                $counts[$tag]++;
            }
        }

        $total = array_sum($counts);
        $real = $counts['normal'] + $counts['mechanic_oneshot'];

        return [
            'total_deaths'   => $total,
            'real_failures'  => $real,  // deaths AI should treat as coachable
            'suppressed'     => $counts['tank_loss_cascade'] + $counts['wipe_called'],
            'by_tag'         => $counts,
        ];
    }

    /**
     * Build a {playerName: {class, spec}} map so FightBreakdownBuilder can
     * resolve spec baselines without needing the full perPlayerData (which
     * isn't assembled until after analyzeEncounter() returns).
     */
    private function playerSpecLookup(array $playerNames, array $logData): array
    {
        $details = $logData['player_details'] ?? [];
        $out = [];
        foreach ($playerNames as $name) {
            $d = $details[$name] ?? [];
            $out[$name] = [
                'class' => $d['class'] ?? null,
                'spec'  => $d['spec']  ?? null,
            ];
        }
        return $out;
    }

    /**
     * Pluck tank names from player_details for WipeDetector lookup.
     */
    private function extractTankNames(array $playerDetails): array
    {
        $tanks = [];
        foreach ($playerDetails as $name => $details) {
            if (($details['role'] ?? null) === 'tanks') {
                $tanks[] = $name;
            }
        }
        return $tanks;
    }

    /**
     * Resolve the boss-timeline slug from the loaded tactics file (its source
     * filename matches the timeline slug — e.g. `vorasius.md` → `vorasius`)
     * and return the slim AI payload, or null if no timeline YAML exists.
     */
    private function loadBossTimelineForEncounter(array $tactics, string $difficulty): ?array
    {
        $sourceFile = $tactics['source_file'] ?? '';
        if (!$sourceFile) return null;

        $slug = preg_replace('/\.md$/i', '', $sourceFile);
        if (!$slug) return null;

        $season = (string) config('wow_season.current_season', 'midnight-s1');
        return $this->bossTimelineLoader->load($season, $slug, mb_strtolower($difficulty));
    }

    private function loadTacticsForBoss(string $bossName, array $logData): array
    {
        // Try resolving encounter_id via config mapping
        $configMap = config('wow_season.wcl_encounter_ids', []);
        $encounterId = null;
        foreach ($configMap as $id => $name) {
            if ($this->bossNameMatches($name, $bossName)) {
                $encounterId = $id;
                break;
            }
        }

        if ($encounterId !== null) {
            return $this->tacticsLoader->loadByEncounterId($encounterId);
        }

        return $this->tacticsLoader->loadByBossName($bossName);
    }

    private function bossNameMatches(string $a, string $b): bool
    {
        $norm = fn($s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', str_replace([',', "'", '’'], '', $s))));
        return $norm($a) === $norm($b);
    }

    // ─── MECHANIC FAILURE DETECTION ─────────────────────────────────────────

    private function detectMechanicFailures(array $tactics, array $deaths, array $majorDamageTaken, array $debuffStacks, array $orbStaggering, array $shieldedCasts, string $difficulty, array $targetedDamage = [], array $enemyBuffUptimes = [], array $addsDamage = []): array
    {
        $failures = [];

        foreach ($tactics['mechanics'] ?? [] as $mechanic) {
            if (($mechanic['mythic_only'] ?? false) && $difficulty !== 'mythic') {
                continue;
            }

            $mechanicName = $this->prettifyMechanicName($mechanic['name'] ?? 'Unknown');
            $abilityIds = array_map('intval', $mechanic['ability_ids'] ?? []);
            $aliases = $mechanic['aliases'] ?? [];

            $entry = [
                'mechanic_name'   => $mechanicName,
                'type'            => $mechanic['type'] ?? 'unknown',
                'severity'        => $mechanic['severity'] ?? 'major',
                'tactic_rule'     => $mechanic['description'] ?? '',
                'ability_ids'     => $abilityIds,
                'evidence'        => [],
                'players'         => [],
                'total_failures'  => 0,
                'notes'           => [],
            ];

            // Death-based evidence (match by guid OR by name substring OR aliases)
            $deathMatches = $this->findDeathsForMechanic($deaths, $abilityIds, $mechanicName, $aliases);
            if (!empty($deathMatches)) {
                $entry['evidence'][] = 'death';
                $entry['total_failures'] += count($deathMatches);
                foreach ($deathMatches as $dm) {
                    $entry['players'][$dm['player']] = ($entry['players'][$dm['player']] ?? 0) + 1;
                }
            }

            // Damage-taken-based evidence: prefer targetedDamage (precise per-ability),
            // fallback to majorDamageTaken top-N matching.
            $damageMatches = $this->collectTargetedDamage($targetedDamage, $abilityIds);
            $damageSource = empty($damageMatches) ? 'top_n' : 'targeted';
            if (empty($damageMatches)) {
                $damageMatches = $this->findDamageTakenForMechanic($majorDamageTaken, $abilityIds, $mechanicName, $aliases);
            }
            if (!empty($damageMatches)) {
                $entry['evidence'][] = 'damage_taken';
                $totalDamage = 0;
                $hits = 0;
                foreach ($damageMatches as $dm) {
                    $totalDamage += $dm['total_damage_to_raid'] ?? 0;
                    $hits += $dm['hit_count'] ?? count($dm['biggest_victims'] ?? []);
                    foreach ($dm['biggest_victims'] as $victim => $amount) {
                        $entry['players'][$victim] = ($entry['players'][$victim] ?? 0) + max(1, (int) round($amount / 1_000_000));
                    }
                }
                if ($totalDamage > 0) {
                    $entry['notes'][] = sprintf(
                        'Total %s damage: %s over %d player-hits (source: %s)',
                        $mechanicName,
                        number_format($totalDamage),
                        $hits,
                        $damageSource
                    );
                }
            }

            // Type-specific evidence (tank_swap, absorb_shield, shield_interrupt, orb_management, etc.)
            $this->applyTypeSpecificEvidence($entry, $mechanic, $debuffStacks, $orbStaggering, $shieldedCasts, $enemyBuffUptimes, $addsDamage);

            // Only include mechanics with some evidence
            if (!empty($entry['evidence'])) {
                arsort($entry['players']);
                $entry['players'] = array_slice(
                    array_map(
                        fn($name, $count) => ['name' => $name, 'failures' => $count],
                        array_keys($entry['players']),
                        array_values($entry['players'])
                    ),
                    0, 10
                );
                $entry['evidence'] = array_values(array_unique($entry['evidence']));
                $failures[] = $entry;
            }
        }

        return $failures;
    }

    /**
     * Convert YAML mechanic names from various styles (snake_case, Title Case) to Title Case.
     */
    private function prettifyMechanicName(string $raw): string
    {
        if (str_contains($raw, ' ')) return $raw;
        if (!str_contains($raw, '_')) return $raw;
        return ucwords(str_replace('_', ' ', $raw));
    }

    /**
     * Match death events to a mechanic using:
     *   - Primary: killing_blow_guid IN ability_ids
     *   - Fallback: killing_blow name contains mechanic name or any of its token pieces
     */
    private function findDeathsForMechanic(array $deaths, array $abilityIds, string $mechanicName, array $aliases = []): array
    {
        $matches = [];
        $nameTokens = $this->extractNameTokens($mechanicName);
        foreach ($aliases as $alias) {
            $nameTokens = array_merge($nameTokens, $this->extractNameTokens($alias));
        }
        $nameTokens = array_values(array_unique($nameTokens));
        $idSet = array_flip($abilityIds);

        foreach ($deaths as $tryKey => $tryDeaths) {
            foreach ($tryDeaths as $d) {
                // Skip deaths the WipeDetector flagged as RL /wipe call or
                // tank-loss cascade — those aren't player-mechanic mistakes.
                // mechanic_oneshot stays in (it IS a mechanic failure).
                if (!empty($d['suppressed_as_wipe_call']) || !empty($d['suppressed_as_tank_loss'])) {
                    continue;
                }

                $blow = $d['killing_blow'] ?? '';
                $blowGuid = $d['killing_blow_guid'] ?? null;

                $matched = ($blowGuid !== null && isset($idSet[$blowGuid]))
                    || $this->nameContainsAnyToken($blow, $nameTokens);

                if ($matched) {
                    $matches[] = [
                        'player'       => $d['player'] ?? 'Unknown',
                        'time'         => $d['time_into_fight'] ?? null,
                        'try'          => $tryKey,
                        'killing_blow' => $blow,
                    ];
                }
            }
        }
        return $matches;
    }

    /**
     * Collect per-ability damage from the targeted-damage batch (keyed by ability_id).
     */
    private function collectTargetedDamage(array $targetedDamage, array $abilityIds): array
    {
        $matches = [];
        foreach ($abilityIds as $aid) {
            if (isset($targetedDamage[$aid]) && !empty($targetedDamage[$aid]['biggest_victims'])) {
                $matches[] = $targetedDamage[$aid];
            }
        }
        return $matches;
    }

    /**
     * Find damage_taken entries whose ability matches mechanic (by guid or name).
     */
    private function findDamageTakenForMechanic(array $majorDamageTaken, array $abilityIds, string $mechanicName, array $aliases = []): array
    {
        $matches = [];
        $nameTokens = $this->extractNameTokens($mechanicName);
        foreach ($aliases as $alias) {
            $nameTokens = array_merge($nameTokens, $this->extractNameTokens($alias));
        }
        $nameTokens = array_values(array_unique($nameTokens));
        $idSet = array_flip($abilityIds);

        foreach ($majorDamageTaken as $entry) {
            $guid = $entry['ability_guid'] ?? null;
            $name = $entry['ability'] ?? '';

            $matched = ($guid !== null && isset($idSet[$guid]))
                || $this->nameContainsAnyToken($name, $nameTokens);

            if ($matched) {
                $matches[] = $entry;
            }
        }
        return $matches;
    }

    /**
     * Split a mechanic name into lowercase tokens for flexible matching.
     * "Entropic Unraveling" → ["entropic unraveling", "entropic", "unraveling"]
     * Single-word names also return their own lowercase form.
     */
    private function extractNameTokens(string $name): array
    {
        $lower = mb_strtolower(trim($name));
        if ($lower === '') return [];

        $tokens = [$lower];
        $parts = preg_split('/[\s\-\/]+/u', $lower) ?: [];
        foreach ($parts as $p) {
            if (mb_strlen($p) >= 4) {
                $tokens[] = $p;
            }
        }
        return array_values(array_unique($tokens));
    }

    private function nameContainsAnyToken(string $haystack, array $tokens): bool
    {
        if (empty($tokens)) return false;
        $hay = mb_strtolower($haystack);
        foreach ($tokens as $t) {
            if ($t !== '' && str_contains($hay, $t)) return true;
        }
        return false;
    }

    private function applyTypeSpecificEvidence(array &$entry, array $mechanic, array $debuffStacks, array $orbStaggering, array $shieldedCasts, array $enemyBuffUptimes = [], array $addsDamage = []): void
    {
        $type = $mechanic['type'] ?? '';
        $mechName = $mechanic['name'] ?? '';

        $tokens = $this->extractNameTokens($mechName);

        switch ($type) {
            case 'tank_swap':
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                    $threshold = $mechanic['stack_swap_threshold'] ?? 7;
                    $criticalDeath = $mechanic['critical_death_stacks'] ?? 10;

                    foreach ($data['max_stacks_per_player'] ?? [] as $p => $max) {
                        if ($max > $threshold) {
                            $entry['players'][$p] = ($entry['players'][$p] ?? 0) + 1;
                        }
                    }
                    if (($data['swap_timing']['late_swaps'] ?? 0) > 0) {
                        $entry['evidence'][] = 'late_swaps';
                        $entry['notes'][] = "Late swaps: {$data['swap_timing']['late_swaps']}/{$data['swap_timing']['total_swaps']} (avg gap {$data['swap_timing']['avg_gap_ms']}ms, max {$data['swap_timing']['max_gap_ms']}ms)";
                    }

                    foreach ($data['stacks_at_death_per_player'] ?? [] as $p => $deaths) {
                        foreach ($deaths as $d) {
                            if ($d['max_stacks'] >= $criticalDeath) {
                                $entry['evidence'][] = 'death_with_high_stacks';
                                $entry['notes'][] = "{$p} died on try fight={$d['fight_id']} with max_stacks={$d['max_stacks']} (killing_blow={$d['killing_blow']})";
                            }
                        }
                    }
                }
                break;

            case 'absorb_shield':
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                    $natural = $mechanic['natural_duration_ms'] ?? null;
                    $expected = $mechanic['expected_clear_ms'] ?? null;
                    $actual = $data['avg_duration_ms'] ?? null;

                    if ($actual && $natural && $actual >= $natural * 0.9) {
                        $entry['evidence'][] = 'absorb_not_cleared';
                        $entry['notes'][] = "Absorb NOT cleared: avg_duration={$actual}ms / natural={$natural}ms. Healers missed this mechanic.";
                    } elseif ($actual && $expected && $actual > $expected * 1.5) {
                        $entry['evidence'][] = 'absorb_cleared_slowly';
                        $entry['notes'][] = "Absorb cleared slowly: avg_duration={$actual}ms / expected≤{$expected}ms.";
                    }

                    // Add players who received the absorb debuff (so AI can name them)
                    foreach ($data['max_stacks_per_player'] ?? [] as $p => $max) {
                        $entry['players'][$p] = ($entry['players'][$p] ?? 0) + 1;
                    }
                }
                break;

            case 'shield_interrupt':
                $protects = $mechanic['protects_ability'] ?? null;
                if ($protects && isset($shieldedCasts[$protects])) {
                    $sc = $shieldedCasts[$protects];
                    if (($sc['casts_on_shielded'] ?? 0) > 0) {
                        $entry['evidence'][] = 'interrupts_on_shield';
                        $entry['total_failures'] += $sc['casts_on_shielded'];
                        $entry['notes'][] = "Clone shielded during {$sc['casts_on_shielded']}/{$sc['casts_total']} casts — identify unshielded target first.";
                    }
                }
                // List active interrupters of the protected ability (these players had the chance to waste interrupts on shield).
                if ($protects && !empty($this->interruptsByAbility[$protects] ?? [])) {
                    $interrupters = $this->interruptsByAbility[$protects];
                    arsort($interrupters);
                    $list = [];
                    foreach ($interrupters as $player => $count) {
                        $entry['players'][$player] = ($entry['players'][$player] ?? 0) + 1;
                        $list[] = "{$player}({$count})";
                    }
                    if (!empty($list)) {
                        $entry['notes'][] = "Active {$protects} interrupters who may have wasted attempts on shield: " . implode(', ', $list);
                    }
                }
                break;

            case 'orb_management':
                foreach (($mechanic['orb_npcs'] ?? []) as $orbName) {
                    if (!isset($orbStaggering[$orbName])) continue;
                    $data = $orbStaggering[$orbName];
                    if (($data['overlapping_kills'] ?? 0) > 0) {
                        $entry['evidence'][] = 'orb_overlap_kills';
                        $entry['total_failures'] += $data['overlapping_kills'];
                        $entry['notes'][] = "{$orbName}: {$data['overlapping_kills']} overlapping kills in " . count($data['kill_clusters'] ?? []) . " clusters (min interval {$data['min_interval_ms']}ms).";
                    }
                }
                break;

            case 'interrupt':
                // Handled by interrupt_analysis separately; noop here
                break;

            case 'knockback':
                $fallDeaths = $this->countDeathsByAbilityName($entry, 'Fall');
                if ($fallDeaths > 0) {
                    $entry['evidence'][] = 'fall_deaths';
                    $entry['notes'][] = "{$fallDeaths} fall deaths detected — likely knockback mismanagement.";
                }
                break;

            case 'positioning':
            case 'enrage_mechanic':
            case 'energy_ramp':
                // For boss self-buffs (Imperator's Glory, Aura of Wrath, Light Infused, Berserk),
                // check enemy_buff_uptimes and report uptime % as evidence.
                foreach ($enemyBuffUptimes as $buffName => $data) {
                    if (!$this->nameContainsAnyToken($buffName, $tokens)) continue;
                    $uptime = $data['uptime_pct'] ?? 0;
                    $uses = $data['uses'] ?? 0;
                    if ($uptime > 0 || $uses > 0) {
                        $entry['evidence'][] = 'enemy_buff_uptime';
                        $entry['notes'][] = "{$buffName}: uptime {$uptime}% over {$uses} applications.";
                    }
                }
                break;

            case 'tankbuster':
                // Tankbusters often have low total damage but high per-hit. The targeted_damage
                // result (if available) already gives us per-hit numbers; if no damage data and
                // no death evidence, mark as 'not triggered or fully mitigated'.
                if (empty($entry['evidence'])) {
                    $entry['evidence'][] = 'no_significant_damage';
                    $entry['notes'][] = "{$mechName}: no notable damage taken or deaths — fully mitigated or not triggered.";
                }
                break;

            case 'add_management':
            case 'add_kill_priority':
                // Match against target_damage.adds for damage dealt + top dealers as players.
                $addNpcs = $mechanic['add_npcs'] ?? [];
                if (empty($addNpcs)) $addNpcs = [$mechName];
                $totalDmg = 0;
                $found = false;
                $topDealers = [];
                foreach ($addNpcs as $addName) {
                    foreach ($addsDamage as $foundName => $data) {
                        if (stripos($foundName, $addName) === false) continue;
                        $totalDmg += $data['total'] ?? 0;
                        $found = true;
                        foreach ($data['top_sources'] ?? [] as $player => $dmg) {
                            $topDealers[$player] = ($topDealers[$player] ?? 0) + $dmg;
                        }
                    }
                }
                if ($found && $totalDmg > 0) {
                    $entry['evidence'][] = 'add_damage';
                    $entry['notes'][] = sprintf('Total damage to %s adds: %s', $mechName, number_format($totalDmg));
                    arsort($topDealers);
                    foreach (array_slice($topDealers, 0, 5, true) as $player => $dmg) {
                        $entry['players'][$player] = ($entry['players'][$player] ?? 0) + 1;
                    }
                }
                // If no add damage found, leave evidence empty — universal fallback below
                // will try matching by debuff (e.g. Fixate is a player debuff, not an add NPC).
                break;

            case 'tether':
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                    $entry['evidence'][] = 'tether_debuff';
                    $entry['notes'][] = "{$debuffName}: " . ($data['total_applications'] ?? 0) . ' applications.';
                    foreach ($data['max_stacks_per_player'] ?? [] as $p => $max) {
                        $entry['players'][$p] = ($entry['players'][$p] ?? 0) + 1;
                    }
                }
                // Also check enemy_buff_uptimes (e.g. Twilight Bond as boss self-buff)
                foreach ($enemyBuffUptimes as $buffName => $data) {
                    if (!$this->nameContainsAnyToken($buffName, $tokens)) continue;
                    $uptime = $data['uptime_pct'] ?? 0;
                    $uses = $data['uses'] ?? 0;
                    if ($uptime > 0 || $uses > 0) {
                        $entry['evidence'][] = 'tether_buff_uptime';
                        $entry['notes'][] = "{$buffName}: uptime {$uptime}% over {$uses} applications.";
                    }
                }
                break;

            case 'phase_transition':
            case 'intermission':
                // Phase transitions are inferred from enemy buff uptime (boss self-buffs
                // applied at phase start) or via specific cast events. Use enemy_buff_uptimes.
                foreach ($enemyBuffUptimes as $buffName => $data) {
                    if (!$this->nameContainsAnyToken($buffName, $tokens)) continue;
                    $uses = $data['uses'] ?? 0;
                    if ($uses > 0) {
                        $entry['evidence'][] = 'phase_marker_buff';
                        $entry['notes'][] = "{$buffName}: triggered {$uses} times during fights.";
                    }
                }
                // Otherwise fall through to absorb_shield / soak detection if available
                if (empty($entry['evidence'])) {
                    foreach ($debuffStacks as $debuffName => $data) {
                        if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                        $entry['evidence'][] = 'phase_marker_debuff';
                        $entry['notes'][] = "{$debuffName}: " . ($data['total_applications'] ?? 0) . ' applications during transition.';
                    }
                }
                break;

            case 'soak':
                // Soak mechanics — players who took the soak debuff are the participants.
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                    $apps = $data['total_applications'] ?? 0;
                    $entry['evidence'][] = 'soak_debuff';
                    $entry['notes'][] = "{$debuffName}: {$apps} applications across the fight.";
                    foreach ($data['max_stacks_per_player'] ?? [] as $p => $max) {
                        $entry['players'][$p] = ($entry['players'][$p] ?? 0) + 1;
                    }
                }
                break;

            case 'fear':
                // Fear mechanics often have a cast that's interruptible — already handled by
                // interrupt_analysis. Also check debuff_stacks for the resulting fear effect.
                foreach ($debuffStacks as $debuffName => $data) {
                    if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                    $entry['evidence'][] = 'fear_debuff';
                    $entry['notes'][] = "{$debuffName}: " . ($data['total_applications'] ?? 0) . ' fear applications.';
                }
                break;
        }

        // Universal fallback — if no evidence collected, try matching by:
        //   1. EXACT ability_id (highest precision — only exact matches)
        //   2. Exact mechanic name (whole-string match, not token contains)
        // We avoid fuzzy token matching here because words like "Wrath" or "Light"
        // are common across many unrelated abilities (e.g. Tyr's Wrath vs Avenging Wrath).
        if (empty($entry['evidence'])) {
            $abilityIds = array_map('intval', $mechanic['ability_ids'] ?? []);
            $exactName = mb_strtolower(trim($mechName));

            $matchExact = function (string $candidateName, ?int $candidateGuid) use ($abilityIds, $exactName): bool {
                if ($candidateGuid !== null && in_array($candidateGuid, $abilityIds, true)) return true;
                return mb_strtolower(trim($candidateName)) === $exactName;
            };

            foreach ($enemyBuffUptimes as $buffName => $data) {
                if (!$matchExact($buffName, $data['guid'] ?? null)) continue;
                $uses = $data['uses'] ?? 0;
                $uptime = $data['uptime_pct'] ?? 0;
                if ($uses > 0 || $uptime > 0) {
                    $entry['evidence'][] = 'enemy_buff_observed';
                    $entry['notes'][] = "{$buffName}: observed {$uses} times (uptime {$uptime}%).";
                }
            }
            foreach ($debuffStacks as $debuffName => $data) {
                if (!$matchExact($debuffName, $data['guid'] ?? null)) continue;
                $apps = $data['total_applications'] ?? 0;
                if ($apps > 0) {
                    $entry['evidence'][] = 'player_debuff_observed';
                    $entry['notes'][] = "{$debuffName}: applied {$apps} times to players.";
                    foreach ($data['max_stacks_per_player'] ?? [] as $p => $max) {
                        $entry['players'][$p] = ($entry['players'][$p] ?? 0) + 1;
                    }
                }
            }
        }
    }

    private function countDeathsByAbilityName(array &$entry, string $needle): int
    {
        // helper placeholder — this info is already captured via findDeathsForMechanic
        // via "Fall" tokens when mechanic knockback has fallback keyword
        return 0;
    }

    // ─── INTERRUPT ANALYSIS ─────────────────────────────────────────────────

    private function buildInterruptAnalysis(array $tactics, array $interrupts, array $shieldedCasts, array $playerNames): array
    {
        $result = [];
        foreach ($tactics['mechanics'] ?? [] as $mechanic) {
            if (($mechanic['type'] ?? '') !== 'interrupt') continue;

            $abilityName = $mechanic['name'] ?? '';
            $threshold = $mechanic['miss_threshold_pct'] ?? 20;

            // Find matching interrupt entry
            foreach ($interrupts as $i) {
                if (stripos($i['enemy_ability'] ?? '', $abilityName) === false) continue;

                $interrupted = $i['total_interrupted'] ?? 0;
                $missed = $i['total_missed'] ?? 0;
                $total = $interrupted + $missed;
                $missRate = $total > 0 ? round(($missed / $total) * 100, 1) : 0;

                $severity = $mechanic['severity'] ?? 'major';
                if ($missRate > $threshold) {
                    $severity = 'critical';
                }

                $entry = [
                    'ability'           => $abilityName,
                    'total_casts'       => $total,
                    'interrupted'       => $interrupted,
                    'missed'            => $missed,
                    'miss_rate_pct'     => $missRate,
                    'severity'          => $severity,
                    'interrupters'      => $i['interrupted_by'] ?? [],
                    'wasted_on_shield'  => $shieldedCasts[$abilityName]['casts_on_shielded'] ?? 0,
                ];
                $result[] = $entry;
                break;
            }
        }
        return $result;
    }

    // ─── ADD PERFORMANCE ─────────────────────────────────────────────────────

    private function buildAddPerformance(array $tactics, array $adds, array $orbStaggering): array
    {
        $result = [];
        foreach ($adds as $addName => $data) {
            $entry = [
                'add_name'             => $addName,
                'total_damage_received' => $data['total'] ?? 0,
                'top_damage_dealers'   => $data['top_sources'] ?? [],
            ];

            // Check if this add has orb staggering data
            if (isset($orbStaggering[$addName])) {
                $os = $orbStaggering[$addName];
                $entry['overlapping_kills'] = $os['overlapping_kills'] ?? 0;
                $entry['kill_clusters_count'] = count($os['kill_clusters'] ?? []);
                $entry['avg_interval_ms'] = $os['avg_interval_ms'] ?? null;
                if (($os['overlapping_kills'] ?? 0) > 0) {
                    $entry['notable_issues'] = "{$os['overlapping_kills']} overlapping kills detected — stagger kills to avoid DoT overlap wipes.";
                }
            }

            $result[] = $entry;
        }
        return $result;
    }

    // ─── TANK / HEALING / PERFORMERS ─────────────────────────────────────────

    private function buildTankAnalysis(array $tactics, array $debuffStacks, array $deaths): array
    {
        $swapMechanic = null;
        foreach ($tactics['mechanics'] ?? [] as $m) {
            if (($m['type'] ?? '') === 'tank_swap') {
                $swapMechanic = $m['name'];
                break;
            }
        }

        if (!$swapMechanic) return ['note' => 'No tank swap mechanic defined in tactics.'];

        $tokens = $this->extractNameTokens($swapMechanic);
        $data = null;
        foreach ($debuffStacks as $name => $d) {
            if ($this->nameContainsAnyToken($name, $tokens)) {
                $data = $d;
                break;
            }
        }

        if (!$data) return ['swap_mechanic' => $swapMechanic, 'note' => 'No debuff stack data collected.'];

        return [
            'swap_mechanic' => $swapMechanic,
            'max_stacks_per_player' => $data['max_stacks_per_player'] ?? [],
            'swap_timing' => $data['swap_timing'] ?? null,
            'deaths_with_high_stacks' => $data['stacks_at_death_per_player'] ?? [],
        ];
    }

    private function buildHealingAnalysis(array $tactics, array $deaths, array $debuffStacks, array $logData): array
    {
        $absorbMechanics = array_filter($tactics['mechanics'] ?? [], fn($m) => ($m['type'] ?? '') === 'absorb_shield');
        $dispels = $logData['dispels'] ?? [];
        $deathsCount = 0;
        foreach ($deaths as $tryDeaths) {
            $deathsCount += count($tryDeaths);
        }

        $absorbStatus = [];
        foreach ($absorbMechanics as $m) {
            $name = $this->prettifyMechanicName($m['name'] ?? '');
            $tokens = $this->extractNameTokens($name);
            foreach ($debuffStacks as $debuffName => $data) {
                if (!$this->nameContainsAnyToken($debuffName, $tokens)) continue;
                $natural = $m['natural_duration_ms'] ?? null;
                $actual = $data['avg_duration_ms'] ?? null;
                $cleared = ($actual && $natural) ? ($actual < $natural * 0.7) : null;
                $absorbStatus[$name] = [
                    'avg_duration_ms' => $actual,
                    'natural_duration_ms' => $natural,
                    'being_cleared' => $cleared,
                ];
            }
        }

        return [
            'total_deaths' => $deathsCount,
            'dispel_performance' => $dispels,
            'absorb_shields' => $absorbStatus,
        ];
    }

    private function rankPerformers(array $performance, array $deaths, array $interrupts): array
    {
        // Count deaths per player
        $deathsPerPlayer = [];
        foreach ($deaths as $tryDeaths) {
            foreach ($tryDeaths as $d) {
                $p = $d['player'] ?? null;
                if ($p) $deathsPerPlayer[$p] = ($deathsPerPlayer[$p] ?? 0) + 1;
            }
        }

        $scored = [];
        foreach ($performance as $name => $p) {
            $parseScore = $p['parse_pct'] ?? 0;
            $deaths = $deathsPerPlayer[$name] ?? 0;
            $deathPenalty = $deaths * 10;
            $composite = $parseScore - $deathPenalty;
            $scored[$name] = [
                'composite_score' => $composite,
                'parse_pct' => $parseScore,
                'deaths' => $deaths,
                'dps' => $p['dps'] ?? null,
                'hps' => $p['hps'] ?? null,
            ];
        }

        uasort($scored, fn($a, $b) => $b['composite_score'] <=> $a['composite_score']);

        $top = [];
        $worst = [];
        $idx = 0;
        foreach ($scored as $name => $s) {
            $evidence = [];
            if ($s['parse_pct']) $evidence[] = "Parse: {$s['parse_pct']}%";
            if ($s['dps']) $evidence[] = "DPS: " . number_format($s['dps']);
            if ($s['hps']) $evidence[] = "HPS: " . number_format($s['hps']);
            if ($s['deaths']) $evidence[] = "Deaths: {$s['deaths']}";

            if ($idx < 5) {
                $top[] = ['name' => $name, 'evidence' => $evidence];
            }
            $idx++;
        }
        foreach (array_reverse($scored, true) as $name => $s) {
            $evidence = [];
            if ($s['parse_pct']) $evidence[] = "Parse: {$s['parse_pct']}%";
            if ($s['deaths']) $evidence[] = "Deaths: {$s['deaths']}";
            if ($s['dps']) $evidence[] = "DPS: " . number_format($s['dps']);
            $worst[] = ['name' => $name, 'evidence' => $evidence];
            if (count($worst) >= 5) break;
        }

        return [$top, $worst];
    }

    // ─── PER-PLAYER DATA ─────────────────────────────────────────────────────

    private function buildPerPlayerData(array $logData, array $encounters, array $players): array
    {
        $perPlayer = [];
        $performance = $logData['performance_metrics'] ?? [];
        $playerDetails = $logData['player_details'] ?? [];
        $majorDamageTaken = $logData['major_damage_taken'] ?? [];
        $targetDamage = $logData['target_damage']['per_player'] ?? [];
        $consumablesUsed = $logData['consumables_used'] ?? [];
        $resourceWaste = $logData['resource_waste'] ?? [];
        $interrupts = $logData['interrupts'] ?? [];
        $buffUptime = $logData['buff_uptime'] ?? [];

        foreach ($players as $player) {
            $name = $player['name'] ?? null;
            if (!$name) continue;

            $details = $playerDetails[$name] ?? [];
            $perf = $performance[$name] ?? [];
            $td = $targetDamage[$name] ?? [];

            // Collect deaths — pass through WipeDetector tags so the AI knows
            // which were the player's mistake vs suppressed (RL wipe / tank loss).
            $deathDetails = [];
            $mechanicFailures = [];
            foreach ($encounters as $enc) {
                $tryDeaths = $logData['deaths'][$enc['boss']] ?? [];
                foreach ($tryDeaths as $tryKey => $deaths) {
                    foreach ($deaths as $d) {
                        if (($d['player'] ?? '') !== $name) continue;
                        $deathDetails[] = [
                            'boss' => $enc['boss'],
                            'try' => $tryKey,
                            'time' => $d['time_into_fight'] ?? null,
                            'killing_blow' => $d['killing_blow'] ?? null,
                            'tag' => $d['tag'] ?? 'normal',
                            'suppressed_as_wipe_call' => !empty($d['suppressed_as_wipe_call']),
                            'suppressed_as_tank_loss' => !empty($d['suppressed_as_tank_loss']),
                        ];
                    }
                }
            }

            // Avoidable damage per player
            $avoidable = [];
            foreach ($majorDamageTaken as $dt) {
                if (!isset($dt['biggest_victims'][$name])) continue;
                $avoidable[] = [
                    'ability' => $dt['ability'],
                    'total'   => $dt['biggest_victims'][$name],
                    'is_top_victim' => array_key_first($dt['biggest_victims']) === $name,
                ];
            }
            // Also pull from encounters[].mechanic_failures — these include targeted_damage with
            // precise per-ability hits (where Zavrikk may not be in major_damage_taken top-5).
            foreach ($encounters as $enc) {
                foreach ($enc['mechanic_failures'] ?? [] as $mf) {
                    foreach ($mf['players'] ?? [] as $p) {
                        if (($p['name'] ?? '') !== $name) continue;
                        $existsAlready = false;
                        foreach ($avoidable as $av) {
                            if (stripos($av['ability'] ?? '', $mf['mechanic_name']) !== false) {
                                $existsAlready = true;
                                break;
                            }
                        }
                        if (!$existsAlready) {
                            $avoidable[] = [
                                'ability' => $mf['mechanic_name'],
                                'boss'    => $enc['boss'],
                                'failures' => $p['failures'] ?? 1,
                                'evidence' => $mf['evidence'] ?? [],
                            ];
                        }
                    }
                }
            }
            usort($avoidable, fn($a, $b) => ($b['total'] ?? 0) <=> ($a['total'] ?? 0));
            $avoidable = array_slice($avoidable, 0, 15);

            // Interrupt contribution
            $interruptContrib = [];
            foreach ($interrupts as $int) {
                $ability = $int['enemy_ability'] ?? '?';
                $count = $int['interrupted_by'][$name] ?? 0;
                if ($count > 0) $interruptContrib[$ability] = $count;
            }

            // Buff uptime notes — collect any major-cooldown-like buffs this class has
            $buffNotes = [];
            foreach ($buffUptime as $buffName => $data) {
                if (($data['total_uses'] ?? 0) > 0 && ($data['uptime_pct'] ?? 0) < 90) {
                    // Only report buffs with notable uptime variance
                    $buffNotes[$buffName] = $data['uptime_pct'];
                }
                if (count($buffNotes) >= 5) break;
            }

            $entry = [
                'role' => $details['role'] ?? null,
                'class' => $details['class'] ?? null,
                'spec' => $details['spec'] ?? null,
                'ilvl' => $details['avg_ilvl'] ?? null,
                'trinkets' => $details['trinkets'] ?? [],
                'stats' => $details['stats'] ?? [],
                'parse_pct' => $perf['parse_pct'] ?? null,
                'parse_today_pct' => $perf['parse_today_pct'] ?? null,
                'dps' => $perf['dps'] ?? null,
                'hps' => $perf['hps'] ?? null,
                'dps_rank' => $perf['dps_rank'] ?? null,
                'hps_rank' => $perf['hps_rank'] ?? null,
                'overheal_pct' => $perf['overheal_pct'] ?? null,
                'total_deaths' => count($deathDetails),
                'death_details' => $deathDetails,
                'avoidable_damage_taken' => $avoidable,
                'interrupt_contribution' => $interruptContrib,
                'add_damage_pct' => $td['add_pct'] ?? null,
                'boss_damage' => $td['boss_damage'] ?? null,
                'add_damage' => $td['add_damage'] ?? null,
                'consumables' => $consumablesUsed[$name] ?? [],
                'resource_waste_pct' => $resourceWaste[$name]['waste_pct'] ?? null,
                'buff_uptime_snippet' => $buffNotes,
            ];

            // Only include players who had actual activity in this report (took damage,
            // dealt damage, died, or have player_details). Skip empty placeholder entries.
            $hasActivity = !empty($details)
                || $entry['total_deaths'] > 0
                || $entry['boss_damage']
                || $entry['add_damage']
                || $entry['hps']
                || $entry['dps']
                || !empty($entry['avoidable_damage_taken'])
                || !empty($entry['interrupt_contribution']);

            if ($hasActivity) {
                $perPlayer[$name] = $entry;
            }
        }

        return $perPlayer;
    }

    // ─── CONSUMABLE AUDIT ─────────────────────────────────────────────────────

    private function buildConsumableAudit(array $logData, int $raidSize): array
    {
        $buffs = $logData['consumable_buffs'] ?? [];
        $perPlayer = $logData['per_player_consumable_audit'] ?? null;

        $flasks = [];
        $foods = [];
        $augRunes = [];
        $vantusRunes = [];

        foreach ($buffs as $name => $data) {
            $lower = mb_strtolower($name);
            if (str_contains($lower, 'flask') || str_contains($lower, 'phial')) {
                $flasks[$name] = $data;
            } elseif (str_contains($lower, 'well fed') || str_contains($lower, 'hearty')) {
                $foods[$name] = $data;
            } elseif (str_contains($lower, 'augment')) {
                $augRunes[$name] = $data;
            } elseif (str_contains($lower, 'vantus')) {
                $vantusRunes[$name] = $data;
            }
        }

        // Build issues
        $issues = [];
        $totalFlaskUsers = array_sum(array_column($flasks, 'avg_players_per_fight'));
        $totalFoodUsers = array_sum(array_column($foods, 'avg_players_per_fight'));
        $totalAugUsers = array_sum(array_column($augRunes, 'avg_players_per_fight'));

        if ($raidSize > 0 && $totalFlaskUsers < $raidSize * 0.8) {
            $issues[] = "Only {$totalFlaskUsers} avg players with flask (out of {$raidSize}).";
        }
        if ($raidSize > 0 && $totalFoodUsers < $raidSize * 0.8) {
            $issues[] = "Only {$totalFoodUsers} avg players with food (out of {$raidSize}).";
        }
        if ($raidSize > 0 && $totalAugUsers < $raidSize * 0.5) {
            $issues[] = "Only {$totalAugUsers} avg players with augment rune (out of {$raidSize}).";
        }

        return [
            'raid_size'          => $raidSize,
            'flask_coverage'     => $flasks,
            'food_coverage'      => $foods,
            'augment_rune_coverage' => $augRunes,
            'vantus_rune_usage'  => $vantusRunes,
            'issues'             => $issues,
        ];
    }

    // ─── RAID SUMMARY ─────────────────────────────────────────────────────────

    private function buildRaidSummary(array $logData, string $difficulty, array $encounters): array
    {
        $totalKills = 0;
        $totalWipes = 0;
        $killed = [];
        $wiped = [];

        foreach ($encounters as $enc) {
            $totalKills += $enc['kills'];
            $totalWipes += $enc['wipes'];
            if ($enc['kills'] > 0) $killed[] = $enc['boss'];
            if ($enc['wipes'] > 0 && $enc['kills'] === 0) $wiped[] = $enc['boss'];
        }

        $durationMin = round(($logData['fight_durations']['total_seconds'] ?? 0) / 60, 1);

        // Assessment
        $totalBosses = count($encounters);
        $assessment = 'progression';
        if ($totalBosses > 0) {
            if ($totalKills === $totalBosses && $totalWipes === 0) $assessment = 'clean_clear';
            elseif ($totalKills === 0) $assessment = 'disaster';
            elseif ($totalWipes >= $totalKills * 3) $assessment = 'struggling';
        }

        return [
            'title'                => ($logData['raid_title'] ?? 'Raid Analysis'),
            'bosses_killed'        => $killed,
            'bosses_wiped'         => $wiped,
            'total_kills'          => $totalKills,
            'total_wipes'          => $totalWipes,
            'raid_duration_minutes' => $durationMin,
            'difficulty'           => $difficulty,
            'overall_assessment'   => $assessment,
        ];
    }

    private function buildPhaseProgression(array $tries): array
    {
        $progression = [];
        foreach ($tries as $t) {
            $phase = $t['last_phase'] ?? 'Unknown';
            if (!isset($progression[$phase])) {
                $progression[$phase] = [
                    'phase' => $phase,
                    'wipes_here' => 0,
                    'best_attempt_pct' => 100,
                    'best_attempt_fight_id' => null,
                    'best_attempt_try_index' => null,
                ];
            }
            if (($t['outcome'] ?? '') === 'wipe') {
                $progression[$phase]['wipes_here']++;
                $bossPct = $t['boss_pct'] ?? 100;
                if ($bossPct < $progression[$phase]['best_attempt_pct']) {
                    $progression[$phase]['best_attempt_pct'] = $bossPct;
                    $progression[$phase]['best_attempt_fight_id'] = $t['fight_id'] ?? null;
                    // Find the try index (1-based) by matching fight_id position in the tries list
                    foreach ($tries as $idx => $try) {
                        if (($try['fight_id'] ?? null) === ($t['fight_id'] ?? null)) {
                            $progression[$phase]['best_attempt_try_index'] = $idx + 1;
                            break;
                        }
                    }
                }
            }
        }
        return array_values($progression);
    }

    // ─── FILTERING HELPERS ─────────────────────────────────────────────────────

    private function filterOrbStaggering(array $orbStaggering, array $fightIdLookup): array
    {
        $result = [];
        foreach ($orbStaggering as $addName => $data) {
            $clusters = array_values(array_filter(
                $data['kill_clusters'] ?? [],
                fn($c) => isset($fightIdLookup[$c['fight_id']])
            ));
            if (!empty($clusters)) {
                $result[$addName] = array_merge($data, ['kill_clusters' => $clusters, 'overlapping_kills' => array_sum(array_column($clusters, 'cluster_size'))]);
            }
        }
        return $result;
    }

    private function filterShieldedCasts(array $shieldedCasts, array $fightIdLookup): array
    {
        $result = [];
        foreach ($shieldedCasts as $ability => $data) {
            $details = array_values(array_filter(
                $data['shielded_details'] ?? [],
                fn($d) => isset($fightIdLookup[$d['fight']])
            ));
            if (!empty($details) || ($data['casts_total'] ?? 0) > 0) {
                $result[$ability] = array_merge($data, ['shielded_details' => $details]);
            }
        }
        return $result;
    }

    private function filterDebuffStacks(array $debuffStacks, array $fightIdLookup): array
    {
        $result = [];
        foreach ($debuffStacks as $debuffName => $data) {
            $filteredDeaths = [];
            foreach ($data['stacks_at_death_per_player'] ?? [] as $player => $deaths) {
                $keep = array_values(array_filter(
                    $deaths,
                    fn($d) => isset($fightIdLookup[$d['fight_id']])
                ));
                if (!empty($keep)) {
                    $filteredDeaths[$player] = $keep;
                }
            }
            $result[$debuffName] = array_merge($data, ['stacks_at_death_per_player' => $filteredDeaths]);
        }
        return $result;
    }

    // ─── WAVE 4: PULL-BY-PULL PROGRESSION ──────────────────────────────────────

    /**
     * Render the attempts list as a clean ordered timeline so the AI can show progression.
     *
     * @return array<int, array{attempt:int, outcome:string, last_phase:?string, boss_pct:?float, duration_s:int}>
     */
    private function buildPullByPull(array $tries): array
    {
        $out = [];
        foreach (array_values($tries) as $idx => $t) {
            $out[] = [
                'attempt'     => $idx + 1,
                'outcome'     => $t['outcome'] ?? 'wipe',
                'last_phase'  => $t['last_phase'] ?? null,
                'boss_pct'    => isset($t['boss_pct']) ? (float) $t['boss_pct'] : null,
                'duration_s'  => (int) ($t['duration_s'] ?? 0),
            ];
        }
        return $out;
    }

    // ─── WAVE 1: PHASE + COOLDOWN HELPERS ────────────────────────────────────

    /**
     * For each fight in this encounter, bucket deaths by the phase window the death occurred in.
     * Aggregates across attempts.
     *
     * @return array<string, array{deaths:int, attempts_with_deaths:int}>
     */
    private function bucketEncounterDeathsByPhase(array $logData, string $bossName, array $tries): array
    {
        $deathsByTry = $logData['deaths'][$bossName] ?? [];
        if (empty($deathsByTry)) return [];

        // Flatten try groups → flat list with fight_id + time_ms_relative on each entry.
        // Skip suppressed deaths (RL /wipe call, tank-loss cascade) — they're
        // not phase-attributable mistakes.
        $deathsByBoss = [];
        foreach ($deathsByTry as $tryDeaths) {
            if (!is_array($tryDeaths)) continue;
            foreach ($tryDeaths as $d) {
                if (!is_array($d)) continue;
                if (!empty($d['suppressed_as_wipe_call']) || !empty($d['suppressed_as_tank_loss'])) continue;
                $deathsByBoss[] = $d;
            }
        }

        // Build fight_id → [phase transitions, fight start]
        $rawFights = $logData['raid_fights'] ?? [];
        $bossFightIds = array_flip(array_column($tries, 'fight_id'));

        $fightInfo = [];
        foreach ($rawFights as $f) {
            $fid = $f['id'] ?? null;
            if ($fid === null || !isset($bossFightIds[$fid])) continue;
            $fightInfo[$fid] = [
                'startTime' => (int) ($f['startTime'] ?? 0),
                'phases'    => $f['phaseTransitions'] ?? [],
                'encounter' => (int) ($f['encounterID'] ?? 0),
            ];
        }
        if (empty($fightInfo)) return [];

        // Map encounterID → phase id → name
        $phaseNameById = [];
        foreach ($logData['report_phases'] ?? [] as $ep) {
            $eid = $ep['encounterID'] ?? 0;
            foreach ($ep['phases'] ?? [] as $ph) {
                $phaseNameById[$eid][$ph['id']] = $ph['name'] ?? "Phase {$ph['id']}";
            }
        }

        $phaseDeathCounts = [];
        $phaseAttemptsWithDeath = [];

        foreach ($fightInfo as $fid => $info) {
            $fightStartMs = $info['startTime'];
            $encId = $info['encounter'];
            $localPhaseNames = $phaseNameById[$encId] ?? [];

            // Convert phase transitions from absolute to relative (start at 0)
            $relPhases = [];
            foreach ($info['phases'] as $pt) {
                $relPhases[] = [
                    'id'        => $pt['id'] ?? 0,
                    'startTime' => max(0, ($pt['startTime'] ?? 0) - $fightStartMs),
                ];
            }
            // Implicit phase 1 starts at 0
            if (empty($relPhases) || $relPhases[0]['startTime'] > 0) {
                array_unshift($relPhases, ['id' => 1, 'startTime' => 0]);
            }

            // Player deaths for this fight
            $fightDeaths = array_filter($deathsByBoss, fn($d) => ($d['fight_id'] ?? null) === $fid);
            if (empty($fightDeaths)) continue;

            $attemptPhasesSeen = [];
            foreach ($fightDeaths as $d) {
                $tMs = $d['time_ms_relative'] ?? null;
                if ($tMs === null) continue;

                $phaseId = 1;
                foreach ($relPhases as $rp) {
                    if ($tMs >= $rp['startTime']) $phaseId = $rp['id'];
                    else break;
                }
                $phaseName = $localPhaseNames[$phaseId] ?? "Phase {$phaseId}";
                $phaseDeathCounts[$phaseName] = ($phaseDeathCounts[$phaseName] ?? 0) + 1;
                $attemptPhasesSeen[$phaseName] = true;
            }
            foreach (array_keys($attemptPhasesSeen) as $pn) {
                $phaseAttemptsWithDeath[$pn] = ($phaseAttemptsWithDeath[$pn] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($phaseDeathCounts as $phaseName => $count) {
            $out[$phaseName] = [
                'deaths'                => $count,
                'attempts_with_deaths'  => $phaseAttemptsWithDeath[$phaseName] ?? 0,
            ];
        }
        return $out;
    }

    /**
     * Collect cooldown timing analysis for all participating players' major CDs (cooldown ≥ 60s).
     */
    private function collectCooldownTimings(string $reportId, array $bossFightIds, array $logData, array $tries, array $rosterNames): array
    {
        // Gather participating players (from rawFights' friendlyPlayers + their specs from playerDetails).
        // Determine specs from logData['player_details'].
        $playerDetails = $logData['player_details'] ?? [];
        $specsInFight = [];
        foreach ($playerDetails as $name => $d) {
            $class = $d['class'] ?? null;
            $spec = $d['spec'] ?? null;
            if (!$class || !$spec) continue;
            if (!empty($rosterNames) && !in_array($name, $rosterNames)) continue;
            $key = "{$class}|{$spec}";
            $specsInFight[$key] = ['class' => $class, 'spec' => $spec];
        }
        if (empty($specsInFight)) return [];

        $cooldownsByAbility = [];
        $abilityNames = [];
        foreach ($specsInFight as $entry) {
            $baseline = $this->specBaselineLoader->load($entry['class'], $entry['spec']);
            if (!$baseline) continue;
            foreach ($baseline['rotation_checks'] ?? [] as $check) {
                $cdSec = (float) ($check['cooldown_seconds'] ?? 0);
                $aId   = (int) ($check['ability_id'] ?? 0);
                if ($cdSec < 60 || $aId <= 0) continue; // major CDs only
                $cooldownsByAbility[$aId] = $cdSec;
                $abilityNames[$aId] = (string) ($check['ability'] ?? "Spell {$aId}");
            }
        }
        if (empty($cooldownsByAbility)) return [];

        // Build actor map (id → name) from raid_fights
        $actorMap = [];
        foreach ($logData['raid_actors'] ?? [] as $actor) {
            if (($actor['type'] ?? '') !== 'Player') continue;
            $actorMap[$actor['id']] = $actor['name'];
        }

        // Per-fight durations (seconds)
        $fightDurations = [];
        foreach ($tries as $t) {
            $fightDurations[$t['fight_id']] = (int) ($t['duration_s'] ?? 0);
        }

        return $this->wclService->getCooldownTimings(
            $reportId,
            $bossFightIds,
            array_keys($cooldownsByAbility),
            $cooldownsByAbility,
            $abilityNames,
            $actorMap,
            $fightDurations
        );
    }

    /**
     * Wave 2 — fetch external defensive cooldowns per encounter and aggregate them
     * by caster (who gave) and by target (who received).
     *
     * @return array{events:array, by_caster:array, by_target:array}
     */
    private function collectExternalCooldowns(string $reportId, array $bossFightIds, array $logData): array
    {
        $refs = $this->combatRefs->externalCooldowns();
        if (empty($refs)) return [];

        $abilityIds = array_column($refs, 'id');
        $abilityNames = array_column($refs, 'name', 'id');

        $actorMap = [];
        foreach ($logData['raid_actors'] ?? [] as $actor) {
            if (($actor['type'] ?? '') !== 'Player') continue;
            $actorMap[$actor['id']] = $actor['name'];
        }

        $events = $this->wclService->getExternalCooldownEvents(
            $reportId,
            $bossFightIds,
            $abilityIds,
            $actorMap,
            $abilityNames
        );
        if (empty($events)) return [];

        $byCaster = [];
        $byTarget = [];
        foreach ($events as $e) {
            $byCaster[$e['caster']][$e['ability']] = ($byCaster[$e['caster']][$e['ability']] ?? 0) + 1;
            // Target-side: count self-casts as personal, external-casts separately
            if ($e['caster'] !== $e['target']) {
                $byTarget[$e['target']][$e['ability']] = ($byTarget[$e['target']][$e['ability']] ?? 0) + 1;
            }
        }

        // Sort ability counts desc per player
        foreach ($byCaster as &$abilities) arsort($abilities);
        unset($abilities);
        foreach ($byTarget as &$abilities) arsort($abilities);
        unset($abilities);

        return [
            'events_count' => count($events),
            'by_caster'    => $byCaster,
            'by_target'    => $byTarget,
        ];
    }

    /**
     * Wave 4 — burst sync analysis. Find when lust-class abilities drop, then check whether
     * each player's personal major CD casts fall within the burst window.
     *
     * @param array $cooldownTimings  Already-fetched cooldown_timings from Wave 1
     *                                (so we don't re-query). Shape: {playerName: {abilityName: {cast_times_s, ...}}}
     */
    private function collectBurstSync(string $reportId, array $bossFightIds, array $logData, array $tries, array $cooldownTimings): array
    {
        $burstAbilities = $this->combatRefs->raidBurstCooldowns();
        if (empty($burstAbilities)) return [];

        $abilityIds = array_column($burstAbilities, 'id');
        $abilityNames = array_column($burstAbilities, 'name', 'id');

        $actorMap = [];
        foreach ($logData['raid_actors'] ?? [] as $actor) {
            if (($actor['type'] ?? '') !== 'Player') continue;
            $actorMap[$actor['id']] = $actor['name'];
        }

        // Reuse the cooldown events fetcher — but it needs cooldown_seconds map. Lust = 5min
        // baseline; we don't really care about idle math here, so pass dummy 300s for all.
        $cooldownsByAbility = array_fill_keys($abilityIds, 300.0);
        $fightDurations = [];
        foreach ($tries as $t) {
            $fightDurations[$t['fight_id']] = (int) ($t['duration_s'] ?? 0);
        }

        $rawLustEvents = $this->wclService->getCooldownTimings(
            $reportId,
            $bossFightIds,
            $abilityIds,
            $cooldownsByAbility,
            $abilityNames,
            $actorMap,
            $fightDurations
        );

        // Build flat list of (lust ability + time_s + caster) drops across all fights
        $lustDrops = [];
        foreach ($rawLustEvents as $caster => $byAbility) {
            foreach ($byAbility as $abilityName => $cd) {
                foreach ($cd['cast_times_s'] ?? [] as $t) {
                    $lustDrops[] = [
                        'time_s'  => (int) $t,
                        'ability' => $abilityName,
                        'caster'  => $caster,
                    ];
                }
            }
        }
        if (empty($lustDrops)) return [];

        usort($lustDrops, fn($a, $b) => $a['time_s'] <=> $b['time_s']);

        // For each player's major CDs (cooldown_timings), check sync with any lust drop ±15s
        $window = 15;
        $synced = [];
        foreach ($cooldownTimings as $player => $byAbility) {
            $playerSynced = [];
            foreach ($byAbility as $ability => $cd) {
                $totalCasts = count($cd['cast_times_s'] ?? []);
                if ($totalCasts === 0) continue;

                $syncedCasts = 0;
                foreach ($cd['cast_times_s'] as $castT) {
                    foreach ($lustDrops as $drop) {
                        if (abs($castT - $drop['time_s']) <= $window) {
                            $syncedCasts++;
                            break;
                        }
                    }
                }
                $playerSynced[$ability] = [
                    'total_casts'   => $totalCasts,
                    'synced_casts'  => $syncedCasts,
                    'sync_pct'      => (int) round($syncedCasts / $totalCasts * 100),
                ];
            }
            if (!empty($playerSynced)) {
                $synced[$player] = $playerSynced;
            }
        }

        return [
            'lust_drops'    => $lustDrops,
            'sync_window_s' => $window,
            'players'       => $synced,
        ];
    }

    /**
     * Wave 2 — per-tank active mitigation uptime. For each tank identified by role, query
     * each of their spec's mitigation buffs via events-based per-player uptime calc.
     *
     * @return array<string, array<int, array{ability:string, ability_id:int, uptime_pct:float}>>
     */
    private function collectTankMitigation(string $reportId, array $bossFightIds, array $logData, int $durationMs): array
    {
        $playerDetails = $logData['player_details'] ?? [];
        $cleanPlayers = $logData['players'] ?? [];

        $out = [];
        foreach ($playerDetails as $name => $d) {
            $role = $d['role'] ?? '';
            if (!in_array($role, ['tank', 'tanks'], true)) continue;
            $class = $d['class'] ?? null;
            $spec  = $d['spec']  ?? null;
            $buffs = $this->combatRefs->tankMitigationFor($class, $spec);
            if (empty($buffs)) continue;

            // Find player ID for event matching
            $playerId = null;
            foreach ($cleanPlayers as $p) {
                if (($p['name'] ?? '') === $name) { $playerId = $p['id'] ?? null; break; }
            }
            if (!$playerId) continue;

            $rows = [];
            foreach ($buffs as $buff) {
                try {
                    $uptime = $this->wclService->getPerPlayerBuffUptime(
                        $reportId,
                        $bossFightIds,
                        (int) $buff['id'],
                        $durationMs,
                        [['id' => $playerId, 'name' => $name]],
                        $buff['data_type'] ?? 'Buffs'
                    );
                    $pct = $uptime[$name]['uptime_pct'] ?? null;
                    if ($pct === null) continue;
                    $rows[] = [
                        'ability'    => $buff['name'],
                        'ability_id' => (int) $buff['id'],
                        'uptime_pct' => (float) $pct,
                    ];
                } catch (\Throwable $e) {
                    // non-fatal per-ability
                    continue;
                }
            }
            if (!empty($rows)) {
                $out[$name] = $rows;
            }
        }
        return $out;
    }
}
