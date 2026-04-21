<?php

namespace App\Http\Controllers\Raid;

use App\Http\Controllers\Controller;
use App\Models\CharacterCooldownOverride;
use App\Models\RaidPlan;
use App\Models\StaticGroup;
use App\Services\Raid\BossPlannerService;
use App\Services\Raid\RaidAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BossPlannerController extends Controller
{
    public function __construct(
        protected BossPlannerService $bossPlannerService,
    ) {}

    /**
     * Display the boss planner index — all encounters with plan status.
     */
    public function index(StaticGroup $static): View
    {
        $plannerData = $this->bossPlannerService->buildPlannerPayload($static->id);

        $roster = $this->buildRosterPayload($static);

        $myCharacterIds = Auth::user()->characters()
            ->whereHas('statics', fn ($q) => $q->where('statics.id', $static->id))
            ->pluck('id')->toArray();

        return view('boss-planner.index', [
            'plannerData' => $plannerData,
            'roster' => $roster,
            'static' => $static,
            'myCharacterIds' => $myCharacterIds,
        ]);
    }

    /**
     * Save or update a raid plan.
     */
    public function save(Request $request, StaticGroup $static): JsonResponse
    {
        Gate::authorize('canManageSchedule', $static);

        $validated = $request->validate([
            'encounter_slug' => 'required|string',
            'title' => 'nullable|string|max:255',
            'steps' => 'required|array',
            'timeline' => 'nullable|array',
            'difficulty' => 'string|in:raid_finder,normal,heroic,mythic',
            'plan_id' => 'nullable|integer|exists:raid_plans,id',
        ]);

        if (!empty($validated['plan_id'])) {
            $plan = RaidPlan::findOrFail($validated['plan_id']);
            $plan = $this->bossPlannerService->updatePlan($plan, $validated);
        } else {
            $plan = $this->bossPlannerService->createPlan([
                'static_id' => $static->id,
                'encounter_slug' => $validated['encounter_slug'],
                'difficulty' => $validated['difficulty'] ?? 'mythic',
                'title' => $validated['title'],
                'steps' => $validated['steps'],
            ]);
        }

        return response()->json(['success' => true, 'plan' => $plan]);
    }

    /**
     * Delete a raid plan.
     */
    public function destroy(StaticGroup $static, RaidPlan $raidPlan): JsonResponse
    {
        Gate::authorize('canManageSchedule', $static);

        $this->bossPlannerService->deletePlan($raidPlan);

        return response()->json(['success' => true]);
    }

    /**
     * Generate a share link for a plan.
     */
    public function share(StaticGroup $static, RaidPlan $raidPlan): JsonResponse
    {
        Gate::authorize('canManageSchedule', $static);

        $token = $this->bossPlannerService->sharePlan($raidPlan);

        return response()->json([
            'success' => true,
            'share_url' => route('plan.shared', $token),
            'token' => $token,
        ]);
    }

    /**
     * Revoke a share link.
     */
    public function unshare(StaticGroup $static, RaidPlan $raidPlan): JsonResponse
    {
        Gate::authorize('canManageSchedule', $static);

        $this->bossPlannerService->unsharePlan($raidPlan);

        return response()->json(['success' => true]);
    }

    /**
     * Public read-only view of a shared plan.
     */
    public function shared(string $token)
    {
        $plan = RaidPlan::where('share_token', $token)->first();

        if (!$plan) {
            return view('boss-planner.not-found');
        }
        $plan->load('static');

        $encounterMaps = config('wow_season.encounter_maps', []);
        $encounterBosses = config('wow_season.encounter_bosses', []);
        $raidInstances = config('wow_season.current_raid_instances', []);

        $bossName = null;
        $bossData = $encounterBosses[$plan->encounter_slug] ?? [];
        foreach ($raidInstances as $bosses) {
            foreach ($bosses as $name) {
                if (\Illuminate\Support\Str::slug($name) === $plan->encounter_slug) {
                    $bossName = $name;
                    break 2;
                }
            }
        }

        $portraits = array_map(fn (int $id) => "/images/raidplan/portraits/{$id}.png", $bossData['portraits'] ?? []);

        return view('boss-planner.shared', [
            'plan' => $plan,
            'bossName' => $bossName ?? $plan->encounter_slug,
            'maps' => $encounterMaps[$plan->encounter_slug] ?? [],
            'portrait' => $portraits[0] ?? null,
            'staticName' => $plan->static->name ?? '',
        ]);
    }

    /**
     * Build a simplified roster payload for the player palette.
     */
    private function buildRosterPayload(StaticGroup $static): array
    {
        $members = $static->members()
            ->with(['characters' => function ($q) use ($static) {
                $q->whereHas('statics', fn ($sq) => $sq->where('statics.id', $static->id))
                    ->with([
                        'statics' => fn ($sq) => $sq->where('statics.id', $static->id),
                        'characterStaticSpecs' => fn ($sq) => $sq->where('static_id', $static->id)
                            ->where('is_main', true)
                            ->with('specialization'),
                    ]);
            }])
            ->get();

        $characters = [];
        $charsByMember = [];
        foreach ($members as $user) {
            $mainChar = $user->characters->first(
                fn ($c) => $c->statics->first()?->pivot->role === 'main'
            ) ?? $user->characters->first();
            if ($mainChar) {
                $charsByMember[$mainChar->id] = $mainChar;
            }
        }

        // Fetch all disabled CD overrides for these characters in one query.
        $disabledByChar = CharacterCooldownOverride::query()
            ->disabledForCharacters(array_keys($charsByMember))
            ->groupBy('character_id')
            ->map(fn ($rows) => $rows->pluck('spell_id')->map(fn ($id) => (int) $id)->values()->toArray());

        foreach ($charsByMember as $mainChar) {
            $spec = $mainChar->getMainSpecInStatic($static->id);
            $specSlug = null;
            if ($spec) {
                $specSlug = strtolower(str_replace(' ', '', $spec->class_name))
                    . '.' . strtolower(str_replace(' ', '', $spec->name));
            }
            $characters[] = [
                'id' => $mainChar->id,
                'name' => $mainChar->name,
                'playable_class' => $mainChar->playable_class,
                'avatar_url' => $mainChar->avatar_url,
                'assigned_role' => $mainChar->getCombatRoleInStatic($static->id),
                'spec_slug' => $specSlug,
                'spec_name' => $spec?->name,
                'disabled_cd_spell_ids' => $disabledByChar[$mainChar->id] ?? [],
            ];
        }

        return $characters;
    }

    /**
     * Toggle a single cooldown for a character (officer override).
     * enabled=false hides the CD from the player's draggable list.
     */
    public function toggleCharacterCooldown(\Illuminate\Http\Request $request, StaticGroup $static, \App\Models\Character $character): \Illuminate\Http\JsonResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('canManageSchedule', $static);

        $validated = $request->validate([
            'spell_id' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
        ]);

        if ($validated['enabled']) {
            CharacterCooldownOverride::query()
                ->where('character_id', $character->id)
                ->where('spell_id', $validated['spell_id'])
                ->delete();
        } else {
            CharacterCooldownOverride::query()->updateOrCreate(
                ['character_id' => $character->id, 'spell_id' => $validated['spell_id']],
                ['enabled' => false],
            );
        }

        return response()->json(['success' => true]);
    }
}
