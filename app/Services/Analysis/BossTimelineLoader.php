<?php

declare(strict_types=1);

namespace App\Services\Analysis;

use Symfony\Component\Yaml\Yaml;

/**
 * Loads boss timeline YAML files (the same source used by the BossPlanner Vue
 * UI) and produces a slim, AI-friendly payload — phases + absolute-time
 * scheduled ability casts.
 *
 * Lets the AI correlate deaths / missed cooldowns with scheduled mechanics:
 *   "player died at 134s — Primordial Roar was scheduled at 132s"
 *   "player CD missed at 130s — Primordial Roar was 2s away"
 *
 * UI fluff (colors, icons, segment_order, default_casts shape) is stripped.
 * Per-phase `at` offsets are converted to absolute fight-time seconds so the
 * AI doesn't need to chain phase start times.
 *
 * Files: resources/boss-timelines/{season}/{difficulty}/{slug}.yml
 *
 * Output shape:
 *   fight_duration_max: int|null
 *   phases:
 *     - { id, name, starts_at, duration, intermission }
 *   scheduled_abilities:
 *     - { name, spell_id, priority, school, casts_at: [seconds…], notes }
 *   conditional_abilities:
 *     - { name, spell_id, priority, trigger, notes }
 */
class BossTimelineLoader
{
    /** @var array<string, array|null> */
    private array $cache = [];

    public function load(?string $season, ?string $slug, ?string $difficulty): ?array
    {
        if (!$season || !$slug || !$difficulty) return null;

        $key = "{$season}/{$difficulty}/{$slug}";
        if (array_key_exists($key, $this->cache)) return $this->cache[$key];

        $path = resource_path("boss-timelines/{$season}/{$difficulty}/{$slug}.yml");
        if (!is_file($path)) {
            return $this->cache[$key] = null;
        }

        try {
            $raw = Yaml::parseFile($path);
            if (!is_array($raw)) return $this->cache[$key] = null;
            return $this->cache[$key] = $this->buildSlim($raw);
        } catch (\Throwable) {
            return $this->cache[$key] = null;
        }
    }

    private function buildSlim(array $raw): array
    {
        $phases = [];
        $phaseStartById = [];
        $cursor = 0;
        foreach (array_values($raw['phases'] ?? []) as $p) {
            $id = (string) ($p['id'] ?? '');
            $duration = (int) ($p['seed_duration'] ?? 0);
            $phases[] = [
                'id' => $id,
                'name' => (string) ($p['name'] ?? ''),
                'starts_at' => $cursor,
                'duration' => $duration,
                'intermission' => (bool) ($p['is_intermission'] ?? false),
            ];
            $phaseStartById[$id] = $cursor;
            $cursor += $duration;
        }

        $scheduled = [];
        foreach ($raw['abilities'] ?? [] as $ab) {
            $castsAt = [];
            foreach ($ab['casts'] ?? [] as $c) {
                $phaseId = (string) ($c['phase'] ?? '');
                if (!array_key_exists($phaseId, $phaseStartById)) continue;
                $castsAt[] = $phaseStartById[$phaseId] + (int) ($c['at'] ?? 0);
            }
            sort($castsAt);
            $scheduled[] = $this->prune([
                'name' => (string) ($ab['name'] ?? ''),
                'spell_id' => isset($ab['spell_id']) ? (int) $ab['spell_id'] : null,
                'priority' => (string) ($ab['priority'] ?? 'medium'),
                'school' => (string) ($ab['school'] ?? ''),
                'casts_at' => $castsAt,
                'notes' => (string) ($ab['notes'] ?? ''),
            ]);
        }

        $conditional = [];
        foreach ($raw['conditional_abilities'] ?? [] as $ab) {
            $conditional[] = $this->prune([
                'name' => (string) ($ab['name'] ?? ''),
                'spell_id' => isset($ab['spell_id']) ? (int) $ab['spell_id'] : null,
                'priority' => (string) ($ab['priority'] ?? 'medium'),
                'trigger' => (string) ($ab['trigger'] ?? ''),
                'notes' => (string) ($ab['notes'] ?? ''),
            ]);
        }

        $duration = (int) ($raw['encounter']['fight_duration_max'] ?? 0);

        return [
            'fight_duration_max' => $duration > 0 ? $duration : null,
            'phases' => $phases,
            'scheduled_abilities' => $scheduled,
            'conditional_abilities' => $conditional,
        ];
    }

    private function prune(array $row): array
    {
        return array_filter(
            $row,
            fn($v) => $v !== null && $v !== '' && $v !== []
        );
    }
}
