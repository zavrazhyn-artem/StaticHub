<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\WclQueryBuilder;
use App\Helpers\WclReportParserHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WclService
{
    protected string $baseUrl = 'https://www.warcraftlogs.com/api/v2/client';
    protected ?string $accessToken = null;

    /**
     * Execute a WCL GraphQL query.
     */
    public function executeGraphql(string $query, array $variables = []): array
    {
        $response = Http::withToken($this->getAccessToken())->post($this->baseUrl, [
            'query' => $query,
            'variables' => $variables,
        ]);

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

        $response = Http::asForm()->post('https://www.warcraftlogs.com/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $token = $response->json('access_token');
        Cache::put('wcl_access_token', $token, 3600);

        return $this->accessToken = $token;
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
