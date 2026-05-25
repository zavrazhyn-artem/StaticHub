<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\MechanicMetricsNormalizer;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Refresh per-boss mechanic baselines from top WCL kills. Mirrors
 * refresh:spec-baselines but for boss mechanics: harvests top-kill logs,
 * runs the existing getLogSummary extraction, normalises each log's
 * mechanics through MechanicMetricsNormalizer, aggregates to median/p5/p95,
 * and merges an `empirical:` block into the boss-timeline YAML (authored
 * `mechanics:` preserved).
 *
 * Usage:
 *   php artisan refresh:mechanic-baselines --bosses=vaelgor-ezzorak --kills=5 --dry-run
 *   php artisan refresh:mechanic-baselines               # all bosses in the season
 */
class RefreshMechanicBaselinesCommand extends Command
{
    protected $signature = 'refresh:mechanic-baselines
                            {--bosses= : comma-separated boss slugs (default: all in season/difficulty)}
                            {--kills=10 : top kills to sample per boss}
                            {--difficulty=5 : 3=Normal 4=Heroic 5=Mythic}
                            {--season= : season slug (default: config wow_season.current_season)}
                            {--dry-run : print summary without writing YAML}
                            {--force : ignore cached per-report extraction}
                            {--pace=6 : seconds between WCL calls}';

    protected $description = 'Refresh boss mechanic baselines from empirical top-kill WCL data.';

    private const CACHE_DIR = 'mechanic-baseline-cache';
    private const CACHE_TTL_DAYS = 7;

    private const DIFFICULTY_NAME = [3 => 'normal', 4 => 'heroic', 5 => 'mythic'];

    public function __construct(
        private readonly WclService $wcl,
        private readonly MechanicMetricsNormalizer $normalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $season = (string) ($this->option('season') ?: config('wow_season.current_season'));
        $difficulty = (int) $this->option('difficulty');
        $diffName = self::DIFFICULTY_NAME[$difficulty] ?? 'mythic';
        $kills = (int) $this->option('kills');
        $pace = (int) $this->option('pace');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $dir = resource_path("boss-timelines/{$season}/{$diffName}");
        $slugs = $this->resolveBosses((string) $this->option('bosses'), $dir);

        if (empty($slugs)) {
            $this->error("No boss-timeline YAMLs found in {$dir}.");
            return self::FAILURE;
        }

        foreach ($slugs as $slug) {
            $path = "{$dir}/{$slug}.yml";
            $yaml = Yaml::parseFile($path) ?: [];
            $encounterId = (int) ($yaml['wcl_encounter_id'] ?? 0);
            $mechanics = $yaml['mechanics'] ?? [];
            if ($encounterId === 0 || empty($mechanics)) {
                $this->warn("  {$slug}: missing wcl_encounter_id or mechanics — skipped.");
                continue;
            }

            $reports = $this->topKillReports($encounterId, $difficulty, $kills, $pace);
            if (empty($reports)) {
                $this->warn("  {$slug}: no top-kill reports returned — skipped.");
                continue;
            }

            // type → mechanic name → list of per-log vectors
            $vectorsByMechanic = [];
            foreach ($reports as [$reportCode, $fightId]) {
                $logData = $this->getOrFetchLog($slug, $reportCode, $fightId, $difficulty, $force, $pace);
                $debuffStacks = $logData['debuff_stacks'] ?? [];

                foreach ($mechanics as $mech) {
                    if (($mech['type'] ?? '') !== 'tank_swap') {
                        continue; // Phase 1 models tank_swap only
                    }
                    $name = (string) ($mech['name'] ?? '');
                    $aliases = $mech['aliases'] ?? [];
                    foreach ($debuffStacks as $debuffName => $entry) {
                        if (!MechanicMetricsNormalizer::debuffMatchesMechanic($debuffName, $name, $aliases)) {
                            continue;
                        }
                        $vectorsByMechanic[$name][] = $this->normalizer->tankSwap($entry);
                    }
                }
            }

            $empiricalMechanics = [];
            foreach ($vectorsByMechanic as $name => $vectors) {
                $empiricalMechanics[$name] = $this->normalizer->aggregateTankSwap($vectors);
            }

            if (empty($empiricalMechanics)) {
                $this->warn("  {$slug}: no tank_swap data extracted — nothing written.");
                continue;
            }

            $empirical = [
                'difficulty'    => $diffName,
                'kills_sampled' => count($reports),
                'fetched_at'    => now()->toDateString(),
                'mechanics'     => $empiricalMechanics,
            ];

            $this->line("  {$slug}: " . count($empiricalMechanics) . " mechanic(s), {$empirical['kills_sampled']} kills sampled.");
            foreach ($empiricalMechanics as $name => $e) {
                $sa = $e['metrics']['swap_at_stacks'] ?? null;
                if ($sa) {
                    $this->line(sprintf('      %s: swap_at_stacks median=%.1f p95=%.1f lethal=%s', $name, $sa['median'], $sa['p95'], $e['lethal_stacks'] ?? '—'));
                }
            }

            if (!$dryRun) {
                $yaml['empirical'] = $empirical;
                $out = Yaml::dump($yaml, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                $tmp = $path . '.tmp';
                file_put_contents($tmp, $out);
                rename($tmp, $path);
                $this->info("  {$slug}: empirical block written.");
            }
        }

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function resolveBosses(string $arg, string $dir): array
    {
        if ($arg !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $arg))));
        }
        if (!is_dir($dir)) {
            return [];
        }
        return array_values(array_map(
            fn($p) => basename($p, '.yml'),
            glob("{$dir}/*.yml") ?: []
        ));
    }

    /**
     * Top-kill (report, fightID) pairs for an encounter, deduped to unique
     * report codes. Reuses fetchCharacterRankings (kill reports are what top
     * parses live in); dedupe collapses multiple top parsers in one kill log.
     *
     * @return list<array{0:string,1:int}>
     */
    private function topKillReports(int $encounterId, int $difficulty, int $kills, int $pace): array
    {
        // A single reference query (any high-representation spec) returns the
        // top kill reports; we only need the report codes, deduped.
        $rankings = $this->wcl->fetchCharacterRankings($encounterId, 'DeathKnight', 'Blood', 'playerscore', $difficulty, 1);
        if ($pace > 0) {
            sleep($pace);
        }

        $seen = [];
        $out = [];
        foreach ($rankings as $rank) {
            $code = $rank['report']['code'] ?? null;
            $fightId = (int) ($rank['report']['fightID'] ?? 0);
            if (!$code || !$fightId || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = [$code, $fightId];
            if (count($out) >= $kills) {
                break;
            }
        }
        return $out;
    }

    private function getOrFetchLog(string $slug, string $reportCode, int $fightId, int $difficulty, bool $force, int $pace): array
    {
        $cachePath = storage_path(sprintf('app/%s/%s/%s-f%d-d%d.json', self::CACHE_DIR, $slug, $reportCode, $fightId, $difficulty));

        if (!$force && file_exists($cachePath)) {
            $age = time() - filemtime($cachePath);
            if ($age < self::CACHE_TTL_DAYS * 86400) {
                $cached = json_decode((string) file_get_contents($cachePath), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        }

        @mkdir(dirname($cachePath), 0775, true);
        $logData = $this->wcl->getLogSummary($reportCode, []);
        if ($pace > 0) {
            sleep($pace);
        }
        file_put_contents($cachePath, json_encode($logData, JSON_UNESCAPED_UNICODE));
        return $logData;
    }
}
