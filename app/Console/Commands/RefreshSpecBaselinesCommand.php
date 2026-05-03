<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\SpecBaselineLoader;
use App\Services\Analysis\WclService;
use App\Services\Blizzard\BlizzardGameDataApiService;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Refresh per-spec rotation baselines using empirical data from top WCL
 * parsers (98-99-100 percentile). Replaces theoretical icy-veins/wowanalyzer
 * thresholds with what top players ACTUALLY do in real combat.
 *
 * For each (spec × boss × parser):
 *   - read casts table → CPM per ability
 *   - read playerDetails → talents loadout
 *   - aggregate per ability across the sample → median + p95
 *
 * Per-spec output written into resources/spec-baselines/{slug}.yaml as a
 * new `empirical:` block alongside existing rotation_checks. Existing
 * theoretical entries are kept (source of truth fallback when sample data
 * is thin).
 *
 * Usage:
 *   php artisan refresh:spec-baselines
 *   php artisan refresh:spec-baselines --specs=druid-restoration,demonhunter-devourer
 *   php artisan refresh:spec-baselines --bosses=3 --dry-run
 *   php artisan refresh:spec-baselines --force          # ignore cache
 */
class RefreshSpecBaselinesCommand extends Command
{
    protected $signature = 'refresh:spec-baselines
                            {--specs= : comma-separated class-spec slugs (default: all)}
                            {--bosses=3 : number of bosses to sample per spec}
                            {--parses=10 : top parses per (spec, boss)}
                            {--difficulty=5 : 3=Normal, 4=Heroic, 5=Mythic}
                            {--dry-run : print summary without writing YAMLs}
                            {--force : ignore cached parse data}
                            {--pace=6 : seconds between WCL calls}';

    protected $description = 'Refresh spec rotation baselines from empirical WCL top parses.';

    private const CACHE_DIR = 'spec-baseline-cache';
    private const CACHE_TTL_DAYS = 7;

    public function __construct(
        private readonly WclService $wcl,
        private readonly BlizzardGameDataApiService $blizzard,
        private readonly SpecBaselineLoader $loader,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $specsArg = (string) $this->option('specs');
        $bosses = max(1, (int) $this->option('bosses'));
        $parsesPerBoss = max(1, (int) $this->option('parses'));
        $difficulty = (int) $this->option('difficulty');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $pace = max(1, (int) $this->option('pace'));

        $specs = $this->resolveSpecs($specsArg);
        $encounterIds = $this->pickBosses($bosses);

        $this->info(sprintf(
            'Refreshing %d specs × %d bosses × %d parses (difficulty=%d, pace=%ds, dry_run=%s)',
            count($specs), count($encounterIds), $parsesPerBoss, $difficulty, $pace, $dryRun ? 'yes' : 'no'
        ));

        $bar = $this->output->createProgressBar(count($specs));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('starting...');
        $bar->start();

        $abilityCache = []; // ability_id => description
        $writtenCount = 0;

        foreach ($specs as $specSlug) {
            $bar->setMessage($specSlug);
            try {
                $specData = $this->refreshSpec(
                    $specSlug,
                    $encounterIds,
                    $parsesPerBoss,
                    $difficulty,
                    $force,
                    $pace,
                    $abilityCache
                );

                if (!$specData) {
                    $bar->advance();
                    continue;
                }

                if ($dryRun) {
                    $this->printDiff($specSlug, $specData);
                } else {
                    $this->mergeYaml($specSlug, $specData);
                    $writtenCount++;
                }
            } catch (\Throwable $e) {
                $this->error("\n{$specSlug}: " . $e->getMessage());
                $this->line($e->getFile() . ':' . $e->getLine());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(sprintf('Done. %d / %d specs %s.',
            $writtenCount, count($specs), $dryRun ? 'previewed' : 'written'));

        return self::SUCCESS;
    }

    /**
     * @return list<string> spec slugs (e.g. "druid-restoration")
     */
    private function resolveSpecs(string $arg): array
    {
        if ($arg !== '') {
            return array_filter(array_map('trim', explode(',', $arg)));
        }
        $files = glob(resource_path('spec-baselines/*.yaml')) ?: [];
        return array_map(
            fn($p) => preg_replace('/\.yaml$/', '', basename($p)),
            $files
        );
    }

    /**
     * Pick N encounter IDs from current-tier config.
     * @return list<int>
     */
    private function pickBosses(int $count): array
    {
        $all = array_keys((array) config('wow_season.wcl_encounter_ids', []));
        if (empty($all)) {
            throw new \RuntimeException('No encounter IDs found in config/wow_season.php');
        }
        shuffle($all);
        return array_slice($all, 0, $count);
    }

    /**
     * Aggregate parse data for ONE spec across the chosen bosses. Returns the
     * empirical block ready to merge into YAML, or null if sample is empty.
     *
     * @param array<string,string> &$abilityCache  ability_id => description (mutated)
     * @return array{
     *   bosses_sampled: list<string>,
     *   sample_size: int,
     *   abilities: array<int, array>,
     *   talents: array<string,int>,
     *   fetched_at: string,
     * }|null
     */
    private function refreshSpec(
        string $specSlug,
        array $encounterIds,
        int $parsesPerBoss,
        int $difficulty,
        bool $force,
        int $pace,
        array &$abilityCache
    ): ?array {
        [$class, $spec] = $this->parseSlug($specSlug);
        $role = $this->roleFor($specSlug);
        $metric = match ($role) {
            'healers' => 'hps',
            'tanks'   => 'playerscore',
            default   => 'dps',
        };

        $bossEncounterMap = (array) config('wow_season.wcl_encounter_ids', []);
        $bossesSampled = [];
        $allCpms = [];                  // ability_id => list<float>
        $allCounts = [];                // ability_id => list<int>
        $abilityNames = [];             // ability_id => string
        $talentBuilds = [];             // talents-string => count

        foreach ($encounterIds as $encId) {
            $bossName = $bossEncounterMap[$encId] ?? "Encounter {$encId}";
            $cacheData = $this->getOrFetchSpecBoss(
                $specSlug, $class, $spec, $encId, $metric, $difficulty,
                $parsesPerBoss, $force, $pace
            );

            if (empty($cacheData['parsers'])) continue;
            $bossesSampled[] = $bossName;

            foreach ($cacheData['parsers'] as $parser) {
                $duration = max(1, (int) ($parser['fight_duration_s'] ?? 1));

                foreach ($parser['casts'] as $cast) {
                    $abilityId = (int) ($cast['guid'] ?? 0);
                    if ($abilityId === 0) continue;
                    $count = (int) ($cast['total'] ?? 0);
                    $name = (string) ($cast['name'] ?? '');

                    $allCpms[$abilityId][] = $count / ($duration / 60);
                    $allCounts[$abilityId][] = $count;
                    if ($name && empty($abilityNames[$abilityId])) {
                        $abilityNames[$abilityId] = $name;
                    }
                }

                $talentsKey = $parser['talents_string'] ?? '';
                if ($talentsKey !== '') {
                    $talentBuilds[$talentsKey] = ($talentBuilds[$talentsKey] ?? 0) + 1;
                }
            }
        }

        $sampleSize = count($allCpms) > 0 ? max(array_map('count', $allCpms)) : 0;
        if ($sampleSize === 0) return null;

        // Aggregate per ability — require an ability appears in at least
        // 1/3 of the sample so we filter rare procs/oneshots without
        // throwing out unique CDs (which only fire 1-2 times per fight).
        $abilities = [];
        $minOccurrences = max(1, (int) ceil($sampleSize / 3));
        foreach ($allCpms as $abilityId => $cpms) {
            if (count($cpms) < $minOccurrences) continue;
            $counts = $allCounts[$abilityId];
            $abilities[$abilityId] = [
                'name'         => $abilityNames[$abilityId] ?? null,
                'cpm_median'   => round($this->percentile($cpms, 50), 2),
                'cpm_p95'      => round($this->percentile($cpms, 95), 2),
                'casts_median' => (int) round($this->percentile($counts, 50)),
                'casts_p95'    => (int) round($this->percentile($counts, 95)),
                'sample'       => count($cpms),
                'description'  => $this->descriptionFor($abilityId, $abilityCache),
            ];
        }

        // Top talent builds — keep those with >= 30% of the sample
        $threshold = max(1, (int) round($sampleSize * 0.30));
        $topTalents = array_filter($talentBuilds, fn($c) => $c >= $threshold);
        arsort($topTalents);

        return [
            'bosses_sampled' => array_values(array_unique($bossesSampled)),
            'sample_size'    => $sampleSize,
            'abilities'      => $abilities,
            'top_talents'    => array_keys(array_slice($topTalents, 0, 5, true)),
            'fetched_at'     => now()->toDateString(),
        ];
    }

    /**
     * Fetch top parsers for ONE (spec, boss). Caches to storage/app/spec-baseline-cache/.
     *
     * @return array{parsers: list<array>}
     */
    private function getOrFetchSpecBoss(
        string $specSlug, string $class, string $spec,
        int $encounterId, string $metric, int $difficulty,
        int $parsesNeeded, bool $force, int $pace
    ): array {
        $cachePath = storage_path(sprintf(
            'app/%s/%s/%d-d%d-m%s.json',
            self::CACHE_DIR, $specSlug, $encounterId, $difficulty, $metric
        ));

        if (!$force && file_exists($cachePath)) {
            $age = time() - filemtime($cachePath);
            if ($age < self::CACHE_TTL_DAYS * 86400) {
                $cached = json_decode((string) file_get_contents($cachePath), true);
                if (is_array($cached)) return $cached;
            }
        }

        @mkdir(dirname($cachePath), 0775, true);

        $rankings = $this->wcl->fetchCharacterRankings(
            $encounterId, $class, $spec, $metric, $difficulty, 1
        );
        sleep($pace);

        $parsers = [];
        foreach (array_slice($rankings, 0, $parsesNeeded) as $rank) {
            $reportCode = $rank['report']['code'] ?? null;
            $fightId    = (int) ($rank['report']['fightID'] ?? 0);
            $sourceName = (string) ($rank['name'] ?? '');
            if (!$reportCode || !$fightId || !$sourceName) continue;

            $parsed = $this->wcl->fetchParserCasts($reportCode, $fightId);
            sleep($pace);
            if (!$parsed) continue;

            $playerCasts = $this->extractPlayerCasts($parsed['casts'], $sourceName);
            // Talents live on the casts-table entry itself, not playerDetails.
            $talents     = $this->extractTalentsFromCasts($parsed['casts'], $sourceName);

            $parsers[] = [
                'name'              => $sourceName,
                'percentile'        => $rank['percentile'] ?? null,
                'fight_duration_s'  => (int) $parsed['fight_duration_s'],
                'casts'             => $playerCasts,
                'talents_string'    => $talents,
            ];
        }

        $payload = ['parsers' => $parsers];
        file_put_contents($cachePath, json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $payload;
    }

    /**
     * Casts table from WCL is grouped per-source; flatten to that source's casts.
     */
    private function extractPlayerCasts(array $castsData, string $sourceName): array
    {
        // The casts table format: { entries: [ {name, total, abilities: [{name, total, guid}]} ] }
        $entries = $castsData['entries'] ?? [];
        foreach ($entries as $entry) {
            if (($entry['name'] ?? '') !== $sourceName) continue;
            $abilities = $entry['abilities'] ?? [];
            return array_values(array_filter(array_map(
                fn($a) => [
                    'name'  => $a['name'] ?? '',
                    'guid'  => $a['guid'] ?? null,
                    'total' => (int) ($a['total'] ?? 0),
                ],
                $abilities
            ), fn($a) => $a['guid'] !== null));
        }
        return [];
    }

    /**
     * Talents come back attached to each casts-table entry (alongside name/
     * abilities/gear). Serialise as a sorted ID-list to make duplicates of
     * the same loadout cluster together.
     */
    private function extractTalentsFromCasts(array $castsData, string $sourceName): string
    {
        foreach ($castsData['entries'] ?? [] as $entry) {
            if (($entry['name'] ?? '') !== $sourceName) continue;
            $talents = $entry['talents'] ?? [];
            if (!is_array($talents)) return '';
            $ids = array_map(fn($t) => (int) ($t['talentID'] ?? $t['guid'] ?? $t['id'] ?? 0), $talents);
            $ids = array_filter($ids);
            sort($ids);
            return implode(',', $ids);
        }
        return '';
    }

    private function percentile(array $values, float $p): float
    {
        if (empty($values)) return 0;
        sort($values);
        $idx = ($p / 100) * (count($values) - 1);
        $lower = (int) floor($idx);
        $upper = (int) ceil($idx);
        if ($lower === $upper) return (float) $values[$lower];
        $frac = $idx - $lower;
        return $values[$lower] * (1 - $frac) + $values[$upper] * $frac;
    }

    /**
     * Lookup ability description via Blizzard Game Data API (cached for the
     * duration of one command run).
     */
    private function descriptionFor(int $abilityId, array &$abilityCache): ?string
    {
        if (array_key_exists($abilityId, $abilityCache)) {
            return $abilityCache[$abilityId];
        }
        $spell = $this->blizzard->getSpell($abilityId);
        $desc = null;
        if ($spell) {
            $desc = $this->trimDescription($spell['description'] ?? '');
        }
        $abilityCache[$abilityId] = $desc;
        return $desc;
    }

    /**
     * Strip Blizzard's tooltip variables ($s1, $@spelldesc, etc) and clamp to
     * a reasonable length. The AI doesn't need full spelltips, just gist.
     */
    private function trimDescription(string $raw): string
    {
        $clean = preg_replace('/\$[a-z@]?[0-9a-z]+/i', '...', $raw) ?? $raw;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = trim($clean);
        if (mb_strlen($clean) > 240) {
            $clean = mb_substr($clean, 0, 237) . '...';
        }
        return $clean;
    }

    private function parseSlug(string $slug): array
    {
        // class-spec → ["DeathKnight", "Blood"] for WCL; we Studly-case the
        // class part since WCL expects that exactly.
        $parts = explode('-', $slug, 2);
        if (count($parts) !== 2) {
            throw new \RuntimeException("Invalid spec slug: {$slug}");
        }
        return [
            $this->wclClassName($parts[0]),
            $this->wclSpecName($parts[1]),
        ];
    }

    /**
     * WCL is picky about spec capitalisation — most specs are simple ucfirst,
     * but a few compound names need camelCase ("BeastMastery"). Compound list
     * is small; explicit map keeps the logic obvious.
     */
    private function wclSpecName(string $slug): string
    {
        return match (strtolower($slug)) {
            'beastmastery' => 'BeastMastery',
            default        => ucfirst($slug),
        };
    }

    /**
     * WCL expects exact spelling: "DeathKnight", "DemonHunter", etc. Map our
     * slug shortcuts to those.
     */
    private function wclClassName(string $slug): string
    {
        return match (strtolower($slug)) {
            'deathknight' => 'DeathKnight',
            'demonhunter' => 'DemonHunter',
            default       => ucfirst($slug),
        };
    }

    /**
     * Best-effort role inference from existing YAML so we know which metric
     * to query (dps / hps / playerscore).
     */
    private function roleFor(string $specSlug): string
    {
        $path = resource_path("spec-baselines/{$specSlug}.yaml");
        if (!file_exists($path)) return 'dps';
        try {
            $parsed = Yaml::parseFile($path);
            return (string) ($parsed['role'] ?? 'dps');
        } catch (\Throwable $e) {
            return 'dps';
        }
    }

    /**
     * Merge empirical block into existing YAML, preserving rotation_checks
     * and theoretical fields.
     */
    private function mergeYaml(string $specSlug, array $empirical): void
    {
        $path = resource_path("spec-baselines/{$specSlug}.yaml");
        $existing = file_exists($path) ? (Yaml::parseFile($path) ?: []) : [];

        $existing['empirical'] = $empirical;

        $yaml = Yaml::dump($existing, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $yaml);
        rename($tmp, $path);
    }

    private function printDiff(string $specSlug, array $empirical): void
    {
        $abilities = $empirical['abilities'] ?? [];
        $this->line(sprintf(
            "  %s: sample=%d, %d abilities tracked, %d talent builds, bosses=%s",
            $specSlug,
            $empirical['sample_size'],
            count($abilities),
            count($empirical['top_talents'] ?? []),
            implode(',', $empirical['bosses_sampled'] ?? [])
        ));
        if ($this->getOutput()->isVerbose()) {
            foreach (array_slice($abilities, 0, 5, true) as $aid => $a) {
                $this->line(sprintf(
                    "      %d %s — cpm med=%.2f p95=%.2f (n=%d)",
                    $aid, $a['name'], $a['cpm_median'], $a['cpm_p95'], $a['sample']
                ));
            }
        }
    }
}
