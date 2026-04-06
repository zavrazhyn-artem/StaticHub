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

        // Deaths (with fight-relative timestamp)
        $cleanDeaths = WclReportParserHelper::parseDeaths(
            $tablesData['deaths']['data'] ?? [],
            $rosterNames,
            $fightStartTimes
        );

        // Interrupts
        $interruptEntries = $tablesData['interrupts']['data']['entries'][0]['entries'] ?? [];
        $cleanInterrupts  = WclReportParserHelper::parseInterrupts($interruptEntries, $rosterNames);

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
        $buffUptime = WclReportParserHelper::parseBuffUptime(
            $tablesData['buffs']['data'] ?? [],
            $totalDurationMs
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

        // Rankings (parse %)
        $rankingsRaw = $tablesData['rankings'] ?? null;
        $rankings    = WclReportParserHelper::parseRankings($rankingsRaw, $rosterNames);

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
            'fight_durations'    => $durations,
            'phase_summary'      => $phaseSummary,
            'players'            => $cleanPlayers,
            'player_details'     => $playerDetails,
            'deaths'             => $cleanDeaths,
            'interrupts'         => $cleanInterrupts,
            'major_damage_taken' => array_slice($cleanDamageTaken, 0, 15),
            'casts_summary'      => $castsAndConsumables['casts'],
            'consumables_used'   => $castsAndConsumables['consumables'],
            'dispels'            => $cleanDispels,
            'buff_uptime'        => $buffUptime,
            'debuff_uptime'      => $debuffUptime,
            'resource_waste'     => $resourceWaste,
            'performance_metrics' => $performanceMetrics,
        ];
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
