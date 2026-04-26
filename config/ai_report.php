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
    'prompt_version' => env('AI_REPORT_PROMPT_VERSION', 'v1'),

    'change_log' => [
        'v1' => '2026-04-26 — Baseline after feedback loop introduction. '
            . 'Includes: per-fight references, boss timeline citations, anti-hallucination clauses, '
            . 'Active Mitigation required for tanks, severity capping in RotationAnalyzer, '
            . 'death_tag_distribution in payload, debuff_stacks payload exposure.',
    ],
];
