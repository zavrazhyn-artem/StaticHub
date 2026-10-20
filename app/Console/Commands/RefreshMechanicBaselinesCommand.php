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
 * and merges an `empirical:` block into the tactics .md frontmatter
 * (authored `mechanics:` + markdown body preserved).
 *
 * Usage:
 *   php artisan refresh:mechanic-baselines --bosses=lightblinded-vanguard --kills=5 --dry-run
 *   php artisan refresh:mechanic-baselines               # all tactics files
 */
class RefreshMechanicBaselinesCommand extends Command
{
    protected $signature = 'refresh:mechanic-baselines
                            {--bosses= : comma-separated tactics slugs (default: all in resources/tactics/)}
                            {--kills=10 : top kills to sample per boss}
                            {--difficulty=5 : 3=Normal 4=Heroic 5=Mythic (WCL ranking param)}
                            {--dry-run : print summary without writing}
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
        $difficulty = (int) $this->option('difficulty');
        $diffName = self::DIFFICULTY_NAME[$difficulty] ?? 'mythic';
        $kills = (int) $this->option('kills');
        $pace = (int) $this->option('pace');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $dir = resource_path('tactics');
        $slugs = $this->resolveBosses((string) $this->option('bosses'), $dir);

        if (empty($slugs)) {
            $this->error("No tactics .md files found in {$dir}.");
            return self::FAILURE;
        }

        foreach ($slugs as $slug) {
            $path = "{$dir}/{$slug}.md";
            $parsed = $this->parseTacticsFile($path);
            if (!$parsed) {
                $this->warn("  {$slug}: no YAML frontmatter — skipped.");
                continue;
            }
            [$frontmatter, $body] = $parsed;

            $encounterId = (int) ($frontmatter['wcl_encounter_id'] ?? 0);
            $mechanics = $frontmatter['mechanics'] ?? [];
            if ($encounterId === 0 || empty($mechanics)) {
                $this->warn("  {$slug}: missing wcl_encounter_id or mechanics — skipped.");
                continue;
            }

            $reports = $this->topKillReports($encounterId, $difficulty, $kills, $pace);
            if (empty($reports)) {
                $this->warn("  {$slug}: no top-kill reports returned — skipped.");
                continue;
            }

            $vectorsByMechanic = [];
            $sampled = 0;
            foreach ($reports as [$reportCode, $fightId]) {
                try {
                    $logData = $this->getOrFetchLog($slug, $reportCode, $fightId, $difficulty, $force, $pace);
                } catch (\Throwable $e) {
                    $this->warn("    skip {$reportCode}: " . $e->getMessage());
                    continue;
                }
                $sampled++;
                $debuffStacks = $logData['debuff_stacks'] ?? [];

                foreach ($mechanics as $mech) {
                    if (($mech['type'] ?? '') !== 'tank_swap') {
                        continue; // Phase 1 models tank_swap only
                    }
                    $name = (string) ($mech['name'] ?? '');
                    $abilityIds = $mech['ability_ids'] ?? [];
                    $aliases = $mech['aliases'] ?? [];
                    foreach ($debuffStacks as $debuffName => $entry) {
                        $guid = isset($entry['guid']) ? (int) $entry['guid'] : null;
                        if (!MechanicMetricsNormalizer::debuffMatchesMechanic($debuffName, $guid, $name, $abilityIds, $aliases)) {
                            continue;
                        }
                        $vectorsByMechanic[$name][] = $this->normalizer->tankSwap($entry);
                    }
                }
            }

            $empiricalMechanics = [];
            foreach ($vectorsByMechanic as $name => $vectors) {
                $agg = $this->normalizer->aggregateTankSwap($vectors);
                // Skip non-stacking debuffs (event-based "tank swap" like
                // Judgment+Shield where each apply is 1 stack). Writing
                // p95=1 would cause the live evaluator to flag every swap.
                $p95 = $agg['metrics']['swap_at_stacks']['p95'] ?? 0;
                if ($p95 < 2) {
                    $this->warn("    skip {$name}: not stack-based (p95={$p95}).");
                    continue;
                }
                $empiricalMechanics[$name] = $agg;
            }

            if (empty($empiricalMechanics)) {
                $this->warn("  {$slug}: no tank_swap data extracted — nothing written.");
                continue;
            }

            $empirical = [
                'difficulty'    => $diffName,
                'kills_sampled' => $sampled,
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
                $frontmatter['empirical'] = $empirical;
                $yaml = Yaml::dump($frontmatter, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
                $out = "---\n" . $yaml . "---\n" . $body;
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
            fn($p) => basename($p, '.md'),
            glob("{$dir}/*.md") ?: []
        ));
    }

    /**
     * Read a tactics .md file and split it into [frontmatter array, markdown body].
     *
     * @return array{0: array, 1: string}|null
     */
    private function parseTacticsFile(string $path): ?array
    {
        $content = @file_get_contents($path);
        if (!$content) {
            return null;
        }
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $m)) {
            return null;
        }
        try {
            $data = Yaml::parse($m[1]) ?? [];
        } catch (\Throwable) {
            return null;
        }
        return [is_array($data) ? $data : [], $m[2]];
    }

    /**
     * Top-kill (report, fightID) pairs for an encounter, deduped to unique
     * report codes. Uses a classless `dps`-metric query — kill reports are
     * what top parses live in regardless of role, and dedupe collapses
     * multiple top parsers in one kill log into a single report fetch.
     *
     * @return list<array{0:string,1:int}>
     */
    private function topKillReports(int $encounterId, int $difficulty, int $kills, int $pace): array
    {
        $query = 'query ($id: Int!, $diff: Int!) {
            worldData {
              encounter(id: $id) {
                characterRankings(metric: dps, difficulty: $diff, page: 1)
              }
            }
          }';

        try {
            $resp = $this->wcl->executeGraphql($query, ['id' => $encounterId, 'diff' => $difficulty]);
        } catch (\Throwable) {
            return [];
        }
        if ($pace > 0) {
            sleep($pace);
        }

        $payload = $resp['worldData']['encounter']['characterRankings'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        $rankings = $payload['rankings'] ?? [];

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

        // mkdir respects umask, so a host-side test run leaves a 0755-owned
        // dir that docker (www-data) cannot write into. chmod the cache root
        // and the per-boss subdir to 0777 after creation so both UIDs share.
        $cacheRoot = storage_path('app/' . self::CACHE_DIR);
        $bossDir = dirname($cachePath);
        foreach ([$cacheRoot, $bossDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException("Failed to create cache dir: {$dir}");
            }
            @chmod($dir, 0777);
        }

        $logData = $this->wcl->getLogSummary($reportCode, []);
        if ($pace > 0) {
            sleep($pace);
        }
        file_put_contents($cachePath, json_encode($logData, JSON_UNESCAPED_UNICODE));
        @chmod($cachePath, 0666);
        return $logData;
    }
}
