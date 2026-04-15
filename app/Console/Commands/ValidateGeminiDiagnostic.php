<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Helpers\GeminiResponseFormatter;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\TacticalDataAnalyzer;
use App\Services\Analysis\TacticsLoader;
use App\Services\Analysis\WclService;
use Illuminate\Console\Command;

/**
 * End-to-end validation: WCL → PHP TacticalDataAnalyzer → Gemini-3-flash diagnostic verdict.
 * For each boss in the report, asks Gemini whether the preprocessed data is sufficient
 * to write a full per-mechanic raid report.
 */
class ValidateGeminiDiagnostic extends Command
{
    protected $signature = 'ai:validate-diagnostic
        {wclReportId : The WCL report ID to validate}
        {--boss= : Optional boss name filter}
        {--roster=* : Optional roster character names}';

    protected $description = 'Run PHP TacticalDataAnalyzer per boss + ask Gemini-3-flash for diagnostic verdict.';

    public function handle(
        WclService $wclService,
        TacticalDataAnalyzer $analyzer,
        TacticsLoader $tacticsLoader,
        GeminiService $geminiService,
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
        $this->info('Bosses found: ' . implode(', ', $allBosses));

        $bosses = $bossFilter
            ? array_filter($allBosses, fn($b) => stripos($b, $bossFilter) !== false)
            : $allBosses;

        if (empty($bosses)) {
            $this->error("No bosses match filter: {$bossFilter}");
            return self::FAILURE;
        }

        $localization = [
            'raid_leader' => ['locale' => 'Ukrainian'],
            'participants' => array_map(
                fn($p) => ['name' => $p['name'], 'locale' => 'Ukrainian'],
                $logData['players'] ?? []
            ),
        ];

        $this->info('Running PHP TacticalDataAnalyzer for all encounters...');
        $analysis = $analyzer->analyze($logData, $localization, $roster, $reportId);

        $outputDir = storage_path('app/ai-diagnostics');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $timestamp = now()->format('Y-m-d_His');

        $proModel = (string) config('services.gemini.pro_model', 'gemini-3-flash-preview');
        $url = $geminiService->buildModelUrl($proModel);

        $totalPass = 0;
        $totalFail = 0;
        $verdicts = [];

        foreach ($bosses as $bossName) {
            $this->newLine();
            $this->info("================================================");
            $this->info("BOSS: {$bossName}");
            $this->info("================================================");

            $encounter = collect($analysis['encounters'])->firstWhere('boss', $bossName);
            if (!$encounter) {
                $this->warn("No encounter data for {$bossName}");
                continue;
            }

            try {
                $tactics = $tacticsLoader->loadByBossName($bossName);
            } catch (\Exception $e) {
                $this->error("No tactics for {$bossName}: " . $e->getMessage());
                continue;
            }
            unset($tactics['markdown'], $tactics['source_file']);

            $bossAnalysis = [
                'raid_summary'     => $analysis['raid_summary'],
                'encounter'        => $encounter,
                'per_player_data'  => array_map(function ($p) use ($bossName) {
                    $p['death_details'] = array_values(array_filter(
                        $p['death_details'] ?? [],
                        fn($d) => ($d['boss'] ?? '') === $bossName
                    ));
                    return $p;
                }, $analysis['per_player_data']),
                'consumable_audit' => $analysis['consumable_audit'],
            ];

            $slug = $this->bossNameToSlug($bossName);
            $basePath = "{$outputDir}/{$reportId}_{$timestamp}_{$slug}";
            file_put_contents("{$basePath}_analysis.json", json_encode($bossAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            file_put_contents("{$basePath}_tactics.yaml", \Symfony\Component\Yaml\Yaml::dump($tactics, 8, 2));

            $prompt = $this->buildPrompt($bossName, $bossAnalysis, $tactics);
            file_put_contents("{$basePath}_prompt.txt", $prompt);

            $this->info("Asking {$proModel}...");
            try {
                $rawResponse = $geminiService->executeRequestWithModel(
                    $prompt,
                    $url,
                    180,
                    3,
                    true,
                    16384
                );
            } catch (\Exception $e) {
                $this->error("Gemini failed: " . $e->getMessage());
                continue;
            }

            $verdictJson = GeminiResponseFormatter::cleanMarkdown($rawResponse);
            file_put_contents("{$basePath}_verdict.md", "```json\n{$verdictJson}\n```\n");

            // Truncate to first balanced JSON object/array to handle stray trailing braces
            $verdictJson = $this->extractFirstBalancedJson($verdictJson);

            $parsed = json_decode($verdictJson, true);
            // Some Gemini responses wrap the object in a single-element array
            if (is_array($parsed) && array_is_list($parsed) && count($parsed) === 1) {
                $parsed = $parsed[0];
            }
            $status = $parsed['status'] ?? 'UNKNOWN';
            $summary = $parsed['summary'] ?? '';

            if ($status === 'PASS') {
                $this->info("✅ PASS: {$summary}");
                $totalPass++;
            } else {
                $this->warn("❌ FAIL: {$summary}");
                $totalFail++;
                if (!empty($parsed['mechanics'])) {
                    foreach ($parsed['mechanics'] as $m) {
                        if (!($m['can_describe'] ?? true)) {
                            $this->line("    - [{$m['severity']}] {$m['name']}: " . substr($m['fail_reason'] ?? '', 0, 120));
                        }
                    }
                }
            }
            $verdicts[$bossName] = $status;
        }

        $this->newLine();
        $this->info("================================================");
        $this->info("FINAL RESULT: {$totalPass} PASS / {$totalFail} FAIL");
        $this->info("================================================");
        foreach ($verdicts as $boss => $status) {
            $icon = $status === 'PASS' ? '✅' : '❌';
            $this->line("  {$icon} {$boss} — {$status}");
        }

        return $totalFail === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Extract the first balanced JSON object/array from a string,
     * stripping anything before the opening bracket and after the matching close.
     */
    private function extractFirstBalancedJson(string $s): string
    {
        $start = false;
        $open = null;
        $depth = 0;
        $inString = false;
        $escape = false;
        $end = strlen($s);

        for ($i = 0; $i < strlen($s); $i++) {
            $ch = $s[$i];

            if ($start === false) {
                if ($ch === '{' || $ch === '[') {
                    $start = $i;
                    $open = $ch;
                    $depth = 1;
                }
                continue;
            }

            if ($inString) {
                if ($escape) { $escape = false; continue; }
                if ($ch === '\\') { $escape = true; continue; }
                if ($ch === '"') { $inString = false; }
                continue;
            }

            if ($ch === '"') { $inString = true; continue; }
            if ($ch === '{' || $ch === '[') $depth++;
            elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $i + 1;
                    return substr($s, $start, $end - $start);
                }
            }
        }
        return $s;
    }

    private function bossNameToSlug(string $bossName): string
    {
        $slug = strtolower($bossName);
        $slug = str_replace(['&', "'"], ['and', ''], $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function buildPrompt(string $bossName, array $analysis, array $tactics): string
    {
        $analysisJson = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tacticsYaml = \Symfony\Component\Yaml\Yaml::dump($tactics, 8, 2);

        return <<<PROMPT
You are a diagnostic agent. Determine whether the preprocessed raid data is sufficient to describe each mechanic's failures or successes for a written report.

BOSS: {$bossName}

INPUTS:
1. TACTICS — YAML defining mechanics expected on this boss.
2. ANALYSIS — deterministic PHP analyzer output (mechanic_failures, interrupt_analysis, add_performance, tank_analysis, healing_analysis).

OUTPUT — strict raw JSON object (NOT an array, NOT wrapped in markdown). Single object with these fields:

{
  "status": "PASS" | "FAIL",
  "boss": "{$bossName}",
  "mechanics": [
    {
      "name": "exact name from tactics.mechanics",
      "severity": "critical|major|minor",
      "can_describe": true | false,
      "fail_reason": null | "Ukrainian explanation of exact missing fields",
      "suggested_data_source": null | "Ukrainian — which WCL endpoint or field would unlock it"
    }
  ],
  "summary": "1-2 sentence Ukrainian summary"
}

RULES:
- status=PASS only if all critical AND major mechanics have can_describe=true.
- minor mechanics with can_describe=false do NOT block PASS.
- For each mechanic in tactics.mechanics, look in analysis.encounter.mechanic_failures, analysis.encounter.interrupt_analysis, analysis.encounter.add_performance, analysis.encounter.tank_analysis, analysis.encounter.healing_analysis. If any of these contain enough data (player names, counts, evidence types) to write specific failure descriptions, can_describe=true.
- For first-pull clean kills with no wipes: it's acceptable that some mechanics show no events — note this in summary but do not fail.

=== TACTICS (YAML) ===
{$tacticsYaml}

=== ANALYSIS (JSON) ===
{$analysisJson}
PROMPT;
    }
}
