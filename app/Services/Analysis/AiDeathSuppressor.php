<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Trims the preprocessed analyzer output to the slice the AI actually needs.
 * UI/storage retain the unfiltered analyzer output — this only affects the
 * payload handed to GeminiService.
 *
 * Filter applied per pull (per fight):
 *   - First-N — keep the first $cutoff deaths chronologically. Even when a
 *     pull ended in a wipe call (cascade cluster in the final seconds), the
 *     first deaths still mark WHY the wipe happened, so we keep them rather
 *     than collapsing to a single trigger entry.
 *
 * Plus: pulls under 20s carry no useful signal and are dropped from
 * `encounters[].fights[]` entirely. Encounter-level aggregates stay intact.
 *
 * Cutoff range: 1-5 (was 3-10). The lower ceiling avoids burning prompt
 * tokens on cascade tail entries the AI doesn't act on; users wanting more
 * surface area get it from the unfiltered UI view, not the AI payload.
 */
class AiDeathSuppressor
{
    private const SHORT_PULL_THRESHOLD_S = 20;
    private const CUTOFF_MIN = 1;
    private const CUTOFF_MAX = 5;

    public function suppress(array $preprocessed, int $cutoff): array
    {
        $cutoff = max(self::CUTOFF_MIN, min(self::CUTOFF_MAX, $cutoff));

        if (!isset($preprocessed['encounters']) || !is_array($preprocessed['encounters'])) {
            return $preprocessed;
        }

        $encCount = count($preprocessed['encounters']);
        for ($e = 0; $e < $encCount; $e++) {
            $enc = $preprocessed['encounters'][$e];
            if (!isset($enc['fights']) || !is_array($enc['fights'])) {
                continue;
            }

            $kept = [];
            $shortDropped = 0;
            foreach ($enc['fights'] as $fight) {
                if ((int) ($fight['duration_s'] ?? 0) < self::SHORT_PULL_THRESHOLD_S) {
                    $shortDropped++;
                    continue;
                }

                $fight['deaths'] = $this->filterPullDeaths(
                    $fight['deaths'] ?? [],
                    $cutoff
                );
                $kept[] = $fight;
            }

            $preprocessed['encounters'][$e]['fights'] = $kept;
            $preprocessed['encounters'][$e]['_ai_short_pulls_excluded'] = $shortDropped;
        }

        $preprocessed['_ai_death_cutoff'] = $cutoff;
        return $preprocessed;
    }

    /**
     * @param array<int, array> $deaths
     * @return array<int, array>
     */
    private function filterPullDeaths(array $deaths, int $cutoff): array
    {
        if (empty($deaths)) return $deaths;

        usort($deaths, fn($a, $b) => ($a['time_ms_relative'] ?? 0) <=> ($b['time_ms_relative'] ?? 0));

        return array_slice($deaths, 0, $cutoff);
    }
}
