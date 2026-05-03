<?php

declare(strict_types=1);

namespace App\Services\Analysis;

/**
 * Trims the preprocessed analyzer output to the slice the AI actually needs.
 * UI/storage retain the unfiltered analyzer output — this only affects the
 * payload handed to GeminiService.
 *
 * Two-stage filter applied per pull (per fight):
 *   1. Cascade suppression — within the last 10s of the pull, keep only the
 *      first death (the wipe trigger); drop the rest.
 *   2. N-cap — keep at most $cutoff deaths chronologically.
 *
 * Plus: pulls under 20s carry no useful signal and are dropped from
 * `encounters[].fights[]` entirely. Encounter-level aggregates stay intact.
 */
class AiDeathSuppressor
{
    private const CASCADE_WINDOW_MS = 10_000;
    private const SHORT_PULL_THRESHOLD_S = 20;

    public function suppress(array $preprocessed, int $cutoff): array
    {
        $cutoff = max(3, min(10, $cutoff));

        if (!isset($preprocessed['encounters']) || !is_array($preprocessed['encounters'])) {
            return $preprocessed;
        }

        foreach ($preprocessed['encounters'] as &$enc) {
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
                    (int) ($fight['duration_s'] ?? 0) * 1000,
                    $cutoff
                );
                $kept[] = $fight;
            }

            $enc['fights'] = $kept;
            $enc['_ai_short_pulls_excluded'] = $shortDropped;
        }
        unset($enc);

        $preprocessed['_ai_death_cutoff'] = $cutoff;
        return $preprocessed;
    }

    /**
     * @param array<int, array> $deaths
     * @return array<int, array>
     */
    private function filterPullDeaths(array $deaths, int $pullEndMs, int $cutoff): array
    {
        if (empty($deaths)) return $deaths;

        usort($deaths, fn($a, $b) => ($a['time_ms_relative'] ?? 0) <=> ($b['time_ms_relative'] ?? 0));

        $cascadeStart = $pullEndMs - self::CASCADE_WINDOW_MS;
        $cascadeTriggerSeen = false;
        $kept = [];

        foreach ($deaths as $d) {
            $t = $d['time_ms_relative'] ?? null;
            if ($t === null || $t < $cascadeStart) {
                $kept[] = $d;
                continue;
            }
            if (!$cascadeTriggerSeen) {
                $cascadeTriggerSeen = true;
                $kept[] = $d;
            }
        }

        return array_slice($kept, 0, $cutoff);
    }
}
