<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\WclService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Generate a starter boss-timeline YAML from a WCL kill. Captures the
 * phase segments and every boss cast with segment-relative offsets.
 *
 * The output is intentionally minimal — the human editor adds priority,
 * recommended_response, notes, and any conditional_abilities after.
 * Re-running overwrites the target file, so commit your edits before
 * re-scaffolding a boss.
 */
class ScaffoldBossTimelineCommand extends Command
{
    protected $signature = 'cooldowns:scaffold-yaml
        {report : WCL report code (e.g. aDxGFHrhZbQ4NvwA)}
        {fight : Fight id within the report}
        {encounter : Encounter slug (e.g. imperator-averzian)}
        {--season= : Season identifier; defaults to wow_season.current_season}
        {--difficulty=mythic : Difficulty (normal|heroic|mythic)}
        {--encounter-id= : WCL encounterID; only needed when config mapping is missing}
        {--force : Overwrite an existing YAML without prompting}';

    protected $description = 'Build a starter boss-timeline YAML from a WCL report+fight. Hand-edit afterwards to add priorities and notes.';

    public function handle(WclService $wcl): int
    {
        $report = (string) $this->argument('report');
        $fightId = (int) $this->argument('fight');
        $slug = (string) $this->argument('encounter');
        $season = (string) ($this->option('season') ?: config('wow_season.current_season') ?: 'midnight-s1');
        $difficulty = (string) $this->option('difficulty');

        $target = resource_path("boss-timelines/{$season}/{$difficulty}/{$slug}.yml");
        if (File::exists($target) && !$this->option('force')) {
            if (!$this->confirm("{$target} exists — overwrite?", false)) {
                $this->warn('Aborted.');
                return self::FAILURE;
            }
        }

        $this->line("Fetching WCL timeline for {$report}#{$fightId}…");
        $timeline = $wcl->fetchBossCastTimeline($report, $fightId);

        $encounterId = (int) ($this->option('encounter-id') ?: $this->lookupEncounterIdFromConfig($slug) ?: $timeline['fight']['encounter_id'] ?? 0);
        $bossName = $this->lookupBossNameFromConfig($slug) ?? ($timeline['fight']['name'] ?? $slug);

        $phases = [];
        foreach ($timeline['segments'] as $i => $seg) {
            // Rename segment ids from s1/s2… to p1/i1/p2… based on intermission flag
            // so the YAML matches the convention used by hand-authored files.
            $stableId = $this->stableSegmentId($i, (bool) $seg['is_intermission'], $timeline['segments']);
            $phases[] = [
                'id' => $stableId,
                'name' => $seg['phase_name'],
                'is_intermission' => (bool) $seg['is_intermission'],
                'trigger' => $i === 0 ? ['type' => 'pull'] : ['type' => 'time_from_pull', 'value' => $seg['seed_start']],
                'seed_duration' => $seg['seed_duration'],
                '__segment_id' => $seg['segment_id'], // temp key; stripped before emit
            ];
        }

        // Map old segment_id → stable id for cast re-attribution
        $idMap = [];
        foreach ($phases as $p) {
            $idMap[$p['__segment_id']] = $p['id'];
        }
        foreach ($phases as &$p) unset($p['__segment_id']);

        $abilities = [];
        foreach ($timeline['abilities'] as $ab) {
            $casts = [];
            foreach ($ab['casts'] as $c) {
                $casts[] = [
                    'phase' => $idMap[$c['segment_id']] ?? 'p1',
                    'at' => (int) $c['offset'],
                ];
            }
            $abilities[] = [
                'id' => $this->slugifyAbilityName((string) $ab['name']),
                'spell_id' => (int) $ab['spell_id'],
                'name' => (string) $ab['name'],
                'icon' => (string) $ab['icon'],
                'school' => $this->schoolFromMask((int) $ab['type']),
                'priority' => 'medium',
                'recommended_response' => [],
                'notes' => '',
                'duration_sec' => (int) ($ab['duration_sec'] ?? 0),
                'casts' => $casts,
            ];
        }

        $payload = [
            'encounter' => [
                'id' => $encounterId,
                'slug' => $slug,
                'name' => $bossName,
                'season' => $season,
                'difficulty' => $difficulty,
                'fight_duration_max' => $timeline['fight']['duration_sec'],
            ],
            'seed' => [
                'report_code' => $report,
                'fight_id' => $fightId,
                'kill_duration_sec' => $timeline['fight']['duration_sec'],
            ],
            'phases' => $phases,
            'abilities' => $abilities,
            'conditional_abilities' => [],
        ];

        File::ensureDirectoryExists(dirname($target));

        $header = "# Boss timeline — {$bossName}, " . ucfirst($difficulty) . " ({$season}).\n"
            . "# Auto-generated by `php artisan cooldowns:scaffold-yaml {$report} {$fightId} {$slug}`.\n"
            . "# Hand-edit priorities, recommended_response, notes, and conditional_abilities.\n"
            . "# Seed parse: https://www.warcraftlogs.com/reports/{$report}#fight={$fightId}\n\n";

        $yaml = Yaml::dump($payload, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        File::put($target, $header . $yaml);

        $this->info("Wrote {$target}");
        $this->line("  Phases: " . count($phases));
        $this->line("  Abilities: " . count($abilities));
        $this->line("  Fight duration: {$timeline['fight']['duration_sec']}s");
        $this->newLine();
        $this->comment('Next steps:');
        $this->comment('  1. Edit the file — set priority/recommended_response/notes per ability.');
        $this->comment('  2. Add conditional_abilities[] entries from the tactics markdown.');
        $this->comment('  3. Run `php artisan cooldowns:sync-icons` to download new icons.');
        return self::SUCCESS;
    }

    private function stableSegmentId(int $index, bool $isIntermission, array $segments): string
    {
        $phaseN = 1;
        $intN = 1;
        for ($i = 0; $i <= $index; $i++) {
            if ($i === $index) {
                return $segments[$i]['is_intermission'] ? ('i' . $intN) : ('p' . $phaseN);
            }
            if ($segments[$i]['is_intermission']) $intN++;
            else $phaseN++;
        }
        return 'p1';
    }

    private function slugifyAbilityName(string $name): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $name));
        return trim($slug, '_');
    }

    private function schoolFromMask(int $mask): string
    {
        return match (true) {
            ($mask & 32) > 0 => 'shadow',
            ($mask & 4) > 0 => 'fire',
            ($mask & 16) > 0 => 'frost',
            ($mask & 8) > 0 => 'nature',
            ($mask & 64) > 0 => 'arcane',
            ($mask & 2) > 0 => 'holy',
            ($mask & 1) > 0 => 'physical',
            default => 'shadow',
        };
    }

    private function lookupEncounterIdFromConfig(string $slug): ?int
    {
        foreach (config('wow_season.wcl_encounter_ids', []) as $id => $name) {
            if (\Illuminate\Support\Str::slug($name) === $slug) return (int) $id;
        }
        return null;
    }

    private function lookupBossNameFromConfig(string $slug): ?string
    {
        foreach (config('wow_season.wcl_encounter_ids', []) as $id => $name) {
            if (\Illuminate\Support\Str::slug($name) === $slug) return $name;
        }
        return null;
    }
}
