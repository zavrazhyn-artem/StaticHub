<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\RaidPlan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

class BossPlannerService
{
    /** @var array{by_icon?: array<string, list<string>>}|null */
    private ?array $abilityNameCache = null;

    public function __construct(
        private readonly BossTimelineService $timelineService,
    ) {}

    /**
     * Create a new raid plan for an encounter.
     */
    public function createPlan(array $data): RaidPlan
    {
        return RaidPlan::create([
            'event_id' => $data['event_id'] ?? null,
            'static_id' => $data['static_id'],
            'encounter_slug' => $data['encounter_slug'],
            'difficulty' => $data['difficulty'] ?? 'mythic',
            'title' => $data['title'] ?? null,
            'steps' => $data['steps'] ?? [$this->defaultStep()],
            'timeline' => $data['timeline'] ?? null,
        ]);
    }

    /**
     * Update an existing raid plan.
     */
    public function updatePlan(RaidPlan $plan, array $data): RaidPlan
    {
        $plan->update(array_filter([
            'title' => $data['title'] ?? $plan->title,
            'steps' => $data['steps'] ?? $plan->steps,
            'timeline' => array_key_exists('timeline', $data) ? $data['timeline'] : $plan->timeline,
            'difficulty' => $data['difficulty'] ?? $plan->difficulty,
        ], fn ($v) => $v !== null));

        return $plan->fresh();
    }

    /**
     * Delete a raid plan.
     */
    public function deletePlan(RaidPlan $plan): void
    {
        $plan->delete();
    }

    /**
     * Generate a share token for a plan. Returns the token.
     */
    public function sharePlan(RaidPlan $plan): string
    {
        if (!$plan->share_token) {
            $plan->update(['share_token' => bin2hex(random_bytes(16))]);
        }

        return $plan->share_token;
    }

    /**
     * Revoke the share token.
     */
    public function unsharePlan(RaidPlan $plan): void
    {
        $plan->update(['share_token' => null]);
    }

    /**
     * Get all plans for a static group, grouped by encounter.
     */
    public function getPlansForStatic(int $staticId): Collection
    {
        return RaidPlan::query()->allForStatic($staticId);
    }

    /**
     * Get or create a plan for a specific encounter.
     */
    public function getOrCreatePlan(int $staticId, string $encounterSlug, string $difficulty = 'mythic', ?int $eventId = null): RaidPlan
    {
        $existing = RaidPlan::query()->findForEncounter($staticId, $encounterSlug, $difficulty);

        if ($existing) {
            return $existing;
        }

        return $this->createPlan([
            'event_id' => $eventId,
            'static_id' => $staticId,
            'encounter_slug' => $encounterSlug,
            'difficulty' => $difficulty,
            'steps' => [$this->defaultStep()],
        ]);
    }

    /**
     * Add a step to a plan.
     */
    public function addStep(RaidPlan $plan, ?string $label = null): RaidPlan
    {
        $steps = $plan->steps;
        $stepNumber = count($steps) + 1;

        $steps[] = $this->defaultStep($label ?? "Phase {$stepNumber}");

        $plan->update(['steps' => $steps]);

        return $plan->fresh();
    }

    /**
     * Remove a step from a plan.
     */
    public function removeStep(RaidPlan $plan, int $stepIndex): RaidPlan
    {
        $steps = $plan->steps;

        if (count($steps) <= 1) {
            return $plan;
        }

        array_splice($steps, $stepIndex, 1);
        $plan->update(['steps' => array_values($steps)]);

        return $plan->fresh();
    }

    /**
     * Update a single step in a plan.
     */
    public function updateStep(RaidPlan $plan, int $stepIndex, array $stepData): RaidPlan
    {
        $steps = $plan->steps;

        if (!isset($steps[$stepIndex])) {
            return $plan;
        }

        $steps[$stepIndex] = array_merge($steps[$stepIndex], $stepData);
        $plan->update(['steps' => $steps]);

        return $plan->fresh();
    }

    /**
     * Build payload for the boss planner tab.
     */
    public function buildPlannerPayload(int $staticId, ?int $eventId = null): array
    {
        $raidInstances = config('wow_season.current_raid_instances', []);
        $plans = $this->getPlansForStatic($staticId);

        $encounterMaps = array_map(
            fn (array $maps) => array_map(
                fn (array $m) => array_merge($m, ['url' => asset(ltrim((string) ($m['url'] ?? ''), '/'))]),
                $maps
            ),
            config('wow_season.encounter_maps', [])
        );
        $encounterBosses = config('wow_season.encounter_bosses', []);
        $season = (string) (config('wow_season.current_season') ?: 'midnight-s1');

        // YAML-backed timeline data: [slug][difficulty] → { encounter, phases,
        // abilities, conditional_abilities }. Source of truth lives in
        // resources/boss-timelines/{season}/{difficulty}/{slug}.yml.
        $timelineData = $this->timelineService->loadSeason($season);

        // Reverse lookup: boss name → WCL encounterID. Needed by the MRT-note
        // exporter so the generated string can include the `EncounterID:<n>`
        // anchor that NSRT / MethodRaidTools read.
        $encounterIdByName = array_flip(config('wow_season.wcl_encounter_ids', []));

        $encounters = [];
        foreach ($raidInstances as $instanceName => $bosses) {
            foreach ($bosses as $bossName) {
                $slug = \Illuminate\Support\Str::slug($bossName);
                $plan = $plans->first(fn (RaidPlan $p) => $p->encounter_slug === $slug);
                $bossData = $encounterBosses[$slug] ?? [];
                $portraits = $this->enrichPortraits(
                    $bossName,
                    $bossData['portraits'] ?? [],
                    $bossData['portrait_names'] ?? []
                );
                $abilities = $this->enrichAbilities($bossData['abilities'] ?? []);

                $bossPlans = $plans->filter(fn (RaidPlan $p) => $p->encounter_slug === $slug)->values();

                // Slice YAML data per difficulty for this encounter.
                $bossTimeline = $timelineData[$slug] ?? [];
                $timingsByDiff = [];
                $phasesByDiff = [];
                $conditionalsByDiff = [];
                foreach ($bossTimeline as $diff => $payload) {
                    $timingsByDiff[$diff] = $payload['abilities'] ?? [];
                    $phasesByDiff[$diff] = $payload['phases'] ?? [];
                    $conditionalsByDiff[$diff] = $payload['conditional_abilities'] ?? [];
                }

                $encounters[] = [
                    'slug' => $slug,
                    'name' => $bossName,
                    'encounter_id' => $encounterIdByName[$bossName] ?? null,
                    'instance' => $instanceName,
                    'maps' => $encounterMaps[$slug] ?? [],
                    'portrait' => $portraits[0]['url'] ?? null,
                    'portraits' => $portraits,
                    'abilities' => $abilities,
                    'boss_ability_timings' => $timingsByDiff,
                    'phase_segments' => $phasesByDiff,
                    'conditional_abilities' => $conditionalsByDiff,
                    'has_plan' => $bossPlans->isNotEmpty(),
                    'plans' => $bossPlans->map(fn (RaidPlan $p) => [
                        'id' => $p->id,
                        'title' => $p->title,
                        'steps' => $p->steps,
                        'timeline' => $p->timeline,
                        'difficulty' => $p->difficulty,
                        'updated_at' => $p->updated_at->toIso8601String(),
                    ])->toArray(),
                    // Keep backward compat: first plan as 'plan'
                    'plan' => $bossPlans->isNotEmpty() ? [
                        'id' => $bossPlans->first()->id,
                        'title' => $bossPlans->first()->title,
                        'steps' => $bossPlans->first()->steps,
                        'timeline' => $bossPlans->first()->timeline,
                        'difficulty' => $bossPlans->first()->difficulty,
                        'updated_at' => $bossPlans->first()->updated_at->toIso8601String(),
                    ] : null,
                ];
            }
        }

        return [
            'encounters' => $encounters,
            'season' => $season,
            'player_cooldowns' => config('wow_cooldowns', []),
            'staticId' => $staticId,
            'eventId' => $eventId,
        ];
    }

    private function defaultStep(string $label = 'Phase 1'): array
    {
        return [
            'label' => $label,
            'markers' => [],
            'players' => [],
            'shapes' => [],
            'arrows' => [],
            'labels' => [],
        ];
    }

    /**
     * Attach human-readable names to a list of icon filenames so the boss-
     * planner toolbar can show "Void Howl" instead of "inv_cosmicvoid_orb".
     * Names come from storage/app/wow/boss_ability_names.json which is
     * populated by `php artisan boss-planner:sync-names` (resolves YAML
     * spell_ids → Wowhead nether tooltip → icon). Falls back to a
     * pretty-printed filename when the icon isn't in the cache.
     *
     * @param  list<string>  $iconFilenames
     * @return list<array{icon: string, name: string}>
     */
    private function enrichAbilities(array $iconFilenames): array
    {
        $cache = $this->loadAbilityNameCache();
        $byIcon = $cache['by_icon'] ?? [];
        $out = [];
        foreach ($iconFilenames as $icon) {
            $name = $byIcon[$icon][0] ?? $this->prettifyIconFilename($icon);
            $out[] = ['icon' => $icon, 'name' => $name];
        }
        return $out;
    }

    /**
     * Convert raw portrait IDs into URL+name objects. The IDs in
     * `wow_season.encounter_bosses[*].portraits` are arbitrary asset numbers
     * (matching `/images/raidplan/portraits/{id}.png`) — they are NOT
     * Wowhead NPC IDs, so names can't be looked up automatically. Names come
     * from the encounter's `portrait_names` array (manually curated, same
     * order as `portraits`); when missing we fall back to the boss name for
     * index 0 and "Boss · #N" for the rest so the toolbar isn't blank.
     *
     * @param  list<int>  $ids
     * @param  list<string>  $names
     * @return list<array{url: string, name: string}>
     */
    private function enrichPortraits(string $bossName, array $ids, array $names = []): array
    {
        $out = [];
        foreach ($ids as $i => $id) {
            $name = $names[$i] ?? null;
            if (!$name) {
                $name = $i === 0 ? $bossName : ($bossName . ' · #' . ($i + 1));
            }
            $out[] = [
                'url' => asset("images/raidplan/portraits/{$id}.png"),
                'name' => $name,
            ];
        }
        return $out;
    }

    /**
     * Turn a Blizzard icon filename like `inv_cosmicvoid_orb` into a readable
     * "Cosmic Void Orb". Drops the leading category token (inv_/spell_/
     * ability_/sha_/achievement_/etc.) and title-cases the rest. Used as a
     * fallback when the YAML+Wowhead lookup can't resolve a real spell name.
     */
    private function prettifyIconFilename(string $filename): string
    {
        // Strip extension if present
        $name = preg_replace('/\.(png|jpg|jpeg)$/i', '', $filename) ?? $filename;
        // Drop the leading prefix token if it's one of the standard
        // Blizzard category prefixes — they're noise, not part of the name.
        $name = preg_replace(
            '/^(inv|spell|ability|sha|achievement|warlock|talent|trade|item)_(\d+_)?/',
            '',
            $name
        ) ?? $name;
        $name = str_replace(['_', '-'], ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        return $name === '' ? $filename : ucwords($name);
    }

    /**
     * @return array{by_spell?: array<int, array{name:string,icon:string}>, by_icon?: array<string, list<string>>}
     */
    private function loadAbilityNameCache(): array
    {
        if ($this->abilityNameCache !== null) return $this->abilityNameCache;
        $path = storage_path('app/wow/boss_ability_names.json');
        if (!File::exists($path)) {
            return $this->abilityNameCache = [];
        }
        $data = json_decode((string) File::get($path), true);
        return $this->abilityNameCache = is_array($data) ? $data : [];
    }
}
