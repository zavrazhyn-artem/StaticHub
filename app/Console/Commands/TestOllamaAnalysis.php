<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Analysis\AiDeathSuppressor;
use App\Services\Analysis\GeminiService;
use App\Services\Analysis\TacticalDataAnalyzer;
use App\Services\Analysis\WclService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('ai:test-ollama {reportId : WCL report code} {--locale=Ukrainian} {--tone=neutral} {--model=gemma4:latest} {--measure-only : Build context and print size, skip Ollama call}')]
#[Description('Run our raid-analysis pipeline against a local Ollama model (gemma4) and dump the output for inspection.')]
class TestOllamaAnalysis extends Command
{
    private const OLLAMA_URL = 'http://host.docker.internal:11434/api/chat';

    public function handle(
        WclService $wcl,
        TacticalDataAnalyzer $analyzer,
        AiDeathSuppressor $suppressor,
        GeminiService $gemini
    ): int {
        ini_set('memory_limit', '1536M');

        $reportId   = $this->argument('reportId');
        $locale     = (string) $this->option('locale');
        $tone       = (string) $this->option('tone');
        $model      = (string) $this->option('model');
        $measureOnly = (bool) $this->option('measure-only');

        $outDir = storage_path('app/ai-ollama-test/' . date('Y-m-d_His') . '_' . $reportId);
        @mkdir($outDir, 0775, true);

        $this->info("== Stage 1: WCL fetch ==");
        $t0 = microtime(true);
        $logData = $wcl->getLogSummary($reportId);
        if (isset($logData['error'])) {
            $this->error('WCL error: ' . $logData['error']);
            return self::FAILURE;
        }
        $this->info(sprintf('  fights=%d  players=%d  deaths=%d  dt=%.1fs',
            count($logData['raid_fights'] ?? []),
            count($logData['players'] ?? []),
            count($logData['deaths'] ?? []),
            microtime(true) - $t0
        ));
        file_put_contents("$outDir/01_log_data.json", json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("== Stage 2: TacticalDataAnalyzer ==");
        $t0 = microtime(true);
        $localization = ['raid_leader' => ['locale' => $locale], 'participants' => []];
        $preprocessed = $analyzer->analyze($logData, $localization, [], $reportId);
        $this->info(sprintf('  encounters=%d  dt=%.1fs', count($preprocessed['encounters'] ?? []), microtime(true) - $t0));
        file_put_contents("$outDir/02_preprocessed.json", json_encode($preprocessed, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("== Stage 2.5: AiDeathSuppressor ==");
        $aiPreprocessed = $suppressor->suppress($preprocessed, 3);
        $preprocessedJson = json_encode($aiPreprocessed, JSON_UNESCAPED_UNICODE);
        $supplementary = json_encode(['player_details' => $logData['player_details'] ?? []], JSON_UNESCAPED_UNICODE);

        $this->info("== Stage 3: Build context + final prompt ==");
        $context = $gemini->buildRaidContextContent($preprocessedJson, $supplementary);
        $instructions = $gemini->loadPromptWithTone('gemini_main_report.txt', $tone);
        $generateSentence = "Generate the raid-wide main report now. EVERY string in EVERY block (title, headings, paragraphs, table cells, alert text, directive items, metrics labels) MUST be in {$locale} — no other language anywhere. Output strictly raw JSON.";
        $fullPrompt = $context
            . "\n\n=== INSTRUCTIONS ===\n" . $instructions
            . "\n\nRAID_LEADER_LOCALE: {$locale}\n\n{$generateSentence}";

        $size = strlen($fullPrompt);
        $approxTokens = (int) round($size / 3.5);
        $this->info(sprintf('  prompt_size=%d chars (~%d tokens)', $size, $approxTokens));
        file_put_contents("$outDir/03_full_prompt.txt", $fullPrompt);

        if ($measureOnly) {
            $this->warn("--measure-only set, skipping Ollama call.");
            $this->info("Artifacts: $outDir");
            return self::SUCCESS;
        }

        if ($approxTokens > 256_000) {
            $this->warn("Approx tokens ({$approxTokens}) exceed gemma4 256K context window — request will likely truncate.");
        }

        $this->info("== Stage 4: POST to Ollama gemma4 ==");
        $payload = [
            'model'   => $model,
            'stream'  => false,
            'format'  => 'json',
            'options' => [
                'temperature' => 0.2,
                'num_ctx'     => 262144,
                'num_predict' => 65536,
            ],
            'messages' => [
                ['role' => 'user', 'content' => $fullPrompt],
            ],
        ];

        $t0 = microtime(true);
        $response = Http::timeout(1800)
            ->connectTimeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::OLLAMA_URL, $payload);
        $elapsed = microtime(true) - $t0;

        file_put_contents("$outDir/04_ollama_raw.json", $response->body());

        if ($response->failed()) {
            $this->error("Ollama HTTP " . $response->status() . ': ' . mb_substr($response->body(), 0, 500));
            return self::FAILURE;
        }

        $data = $response->json();
        $content = $data['message']['content'] ?? '';
        $evalCount = $data['eval_count'] ?? null;
        $promptEval = $data['prompt_eval_count'] ?? null;

        $this->info(sprintf(
            '  dt=%.1fs  prompt_tokens=%s  output_tokens=%s  content_chars=%d',
            $elapsed,
            $promptEval ?? '?',
            $evalCount ?? '?',
            strlen($content)
        ));

        file_put_contents("$outDir/05_content.txt", $content);

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            file_put_contents("$outDir/06_parsed.json", json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $title = $decoded['title'] ?? '(no title)';
            $blocks = $decoded['main'] ?? [];
            $this->info("");
            $this->info("=== РЕЗУЛЬТАТ ===");
            $this->info("Title: $title");
            $this->info("Blocks: " . (is_array($blocks) ? count($blocks) : 0));
        } else {
            $this->warn("Output is not valid JSON. json_error=" . json_last_error_msg());
            $this->line(mb_substr($content, 0, 500));
        }

        $this->info("");
        $this->info("Artifacts: $outDir");

        return self::SUCCESS;
    }
}
