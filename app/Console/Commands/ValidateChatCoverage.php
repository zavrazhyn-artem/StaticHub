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
 * Coverage check: feeds Gemini a list of ~40 typical player/raid-leader chat questions
 * about a real report, asks it to either answer each one or explain why it can't.
 * Output: per-question answer or fail_reason — used to identify data gaps in cache contents.
 */
class ValidateChatCoverage extends Command
{
    protected $signature = 'ai:validate-chat-coverage
        {wclReportId : The WCL report ID to validate}
        {--player= : Specific player name to focus questions on (defaults to first roster member)}';

    protected $description = 'Ask Gemini to answer 40 typical chat questions on a real report; reports which questions cannot be answered.';

    public function handle(
        WclService $wclService,
        TacticalDataAnalyzer $analyzer,
        TacticsLoader $tacticsLoader,
        GeminiService $geminiService,
    ): int {
        ini_set('memory_limit', '1024M');

        $reportId = $this->argument('wclReportId');
        $playerOpt = $this->option('player');

        $this->info("Fetching WCL data for report: {$reportId}");
        $logData = $wclService->getLogSummary($reportId);
        if (!empty($logData['error'])) {
            $this->error('WCL error: ' . $logData['error']);
            return self::FAILURE;
        }

        $players = $logData['players'] ?? [];
        $playerName = $playerOpt ?: ($players[0]['name'] ?? 'Unknown');
        $this->info("Focus player: {$playerName}");

        $localization = [
            'raid_leader' => ['locale' => 'Ukrainian'],
            'participants' => array_map(
                fn($p) => ['name' => $p['name'], 'locale' => 'Ukrainian'],
                $players
            ),
        ];

        $this->info('Running PHP TacticalDataAnalyzer for all encounters...');
        $analysis = $analyzer->analyze($logData, $localization, [], $reportId);
        $bossNames = array_keys($logData['phase_summary'] ?? []);
        $firstBoss = $bossNames[0] ?? 'Unknown';
        $secondBoss = $bossNames[1] ?? $firstBoss;

        $supplementary = json_encode([
            'player_details' => $logData['player_details'] ?? [],
        ], JSON_UNESCAPED_UNICODE);

        $analysisJson = json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Boss-aware question list — pull mechanic names from actual YAML tactics for first boss
        $firstBossMechanics = [];
        try {
            $firstBossTactics = $tacticsLoader->loadByBossName($firstBoss);
            foreach (array_slice($firstBossTactics['mechanics'] ?? [], 0, 5) as $m) {
                $firstBossMechanics[] = $m['name'] ?? '';
            }
        } catch (\Exception $e) {
            $firstBossMechanics = [];
        }
        $questions = $this->buildQuestionList($playerName, $firstBoss, $secondBoss, $firstBossMechanics);
        $this->info('Question list size: ' . count($questions));

        $proModel = (string) config('services.gemini.pro_model', 'gemini-3-flash-preview');
        $url = $geminiService->buildModelUrl($proModel);

        $prompt = $this->buildPrompt($playerName, $analysisJson, $supplementary, $questions);

        $outputDir = storage_path('app/ai-diagnostics');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);
        $timestamp = now()->format('Y-m-d_His');
        $basePath = "{$outputDir}/{$reportId}_{$timestamp}_chat_coverage";
        file_put_contents("{$basePath}_prompt.txt", $prompt);

        $this->info("Asking {$proModel} to answer {$proModel}...");
        try {
            $rawResponse = $geminiService->executeRequestWithModel(
                $prompt,
                $url,
                300,
                3,
                true,
                32768
            );
        } catch (\Exception $e) {
            $this->error("Gemini failed: " . $e->getMessage());
            return self::FAILURE;
        }

        $cleaned = GeminiResponseFormatter::cleanMarkdown($rawResponse);
        file_put_contents("{$basePath}_response.json", $cleaned);

        $parsed = json_decode($cleaned, true);
        if (!$parsed || !isset($parsed['answers'])) {
            $this->error('Failed to parse response. Saved raw to: ' . $basePath . '_response.json');
            return self::FAILURE;
        }

        $answers = $parsed['answers'];
        $totalQ = count($answers);
        $answerable = 0;
        $unanswerable = [];

        foreach ($answers as $a) {
            if ($a['can_answer'] ?? false) {
                $answerable++;
            } else {
                $unanswerable[] = $a;
            }
        }

        $this->newLine();
        $this->info("================================================");
        $this->info("COVERAGE: {$answerable}/{$totalQ} questions answerable (" . round($answerable / max($totalQ, 1) * 100, 1) . "%)");
        $this->info("================================================");

        if (!empty($unanswerable)) {
            $this->newLine();
            $this->warn('UNANSWERABLE QUESTIONS:');
            foreach ($unanswerable as $a) {
                $cat = $a['category'] ?? '?';
                $q = $a['question'] ?? '?';
                $reason = $a['fail_reason'] ?? '';
                $needs = $a['needs_data'] ?? '';
                $this->line("  [{$cat}] {$q}");
                $this->line("    ❌ {$reason}");
                if ($needs) $this->line("    ➕ Need: {$needs}");
            }
        }

        $this->newLine();
        $summary = $parsed['summary'] ?? '';
        if ($summary) {
            $this->info('SUMMARY: ' . $summary);
        }
        $this->line("Full response: {$basePath}_response.json");
        $this->line("Prompt: {$basePath}_prompt.txt");

        return self::SUCCESS;
    }

    private function buildQuestionList(string $player, string $firstBoss, string $secondBoss, array $firstBossMechanics = []): array
    {
        // Use real mechanics from this boss's YAML — fallback to generic names
        $mech1 = $firstBossMechanics[0] ?? 'main mechanic';
        $mech2 = $firstBossMechanics[1] ?? $mech1;
        $mech3 = $firstBossMechanics[2] ?? $mech1;

        return [
            // === Player personal ===
            ['category' => 'personal',  'question' => "Скільки разів {$player} помер за весь рейд?"],
            ['category' => 'personal',  'question' => "Від чого помер {$player} на босі {$firstBoss}?"],
            ['category' => 'personal',  'question' => "Який parse % у {$player} на {$firstBoss}?"],
            ['category' => 'personal',  'question' => "Який DPS у {$player} на {$secondBoss}?"],
            ['category' => 'personal',  'question' => "Який item level у {$player}?"],
            ['category' => 'personal',  'question' => "Які тринкети носив {$player}?"],
            ['category' => 'personal',  'question' => "Скільки разів {$player} використав хілстоун?"],
            ['category' => 'personal',  'question' => "Скільки потягів {$player} спожив?"],

            // === Player ability counts ===
            ['category' => 'casts',     'question' => "Скільки разів {$player} кастував Ironfur на {$firstBoss}?"],
            ['category' => 'casts',     'question' => "Який аптайм Bear Form у {$player} на {$firstBoss}?"],
            ['category' => 'casts',     'question' => "Скільки інтераптів зробив {$player} на {$firstBoss}?"],
            ['category' => 'casts',     'question' => "Скільки разів {$player} диспелив на {$firstBoss}?"],

            // === Mechanic-specific (using REAL boss mechanics from tactics) ===
            ['category' => 'mechanic',  'question' => "Який max stack {$mech1} був у {$player} на {$firstBoss}?"],
            ['category' => 'mechanic',  'question' => "Скільки damage отримав {$player} від {$mech2} на {$firstBoss}?"],
            ['category' => 'mechanic',  'question' => "Скільки гравців помирали від {$mech3} на {$firstBoss}?"],
            ['category' => 'mechanic',  'question' => "Хто витрачав інтерапти на захищених клонів (якщо такі є)?"],

            // === Raid-wide leader questions ===
            ['category' => 'raid',      'question' => "Хто має найгірше використання консумабельних бафів?"],
            ['category' => 'raid',      'question' => "Скільки гравців у рейді не мають food buff?"],
            ['category' => 'raid',      'question' => "Хто помер найбільше разів за весь рейд?"],
            ['category' => 'raid',      'question' => "На якому босі ми вайпали найчастіше?"],
            ['category' => 'raid',      'question' => "Хто отримав найбільше avoidable damage?"],
            ['category' => 'raid',      'question' => "Скільки часу зайняв весь рейд?"],
            ['category' => 'raid',      'question' => "Хто з танків мав довший boss uptime?"],
            ['category' => 'raid',      'question' => "Який найкращий wipe % на {$secondBoss}?"],

            // === Tactical/strategy (boss-aware via tactics) ===
            ['category' => 'tactical',  'question' => "На якій фазі {$firstBoss} ми втрачаємо найбільше людей?"],
            ['category' => 'tactical',  'question' => "Чи правильно танки свапали на {$firstBoss}?"],
            ['category' => 'tactical',  'question' => "Як рейд відпрацьовував {$mech1} на {$firstBoss}?"],
            ['category' => 'tactical',  'question' => "Які 3 найгірші помилки рейду на {$firstBoss}?"],
            ['category' => 'tactical',  'question' => "Які проблеми з адами на {$firstBoss} (якщо є)?"],

            // === Comparisons ===
            ['category' => 'comparison','question' => "Хто з DPS зробив найменше шкоди по аддам?"],
            ['category' => 'comparison','question' => "Хто з хілерів має найвищий overheal %?"],
            ['category' => 'comparison','question' => "Хто з рейду має найкращий parse сьогодні?"],
            ['category' => 'comparison','question' => "Хто з гравців не використовував augment rune?"],

            // === Performance/cooldowns ===
            ['category' => 'cooldowns', 'question' => "Який аптайм Avenging Wrath у нас на {$firstBoss}?"],
            ['category' => 'cooldowns', 'question' => "Скільки разів використано Bloodlust/Heroism за рейд?"],
            ['category' => 'cooldowns', 'question' => "Хто має найкраще використання major damage cooldowns?"],

            // === Death analysis ===
            ['category' => 'deaths',    'question' => "Хто помер першим на {$firstBoss} під час найкращого вайпу?"],
            ['category' => 'deaths',    'question' => "Які найчастіші killing blows у рейді?"],
            ['category' => 'deaths',    'question' => "Чи помирав хтось двічі від тієї самої механіки?"],
        ];
    }

    private function buildPrompt(string $player, string $analysisJson, string $supplementaryJson, array $questions): string
    {
        $questionsList = '';
        foreach ($questions as $i => $q) {
            $n = $i + 1;
            $questionsList .= "  {$n}. [{$q['category']}] {$q['question']}\n";
        }

        return <<<PROMPT
You are a diagnostic agent testing whether a raid analyzer's data is enough to answer typical chat questions from raid leaders and players.

Your job: for EACH question below, attempt to answer it using ONLY the data in PRE-ANALYZED RAID DATA + SUPPLEMENTARY. If the data is insufficient, say so explicitly with what's missing.

OUTPUT — strict raw JSON object only (no markdown, no trailing text):

{
  "summary": "Ukrainian — overall coverage assessment",
  "answers": [
    {
      "question_index": 1,
      "category": "personal|casts|mechanic|raid|tactical|comparison|cooldowns|deaths",
      "question": "оригінал питання",
      "can_answer": true | false,
      "answer": "Ukrainian — actual answer using data, or null if can_answer=false",
      "fail_reason": null | "Ukrainian — what specific data is missing",
      "needs_data": null | "Ukrainian — which WCL endpoint/field would unlock this"
    }
  ]
}

RULES:
- can_answer=true ONLY if you can give a SPECIFIC concrete answer with numbers/names from the data
- can_answer=false if data is missing/incomplete/general
- Be BRUTAL about can_answer — "I can guess" is can_answer=false
- For per-boss questions, look in encounters[].player_stats (not raid-wide globals)
- For raid-wide questions, look in raid_summary, consumable_audit, per_player_data

QUESTIONS TO ANSWER:
{$questionsList}

=== PRE-ANALYZED RAID DATA ===
{$analysisJson}

=== SUPPLEMENTARY ===
{$supplementaryJson}
PROMPT;
    }
}
