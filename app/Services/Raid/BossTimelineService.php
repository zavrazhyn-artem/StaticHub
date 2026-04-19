<?php

declare(strict_types=1);

namespace App\Services\Raid;

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads boss timelines from YAML files under
 * resources/boss-timelines/{season}/{difficulty}/{slug}.yml
 *
 * Output shape is payload-ready for the BossPlanner Vue layer — same
 * keys the old boss_ability_timings/boss_phase_segments tables produced
 * (segment_id, seed_start, seed_duration, default_casts[{segment_id,offset}])
 * plus the new fields (priority, recommended_response, notes,
 * conditional_abilities, phase triggers).
 *
 * Files are the source of truth. Per-plan overrides continue to live
 * in raid_plans.timeline.phase_segments.
 */
class BossTimelineService
{
    private const SCHOOL_PALETTE = [
        'shadow'   => ['#A78BFA', '#8B5CF6', '#C084FC', '#7C3AED', '#6D28D9', '#DDD6FE'],
        'fire'     => ['#F97316', '#FB923C', '#EA580C', '#FCA5A5', '#DC2626', '#FBBF24'],
        'frost'    => ['#06B6D4', '#38BDF8', '#22D3EE', '#0EA5E9', '#7DD3FC', '#0284C7'],
        'nature'   => ['#22C55E', '#4ADE80', '#16A34A', '#84CC16', '#A3E635', '#65A30D'],
        'arcane'   => ['#EC4899', '#F472B6', '#DB2777', '#F9A8D4', '#E879F9', '#D946EF'],
        'holy'     => ['#FACC15', '#FDE047', '#EAB308', '#FBBF24', '#FEF3C7', '#CA8A04'],
        'physical' => ['#F5F5F4', '#E5E5E5', '#D4D4D8', '#A8A29E', '#FAFAFA', '#78716C'],
    ];

    /**
     * Return a timeline payload for a single encounter+difficulty, or null
     * when no YAML exists yet.
     *
     * @return array{
     *   encounter: array,
     *   phases: array<int, array>,
     *   abilities: array<int, array>,
     *   conditional_abilities: array<int, array>
     * }|null
     */
    public function loadEncounter(string $season, string $slug, string $difficulty): ?array
    {
        $path = $this->pathFor($season, $difficulty, $slug);
        if (!File::exists($path)) {
            return null;
        }

        $data = Yaml::parseFile($path);

        $phases = $this->buildPhases($data['phases'] ?? []);
        $abilities = $this->buildAbilities($data['abilities'] ?? [], $phases);
        $conditional = $this->buildConditionalAbilities($data['conditional_abilities'] ?? []);

        return [
            'encounter' => $data['encounter'] ?? [],
            'phases' => $phases,
            'abilities' => $abilities,
            'conditional_abilities' => $conditional,
        ];
    }

    /**
     * Load every available encounter YAML for a season, keyed by
     * [encounter_slug][difficulty] → payload. Used by the planner payload
     * builder to hydrate all encounters in one pass.
     */
    public function loadSeason(string $season): array
    {
        $root = resource_path('boss-timelines/' . $season);
        if (!File::isDirectory($root)) return [];

        $out = [];
        foreach (File::directories($root) as $diffDir) {
            $difficulty = basename($diffDir);
            foreach (File::files($diffDir) as $file) {
                if ($file->getExtension() !== 'yml' && $file->getExtension() !== 'yaml') continue;
                $slug = $file->getFilenameWithoutExtension();
                $payload = $this->loadEncounter($season, $slug, $difficulty);
                if ($payload !== null) {
                    $out[$slug][$difficulty] = $payload;
                }
            }
        }
        return $out;
    }

    public function pathFor(string $season, string $difficulty, string $slug): string
    {
        return resource_path("boss-timelines/{$season}/{$difficulty}/{$slug}.yml");
    }

    /**
     * Compute seed_start (cumulative offset) and segment_order from phase
     * order. The YAML only declares seed_duration per phase; this method
     * chains them into a continuous timeline. Also retains trigger metadata.
     */
    private function buildPhases(array $raw): array
    {
        $out = [];
        $cursor = 0;
        foreach (array_values($raw) as $i => $p) {
            $duration = (int) ($p['seed_duration'] ?? 0);
            $out[] = [
                'segment_id' => (string) $p['id'],
                'phase_id' => $i + 1,
                'phase_name' => (string) ($p['name'] ?? ('Phase ' . ($i + 1))),
                'is_intermission' => (bool) ($p['is_intermission'] ?? false),
                'seed_start' => $cursor,
                'seed_duration' => $duration,
                'segment_order' => $i,
                'trigger' => $this->normalizeTrigger($p['trigger'] ?? ['type' => 'pull']),
            ];
            $cursor += $duration;
        }
        return $out;
    }

    private function normalizeTrigger(array $trigger): array
    {
        return [
            'type' => (string) ($trigger['type'] ?? 'pull'),
            'value' => isset($trigger['value']) ? (int) $trigger['value'] : null,
            'spell_id' => isset($trigger['spell_id']) ? (int) $trigger['spell_id'] : null,
            'phase_ref' => isset($trigger['phase_ref']) ? (string) $trigger['phase_ref'] : null,
            'estimated_time' => isset($trigger['estimated_time']) ? (int) $trigger['estimated_time'] : null,
            'min_time' => isset($trigger['min_time']) ? (int) $trigger['min_time'] : null,
            'max_time' => isset($trigger['max_time']) ? (int) $trigger['max_time'] : null,
        ];
    }

    private function buildAbilities(array $raw, array $phases): array
    {
        $validPhaseIds = array_column($phases, 'segment_id');
        $out = [];
        $order = 0;
        foreach ($raw as $ab) {
            $casts = [];
            foreach (($ab['casts'] ?? []) as $c) {
                $phaseId = (string) ($c['phase'] ?? '');
                if (!in_array($phaseId, $validPhaseIds, true)) continue;
                $casts[] = [
                    'segment_id' => $phaseId,
                    'offset' => (int) ($c['at'] ?? 0),
                ];
            }
            $out[] = array_merge($this->resolveAbilityCommon($ab), [
                'default_casts' => $casts,
                'duration_sec' => (int) ($ab['duration_sec'] ?? 0),
                'row_order' => $order++,
            ]);
        }
        return $out;
    }

    private function buildConditionalAbilities(array $raw): array
    {
        $out = [];
        foreach ($raw as $ab) {
            $out[] = array_merge($this->resolveAbilityCommon($ab), [
                'trigger' => (string) ($ab['trigger'] ?? 'conditional'),
                'duration_sec' => (int) ($ab['duration_sec'] ?? 0),
            ]);
        }
        return $out;
    }

    /**
     * Shared ability fields — id, spell_id, name, icon, color, school.
     * Icons are resolved to /images/cooldowns/{spell_id}.jpg if downloaded,
     * falling back to the raw zamimg name when the file is missing. All
     * notes_<locale> variants present in the YAML are passed through so the
     * Vue panel can pick the right one for the user's locale at render time.
     */
    private function resolveAbilityCommon(array $ab): array
    {
        $spellId = isset($ab['spell_id']) ? (int) $ab['spell_id'] : null;
        $school = strtolower((string) ($ab['school'] ?? 'shadow'));

        $out = [
            'id' => (string) ($ab['id'] ?? ''),
            'spell_id' => $spellId,
            'name' => (string) ($ab['name'] ?? ''),
            'icon' => (string) ($ab['icon'] ?? ''),
            'icon_filename' => $this->resolveIconFilename($spellId),
            'color' => $this->colorForSchool($school, (string) ($ab['name'] ?? '')),
            'ability_type' => ucfirst($school),
            'school' => $school,
            'priority' => (string) ($ab['priority'] ?? 'medium'),
            'recommended_response' => array_values((array) ($ab['recommended_response'] ?? [])),
            'notes' => (string) ($ab['notes'] ?? ''),
        ];
        foreach ($ab as $k => $v) {
            if (is_string($k) && str_starts_with($k, 'notes_') && $k !== 'notes') {
                $out[$k] = (string) $v;
            }
        }
        return $out;
    }

    private function resolveIconFilename(?int $spellId): ?string
    {
        if ($spellId === null) return null;
        $path = public_path("images/cooldowns/{$spellId}.jpg");
        return File::exists($path) ? "{$spellId}.jpg" : null;
    }

    /**
     * Pick a stable hue from the school palette, keyed by the ability name
     * so two abilities of the same school don't collide visually.
     */
    private function colorForSchool(string $school, string $name): string
    {
        $palette = self::SCHOOL_PALETTE[$school] ?? self::SCHOOL_PALETTE['shadow'];
        return $palette[crc32($name) % count($palette)];
    }
}
