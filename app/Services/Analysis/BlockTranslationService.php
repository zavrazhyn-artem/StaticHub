<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use App\Enums\Locale;
use App\Models\PersonalTacticalReport;
use Illuminate\Support\Facades\Log;

class BlockTranslationService
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly BlockSchema   $blockSchema,
    ) {}

    /**
     * Return the report's blocks translated into the target locale.
     *
     * Reads from the cached `ai_blocks_translations` map if present; otherwise
     * calls Gemini Flash and persists the result. If the target locale matches
     * the source (best-effort — source locale is not tracked per-report), we
     * still translate so Gemini can normalize wording.
     *
     * @return array<int, array<string, mixed>>
     */
    public function translate(PersonalTacticalReport $report, string $targetLocale): array
    {
        if (!$report->ai_blocks || !is_array($report->ai_blocks)) {
            return [];
        }

        $locale = Locale::fromString($targetLocale);
        $cacheKey = $locale->value;

        $cached = $report->ai_blocks_translations[$cacheKey] ?? null;
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        $translated = $this->callGemini($report->ai_blocks, $locale);

        $translations = $report->ai_blocks_translations ?? [];
        $translations[$cacheKey] = $translated;
        $report->ai_blocks_translations = $translations;
        $report->save();

        return $translated;
    }

    /**
     * Build the prompt and send a single JSON-mode Flash request.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @return array<int, array<string, mixed>>
     */
    private function callGemini(array $blocks, Locale $locale): array
    {
        $template = file_get_contents(resource_path('prompts/gemini_block_translate.txt'));

        $prompt = $template
            . "\n\nTARGET_LOCALE: " . $locale->fullName()
            . "\n\nBLOCKS: " . json_encode($blocks, JSON_UNESCAPED_UNICODE);

        $raw = $this->geminiService->executeRequest($prompt, timeout: 180, retries: 2, jsonMode: true);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            Log::warning('BlockTranslationService: Gemini returned non-JSON, falling back to source', [
                'locale' => $locale->value,
                'raw_preview' => substr($raw, 0, 200),
            ]);
            return $blocks;
        }

        $sanitized = $this->blockSchema->sanitize($decoded);

        // Guard: if sanitized lost a lot of blocks vs source, the model broke structure — fall back.
        if (count($sanitized) < (int) floor(count($blocks) * 0.5)) {
            Log::warning('BlockTranslationService: sanitized output too short, falling back to source', [
                'locale' => $locale->value,
                'source_count' => count($blocks),
                'sanitized_count' => count($sanitized),
            ]);
            return $blocks;
        }

        return $sanitized;
    }
}
