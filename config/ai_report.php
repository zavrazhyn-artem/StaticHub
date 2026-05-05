<?php

/*
|--------------------------------------------------------------------------
| AI Report Configuration
|--------------------------------------------------------------------------
|
| Controls metadata captured with every AI tactical report so we can
| measure prompt-tuning impact via the feedback dashboard.
|
| `prompt_version`
|   Human-bumped semver-ish tag persisted onto every tactical_report at
|   generation time. Bump THIS value before each commit that touches:
|   - resources/prompts/gemini_main_report.txt
|   - resources/prompts/gemini_player_report.txt
|   - resources/spec-baselines/*.yaml (rotation thresholds)
|   - app/Services/Analysis/*.php (analyzer logic that changes payload shape)
|   - resources/combat-references/*.yaml (data the AI references)
|
|   Format: "v<integer>" (e.g. "v1", "v23", "v100"). Add a free-form
|   suffix for branches: "v23-strict-numbers".
|
| `change_log`
|   Optional running log of what each version changed — kept here for
|   commit/PR cross-reference. Newest first.
|
*/

return [
    'prompt_version' => env('AI_REPORT_PROMPT_VERSION', 'v2'),

    'change_log' => [
        'v2' => '2026-05-05 — Major pipeline overhaul. '
            . 'WCL fetch fixes: per-encounter chunked Deaths fetch (200-cap workaround), per-fight Cast snapshot pagination (multi-fight pagination silently truncated), new per-fight Healing event fetch. '
            . 'DeathAttributionBuilder Phase 2: new categories `defensive_predictable_miss` (boss_timeline match + CD ready) and `healer_lapse` (non-tank gradual death + zero healing in last 5s). '
            . 'CRITICAL fix: `foreach (... ?? [] as &$ref)` reference bug silently dropped ALL attribution writes for weeks; switched to index-based mutation — first regen with attribution actually persisted. '
            . 'Attribution priority reorganized: `defensive_unused` demoted from #4 to #8 (last resort, framed as supplementary) so AI prose explains WHY hit (positioning / coordination / mechanic) instead of "didn\'t use Ice Barrier" on every entry. New order: missed_external > missed_interrupt > missed_dispel > defensive_predictable_miss > soak_uncovered > raid_coordination > healer_lapse > defensive_unused > unavoidable > self_mechanic_miss. '
            . 'Positional context A+C: 6 new structured fields per death — `coord_distance_yards`, `position_cluster` (alone/with_friends/in_stack with 8y/15y thresholds), `position_nearby[]`, `movement_pattern` (outward/inward/lateral/static over [death-6s, death]), `movement_summary`. Cast-snapshot-derived death position (WCL events(Deaths) lacks x/y). '
            . 'Empirical baselines: RotationAnalyzer now emits `cpm_comparison` rows for empirical-only abilities (sample≥5, no rotation_check entry) — fixes Devourer DH where icy-veins and empirical sets had ZERO ability-id overlap. New row carries `threshold_source: "wcl-empirical-only"` + `actual_cpm` vs `empirical_cpm_p95` / `_median`. '
            . 'Prompt rules: `cause` MUST be in raid_leader.locale, 12-30 words, explain mechanic + WHY hit (positioning / soak miss / movement-into-it), NEVER lead with defensive CD or expose internal tag values (`normal` / `mechanic_oneshot` / `wipe_called` literally). Forbidden openings: "Не використав…" / "Didn\'t use…" / "Missed mechanic". '
            . 'Class-spec gating HARD rule: every spell cited for a player MUST belong to their class+spec (no Monk Life Cocoon advice for Resto Druid; anchor list of class-locked spells in both prompts). '
            . 'AiDeathSuppressor: cascade-collapse REMOVED (keep first N chronologically even if all in cascade window — wipe-call deaths still mark WHY); cutoff range 1-5 (was 3-10), default 3 (was 5). '
            . 'BlockSchema: new `pull_breakdown` + `recurring_failures` block types; key_deaths[] allowed fields extended with distance_yards / cluster / nearby / movement. '
            . 'UI: PullBreakdown.vue badges with material icons + tooltips for cluster/movement; cause narrative on its own line; SettingsLogs.vue tone selector + cutoff slider 1-5. '
            . 'Localization: pulls.cluster.* / pulls.movement.* + tone selector strings translated UA+EN.',
        'v1' => '2026-04-26 — Baseline after feedback loop introduction. '
            . 'Includes: per-fight references, boss timeline citations, anti-hallucination clauses, '
            . 'Active Mitigation required for tanks, severity capping in RotationAnalyzer, '
            . 'death_tag_distribution in payload, debuff_stacks payload exposure.',
    ],
];
