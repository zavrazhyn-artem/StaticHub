<?php

declare(strict_types=1);

namespace App\Services\Raid;

use App\Models\RaidPlan;
use Illuminate\Database\Eloquent\Collection;

class BossPlannerService
{
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

        $encounterMaps = config('wow_season.encounter_maps', []);
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
                $portraits = array_map(
                    fn (int $id) => "/images/raidplan/portraits/{$id}.png",
                    $bossData['portraits'] ?? []
                );

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
                    'portrait' => $portraits[0] ?? null,
                    'portraits' => $portraits,
                    'abilities' => $bossData['abilities'] ?? [],
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
}
