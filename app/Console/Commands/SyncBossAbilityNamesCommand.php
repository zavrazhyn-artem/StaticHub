<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;

/**
 * Build a cache of icon-filename → spell-name from boss-timeline YAMLs.
 *
 * The toolbar in MapToolbar shows boss-ability tiles using only icon
 * filenames (config/wow_season.php → encounter_bosses[*].abilities). Users
 * see "spell_priest_voidblast" instead of the real ability name. We resolve
 * proper names by walking every YAML, collecting spell_ids+names, fetching
 * each spell's canonical icon from Wowhead, and writing the mapping to
 * storage/app/wow/boss_ability_names.json. BossPlannerService loads this on
 * every page render to enrich abilities with a `name` field.
 */
class SyncBossAbilityNamesCommand extends Command
{
    protected $signature = 'boss-planner:sync-names {--season=}';
    protected $description = 'Resolve boss-ability spell names from YAMLs+Wowhead and cache an icon→name map.';

    public function handle(): int
    {
        $root = resource_path('boss-timelines');
        if (!File::isDirectory($root)) {
            $this->warn("No boss-timelines directory at {$root}.");
            return self::SUCCESS;
        }

        $onlySeason = (string) ($this->option('season') ?? '');
        $seasons = $onlySeason !== ''
            ? [$root . '/' . $onlySeason]
            : File::directories($root);

        // Collect unique (spell_id, name) pairs from every YAML. Multiple
        // YAMLs may reference the same spell; first name wins.
        $bySpell = [];
        foreach ($seasons as $seasonDir) {
            if (!File::isDirectory($seasonDir)) continue;
            foreach (File::directories($seasonDir) as $diffDir) {
                foreach (File::files($diffDir) as $file) {
                    if (!in_array($file->getExtension(), ['yml', 'yaml'], true)) continue;
                    $data = Yaml::parseFile($file->getPathname());
                    $abilities = array_merge(
                        $data['abilities'] ?? [],
                        $data['conditional_abilities'] ?? []
                    );
                    foreach ($abilities as $ab) {
                        $id = $ab['spell_id'] ?? null;
                        $name = $ab['name'] ?? null;
                        if (!$id || !$name) continue;
                        if (!isset($bySpell[$id])) $bySpell[$id] = $name;
                    }
                }
            }
        }

        $this->info('Collected ' . count($bySpell) . ' unique spell IDs from YAMLs.');

        // Resolve each spell's icon from Wowhead's nether tooltip endpoint.
        $byIcon = [];
        $bySpellOut = [];
        $errors = 0;
        foreach ($bySpell as $id => $name) {
            $url = "https://nether.wowhead.com/tooltip/spell/{$id}";
            try {
                $resp = Http::timeout(10)->get($url);
                if (!$resp->successful()) { $errors++; continue; }
                $body = $resp->json();
                $icon = $body['icon'] ?? null;
                $whName = $body['name'] ?? $name;
                if (!$icon) { $errors++; continue; }
                $bySpellOut[$id] = ['name' => $whName, 'icon' => $icon];
                // Multiple spells can share an icon — keep the first name and
                // accumulate the rest as alternates so the lookup can show
                // disambiguation hints if needed later.
                if (!isset($byIcon[$icon])) $byIcon[$icon] = [];
                $byIcon[$icon][] = $whName;
                $this->line("  {$id} → {$whName} ({$icon})");
            } catch (\Throwable $e) {
                $errors++;
                $this->warn("  ✗ {$id} — {$e->getMessage()}");
            }
        }

        $outDir = storage_path('app/wow');
        if (!File::isDirectory($outDir)) File::makeDirectory($outDir, 0755, true);
        $outPath = $outDir . '/boss_ability_names.json';
        File::put($outPath, json_encode([
            'by_spell' => $bySpellOut,
            'by_icon' => $byIcon,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Wrote {$outPath} — " . count($byIcon) . " icons, {$errors} errors.");
        return self::SUCCESS;
    }
}
