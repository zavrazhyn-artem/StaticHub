<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\WclQueryBuilder;
use App\Helpers\WclReportParserHelper;
use App\Services\Logging\ApiLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WclService
{
    protected string $baseUrl = 'https://www.warcraftlogs.com/api/v2/client';
    protected ?string $accessToken = null;

    public function __construct(
        private readonly ApiLogger $apiLogger,
    ) {}

    /**
     * Execute a WCL GraphQL query.
     */
    public function executeGraphql(string $query, array $variables = []): array
    {
        $startTime = microtime(true);

        $response = Http::withToken($this->getAccessToken())->post($this->baseUrl, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $this->apiLogger->logApiCall('wcl', $this->baseUrl, 'POST', $response, $startTime);

        $data = $response->json();
        if (isset($data['errors'])) {
            throw new \Exception('WCL GraphQL Error: ' . json_encode($data['errors']));
        }

        return $data['data'] ?? [];
    }

    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $clientId = config('services.wcl.public_key');
        $clientSecret = config('services.wcl.private_key');

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('Warcraft Logs API credentials missing.');
        }

        $token = Cache::get('wcl_access_token');
        if (is_string($token)) {
            return $this->accessToken = $token;
        }

        $tokenUrl = 'https://www.warcraftlogs.com/oauth/token';
        $startTime = microtime(true);

        $response = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $this->apiLogger->logApiCall('wcl', $tokenUrl, 'POST', $response, $startTime);

        $token = $response->json('access_token');
        Cache::put('wcl_access_token', $token, 3600);

        return $this->accessToken = $token;
    }

    /**
     * Fetch the world's top speed kill for an encounter at a given difficulty.
     * Returns null if no rankings exist (e.g. brand-new boss with no kills).
     */
    public function findTopKillFightForEncounter(int $encounterId, int $difficulty): ?array
    {
        $query = <<<'GQL'
        query ($encounterId: Int!, $difficulty: Int!) {
          worldData {
            encounter(id: $encounterId) {
              id
              name
              fightRankings(metric: speed, difficulty: $difficulty, page: 1)
            }
          }
        }
GQL;

        $data = $this->executeGraphql($query, [
            'encounterId' => $encounterId,
            'difficulty' => $difficulty,
        ]);

        $encounter = $data['worldData']['encounter'] ?? null;
        $rankings = $encounter['fightRankings']['rankings'] ?? [];
        if (empty($rankings)) return null;

        $first = $rankings[0];
        $report = $first['report'] ?? null;
        if (!$report || empty($report['code']) || !isset($report['fightID'])) return null;

        return [
            'encounter_id' => $encounterId,
            'encounter_name' => $encounter['name'] ?? null,
            'difficulty' => $difficulty,
            'report_code' => (string) $report['code'],
            'fight_id' => (int) $report['fightID'],
            'kill_duration_ms' => (int) ($first['duration'] ?? 0),
            'kill_started_at_ms' => (int) ($first['startTime'] ?? 0),
            'guild_name' => $first['guild']['name'] ?? null,
            'server_name' => $first['server']['name'] ?? null,
            'server_region' => $first['server']['region'] ?? null,
        ];
    }

    /**
     * Fetch all encounters in a WCL zone.
     */
    public function fetchZoneEncounters(int $zoneId): array
    {
        $query = <<<'GQL'
        query ($zoneId: Int!) {
          worldData {
            zone(id: $zoneId) {
              id
              name
              encounters { id name }
            }
          }
        }
GQL;
        $data = $this->executeGraphql($query, ['zoneId' => $zoneId]);
        return $data['worldData']['zone']['encounters'] ?? [];
    }

    /**
     * Fetch boss cast timeline for a single fight. Returns abilities grouped
     * by spell_id with cast timestamps (seconds relative to fight start),
     * plus phase transitions. Used to seed the boss ability timings table.
     */
    public function fetchBossCastTimeline(string $reportId, int $fightId): array
    {
        // Step 1: fights + master data + phase definitions
        $meta = $this->executeGraphql(
            WclQueryBuilder::buildReportFightsAndActorsQuery(),
            ['reportId' => $reportId]
        )['reportData']['report'] ?? [];

        $fight = null;
        foreach ($meta['fights'] ?? [] as $f) {
            if ((int) $f['id'] === $fightId) {
                $fight = $f;
                break;
            }
        }
        if (!$fight) {
            throw new \Exception("Fight #{$fightId} not found in report {$reportId}");
        }

        // Build phase segment index from fight.phaseTransitions. Each transition
        // becomes a segment; segment N ends when segment N+1 starts (or at fight end).
        // Phase definitions (name, isIntermission) come from report.phases.
        $phaseDefs = [];
        foreach ($meta['phases'] ?? [] as $encDef) {
            if ((int) ($encDef['encounterID'] ?? 0) !== (int) ($fight['encounterID'] ?? 0)) continue;
            foreach ($encDef['phases'] ?? [] as $p) {
                $phaseDefs[(int) $p['id']] = [
                    'name' => $p['name'] ?? ('Phase ' . $p['id']),
                    'is_intermission' => (bool) ($p['isIntermission'] ?? false),
                ];
            }
        }

        $abilityIndex = [];
        foreach ($meta['masterData']['abilities'] ?? [] as $ab) {
            $abilityIndex[(int) $ab['gameID']] = $ab;
        }

        $bossActorIds = [];
        foreach ($meta['masterData']['actors'] ?? [] as $actor) {
            if (($actor['type'] ?? '') === 'NPC' && ($actor['subType'] ?? '') === 'Boss') {
                $bossActorIds[(int) $actor['id']] = true;
            }
        }

        $startTime = (float) $fight['startTime'];
        $endTime = (float) $fight['endTime'];

        // Step 2: paginate cast events across the fight window
        $allEvents = [];
        $cursor = $startTime;
        $safety = 20;
        while ($cursor !== null && $cursor < $endTime && $safety-- > 0) {
            $page = $this->executeGraphql(
                WclQueryBuilder::buildBossCastEventsQuery(),
                [
                    'reportId' => $reportId,
                    'fightId' => $fightId,
                    'startTime' => $cursor,
                    'endTime' => $endTime,
                ]
            )['reportData']['report']['events'] ?? [];

            foreach ($page['data'] ?? [] as $event) {
                $allEvents[] = $event;
            }

            $next = $page['nextPageTimestamp'] ?? null;
            if ($next === null || (float) $next <= $cursor) {
                break;
            }
            $cursor = (float) $next;
        }

        // Step 3: group boss casts by ability
        $blacklistedSpells = [
            145629, // Anti-Magic Zone (DK player ability — leaks into boss cast feed via mind-control / scripted reuse)
        ];
        $grouped = [];
        foreach ($allEvents as $event) {
            if (($event['type'] ?? '') !== 'cast') continue;

            $sourceId = (int) ($event['sourceID'] ?? 0);
            $abilityGameId = (int) ($event['abilityGameID'] ?? 0);
            if ($abilityGameId === 0 || $abilityGameId === 1) continue; // skip melee/auto
            if (in_array($abilityGameId, $blacklistedSpells, true)) continue;

            if (!empty($bossActorIds) && !isset($bossActorIds[$sourceId])) continue;

            $ability = $abilityIndex[$abilityGameId] ?? null;
            if (!$ability) continue;

            if (!isset($grouped[$abilityGameId])) {
                $grouped[$abilityGameId] = [
                    'spell_id' => $abilityGameId,
                    'name' => $ability['name'] ?? "Spell {$abilityGameId}",
                    'icon' => pathinfo($ability['icon'] ?? '', PATHINFO_FILENAME),
                    'type' => (int) ($ability['type'] ?? 0),
                    'casts' => [],
                ];
            }

            $relative = (int) round((((float) $event['timestamp']) - $startTime) / 1000);
            if ($relative < 0) continue;
            $grouped[$abilityGameId]['casts'][] = $relative;
        }

        foreach ($grouped as &$ab) {
            $ab['casts'] = array_values(array_unique($ab['casts']));
            sort($ab['casts']);
        }
        unset($ab);

        // Compute durations from buff/debuff apply/remove pairs.
        $auraEvents = $this->fetchBossAuraEvents($reportId, $fightId, $startTime, $endTime, $bossActorIds);
        $durationsBySpell = $this->computeAuraDurations($auraEvents);
        foreach ($grouped as &$ab) {
            $ms = $durationsBySpell[$ab['spell_id']] ?? null;
            if ($ms !== null) {
                $ab['duration_sec'] = max(0, (int) round($ms / 1000));
            } else {
                $ab['duration_sec'] = 0;
            }
        }
        unset($ab);

        // Deduplicate by ability name: encounters often script the same "ability"
        // across multiple spell IDs (telegraph / damage / aoe variants). Keep the
        // variant with the most casts — that's typically the main user-visible one.
        $byName = [];
        foreach ($grouped as $ab) {
            $name = $ab['name'];
            if (!isset($byName[$name]) || count($ab['casts']) > count($byName[$name]['casts'])) {
                $byName[$name] = $ab;
            }
        }
        $grouped = $byName;

        // Build phase segments from transitions. If no transitions exist (phaseless boss),
        // create a single segment spanning the whole fight.
        $fightDuration = (int) round(($endTime - $startTime) / 1000);
        $transitions = [];
        foreach ($fight['phaseTransitions'] ?? [] as $pt) {
            $transitions[] = [
                'phase_id' => (int) $pt['id'],
                'start_sec' => (int) round((((float) $pt['startTime']) - $startTime) / 1000),
            ];
        }
        usort($transitions, fn ($a, $b) => $a['start_sec'] <=> $b['start_sec']);

        $segments = [];
        if (empty($transitions)) {
            $segments[] = [
                'segment_id' => 's1',
                'phase_id' => 1,
                'phase_name' => $phaseDefs[1]['name'] ?? 'Fight',
                'is_intermission' => $phaseDefs[1]['is_intermission'] ?? false,
                'seed_start' => 0,
                'seed_duration' => $fightDuration,
                'segment_order' => 0,
            ];
        } else {
            foreach ($transitions as $i => $tx) {
                $nextStart = $transitions[$i + 1]['start_sec'] ?? $fightDuration;
                $segments[] = [
                    'segment_id' => 's' . ($i + 1),
                    'phase_id' => $tx['phase_id'],
                    'phase_name' => $phaseDefs[$tx['phase_id']]['name'] ?? ('Phase ' . $tx['phase_id']),
                    'is_intermission' => $phaseDefs[$tx['phase_id']]['is_intermission'] ?? false,
                    'seed_start' => $tx['start_sec'],
                    'seed_duration' => max(1, $nextStart - $tx['start_sec']),
                    'segment_order' => $i,
                ];
            }
        }

        // Attribute each cast to the segment it falls in. Replace the flat int list
        // with a list of {segment_id, offset} so it can auto-adjust when a segment
        // is stretched/shrunk at plan time.
        foreach ($grouped as &$ab) {
            $attributed = [];
            foreach ($ab['casts'] as $sec) {
                $seg = null;
                foreach ($segments as $s) {
                    if ($sec >= $s['seed_start'] && $sec < ($s['seed_start'] + $s['seed_duration'])) {
                        $seg = $s;
                        break;
                    }
                }
                if (!$seg) {
                    // Cast at exact fight end — attribute to last segment
                    $seg = end($segments);
                }
                $attributed[] = [
                    'segment_id' => $seg['segment_id'],
                    'offset' => $sec - $seg['seed_start'],
                ];
            }
            $ab['casts'] = $attributed;
        }
        unset($ab);

        return [
            'report_id' => $reportId,
            'fight' => [
                'id' => $fightId,
                'encounter_id' => (int) ($fight['encounterID'] ?? 0),
                'name' => $fight['name'] ?? '',
                'duration_sec' => $fightDuration,
            ],
            'segments' => $segments,
            'abilities' => array_values($grouped),
        ];
    }

    /**
     * Fetch buff + debuff events for the fight, restricted to boss sources.
     * Returns a flat list of { type, ts, sourceID, spellId } records.
     */
    private function fetchBossAuraEvents(string $reportId, int $fightId, float $startTime, float $endTime, array $bossActorIds): array
    {
        $queries = [
            WclQueryBuilder::buildBossBuffEventsQuery(),
            WclQueryBuilder::buildBossDebuffEventsQuery(),
        ];

        $events = [];
        foreach ($queries as $query) {
            $cursor = $startTime;
            $safety = 20;
            while ($cursor !== null && $cursor < $endTime && $safety-- > 0) {
                $page = $this->executeGraphql($query, [
                    'reportId' => $reportId,
                    'fightId' => $fightId,
                    'startTime' => $cursor,
                    'endTime' => $endTime,
                ])['reportData']['report']['events'] ?? [];

                foreach ($page['data'] ?? [] as $event) {
                    $sourceId = (int) ($event['sourceID'] ?? 0);
                    if (!empty($bossActorIds) && !isset($bossActorIds[$sourceId])) continue;
                    $events[] = $event;
                }

                $next = $page['nextPageTimestamp'] ?? null;
                if ($next === null || (float) $next <= $cursor) break;
                $cursor = (float) $next;
            }
        }
        return $events;
    }

    /**
     * Match apply ↔ remove events per (spell_id, target) and return median
     * duration (ms) per spell_id. Median is more robust than average for
     * abilities with occasional refresh / early dispel anomalies.
     */
    private function computeAuraDurations(array $events): array
    {
        $open = [];          // "spellId-targetId" => apply timestamp
        $bySpell = [];       // spellId => [duration_ms, ...]
        $applyTypes = ['applybuff', 'applydebuff'];
        $removeTypes = ['removebuff', 'removedebuff'];

        foreach ($events as $ev) {
            $type = $ev['type'] ?? '';
            $spellId = (int) ($ev['abilityGameID'] ?? 0);
            $targetId = (int) ($ev['targetID'] ?? 0);
            $ts = (float) ($ev['timestamp'] ?? 0);
            if ($spellId === 0) continue;

            $key = $spellId . '-' . $targetId;
            if (in_array($type, $applyTypes, true)) {
                $open[$key] = $ts;
            } elseif (in_array($type, $removeTypes, true) && isset($open[$key])) {
                $duration = $ts - $open[$key];
                unset($open[$key]);
                if ($duration > 0 && $duration < 600000) {
                    $bySpell[$spellId][] = $duration;
                }
            }
        }

        $result = [];
        foreach ($bySpell as $spellId => $durations) {
            sort($durations);
            $n = count($durations);
            $median = $n % 2 === 1
                ? $durations[(int) (($n - 1) / 2)]
                : ($durations[$n / 2 - 1] + $durations[$n / 2]) / 2;
            // Skip "instant" auras (< 1.5s) — likely procs, not mechanic durations
            if ($median >= 1500) $result[$spellId] = $median;
        }
        return $result;
    }

    public function getLogSummary(string $reportId, array $rosterNames = []): array
    {
        // Large Mythic reports with many boss tries can exceed PHP's 128M default.
        // Bump to 1G for the duration of this call; reverted implicitly on CLI exit.
        @ini_set('memory_limit', '1024M');

        // --- Query 1: fights + phases + masterData ---
        $fightsQuery = WclQueryBuilder::buildFightsQuery();
        $initialData = $this->executeGraphql($fightsQuery, ['reportId' => $reportId])['reportData']['report'] ?? [];

        if (empty($initialData['fights'])) {
            return ['error' => 'No data found.'];
        }

        $raidFights = array_filter($initialData['fights'], fn($f) => in_array($f['difficulty'], [3, 4, 5]));
        if (empty($raidFights)) {
            return ['error' => 'No raid encounters found.'];
        }

        $difficultyMap = [3 => 'normal', 4 => 'heroic', 5 => 'mythic'];
        $difficulties = array_values(array_unique(
            array_map(fn($f) => $difficultyMap[$f['difficulty']], $raidFights)
        ));

        $fightIds = array_values(array_column($raidFights, 'id'));

        // --- Query 2: tables + playerDetails + rankings ---
        $tablesQuery = WclQueryBuilder::buildTablesQuery();
        $tablesData  = $this->executeGraphql($tablesQuery, [
            'reportId' => $reportId,
            'fightIds' => $fightIds,
        ])['reportData']['report'] ?? [];

        // Roster: filter to known players (exclude NPCs/Pets)
        $allActors    = $initialData['masterData']['actors'] ?? [];
        $cleanPlayers = array_values(array_filter(
            $allActors,
            fn($a) => ($a['type'] ?? '') === 'Player'
                && (empty($rosterNames) || in_array($a['name'], $rosterNames))
        ));

        // Fight durations (for CPM support)
        $durations = WclReportParserHelper::buildFightDurations($raidFights);
        $raidDuration = $durations['total_seconds'];

        // Phase summary
        $reportPhases = $initialData['phases'] ?? [];
        $phaseSummary = WclReportParserHelper::buildPhaseSummary($raidFights, $reportPhases);

        // Build fightId → startTimeMs map for death timestamp normalisation
        $fightStartTimes = array_column($raidFights, 'startTime', 'id');

        // Deaths (grouped by boss/try, wipe deaths filtered out)
        $raidSize = count($rosterNames) ?: count($cleanPlayers);
        $cleanDeaths = WclReportParserHelper::parseDeaths(
            $tablesData['deaths']['data'] ?? [],
            $rosterNames,
            $fightStartTimes,
            $phaseSummary,
            $raidSize
        );

        // Damage Taken
        $cleanDamageTaken = WclReportParserHelper::parseDamageTaken(
            $tablesData['damageTaken']['data']['entries'] ?? [],
            $rosterNames
        );

        // Casts & Consumables
        $castsAndConsumables = WclReportParserHelper::parseCastsAndConsumables(
            $tablesData['casts']['data']['entries'] ?? [],
            $rosterNames
        );

        // Performance Metrics (DPS/HPS + overheal)
        $performanceMetrics = WclReportParserHelper::calculatePerformanceMetrics(
            $tablesData['damageDone']['data']['entries'] ?? [],
            $tablesData['healing']['data']['entries'] ?? [],
            $raidDuration,
            $rosterNames
        );

        // Inject spec into performance_metrics
        $playerSpecMap = array_column($cleanPlayers, 'subType', 'name');
        foreach ($performanceMetrics as $name => &$metrics) {
            $metrics['spec'] = $playerSpecMap[$name] ?? null;
        }
        unset($metrics);

        // Dispels
        $dispelEntries = $tablesData['dispels']['data']['entries'][0]['entries'] ?? [];
        $cleanDispels  = WclReportParserHelper::parseDispels($dispelEntries, $rosterNames);

        // Buff uptime (raid-wide auras ≥5% uptime)
        $totalDurationMs = $durations['total_seconds'] * 1000;
        $fightCount      = count($raidFights);
        $buffUptime = WclReportParserHelper::parseBuffUptime(
            $tablesData['buffs']['data'] ?? [],
            $totalDurationMs
        );

        // Consumable buffs (flasks, food, augment runes) — raid-wide aggregate
        $consumableBuffs = WclReportParserHelper::parseConsumableBuffs(
            $tablesData['buffs']['data'] ?? [],
            $totalDurationMs,
            $fightCount
        );

        // Debuff uptime
        $debuffUptime = WclReportParserHelper::parseDebuffUptime(
            $tablesData['debuffs']['data'] ?? [],
            $totalDurationMs,
            $rosterNames
        );

        // Resource waste
        $resourceWaste = WclReportParserHelper::parseResources(
            $tablesData['resources']['data'] ?? [],
            $rosterNames
        );

        // Interrupts
        $interruptEntries = $tablesData['interrupts']['data']['entries'][0]['entries'] ?? [];
        $interrupts = WclReportParserHelper::parseInterrupts($interruptEntries, $rosterNames);

        // Debuff stacks for stacking debuffs (tank swaps, soak vulnerabilities)
        $debuffStacks = $this->extractDebuffStacks(
            $reportId,
            $fightIds,
            $tablesData['debuffs']['data']['auras'] ?? [],
            $tablesData['deaths']['data']['entries'] ?? [],
            $cleanPlayers
        );


        // Build NPC actor map (needed for multiple advanced analyses)
        $npcMap = [];
        foreach ($allActors as $a) {
            if (($a['type'] ?? '') === 'NPC' && isset($a['id'], $a['name'])) {
                $npcMap[$a['id']] = $a['name'];
            }
        }

        // Orb staggering analysis (detects overlapping orb kills that cause DoT stack wipes)
        $orbStaggering = $this->extractOrbStaggering($reportId, $fightIds, $npcMap, $fightStartTimes);

        // Fetch enemy casts + enemy buffs tables (needed for Nexus Shield / boss positioning)
        $enemyAuxQuery = 'query ($reportId: String!, $fightIds: [Int]!) {
          reportData { report(code: $reportId) {
            enemyCasts: table(dataType: Casts, fightIDs: $fightIds, killType: Encounters, hostilityType: Enemies, viewBy: Ability)
            enemyBuffs: table(dataType: Buffs, fightIDs: $fightIds, killType: Encounters, hostilityType: Enemies)
          } }
        }';
        $enemyAux = [];
        try {
            $enemyAux = $this->executeGraphql($enemyAuxQuery, ['reportId' => $reportId, 'fightIds' => $fightIds])['reportData']['report'] ?? [];
        } catch (\Exception $e) {
            $enemyAux = [];
        }
        $enemyCastEntries = $enemyAux['enemyCasts']['data']['entries'] ?? [];
        $enemyBuffAuras = $enemyAux['enemyBuffs']['data']['auras'] ?? [];

        // Enemy buff stacks (Vaelwing, Primordial Power, etc.)
        $enemyBuffStacks = $this->extractEnemyBuffStacks($reportId, $fightIds, $enemyBuffAuras, $allActors);

        // Enemy buff uptime (for Aura of Wrath, Imperator's Glory, Berserk, etc.)
        $enemyBuffUptimes = [];
        $enemyBuffsTotalTime = $enemyAux['enemyBuffs']['data']['totalTime'] ?? $totalDurationMs;
        if ($enemyBuffsTotalTime > 0) {
            foreach ($enemyBuffAuras as $aura) {
                $name = $aura['name'] ?? '';
                $uptime = $aura['totalUptime'] ?? 0;
                if ($uptime <= 0) continue;
                $enemyBuffUptimes[$name] = [
                    'guid'       => $aura['guid'] ?? null,
                    'uptime_pct' => round($uptime / $enemyBuffsTotalTime * 100, 1),
                    'uses'       => $aura['totalUses'] ?? 0,
                ];
            }
            uasort($enemyBuffUptimes, fn($a, $b) => $b['uptime_pct'] <=> $a['uptime_pct']);
        }

        // Shielded casts analysis (e.g. Nexus Shield interrupt tracking)
        $shieldedCasts = $this->extractShieldedCasts(
            $reportId,
            $fightIds,
            $enemyBuffAuras,
            $enemyCastEntries,
            $cleanPlayers,
            $fightStartTimes
        );

        // Player coordinate tracking during key debuffs (proxy for puddle placement etc.)
        $playerCoords = $this->extractPlayerCoordsOnDebuffRemoval(
            $reportId,
            $fightIds,
            $tablesData['debuffs']['data']['auras'] ?? [],
            $cleanPlayers,
            $fightStartTimes
        );


        // Damage done by target (boss vs adds breakdown) — parsed from standard DamageDone
        $targetDamage = WclReportParserHelper::parseTargetDamage(
            $tablesData['damageDone']['data']['entries'] ?? [],
            $rosterNames
        );

        // Player details (gear, ilvl, trinkets, stats)
        $playerDetailsRaw = $tablesData['playerDetails'] ?? null;
        $playerDetails    = WclReportParserHelper::parsePlayerDetails($playerDetailsRaw, $rosterNames);

        // Merge actual consumable usage into player_details
        $consumables = $castsAndConsumables['consumables'];
        foreach ($playerDetails as $name => &$details) {
            $details['consumables'] = $consumables[$name] ?? [];
        }
        unset($details);

        // Rankings (parse %) — only available for kills
        $hasKills  = !empty(array_filter($raidFights, fn($f) => $f['kill'] ?? false));
        $rankings  = [];

        if ($hasKills) {
            $killFightIds = array_values(array_map(
                fn($f) => $f['id'],
                array_filter($raidFights, fn($f) => $f['kill'] ?? false)
            ));

            // Re-query rankings with kill-only fight IDs for accurate parse %
            $rankingsQuery = WclQueryBuilder::buildRankingsQuery();
            $rankingsData  = $this->executeGraphql($rankingsQuery, [
                'reportId' => $reportId,
                'fightIds' => $killFightIds,
            ])['reportData']['report'] ?? [];

            $rankings = WclReportParserHelper::parseRankings($rankingsData['rankings'] ?? null, $rosterNames);
        }

        // Merge rankings parse % into performance_metrics
        foreach ($rankings as $name => $rankData) {
            if (isset($performanceMetrics[$name])) {
                $performanceMetrics[$name]['parse_pct']       = $rankData['parse_pct'];
                $performanceMetrics[$name]['parse_today_pct'] = $rankData['today_pct'];
            }
        }

        return [
            'raid_title'          => $initialData['title'] ?? 'Raid Analysis',
            'difficulties'        => $difficulties,
            'has_kills'           => $hasKills,
            'fight_durations'     => $durations,
            'phase_summary'       => $phaseSummary,
            'players'             => $cleanPlayers,
            'player_details'      => $playerDetails,
            'deaths'              => $cleanDeaths,
            'major_damage_taken'  => array_slice($cleanDamageTaken, 0, 50),
            'target_damage'       => $targetDamage,
            'interrupts'          => $interrupts,
            'debuff_stacks'       => $debuffStacks,
            'enemy_buff_stacks'   => $enemyBuffStacks,
            'enemy_buff_uptimes'  => $enemyBuffUptimes,
            'orb_staggering'      => $orbStaggering,
            'shielded_casts'      => $shieldedCasts,
            'player_coords_on_debuff' => $playerCoords,
            'casts_summary'       => $castsAndConsumables['casts'],
            'consumables_used'    => $consumables,
            'dispels'             => $cleanDispels,
            'consumable_buffs'    => $consumableBuffs,
            'buff_uptime'         => array_slice($buffUptime, 0, 100, true),
            'debuff_uptime'       => $debuffUptime,
            'resource_waste'      => $resourceWaste,
            'performance_metrics' => $performanceMetrics,
            // Raw report data (used by per-encounter helpers — phase bucketing, cooldown timing, etc.)
            'raid_fights'         => array_values($raidFights),
            'report_phases'       => $reportPhases,
            'raid_actors'         => $allActors,
        ];
    }

    /**
     * Fetch guild info by WCL guild ID.
     *
     * @return array{id: int, name: string, server_name: string, server_slug: string, region_slug: string, region_name: string}|null
     */
    public function getGuildInfoById(int $guildId): ?array
    {
        $query = WclQueryBuilder::buildGuildInfoByIdQuery();

        $data = $this->executeGraphql($query, ['guildId' => $guildId]);
        $guild = $data['guildData']['guild'] ?? null;

        return $guild ? $this->formatGuildInfo($guild) : null;
    }

    /**
     * Fetch guild info by name, server slug, and region.
     *
     * @return array{id: int, name: string, server_name: string, server_slug: string, region_slug: string, region_name: string}|null
     */
    public function getGuildInfoByName(string $name, string $serverSlug, string $serverRegion): ?array
    {
        $query = WclQueryBuilder::buildGuildInfoByNameQuery();

        $data = $this->executeGraphql($query, [
            'name'         => $name,
            'serverSlug'   => $serverSlug,
            'serverRegion' => $serverRegion,
        ]);

        $guild = $data['guildData']['guild'] ?? null;

        return $guild ? $this->formatGuildInfo($guild) : null;
    }

    private function formatGuildInfo(array $guild): array
    {
        return [
            'id'          => $guild['id'],
            'name'        => $guild['name'],
            'server_name' => $guild['server']['name'] ?? '',
            'server_slug' => $guild['server']['slug'] ?? '',
            'region_slug' => $guild['server']['region']['slug'] ?? '',
            'region_name' => $guild['server']['region']['compactName'] ?? '',
        ];
    }

    /**
     * Fetch recent guild reports from WCL within a time range.
     *
     * @param int   $guildId    WCL guild ID
     * @param float $startTime  UNIX timestamp (ms) — range start
     * @param float $endTime    UNIX timestamp (ms) — range end
     * @return array             Array of reports [{code, title, startTime, endTime}, ...]
     */
    public function getGuildReports(int $guildId, float $startTime, float $endTime): array
    {
        $query = WclQueryBuilder::buildGuildReportsQuery();

        $data = $this->executeGraphql($query, [
            'guildId'   => $guildId,
            'startTime' => $startTime,
            'endTime'   => $endTime,
            'limit'     => 25,
        ]);

        return $data['reportData']['reports']['data'] ?? [];
    }

    /**
     * Identify stacking tank/soak debuffs from the debuffs table and fetch stack events for each.
     * Targets high-uptime debuffs (>10%) with keywords indicating stacking mechanics.
     *
     * @return array [debuffName => parsed stack data]
     */
    private function extractDebuffStacks(string $reportId, array $fightIds, array $debuffAuras, array $deathEntries, array $players): array
    {
        // Keywords for debuffs we want detailed event-level analysis of:
        // - Stacking tank swap debuffs (max_stacks_per_player, stacks_at_death)
        // - Healing absorb shields (avg_duration_ms shows how fast healers clear them)
        // - Soak vulnerability stacks
        $stackingKeywords = [
            // Tank swaps / vulnerability stacks
            'Destabilizing', 'Blackening', 'Vaelwing', 'Rakfang', 'Midnight Manifestation',
            'Rift Vulnerability', 'Rift Slash', 'Impaled', 'Heaven\'s Lance',
            'Ashen Benediction', 'Discordant Roar', 'Light Infused',
            'Smashed', 'Judgment', 'Shield of the Righteous',
            // Healing absorb shields
            'Despotic Command', 'Rift Sickness', 'Eternal Burns', 'Null Corona',
            'Voidstalker Sting',
            // Mythic-specific
            'Rift Madness', 'Gloomtouched', 'Diminish', 'Nexus Shield',
            // Player-targeted debuffs from adds / boss
            'Fixate', 'Aspect of the End', 'Silverstrike Arrow', 'Silverstrike Barrage',
            'Umbral Tether', 'Umbral Collapse', 'Void Marked', 'Lingering Darkness',
            'Dread Breath', 'Ranger Captain\'s Mark', 'Consuming Miasma',
        ];

        // Actor ID → name map
        $actorMap = [];
        foreach ($players as $p) {
            if (isset($p['id'], $p['name'])) {
                $actorMap[$p['id']] = $p['name'];
            }
        }

        $result = [];
        foreach ($debuffAuras as $aura) {
            $name = $aura['name'] ?? '';
            $guid = $aura['guid'] ?? null;
            $uptime = $aura['totalUptime'] ?? 0;

            if (!$guid || $uptime < 10000) continue; // skip very short debuffs

            $isStackingCandidate = false;
            foreach ($stackingKeywords as $kw) {
                if (stripos($name, $kw) !== false) {
                    $isStackingCandidate = true;
                    break;
                }
            }
            if (!$isStackingCandidate) continue;

            try {
                $events = $this->getDebuffStackEvents($reportId, $fightIds, (int) $guid);
                if (empty($events)) continue;

                $parsed = WclReportParserHelper::parseDebuffStacks($events, $actorMap, $deathEntries);
                if (!empty($parsed['max_stacks_per_player'])) {
                    $parsed['guid'] = (int) $guid;
                    $result[$name] = $parsed;
                }
            } catch (\Exception $e) {
                // Skip this debuff on error, don't fail the whole pipeline
                continue;
            }
        }

        return $result;
    }

    /**
     * Extract player x,y coordinates near key debuff removal events.
     * WCL provides coords only on player cast events → for each removedebuff of a puddle-dropping
     * debuff, find the nearest player cast event (within ±3s) to approximate position.
     *
     * Tracked debuffs: puddle-dropping / positioning-critical mechanics.
     *
     * @return array [debuffName => ['applications' => int, 'positions' => [...], 'center_avg' => [x,y], 'outlier_drops' => int]]
     */
    private function extractPlayerCoordsOnDebuffRemoval(string $reportId, array $fightIds, array $debuffAuras, array $cleanPlayers, array $fightStartTimes): array
    {
        // Debuffs whose expiration drops a puddle or positions matter
        $keywords = [
            'Despotic Command', 'Blisterburst', 'Void Dive', 'Light Dive',
            'Corrupting Essence', 'Voidfire', 'Rift Madness', 'Alnshroud',
            'Dread Breath', 'Gloom', 'Eruption', 'Consuming Miasma',
        ];

        $matched = [];
        foreach ($debuffAuras as $aura) {
            $name = $aura['name'] ?? '';
            $guid = $aura['guid'] ?? null;
            if (!$guid) continue;
            foreach ($keywords as $kw) {
                if (stripos($name, $kw) !== false) {
                    $matched[$name] = (int) $guid;
                    break;
                }
            }
        }

        if (empty($matched)) return [];

        $playerMap = [];
        foreach ($cleanPlayers as $p) {
            if (isset($p['id'], $p['name'])) $playerMap[$p['id']] = $p['name'];
        }

        $result = [];
        foreach ($matched as $debuffName => $debuffId) {
            try {
                $debuffEvents = $this->getDebuffStackEvents($reportId, $fightIds, $debuffId);
            } catch (\Exception $e) {
                continue;
            }

            // Keep only removedebuff events on known players
            $removals = array_values(array_filter($debuffEvents, function ($e) use ($playerMap) {
                return ($e['type'] ?? '') === 'removedebuff'
                    && isset($playerMap[$e['targetID'] ?? null]);
            }));

            if (empty($removals)) continue;

            // Fetch all player cast events with coords in this fight range
            // Query casts table and fetch events per fight for coords
            $positions = WclReportParserHelper::correlatePlayerCoordsWithEvents(
                $removals,
                $this->fetchPlayerCoordSnapshots($reportId, $fightIds, $playerMap),
                $playerMap,
                $fightStartTimes
            );

            if (!empty($positions)) {
                $result[$debuffName] = $positions;
            }
        }

        return $result;
    }

    /**
     * Fetch a sample of player cast events with x,y for coordinate lookup.
     * Caps pagination to avoid loading tens of thousands of events for large reports.
     */
    private function fetchPlayerCoordSnapshots(string $reportId, array $fightIds, array $playerMap): array
    {
        $query = 'query ($reportId: String!, $fightIds: [Int]!, $startTime: Float) {
          reportData { report(code: $reportId) {
            events(dataType: Casts, fightIDs: $fightIds, killType: Encounters, includeResources: true, hostilityType: Friendlies, startTime: $startTime, limit: 2000) {
              data
              nextPageTimestamp
            }
          } }
        }';

        $filtered = [];
        $startTime = 0.0;
        $pagesFetched = 0;
        $maxPages = 10; // cap ~20,000 events total to bound memory

        try {
            do {
                $data = $this->executeGraphql($query, [
                    'reportId'  => $reportId,
                    'fightIds'  => $fightIds,
                    'startTime' => $startTime,
                ]);

                $page = $data['reportData']['report']['events'] ?? [];
                $events = $page['data'] ?? [];

                // Filter immediately to keep only useful events — reduces memory pressure
                foreach ($events as $e) {
                    if (!isset($playerMap[$e['sourceID'] ?? null])) continue;
                    if (!isset($e['x'], $e['y'])) continue;
                    // Keep only essential fields to save memory
                    $filtered[] = [
                        'timestamp' => $e['timestamp'] ?? 0,
                        'sourceID'  => $e['sourceID'],
                        'fight'     => $e['fight'] ?? null,
                        'x'         => $e['x'],
                        'y'         => $e['y'],
                    ];
                }
                unset($events, $data, $page);

                $next = $page['nextPageTimestamp'] ?? null;
                $pagesFetched++;
                if ($next === null || $next <= $startTime || $pagesFetched >= $maxPages) break;
                $startTime = (float) $next;
            } while (true);
        } catch (\Exception $e) {
            return $filtered;
        }

        return $filtered;
    }

    /**
     * Extract stacking enemy buffs (e.g. Vaelwing on boss, Primordial Power).
     * Boss self-buffs track tank swap/vulnerability via buff application counts.
     *
     * @return array [buffName => {max_stacks, applications, targets_seen}]
     */
    private function extractEnemyBuffStacks(string $reportId, array $fightIds, array $enemyBuffAuras, array $allActors): array
    {
        $stackingKeywords = [
            'Vaelwing', 'Primordial Power', 'Rakfang', 'Aura of Wrath', 'Aura of Peace',
            'Aura of Devotion', 'Tyr\'s Wrath', 'Light Infused', 'Zealous Spirit',
            'Empowering Darkness', 'Cosmic Barrier', 'Umbral Barrier',
        ];

        $npcMap = [];
        foreach ($allActors as $a) {
            if (($a['type'] ?? '') === 'NPC' && isset($a['id'], $a['name'])) {
                $npcMap[$a['id']] = $a['name'];
            }
        }

        $result = [];
        foreach ($enemyBuffAuras as $aura) {
            $name = $aura['name'] ?? '';
            $guid = $aura['guid'] ?? null;
            if (!$guid) continue;

            $matched = false;
            foreach ($stackingKeywords as $kw) {
                if (stripos($name, $kw) !== false) { $matched = true; break; }
            }
            if (!$matched) continue;

            try {
                $events = $this->getEnemyBuffEvents($reportId, $fightIds, (int) $guid);
            } catch (\Exception $e) {
                continue;
            }
            if (empty($events)) continue;

            $maxStack = 0;
            $applications = 0;
            $targets = [];
            foreach ($events as $e) {
                $t = $e['type'] ?? '';
                if ($t === 'applybuff') $applications++;
                elseif ($t === 'applybuffstack') {
                    $s = $e['stack'] ?? 0;
                    if ($s > $maxStack) $maxStack = $s;
                }
                $tid = $e['targetID'] ?? null;
                if ($tid && isset($npcMap[$tid])) {
                    $targets[$npcMap[$tid]] = ($targets[$npcMap[$tid]] ?? 0) + 1;
                }
            }

            $result[$name] = [
                'max_stacks'   => $maxStack,
                'applications' => $applications,
                'targets_seen' => $targets,
            ];
        }

        return $result;
    }

    /**
     * Extract orb kill timing to detect overlapping kills (cause of DoT-stack wipes).
     * Filters to known orb-style NPCs. Returns empty if no relevant NPC detected.
     */
    private function extractOrbStaggering(string $reportId, array $fightIds, array $npcMap, array $fightStartTimes): array
    {
        // NPC names that represent "orb"-style adds which cause DoT-overlap wipes
        $orbKeywords = [
            'Concentrated Void', 'Enduring Void', 'Void Spawn', 'Voidstalker', 'Unbound Void',
            'Void Convergence', 'Void Orb', 'Blistercreep', 'Voidorb',
        ];

        $relevantNpcs = [];
        foreach ($npcMap as $id => $name) {
            foreach ($orbKeywords as $kw) {
                if (stripos($name, $kw) !== false) {
                    $relevantNpcs[$id] = $name;
                    break;
                }
            }
        }

        if (empty($relevantNpcs)) return [];

        try {
            $deaths = $this->getEnemyDeathEvents($reportId, $fightIds);
        } catch (\Exception $e) {
            return [];
        }

        // Filter to only relevant NPCs
        $filtered = array_values(array_filter($deaths, fn($e) => isset($relevantNpcs[$e['targetID'] ?? null])));
        if (empty($filtered)) return [];

        return WclReportParserHelper::parseOrbStaggering($filtered, $relevantNpcs, $fightStartTimes);
    }

    /**
     * Analyze shielded cast attempts (e.g. Shadow Fracture interrupts on Nexus Shield clones).
     * Returns empty array if no shield buff found in enemy auras.
     */
    private function extractShieldedCasts(string $reportId, array $fightIds, array $enemyBuffAuras, array $enemyCastEntries, array $cleanPlayers, array $fightStartTimes): array
    {
        $shieldKeywords = ['Nexus Shield'];
        $shieldAura = null;
        foreach ($enemyBuffAuras as $aura) {
            $name = $aura['name'] ?? '';
            foreach ($shieldKeywords as $kw) {
                if (stripos($name, $kw) !== false) {
                    $shieldAura = ['name' => $name, 'guid' => $aura['guid'] ?? null];
                    break 2;
                }
            }
        }

        if (!$shieldAura || !$shieldAura['guid']) return [];

        try {
            $shieldEvents = $this->getEnemyBuffEvents($reportId, $fightIds, (int) $shieldAura['guid']);
        } catch (\Exception $e) {
            return [];
        }
        if (empty($shieldEvents)) return [];

        $castKeywords = ['Shadow Fracture', 'Fearsome Cry', 'Essence Bolt'];
        $targetAbilities = [];
        foreach ($enemyCastEntries as $e) {
            $n = $e['name'] ?? '';
            $g = $e['guid'] ?? null;
            if (!$g) continue;
            foreach ($castKeywords as $kw) {
                if (stripos($n, $kw) !== false) {
                    $targetAbilities[$n] = $g;
                    break;
                }
            }
        }

        $actorMap = [];
        foreach ($cleanPlayers as $p) {
            if (isset($p['id'], $p['name'])) $actorMap[$p['id']] = $p['name'];
        }

        $results = [];
        foreach ($targetAbilities as $abilityName => $abilityId) {
            try {
                // Boss abilities → enemy caster
                $castEvents = $this->getCastEventsForAbility($reportId, $fightIds, (int) $abilityId, true);
                if (empty($castEvents)) continue;

                $parsed = WclReportParserHelper::parseShieldedCasts($castEvents, $shieldEvents, $actorMap, $fightStartTimes);
                if ($parsed['casts_total'] > 0) {
                    $results[$abilityName] = array_merge(
                        ['shield_buff' => $shieldAura['name']],
                        $parsed
                    );
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Extract boss position coordinates from casts of key mechanic abilities.
     */
    private function extractBossPositions(string $reportId, array $fightIds, array $castEntries, array $fightStartTimes): array
    {
        // Key boss mechanic casts to track positioning
        $positioningKeywords = [
            'Entropic Unraveling', 'Primordial Roar', 'Shadowclaw Slam',
            'Imperator\'s Glory', 'Dark Upheaval', 'Void Breath',
        ];

        $targetAbilities = [];
        foreach ($castEntries as $entry) {
            $name = $entry['name'] ?? '';
            $guid = $entry['guid'] ?? null;
            if (!$guid) continue;
            foreach ($positioningKeywords as $kw) {
                if (stripos($name, $kw) !== false) {
                    $targetAbilities[$name] = $guid;
                    break;
                }
            }
        }

        $results = [];
        foreach ($targetAbilities as $abilityName => $abilityId) {
            try {
                // Boss abilities → enemy caster
                $events = $this->getCastEventsForAbility($reportId, $fightIds, (int) $abilityId, true);
                if (empty($events)) continue;
                $parsed = WclReportParserHelper::parseBossPositions($events, $fightStartTimes);
                if ($parsed['casts_with_coords'] > 0) {
                    $results[$abilityName] = $parsed;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $results;
    }

    /**
     * Extract NPC summon waves from events — filters out player pet summons.
     * Maps summon targetID → NPC name via masterData actors.
     *
     * @return array [fight_id => parsed wave data]
     */
    private function extractSummonWaves(string $reportId, array $fightIds, array $allActors, array $fightStartTimes): array
    {
        // Build actor ID → name map for NPCs only
        $npcMap = [];
        foreach ($allActors as $a) {
            if (($a['type'] ?? '') === 'NPC' && isset($a['id'], $a['name'])) {
                $npcMap[$a['id']] = $a['name'];
            }
        }

        if (empty($npcMap)) return [];

        try {
            $events = $this->getSummonEvents($reportId, $fightIds);
        } catch (\Exception $e) {
            return [];
        }

        // Filter: keep only events where targetID is an NPC we know about
        $npcSummons = array_values(array_filter($events, fn($e) => isset($npcMap[$e['targetID'] ?? null])));

        if (empty($npcSummons)) return [];

        // Build abilityGameID → targetName map for labeling
        $abilityMap = [];
        foreach ($npcSummons as $e) {
            $aid = $e['abilityGameID'] ?? 0;
            $tid = $e['targetID'] ?? null;
            if ($aid && $tid && isset($npcMap[$tid]) && !isset($abilityMap[$aid])) {
                $abilityMap[$aid] = $npcMap[$tid];
            }
        }

        return WclReportParserHelper::parseSummonWaves($npcSummons, $fightStartTimes, $abilityMap);
    }

    /**
     * Fetch all debuff stack events for a given ability ID across the provided fights.
     * Paginates through the events API until all events are retrieved.
     *
     * @return array List of event objects with timestamp, type, sourceID, targetID, stack
     */
    public function getDebuffStackEvents(string $reportId, array $fightIds, int $abilityId): array
    {
        $query = WclQueryBuilder::buildDebuffStackEventsQuery();
        $allEvents = [];
        $startTime = 0.0;

        do {
            $data = $this->executeGraphql($query, [
                'reportId'  => $reportId,
                'fightIds'  => $fightIds,
                'abilityId' => (float) $abilityId,
                'startTime' => $startTime,
            ]);

            $page = $data['reportData']['report']['events'] ?? [];
            $events = $page['data'] ?? [];
            $allEvents = array_merge($allEvents, $events);

            $next = $page['nextPageTimestamp'] ?? null;
            if ($next === null || $next <= $startTime) {
                break;
            }
            $startTime = (float) $next;
        } while (true);

        return $allEvents;
    }

    /**
     * Fetch enemy death events (NPC deaths - orbs, adds).
     */
    public function getEnemyDeathEvents(string $reportId, array $fightIds): array
    {
        return $this->paginateEvents(WclQueryBuilder::buildEnemyDeathEventsQuery(), [
            'reportId' => $reportId,
            'fightIds' => $fightIds,
        ]);
    }

    /**
     * Fetch cast events for a specific ability with coordinates (includeResources).
     * Pass $enemyCaster=true to get boss/NPC casts.
     */
    public function getCastEventsForAbility(string $reportId, array $fightIds, int $abilityId, bool $enemyCaster = false): array
    {
        return $this->paginateEvents(WclQueryBuilder::buildCastEventsQuery(), [
            'reportId'      => $reportId,
            'fightIds'      => $fightIds,
            'abilityId'     => (float) $abilityId,
            'hostilityType' => $enemyCaster ? 'Enemies' : 'Friendlies',
        ]);
    }

    /**
     * Fetch enemy buff events (e.g. Nexus Shield on boss clones).
     */
    public function getEnemyBuffEvents(string $reportId, array $fightIds, int $abilityId): array
    {
        return $this->paginateEvents(WclQueryBuilder::buildEnemyBuffEventsQuery(), [
            'reportId'  => $reportId,
            'fightIds'  => $fightIds,
            'abilityId' => (float) $abilityId,
        ]);
    }

    /**
     * Generic helper: paginate events API calls with startTime cursor.
     */
    private function paginateEvents(string $query, array $variables): array
    {
        $all = [];
        $variables['startTime'] = 0.0;

        do {
            $data = $this->executeGraphql($query, $variables);
            $page = $data['reportData']['report']['events'] ?? [];
            $events = $page['data'] ?? [];
            $all = array_merge($all, $events);

            $next = $page['nextPageTimestamp'] ?? null;
            if ($next === null || $next <= $variables['startTime']) break;
            $variables['startTime'] = (float) $next;
        } while (true);

        return $all;
    }

    /**
     * Fetch all summon events (add spawns) across the given fights.
     */
    public function getSummonEvents(string $reportId, array $fightIds): array
    {
        $query = WclQueryBuilder::buildSummonEventsQuery();
        $all = [];
        $startTime = 0.0;

        do {
            $data = $this->executeGraphql($query, [
                'reportId'  => $reportId,
                'fightIds'  => $fightIds,
                'startTime' => $startTime,
            ]);

            $page = $data['reportData']['report']['events'] ?? [];
            $events = $page['data'] ?? [];
            $all = array_merge($all, $events);

            $next = $page['nextPageTimestamp'] ?? null;
            if ($next === null || $next <= $startTime) break;
            $startTime = (float) $next;
        } while (true);

        return $all;
    }

    /**
     * Per-player consumable buff coverage — checks which players had each buff at any point.
     * Iterates over consumable buff aura events and returns a per-player audit.
     *
     * @return array [
     *   'players_with' => [buffName => [playerName, ...]],
     *   'players_without' => [buffName => [playerName, ...]],
     *   'raid_size' => int,
     * ]
     */
    public function getPerPlayerConsumableAudit(string $reportId, array $fightIds, array $cleanPlayers, array $consumableBuffs): array
    {
        $playerNames = array_column($cleanPlayers, 'name');
        $playerIds = array_column($cleanPlayers, 'id');
        $playerMap = array_combine($playerIds, $playerNames);

        $playersWith = [];
        $playersWithout = [];

        foreach ($consumableBuffs as $buffName => $data) {
            $guid = null;
            // Find the guid by querying buffs table again (cheap — we have it cached for raid)
            // Use existing extractor approach: query enemy/friendly buff events for this ability.
            // Simpler: rely on totalUses + bands to detect presence per actor via events query.
            // For minimum cost, skip if this buff was applied to the entire raid (avg_players >= raid_size * 0.95)
            $rs = count($playerNames);
            $avg = $data['avg_players_per_fight'] ?? 0;
            if ($rs > 0 && $avg >= $rs * 0.95) {
                $playersWith[$buffName] = $playerNames;
                $playersWithout[$buffName] = [];
                continue;
            }

            // Otherwise: query buff events to find unique target players
            $q = 'query ($reportId: String!, $fightIds: [Int]!, $abilityName: String!) {
              reportData { report(code: $reportId) {
                events(dataType: Buffs, fightIDs: $fightIds, killType: Encounters, filterExpression: $abilityName, hostilityType: Friendlies, limit: 2000) {
                  data
                }
              } }
            }';
            try {
                $r = $this->executeGraphql($q, [
                    'reportId' => $reportId,
                    'fightIds' => $fightIds,
                    'abilityName' => 'ability.name = "' . str_replace('"', '\\"', $buffName) . '"',
                ]);
                $events = $r['reportData']['report']['events']['data'] ?? [];
                $with = [];
                foreach ($events as $e) {
                    if (($e['type'] ?? '') !== 'applybuff') continue;
                    $tid = $e['targetID'] ?? null;
                    if ($tid && isset($playerMap[$tid])) {
                        $with[$playerMap[$tid]] = true;
                    }
                }
                $playersWith[$buffName] = array_keys($with);
                $playersWithout[$buffName] = array_values(array_diff($playerNames, array_keys($with)));
            } catch (\Exception $e) {
                continue;
            }
        }

        return [
            'players_with'    => $playersWith,
            'players_without' => $playersWithout,
            'raid_size'       => count($playerNames),
        ];
    }

    /**
     * Per-player buff uptime for a specific aura.
     * Calculates uptime % for each player based on applybuff/removebuff events.
     *
     * @return array [playerName => ['uptime_pct' => float, 'uses' => int, 'total_uptime_ms' => int]]
     */
    public function getPerPlayerBuffUptime(string $reportId, array $fightIds, int $abilityId, int $totalDurationMs, array $cleanPlayers = []): array
    {
        if ($totalDurationMs <= 0) return [];

        $playerMap = [];
        foreach ($cleanPlayers as $p) {
            if (isset($p['id'], $p['name'])) $playerMap[$p['id']] = $p['name'];
        }

        $query = 'query ($reportId: String!, $fightIds: [Int]!, $abilityId: Float!, $startTime: Float) {
          reportData { report(code: $reportId) {
            events(dataType: Buffs, fightIDs: $fightIds, killType: Encounters, abilityID: $abilityId, hostilityType: Friendlies, startTime: $startTime, limit: 2000) {
              data
              nextPageTimestamp
            }
          } }
        }';

        try {
            $events = $this->paginateEvents($query, [
                'reportId' => $reportId,
                'fightIds' => $fightIds,
                'abilityId' => (float) $abilityId,
            ]);
        } catch (\Exception $e) {
            return [];
        }

        // Compute uptime per player
        $current = []; // playerId => apply_timestamp
        $totals = []; // playerId => sum_uptime_ms
        $uses = [];

        foreach ($events as $e) {
            $type = $e['type'] ?? '';
            $tid = $e['targetID'] ?? null;
            $ts = $e['timestamp'] ?? 0;
            if (!$tid || !isset($playerMap[$tid])) continue;

            if ($type === 'applybuff') {
                $current[$tid] = $ts;
                $uses[$tid] = ($uses[$tid] ?? 0) + 1;
            } elseif ($type === 'removebuff' && isset($current[$tid])) {
                $totals[$tid] = ($totals[$tid] ?? 0) + ($ts - $current[$tid]);
                unset($current[$tid]);
            } elseif ($type === 'refreshbuff') {
                // refresh extends — keep current apply timestamp
            }
        }

        $result = [];
        foreach ($totals as $tid => $totalMs) {
            $name = $playerMap[$tid];
            $result[$name] = [
                'uptime_pct'      => round($totalMs / $totalDurationMs * 100, 1),
                'uses'            => $uses[$tid] ?? 0,
                'total_uptime_ms' => $totalMs,
            ];
        }
        uasort($result, fn($a, $b) => $b['uptime_pct'] <=> $a['uptime_pct']);
        return $result;
    }

    /**
     * Per-encounter aggregated stats — casts/buffs/dispels/interrupts/debuffs/consumables
     * scoped to a specific boss's fight IDs. Used to populate encounters[].player_stats.
     *
     * @return array{casts_summary: array, consumables_used: array, buff_uptime: array, debuff_uptime: array, dispels: array, interrupts: array}
     */
    public function getPerEncounterStats(string $reportId, array $fightIds, array $rosterNames = [], int $totalDurationMs = 0, array $playerDetails = []): array
    {
        $query = WclQueryBuilder::buildPerEncounterStatsQuery();
        try {
            $data = $this->executeGraphql($query, [
                'reportId' => $reportId,
                'fightIds' => $fightIds,
            ]);
        } catch (\Exception $e) {
            return [
                'casts_summary' => [],
                'consumables_used' => [],
                'buff_uptime' => [],
                'debuff_uptime' => [],
                'dispels' => [],
                'interrupts' => [],
            ];
        }

        $report = $data['reportData']['report'] ?? [];

        // Casts + consumables (re-uses existing parser)
        $castEntries = $report['casts']['data']['entries'] ?? [];
        $castsAndConsumables = WclReportParserHelper::parseCastsAndConsumables($castEntries, $rosterNames);

        // Buff uptime — top 100 most-frequent buffs in this fight
        $buffsTotalTime = $report['buffs']['data']['totalTime'] ?? $totalDurationMs;
        $buffUptime = WclReportParserHelper::parseBuffUptime(
            $report['buffs']['data'] ?? [],
            $buffsTotalTime ?: 1
        );
        $buffUptime = array_slice($buffUptime, 0, 100, true);

        // Debuffs (top 30)
        $debuffUptime = WclReportParserHelper::parseDebuffUptime(
            $report['debuffs']['data'] ?? [],
            $buffsTotalTime ?: 1,
            $rosterNames
        );
        $debuffUptime = array_slice($debuffUptime, 0, 30, true);

        // Dispels
        $dispelEntries = $report['dispels']['data']['entries'][0]['entries'] ?? [];
        $dispels = WclReportParserHelper::parseDispels($dispelEntries, $rosterNames);

        // Interrupts
        $interruptEntries = $report['interrupts']['data']['entries'][0]['entries'] ?? [];
        $interrupts = WclReportParserHelper::parseInterrupts($interruptEntries, $rosterNames);

        // Wave 1: per-player damage / damage taken / healing breakdowns
        $damageDoneBreakdown  = WclReportParserHelper::parseDamageDoneBreakdown(
            $report['damageDone']['data'] ?? [],
            $rosterNames
        );
        $damageTakenBreakdown = WclReportParserHelper::parseDamageTakenBreakdown(
            $report['damageTaken']['data'] ?? [],
            $rosterNames
        );
        $healingBreakdown     = WclReportParserHelper::parseHealingBreakdown(
            $report['healing']['data'] ?? [],
            $rosterNames
        );
        $healTargets          = WclReportParserHelper::parseHealTargets(
            $report['healing']['data'] ?? [],
            $rosterNames,
            $playerDetails
        );

        return [
            'casts_summary'           => $castsAndConsumables['casts'],
            'consumables_used'        => $castsAndConsumables['consumables'],
            'buff_uptime'             => $buffUptime,
            'debuff_uptime'           => $debuffUptime,
            'dispels'                 => $dispels,
            'interrupts'              => $interrupts,
            'damage_done_breakdown'   => $damageDoneBreakdown,
            'damage_taken_breakdown'  => $damageTakenBreakdown,
            'healing_breakdown'       => $healingBreakdown,
            'heal_targets'            => $healTargets,
        ];
    }

    /**
     * Cooldown discipline analysis for a single boss. Queries cast events filtered to a list
     * of major cooldown ability IDs, returns per-player per-ability timing data.
     *
     * @param int[]  $abilityIds   Major cooldown spell IDs (cooldown_seconds >= some threshold)
     * @param array  $cooldownsByAbility  abilityId → cooldown_seconds (used by parser)
     * @param array  $abilityNames        abilityId → human-readable name
     * @param array  $actorMap            actorId → playerName
     * @param array  $fightDurations      fightId → duration_seconds (encounter total)
     */
    public function getCooldownTimings(
        string $reportId,
        array $fightIds,
        array $abilityIds,
        array $cooldownsByAbility,
        array $abilityNames,
        array $actorMap,
        array $fightDurations
    ): array {
        if (empty($abilityIds) || empty($fightIds)) return [];

        $expr = 'ability.id IN (' . implode(',', array_unique(array_map('intval', $abilityIds))) . ')';
        $query = WclQueryBuilder::buildCooldownEventsQuery();

        try {
            $data = $this->executeGraphql($query, [
                'reportId'         => $reportId,
                'fightIds'         => $fightIds,
                'filterExpression' => $expr,
            ]);
        } catch (\Exception $e) {
            return [];
        }

        $events = $data['reportData']['report']['events']['data'] ?? [];
        return WclReportParserHelper::parseCooldownEvents(
            $events,
            $actorMap,
            $abilityNames,
            $cooldownsByAbility,
            $fightDurations
        );
    }

    /**
     * Fetch applybuff events for a list of external cooldown spell IDs. Returns events
     * grouped by (caster → target → ability) so the analyzer can show "who threw what on whom".
     *
     * @param int[] $abilityIds
     * @param array<int, string> $actorMap  actorID → name
     * @param array<int, string> $abilityNames  abilityID → name
     * @return array<int, array{timestamp:int, fight:?int, caster:string, target:string, ability:string, ability_id:int}>
     */
    public function getExternalCooldownEvents(
        string $reportId,
        array $fightIds,
        array $abilityIds,
        array $actorMap,
        array $abilityNames
    ): array {
        if (empty($abilityIds) || empty($fightIds)) return [];

        $expr = 'type = "applybuff" AND ability.id IN (' . implode(',', array_unique(array_map('intval', $abilityIds))) . ')';
        $query = WclQueryBuilder::buildCooldownEventsQuery(); // reuse: Casts events query
        // Note: we need Buffs dataType, not Casts. Build a dedicated query instead.
        $query = <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $filterExpression: String!) {
          reportData {
            report(code: $reportId) {
              events(dataType: Buffs, fightIDs: $fightIds, killType: Encounters, filterExpression: $filterExpression, limit: 5000, hostilityType: Friendlies) {
                data
              }
            }
          }
        }
GQL;

        try {
            $data = $this->executeGraphql($query, [
                'reportId'         => $reportId,
                'fightIds'         => $fightIds,
                'filterExpression' => $expr,
            ]);
        } catch (\Exception $e) {
            return [];
        }

        $events = $data['reportData']['report']['events']['data'] ?? [];
        $out = [];
        foreach ($events as $e) {
            if (($e['type'] ?? '') !== 'applybuff') continue;
            $sid = $e['sourceID'] ?? null;
            $tid = $e['targetID'] ?? null;
            $aid = $e['abilityGameID'] ?? null;
            $ts  = $e['timestamp'] ?? null;
            if ($sid === null || $tid === null || $aid === null) continue;

            $caster = $actorMap[$sid] ?? null;
            $target = $actorMap[$tid] ?? null;
            if (!$caster || !$target) continue;

            $out[] = [
                'timestamp'  => (int) $ts,
                'fight'      => $e['fight'] ?? null,
                'caster'     => $caster,
                'target'     => $target,
                'ability'    => $abilityNames[$aid] ?? "Spell {$aid}",
                'ability_id' => (int) $aid,
            ];
        }

        return $out;
    }

    /**
     * Per-boss adds lookup. Returns NPC targets and per-player damage dealt to them
     * in the specified fights (avoids the top-N merge across all bosses in the report).
     *
     * @return array [addName => ['total' => int, 'top_sources' => [playerName => int]]]
     */
    public function getBossAdds(string $reportId, array $fightIds, array $rosterNames = []): array
    {
        $query = WclQueryBuilder::buildBossAddsQuery();
        try {
            $data = $this->executeGraphql($query, [
                'reportId' => $reportId,
                'fightIds' => $fightIds,
            ]);
        } catch (\Exception $e) {
            return [];
        }

        $entries = $data['reportData']['report']['addsDamage']['data']['entries'] ?? [];
        $parsed = WclReportParserHelper::parseTargetDamage($entries, $rosterNames);
        return $parsed['adds'] ?? [];
    }

    /**
     * Targeted damage_taken lookup for a specific ability across given fights.
     *
     * @return array{ability_id: int, entries: array} — entries are [name, type, total, ...]
     */
    public function getDamageTakenForAbility(string $reportId, array $fightIds, int $abilityId): array
    {
        $query = WclQueryBuilder::buildTargetedDamageTakenQuery();
        try {
            $data = $this->executeGraphql($query, [
                'reportId'  => $reportId,
                'fightIds'  => $fightIds,
                'abilityId' => (float) $abilityId,
            ]);
        } catch (\Exception $e) {
            return ['ability_id' => $abilityId, 'entries' => []];
        }

        $entries = $data['reportData']['report']['damageTaken']['data']['entries'] ?? [];
        return [
            'ability_id' => $abilityId,
            'entries'    => array_values(array_filter($entries, fn($e) => ($e['type'] ?? '') !== 'NPC')),
        ];
    }

    /**
     * Batch-fetch damage_taken for multiple abilities. Filters to a specific boss's fights.
     * Used by TacticalDataAnalyzer for precise per-mechanic damage aggregation.
     *
     * @param int[] $abilityIds
     * @param int[] $fightIds  (boss-specific)
     * @return array [abilityId => parsed damage taken]
     */
    public function getTargetedDamageTakenBatch(string $reportId, array $fightIds, array $abilityIds, array $rosterNames = []): array
    {
        $result = [];
        foreach ($abilityIds as $aid) {
            $aid = (int) $aid;
            if ($aid <= 0) continue;

            $raw = $this->getDamageTakenForAbility($reportId, $fightIds, $aid);
            $victims = [];
            foreach ($raw['entries'] as $playerEntry) {
                $pname = $playerEntry['name'] ?? '';
                if (!empty($rosterNames) && !in_array($pname, $rosterNames)) continue;

                // When querying a single abilityID, WCL returns total damage directly
                // on the player entry (not nested in abilities[]).
                $dmg = (int) ($playerEntry['total'] ?? 0);
                if ($dmg > 0) $victims[$pname] = $dmg;
            }
            arsort($victims);
            if (!empty($victims)) {
                $total = array_sum($victims);
                $result[$aid] = [
                    'ability_id'           => $aid,
                    'total_damage_to_raid' => $total,
                    'biggest_victims'      => array_slice($victims, 0, 5, true),
                    'hit_count'            => count($victims),
                ];
            }
        }
        return $result;
    }

    /**
     * Fetch character parses from the WCL API.
     */
    public function getCharacterParses(string $region, string $server, string $name): ?array
    {
        $query = WclQueryBuilder::buildCharacterParsesQuery();

        $variables = [
            'name'         => $name,
            'serverSlug'   => $server,
            'serverRegion' => $region,
        ];

        try {
            return $this->executeGraphql($query, $variables);
        } catch (\Exception $e) {
            return null;
        }
    }
}
