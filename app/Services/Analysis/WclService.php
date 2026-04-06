<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Helpers\WclQueryBuilder;
use App\Helpers\WclReportParserHelper;
use App\Tasks\Analysis\ExecuteWclGraphqlTask;

class WclService
{
    public function __construct(
        protected ExecuteWclGraphqlTask $executeWclGraphqlTask
    ) {}

    public function getLogSummary(string $reportId, array $rosterNames = []): array
    {
        // --- Query 1: fights + phases + masterData ---
        $fightsQuery = WclQueryBuilder::buildFightsQuery();
        $initialData = $this->executeWclGraphqlTask->run($fightsQuery, ['reportId' => $reportId])['reportData']['report'] ?? [];

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
        $tablesData  = $this->executeWclGraphqlTask->run($tablesQuery, [
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
            return $this->executeWclGraphqlTask->run($query, $variables);
        } catch (\Exception $e) {
            return null;
        }
    }
}
