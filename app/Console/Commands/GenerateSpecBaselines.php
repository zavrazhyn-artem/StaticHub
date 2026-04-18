<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

/**
 * Offline generator — parses WoWAnalyzer-midnight project and emits spec baseline
 * YAMLs under resources/spec-baselines/. Source of truth for ability IDs, names,
 * categories (ROTATIONAL/COOLDOWNS) and cooldowns comes from WoWAnalyzer.
 *
 * Usage: php artisan generate:spec-baselines
 */
class GenerateSpecBaselines extends Command
{
    protected $signature = 'generate:spec-baselines {--src=WoWAnalyzer-midnight} {--dry-run}';
    protected $description = 'Generate spec baseline YAMLs from WoWAnalyzer-midnight source tree';

    private string $srcRoot;

    public function handle(): int
    {
        $this->srcRoot = base_path((string) $this->option('src')) . '/src';
        $dryRun = (bool) $this->option('dry-run');

        if (!is_dir($this->srcRoot)) {
            $this->error("Source not found: {$this->srcRoot}");
            return self::FAILURE;
        }

        $specsMap = $this->loadSpecsMap();
        $outDir = resource_path('spec-baselines');
        if (!$dryRun && !is_dir($outDir)) mkdir($outDir, 0755, true);

        $classesDir = $this->srcRoot . '/analysis/retail';
        $written = 0;
        $skipped = [];

        foreach ($this->listDirs($classesDir) as $classDir) {
            $classSlug = basename($classDir);
            $talents = $this->loadTalentsFor($classSlug);
            $spells  = $this->loadSpellsFor($classSlug);

            foreach ($this->listDirs($classDir) as $specDir) {
                $specSlug = basename($specDir);
                if ($specSlug === 'shared') continue;

                $abilitiesPath = $this->findAbilitiesFile($specDir);
                if (!$abilitiesPath) {
                    $skipped[] = "{$classSlug}/{$specSlug} (no Abilities file)";
                    continue;
                }

                $configPath = "{$specDir}/CONFIG.tsx";
                $specConst = $this->extractSpecConstFromConfig($configPath);

                $wcl = $specConst ? ($specsMap[$specConst] ?? null) : null;
                if (!$wcl) {
                    $skipped[] = "{$classSlug}/{$specSlug} (no WCL names — const {$specConst})";
                    continue;
                }

                $abilities = $this->parseAbilities($abilitiesPath, $spells, $talents);
                if (empty($abilities)) {
                    $skipped[] = "{$classSlug}/{$specSlug} (no rotational abilities)";
                    continue;
                }

                $yaml = $this->buildYaml($wcl, $abilities);

                $fileSlug = mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $wcl['class']))
                    . '-' . mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $wcl['spec']));
                $outFile = "{$outDir}/{$fileSlug}.yaml";

                if ($dryRun) {
                    $this->line("DRY  {$fileSlug}.yaml (" . count($abilities) . " abilities)");
                } else {
                    file_put_contents($outFile, $yaml);
                    $this->info("WROTE {$fileSlug}.yaml (" . count($abilities) . " abilities)");
                }
                $written++;
            }
        }

        $this->line("");
        $this->info("Total: {$written}");
        if (!empty($skipped)) {
            $this->warn("Skipped: " . count($skipped));
            foreach ($skipped as $s) $this->line("  - {$s}");
        }

        return self::SUCCESS;
    }

    /**
     * SPECS.ts → map: PROTECTION_WARRIOR => ['class' => 'Warrior', 'spec' => 'Protection', 'role' => 'tank']
     */
    private function loadSpecsMap(): array
    {
        $file = "{$this->srcRoot}/game/SPECS.ts";
        $src = file_get_contents($file);
        $map = [];

        // Match: KEY_NAME: {  ... wclClassName: 'X', wclSpecName: 'Y', role: ROLES.TANK, ... }
        if (preg_match_all('/^\s*([A-Z][A-Z_]+)\s*:\s*\{(.+?)^\s*\},/ms', $src, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $key = $match[1];
                $body = $match[2];
                if (!preg_match("/wclClassName:\s*'([^']+)'/", $body, $c)) continue;
                if (!preg_match("/wclSpecName:\s*'([^']+)'/", $body, $s)) continue;
                $role = 'dps';
                if (preg_match('/role:\s*ROLES\.([A-Z]+)/', $body, $r)) {
                    $role = strtolower($r[1]);
                }
                $map[$key] = [
                    'class' => $c[1],
                    'spec'  => $s[1],
                    'role'  => $role,
                ];
            }
        }
        return $map;
    }

    /**
     * CONFIG.tsx has `spec: SPECS.XYZ,` — extract the XYZ identifier.
     */
    private function extractSpecConstFromConfig(string $path): ?string
    {
        if (!file_exists($path)) return null;
        $src = file_get_contents($path);
        if (preg_match('/spec:\s*SPECS\.([A-Z_]+)/', $src, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * common/TALENTS/<class>.ts → map KEY => ['id' => int, 'name' => string]
     */
    private function loadTalentsFor(string $classSlug): array
    {
        $file = "{$this->srcRoot}/common/TALENTS/{$classSlug}.ts";
        return $this->parseIdNameMap($file);
    }

    private function loadSpellsFor(string $classSlug): array
    {
        $map = $this->parseIdNameMap("{$this->srcRoot}/common/SPELLS/{$classSlug}.ts");

        // Some classes (notably Mage in Midnight) split spells into analysis/retail/<class>/**/SPELLS.ts
        $classAnalysisDir = "{$this->srcRoot}/analysis/retail/{$classSlug}";
        if (is_dir($classAnalysisDir)) {
            foreach ($this->findSpellsFilesRecursive($classAnalysisDir) as $path) {
                foreach ($this->parseIdNameMap($path) as $k => $v) {
                    if (!isset($map[$k])) $map[$k] = $v;
                }
            }
        }
        return $map;
    }

    /**
     * Walk $dir looking for *SPELLS.ts / *SPELLS.tsx files (any case).
     * @return array<int, string>
     */
    private function findSpellsFilesRecursive(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) continue;
            $name = $fileInfo->getFilename();
            if (preg_match('/^SPELLS\.tsx?$/', $name) || preg_match('/spell-list.*\.(ts|tsx)$/i', $name)) {
                $out[] = $fileInfo->getPathname();
            }
        }
        return $out;
    }

    /**
     * Parse `KEY: { id: 12345, name: 'Thing', ... }` blocks into map KEY => [id, name].
     * Uses brace-depth scanner since talent/spell objects may contain nested arrays/objects.
     */
    private function parseIdNameMap(string $file): array
    {
        if (!file_exists($file)) return [];
        $src = file_get_contents($file);
        $len = strlen($src);
        $map = [];

        $pos = 0;
        while ($pos < $len) {
            // Look for start of a KEY: { block
            if (!preg_match('/\b([A-Z][A-Z0-9_]+)\s*:\s*\{/', $src, $m, PREG_OFFSET_CAPTURE, $pos)) {
                break;
            }
            $key = $m[1][0];
            $blockStart = $m[0][1] + strlen($m[0][0]); // position just after the `{`
            $end = $this->findMatchingBrace($src, $blockStart);
            if ($end === null) { $pos = $blockStart; continue; }
            $body = substr($src, $blockStart, $end - $blockStart);

            if (preg_match('/\bid:\s*(\d+)/', $body, $idM) &&
                preg_match("/\bname:\s*'([^']+)'/", $body, $nameM)) {
                // Only set if not already present (prefer first occurrence)
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'id'   => (int) $idM[1],
                        'name' => $nameM[1],
                    ];
                }
            }
            $pos = $end + 1;
        }
        return $map;
    }

    /**
     * Given a position right after `{`, return the position of the matching `}`.
     */
    private function findMatchingBrace(string $src, int $startAfterOpen): ?int
    {
        $len = strlen($src);
        $depth = 1;
        $p = $startAfterOpen;
        while ($p < $len) {
            $ch = $src[$p];
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $q = $ch; $p++;
                while ($p < $len) {
                    $cc = $src[$p];
                    if ($cc === '\\' && $p + 1 < $len) { $p += 2; continue; }
                    if ($cc === $q) { $p++; break; }
                    $p++;
                }
                continue;
            }
            if ($ch === '/' && $p + 1 < $len && $src[$p + 1] === '/') {
                while ($p < $len && $src[$p] !== "\n") $p++;
                continue;
            }
            if ($ch === '/' && $p + 1 < $len && $src[$p + 1] === '*') {
                $p += 2;
                while ($p + 1 < $len && !($src[$p] === '*' && $src[$p + 1] === '/')) $p++;
                $p += 2;
                continue;
            }
            if ($ch === '{') $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) return $p;
            }
            $p++;
        }
        return null;
    }

    /**
     * Parse Abilities.ts — extract top-level ability blocks and their fields.
     *
     * @return array<int, array{id:int,name:string,category:string,cooldown:?float,charges:int,suggestion:bool,recommended:?float}>
     */
    private function parseAbilities(string $file, array $spells, array $talents): array
    {
        $src = file_get_contents($file);
        $blocks = $this->extractSpellbookBlocks($src);

        $out = [];
        $seen = [];
        foreach ($blocks as $body) {
            if (!preg_match('/\bcategory:\s*(?:.*?\?\s*)?SPELL_CATEGORY\.([A-Z_]+)/s', $body, $catM)) continue;
            $category = $catM[1];
            // Treat ROTATIONAL_AOE and COOLDOWNS_AOE same as their base.
            if (str_starts_with($category, 'ROTATIONAL')) $category = 'ROTATIONAL';
            if (str_starts_with($category, 'COOLDOWNS')) $category = 'COOLDOWNS';
            if (!in_array($category, ['ROTATIONAL', 'COOLDOWNS'], true)) continue;

            // Resolve spell reference — first occurrence inside the block
            $resolved = $this->resolveSpellRef($body, $spells, $talents);
            if (!$resolved) continue;

            // Cooldown may be: `N`, `(haste) => X / (1 + haste)`, or a ternary/expression
            // `combatant.hasTalent(X) ? 30 : 45`, `60 - (combatant.has ? 15 : 0)`, etc.
            // Extract the first numeric literal after `cooldown:` as the base CD.
            $cooldown = null;
            if (preg_match('/\bcooldown:\s*\(haste\)\s*=>\s*([0-9.]+)/', $body, $hM)) {
                $cooldown = (float) $hM[1];
            } elseif (preg_match('/\bcooldown:\s*[^,\n]*?([0-9.]+)/', $body, $cM)) {
                $cooldown = (float) $cM[1];
            }

            // Skip ability without a meaningful cooldown — GCD-only fillers can't be efficiency-checked
            if ($cooldown === null || $cooldown < 1) continue;

            $suggestionFlag = (bool) preg_match('/suggestion:\s*true/', $body);

            // Long-CD ROTATIONAL without an explicit suggestion is usually utility (taunts, shouts).
            // Keep only if WoWAnalyzer explicitly flagged it OR if it's short enough to be actual rotation.
            if ($category === 'ROTATIONAL' && $cooldown > 60 && !$suggestionFlag) continue;

            $charges = 1;
            if (preg_match('/\bcharges:\s*(\d+)/', $body, $chM)) {
                $charges = (int) $chM[1];
            }

            $suggestion = $suggestionFlag;
            $recommended = null;
            if (preg_match('/recommendedEfficiency:\s*([0-9.]+)/', $body, $rM)) {
                $recommended = (float) $rM[1];
            }

            $key = $resolved['id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $out[] = [
                'id'           => $resolved['id'],
                'name'         => $resolved['name'],
                'category'     => $category,
                'cooldown'     => $cooldown,
                'charges'      => $charges,
                'suggestion'   => $suggestion,
                'recommended'  => $recommended,
            ];
        }

        return $out;
    }

    /**
     * Scan `spellbook()` body, return individual ability block bodies (contents between `{` and `}`).
     * Uses brace-depth counting from the `return [` marker.
     */
    private function extractSpellbookBlocks(string $src): array
    {
        $idx = strpos($src, 'return [');
        if ($idx === false) return [];
        $pos = $idx + strlen('return [');
        $len = strlen($src);

        $blocks = [];
        $depth = 0; // 0 = inside the array, 1 = inside an ability object
        $buf = '';
        $collecting = false;

        while ($pos < $len) {
            $ch = $src[$pos];

            if (!$collecting) {
                if ($ch === '{') {
                    $collecting = true;
                    $depth = 1;
                    $buf = '';
                } elseif ($ch === ']') {
                    // End of array (top-level)
                    break;
                }
                $pos++;
                continue;
            }

            // In-block: track strings/comments to avoid brace confusion
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                $pos++;
                while ($pos < $len) {
                    $cc = $src[$pos];
                    $buf .= $cc;
                    $pos++;
                    if ($cc === '\\' && $pos < $len) { $buf .= $src[$pos]; $pos++; continue; }
                    if ($cc === $quote) break;
                }
                continue;
            }

            if ($ch === '/' && $pos + 1 < $len && $src[$pos + 1] === '/') {
                while ($pos < $len && $src[$pos] !== "\n") $pos++;
                continue;
            }
            if ($ch === '/' && $pos + 1 < $len && $src[$pos + 1] === '*') {
                $pos += 2;
                while ($pos + 1 < $len && !($src[$pos] === '*' && $src[$pos + 1] === '/')) $pos++;
                $pos += 2;
                continue;
            }

            if ($ch === '{') $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $blocks[] = $buf;
                    $collecting = false;
                    $pos++;
                    continue;
                }
            }

            $buf .= $ch;
            $pos++;
        }

        return $blocks;
    }

    /**
     * Body may contain:
     *   spell: SPELLS.NAME.id
     *   spell: TALENTS.NAME_TALENT.id
     *   spell: TALENTS_DRUID.NAME.id
     *   spell: [SPELLS.A.id, SPELLS.B.id]
     *   spell: [TALENTS.X.id, SPELLS.Y.id, SPELLS.Z.id]
     * Return first resolvable reference.
     */
    private function resolveSpellRef(string $body, array $spells, array $talents): ?array
    {
        // Extract all identifiers that look like SPELLS.X.id or TALENTS*.X.id
        if (!preg_match_all('/\b(SPELLS|TALENTS(?:_[A-Z_]+)?)\.([A-Z][A-Z0-9_]*)\.id\b/', $body, $m, PREG_SET_ORDER)) {
            return null;
        }
        foreach ($m as $ref) {
            $kind = $ref[1];
            $name = $ref[2];
            $map = (str_starts_with($kind, 'TALENTS')) ? $talents : $spells;
            if (isset($map[$name])) {
                return $map[$name];
            }
            // Fallback: try the other map in case the `SPELLS.X_TALENT` name pattern is used
            $other = (str_starts_with($kind, 'TALENTS')) ? $spells : $talents;
            if (isset($other[$name])) return $other[$name];
        }
        return null;
    }

    private function buildYaml(array $wcl, array $abilities): string
    {
        $rotation = [];
        $cooldowns = [];

        foreach ($abilities as $a) {
            $recommended = $a['recommended'] ?? 0.80;
            if ($a['category'] === 'COOLDOWNS' && $a['recommended'] === null) {
                $recommended = 0.90; // major CDs — expect stricter usage
            }

            $severity = $this->severityFor($a);
            $cooldown = $a['cooldown'];
            if ($a['charges'] > 1) {
                // Effective CD for efficiency calc is cooldown / charges.
                $cooldown = $cooldown / $a['charges'];
            }

            $entry = [
                'ability'      => $a['name'],
                'ability_id'   => $a['id'],
                'check'        => 'cast_efficiency',
                'cooldown_seconds' => round($cooldown, 2),
                'recommended_efficiency' => $recommended,
                'severity'     => $severity,
            ];

            if ($a['category'] === 'COOLDOWNS') {
                $cooldowns[] = $entry;
            } else {
                $rotation[] = $entry;
            }
        }

        $structure = [
            'spec'  => $wcl['spec'],
            'class' => $wcl['class'],
            'role'  => $wcl['role'],
            'source' => 'WoWAnalyzer-midnight',
            'rotation_checks' => array_merge($rotation, $cooldowns),
        ];

        return Yaml::dump($structure, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }

    private function severityFor(array $a): string
    {
        if ($a['category'] === 'COOLDOWNS') return 'major';
        if ($a['suggestion']) return 'major';
        if ($a['cooldown'] >= 30) return 'major';
        if ($a['cooldown'] >= 10) return 'minor';
        return 'minor';
    }

    private function findAbilitiesFile(string $specDir): ?string
    {
        $candidates = [
            "{$specDir}/modules/Abilities.ts",
            "{$specDir}/modules/Abilities.tsx",
            "{$specDir}/modules/features/Abilities.ts",
            "{$specDir}/modules/features/Abilities.tsx",
            "{$specDir}/modules/core/Abilities.ts",
            "{$specDir}/modules/core/Abilities.tsx",
            "{$specDir}/core/Abilities.ts",
            "{$specDir}/core/Abilities.tsx",
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) return $c;
        }
        return null;
    }

    private function listDirs(string $parent): array
    {
        if (!is_dir($parent)) return [];
        $out = [];
        foreach (scandir($parent) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $p = "{$parent}/{$entry}";
            if (is_dir($p)) $out[] = $p;
        }
        sort($out);
        return $out;
    }
}
