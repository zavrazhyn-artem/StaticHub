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

        $bossSummary = [];
        foreach ($raidFights as $fight) {
            $bossName = $fight['name'] ?? 'Unknown';
            if (!isset($bossSummary[$bossName])) {
                $bossSummary[$bossName] = ['kills' => 0, 'wipes' => 0, 'best_wipe_pct' => null];
            }
            if ($fight['kill'] ?? false) {
                $bossSummary[$bossName]['kills']++;
            } else {
                $bossSummary[$bossName]['wipes']++;
                $pct = isset($fight['bossPercentage']) ? round((float) $fight['bossPercentage'], 1) : null;
                if ($pct !== null && ($bossSummary[$bossName]['best_wipe_pct'] === null || $pct < $bossSummary[$bossName]['best_wipe_pct'])) {
                    $bossSummary[$bossName]['best_wipe_pct'] = $pct;
                }
            }
        }

        $fightIds = array_column($raidFights, 'id');

        $tablesQuery = WclQueryBuilder::buildTablesQuery();
        $tablesData = $this->executeWclGraphqlTask->run($tablesQuery, [
            'reportId' => $reportId,
            'fightIds' => array_values($fightIds)
        ])['reportData']['report'] ?? [];

        // 1. Filter Roster
        $allActors = $initialData['masterData']['actors'] ?? [];
        $cleanPlayers = [];
        foreach ($allActors as $actor) {
            if (empty($rosterNames) || in_array($actor['name'], $rosterNames)) {
                $cleanPlayers[] = $actor;
            }
        }

        // 2. Parse Deaths
        $cleanDeaths = WclReportParserHelper::parseDeaths($tablesData['deaths']['data'] ?? [], $rosterNames);

        // 3. Parse Interrupts
        $interruptEntries = $tablesData['interrupts']['data']['entries'][0]['entries'] ?? [];
        $cleanInterrupts = WclReportParserHelper::parseInterrupts($interruptEntries, $rosterNames);

        // 4. Parse Damage Taken
        $cleanDamageTaken = WclReportParserHelper::parseDamageTaken($tablesData['damageTaken']['data']['entries'] ?? [], $rosterNames);

        // 5. Parse Casts & Consumables
        $castsAndConsumables = WclReportParserHelper::parseCastsAndConsumables(
            $tablesData['casts']['data']['entries'] ?? [],
            $rosterNames
        );

        // 6. Calculate Metrics
        $raidDuration = WclReportParserHelper::calculateRaidDuration($raidFights);
        $performanceMetrics = WclReportParserHelper::calculatePerformanceMetrics(
            $tablesData['damageDone']['data']['entries'] ?? [],
            $tablesData['healing']['data']['entries'] ?? [],
            $raidDuration,
            $rosterNames
        );

        // Inject spec into performance_metrics from players list
        $playerSpecMap = array_column($cleanPlayers, 'subType', 'name');
        foreach ($performanceMetrics as $name => &$metrics) {
            $metrics['spec'] = $playerSpecMap[$name] ?? null;
        }
        unset($metrics);

        // 7. Parse Dispels (same structure as interrupts: data.entries[0].entries with details per player)
        $dispelEntries = $tablesData['dispels']['data']['entries'][0]['entries'] ?? [];
        $cleanDispels = WclReportParserHelper::parseDispels($dispelEntries, $rosterNames);

        return [
            'raid_title'        => $initialData['title'] ?? 'Raid Analysis',
            'difficulties'      => $difficulties,
            'boss_summary'      => $bossSummary,
            'players'           => $cleanPlayers,
            'deaths'            => $cleanDeaths,
            'interrupts'        => $cleanInterrupts,
            'major_damage_taken' => array_slice($cleanDamageTaken, 0, 15),
            'casts_summary'     => $castsAndConsumables['casts'],
            'consumables_used'  => $castsAndConsumables['consumables'],
            'dispels'           => $cleanDispels,
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
            'name' => $name,
            'serverSlug' => $server,
            'serverRegion' => $region,
        ];

        try {
            return $this->executeWclGraphqlTask->run($query, $variables);
        } catch (\Exception $e) {
            return null;
        }
    }
}
