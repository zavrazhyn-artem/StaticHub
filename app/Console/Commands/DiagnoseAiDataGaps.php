<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\TacticalDataAnalyzer;
use App\Services\Analysis\TacticsLoader;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;

/**
 * Per-boss diagnostic: runs PHP-deterministic TacticalDataAnalyzer, writes per-boss JSON +
 * diagnostic prompt for a Claude Code subagent that plays the role of "diagnostic reviewer".
 *
 * The agent itself is launched by the operator (Claude Code) after this command writes prompts —
 * this command's job is preparing the structured data + prompt, not the LLM call.
 *
 * Output per boss:
 *   storage/app/ai-diagnostics/{reportId}_{ts}_{slug}_preprocessed.json  — analyzer output
 *   storage/app/ai-diagnostics/{reportId}_{ts}_{slug}_prompt.txt         — prompt for subagent
 *   storage/app/ai-diagnostics/{reportId}_{ts}_{slug}_tactics.yaml       — tactics snapshot
 */
class DiagnoseAiDataGaps extends Command
{
    protected $signature = 'ai:diagnose-data
        {wclReportId : The WCL report ID to analyze}
        {--boss= : Optional boss name filter}
        {--roster=* : Optional roster character names to filter log data to}';

    protected $description = 'Per-boss diagnostic: runs PHP TacticalDataAnalyzer + writes prompts for Claude Code subagent review.';

    public function handle(
        WclService $wclService,
        TacticalDataAnalyzer $analyzer,
        TacticsLoader $tacticsLoader,
    ): int {
        ini_set('memory_limit', '1024M');

        $reportId = $this->argument('wclReportId');
        $bossFilter = $this->option('boss');
        $roster = $this->option('roster') ?: [];

        $this->info("Fetching WCL data for report: {$reportId}");
        $logData = $wclService->getLogSummary($reportId, $roster);

        if (!empty($logData['error'])) {
            $this->error('WCL error: ' . $logData['error']);
            return self::FAILURE;
        }

        $allBosses = array_keys($logData['phase_summary'] ?? []);
        $this->info('Bosses in report: ' . implode(', ', $allBosses));
        $this->info('Difficulties: ' . implode(', ', $logData['difficulties'] ?? []));
        if (empty($roster)) {
            $this->warn('No roster filter applied — analyzing ALL players in the report.');
        } else {
            $this->info('Roster filter: ' . implode(', ', $roster));
        }

        $bosses = $bossFilter
            ? array_filter($allBosses, fn($b) => stripos($b, $bossFilter) !== false)
            : $allBosses;

        if (empty($bosses)) {
            $this->error("No bosses match filter: {$bossFilter}");
            return self::FAILURE;
        }

        $outputDir = storage_path('app/ai-diagnostics');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $timestamp = now()->format('Y-m-d_His');

        $localization = [
            'raid_leader' => ['locale' => 'Ukrainian'],
            'participants' => array_map(
                fn($p) => ['name' => $p['name'], 'locale' => 'Ukrainian'],
                $logData['players'] ?? []
            ),
        ];

        // Run PHP analyzer once — produces structured data for ALL bosses at once
        $this->info('Running PHP TacticalDataAnalyzer...');
        try {
            $analysis = $analyzer->analyze($logData, $localization, $roster);
        } catch (\Exception $e) {
            $this->error('Analyzer failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Analyzer output: ' . count($analysis['encounters'] ?? []) . ' encounters, '
            . count($analysis['per_player_data'] ?? []) . ' players');

        foreach ($bosses as $bossName) {
            $this->newLine();
            $this->info("================================================");
            $this->info("BOSS: {$bossName}");
            $this->info("================================================");

            $slug = $this->bossNameToSlug($bossName);
            $basePath = "{$outputDir}/{$reportId}_{$timestamp}_{$slug}";

            // Subset analyzer output to this boss
            $encounter = null;
            foreach ($analysis['encounters'] ?? [] as $e) {
                if ($e['boss'] === $bossName) {
                    $encounter = $e;
                    break;
                }
            }
            if (!$encounter) {
                $this->warn("No encounter data for boss {$bossName}");
                continue;
            }

            // Per-boss structured output
            $bossAnalysis = [
                'raid_summary'     => $analysis['raid_summary'],
                'encounter'        => $encounter,
                'per_player_data'  => $this->filterPerPlayerToBoss($analysis['per_player_data'] ?? [], $encounter),
                'consumable_audit' => $analysis['consumable_audit'] ?? [],
                'localization'     => $localization,
            ];

            file_put_contents(
                "{$basePath}_preprocessed.json",
                json_encode($bossAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $this->info("Saved analyzer output: {$basePath}_preprocessed.json");

            // Load tactics YAML
            try {
                $tactics = $tacticsLoader->loadByBossName($bossName);
            } catch (\Exception $e) {
                $this->error("Tactics missing for {$bossName}: " . $e->getMessage());
                continue;
            }
            file_put_contents(
                "{$basePath}_tactics.yaml",
                $this->serializeTactics($tactics)
            );

            // Build diagnostic prompt for Claude Code subagent
            $prompt = $this->buildDiagnosticPrompt($bossName, $bossAnalysis, $tactics);
            file_put_contents("{$basePath}_prompt.txt", $prompt);
            $this->info("Saved diagnostic prompt: {$basePath}_prompt.txt");
        }

        $this->newLine();
        $this->info('=== ALL BOSSES PROCESSED ===');
        $this->line("Output directory: {$outputDir}");
        $this->newLine();
        $this->comment('NEXT STEP: Claude Code will spawn diagnostic subagents for each boss using the generated prompts.');

        return self::SUCCESS;
    }

    private function filterPerPlayerToBoss(array $perPlayer, array $encounter): array
    {
        $bossName = $encounter['boss'];
        $filtered = [];
        foreach ($perPlayer as $name => $data) {
            $data['death_details'] = array_values(array_filter(
                $data['death_details'] ?? [],
                fn($d) => ($d['boss'] ?? '') === $bossName
            ));
            $data['total_deaths_on_this_boss'] = count($data['death_details']);
            $filtered[$name] = $data;
        }
        return $filtered;
    }

    private function serializeTactics(array $tactics): string
    {
        // Drop markdown from serialized form (agent doesn't need prose)
        $copy = $tactics;
        unset($copy['markdown'], $copy['source_file']);
        return \Symfony\Component\Yaml\Yaml::dump($copy, 8, 2);
    }

    private function bossNameToSlug(string $bossName): string
    {
        $slug = strtolower($bossName);
        $slug = str_replace('&', 'and', $slug);
        $slug = str_replace("'", '', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function buildDiagnosticPrompt(string $bossName, array $analysis, array $tactics): string
    {
        $analysisJson = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tacticsYaml = $this->serializeTactics($tactics);

        return <<<PROMPT
Ти — діагностичний аналітик рейдів WoW Mythic. Твоя єдина задача — визначити, чи достатньо зібраних даних для написання повноцінного звіту для кожної механіки боса.

БОС: {$bossName}

ВХІДНІ ДАНІ:
1. PREPROCESSED DATA — детермінований output PHP аналізатора з усіма метриками (deaths, debuff_stacks, orb_staggering, shielded_casts, interrupts, per-player стрейти тощо).
2. TACTICS — YAML з переліком механік що очікуються на цьому босі.

ЗАВДАННЯ:
Для КОЖНОЇ механіки з `tactics.mechanics` дай відповідь: чи можеш ти (або інший writer agent на цих даних) описати її fail/success достатньо конкретно (з іменами гравців, кількостями, часовими позначками).

ФОРМАТ ВІДПОВІДІ — строго JSON (без markdown обгорток, без коментарів):

{
  "status": "PASS" | "FAIL",
  "boss": "{$bossName}",
  "mechanics": [
    {
      "name": "назва механіки з tactics",
      "severity": "critical|major|minor",
      "can_describe": true|false,
      "fail_reason": null | "рядок з поясненням чого не вистачає (конкретно які поля/дані)",
      "suggested_data_source": null | "рядок — WCL endpoint/поле яке б дозволило це аналізувати"
    }
  ],
  "summary": "коротке резюме однією-двома фразами українською"
}

ПРАВИЛА:
- status=PASS тільки якщо всі `critical` та `major` механіки мають can_describe=true.
- Механіки з severity=minor можуть мати can_describe=false без блокування PASS, але їх все одно треба задокументувати.
- Якщо tactics.mechanics містить механіку але в PREPROCESSED DATA немає відповідних даних — can_describe=false.
- БУДЬ КОНКРЕТНИМ у fail_reason. Приклад поганий: "недостатньо даних для позиціонування". Приклад добрий: "відсутні x,y координати для події removedebuff Despotic Command — WCL events API не повертає coords для debuff events".

=== TACTICS (YAML) ===
{$tacticsYaml}

=== PREPROCESSED DATA (JSON) ===
{$analysisJson}
PROMPT;
    }
}
