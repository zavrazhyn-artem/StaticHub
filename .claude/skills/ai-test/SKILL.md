---
name: ai-test
description: Use when running the AI tester flow on the local app — drives the browser via MCP Playwright as a logged-in member through the env-gated /auth/ai-tester bypass, sweeps every authed page for errors, cross-checks our synced data against live Armory/RIO/WCL, and writes a findings + improvement-proposals report. Phase A (Claude-piloted, single browser).
---

# AI Tester (Phase A — Claude-piloted)

You are the head tester. Drive the REAL local app in a browser and report what's broken plus how to improve it. The `ai_tester` session flag automatically stubs paid Gemini (AI report) generation, so the AI-report UI is testable without spending money — but everything else (including Discord webhooks to the user's personal channel) runs for real.

## Pre-run

1. Confirm the app is up: `curl -sk https://local.blastr.pro/ping` (or `http://localhost/ping`) returns `{"message":"ok"}`. If not, the user starts it (`composer run dev` or their docker stack).
2. Confirm the gate is enabled: `php artisan route:list --name=ai-tester` lists the route. If empty, `AI_ACCESS` is unset in local `.env` — ask the user to set it (see config/ai_access.php) and run `php artisan config:clear`.
3. Read `AI_ACCESS` from local `.env` (it is the `?key=` value).

## Log in via the gate

Navigate the MCP Playwright browser to:
`<APP_URL>/auth/ai-tester?key=<AI_ACCESS>&as=<user_id-or-battletag>`

Pick `as` for the perspective you want (owner vs officer vs member). Default (no `as`) logs in as the first static's owner. A 302 → /dashboard means success. To switch perspective mid-run, hit the gate again with a different `as`.

## Build the page inventory

Run `php artisan route:list --json`. Keep GET routes whose middleware includes `auth` AND `ensure_has_static` (the main authed group). Expand path params from real local data:
- `{static}` → the static slug
- `{character}` → a real character id (Characters page)
- `{event}` / `{raidPlan}` → a real id (Schedule / Boss Planner)
- `{report}` → a real report id (Logs)
- `{user}`, `{list}`, `{wishlist}`, `{config}`, `{transaction}` → real ids from the relevant page
Skip POST/PATCH/DELETE for the read-only sweep; exercise those in the interactive-flow pass.

## Per-page health sweep

First create the run's screenshot dir (browser_take_screenshot does NOT auto-create it and errors ENOENT otherwise): `mkdir -p docs/ai-testing/screenshots/<run>/`. Filenames passed to the tool resolve relative to the repo root.

For each URL: navigate, then collect via MCP Playwright —
- HTTP status (no 4xx/5xx),
- `browser_console_messages` (flag errors/warnings),
- `browser_network_requests` (flag non-2xx XHR/fetch),
- render sanity (not blank, not an error boundary),
- `browser_take_screenshot` into `docs/ai-testing/screenshots/<run>/`,
- your own read of the page: empty states where data should exist, broken layout, untranslated strings.

## Interactive flows (full interaction allowed)

Exercise key journeys end-to-end: create/edit/delete an event, RSVP, edit roster access-role, a treasury transaction, trigger a character re-sync (verify it completes inline since local queue is sync), open the AI-report UI (confirm the [AI TESTER STUB] content renders — NO paid call). Discord side effects are fine (personal channel).

## Cross-source data validation (strict + classified)

For a sample of characters, after a fresh re-sync: open the live Blizzard Armory, Raider.io, and Warcraft Logs pages via Playwright, extract key fields, and compare to our UI. Flag EVERY delta, classified:
- `structural` (class, race, active spec, equipped items, ilvl mismatch) → BUG severity.
- `volatile` (M+ rating, raid progress, lastModified) → INFO (expected drift).

## Report

Copy `docs/ai-testing/REPORT_TEMPLATE.md` to `docs/ai-testing/reports/<YYYY-MM-DD-HHMM>-run.md`, fill the findings table, the cross-source table, and — required — the improvement-proposals section (UX/quality/feature ideas, prioritised). Narrate findings inline to the user as you go.

## Phase B (not yet)

Parallel @playwright/test subagent fleet is a separate plan; do not attempt it here.
