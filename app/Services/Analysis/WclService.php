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

        // Roster: filter to known players
        $allActors    = $initialData['masterData']['actors'] ?? [];
        $cleanPlayers = array_values(array_filter(
            $allActors,
            fn($a) => empty($rosterNames) || in_array($a['name'], $rosterNames)
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
            'raid_title'         => $initialData['title'] ?? 'Raid Analysis',
            'difficulties'       => $difficulties,
            'has_kills'          => $hasKills,
            'fight_durations'    => $durations,
            'phase_summary'      => $phaseSummary,
            'players'            => $cleanPlayers,
            'player_details'     => $playerDetails,
            'deaths'             => $cleanDeaths,
            'major_damage_taken' => array_slice($cleanDamageTaken, 0, 15),
            'casts_summary'      => $castsAndConsumables['casts'],
            'consumables_used'   => $consumables,
            'dispels'            => $cleanDispels,
            'consumable_buffs'   => $consumableBuffs,
            'buff_uptime'        => $buffUptime,
            'debuff_uptime'      => $debuffUptime,
            'resource_waste'     => $resourceWaste,
            'performance_metrics' => $performanceMetrics,
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
