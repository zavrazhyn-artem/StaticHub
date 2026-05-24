# AI Tester — Design Spec

**Date:** 2026-05-24
**Status:** Approved (brainstorming complete, pre-implementation)
**Author:** Claude (head tester) + Artem

## Goal

Make Claude the project's "head tester." Spin up an authenticated, automated testing
flow that drives the **real local app** in a browser via Playwright — first piloted by
Claude directly (MCP Playwright), later scaled to a fleet of cheaper-model subagents
running their own headless browsers in parallel. Agents navigate the app like real
testers, exercise flows, cross-check our synced data against live source sites, and
produce a findings report **plus improvement proposals**. The whole flow is packaged
to be **repeatable** so we test this way regularly.

The hard constraint: this must be **impossible to enable in production**, gated entirely
by an env secret.

## Decisions (locked)

1. **Test data source** — Local dev DB already holds the real 22-person static + characters. Use as-is; re-sync runs via the app token. No seeder needed.
2. **Login identity** — Gate logs in as **any** member by `?as=<user_id|battletag>` (default: static owner). Lets different agents test different `access_role` perspectives. Safe because local + env-gated.
3. **Harness model** — **Phased.** Phase A: Claude pilots one browser via MCP Playwright. Phase B: `@playwright/test` + cheaper-model subagent fleet in parallel.
4. **Interaction scope** — **Full interaction, no side-effect guards.** Discord is the user's personal channel and may fire. Mail is `log` locally.
5. **Reporting** — Inline narration + structured markdown artifact per run. User triages what graduates to a ticket.
6. **AI billing guard** — Paid Gemini calls are **stubbed** when `ai_tester` session is active (UI flow testable, no spend).
7. **Cross-source validation** — Compare against **all** sources (Blizzard Armory, Raider.io, Warcraft Logs), strict. Flag every delta, but **classify severity** so timing-drift noise is separated from real bugs.

## §1 — Auth gate (security-critical)

A single endpoint that establishes an authenticated session **without** Battle.net.

- **Config (not raw `env()`):** add `config/ai_access.php`:
  ```php
  return ['key' => env('AI_ACCESS')];
  ```
  Reason: `env()` returns `null` once `config:cache` runs; a config file is the only
  cache-safe way to read it.

- **Conditional route registration** in `routes/web.php`:
  ```php
  if (filled(config('ai_access.key')) && ! app()->isProduction()) {
      Route::get('/auth/ai-tester', [AiTesterAuthController::class, 'login'])
          ->name('ai-tester.login');
  }
  ```
  No key in `.env` → route never registers → **404** (we don't even reveal it exists,
  hence 404 not 403). The `isProduction()` check is belt-and-suspenders.

- **Controller** `app/Http/Controllers/Auth/AiTesterAuthController.php`:
  1. Re-assert `! app()->isProduction()` (abort 404 otherwise).
  2. Read secret from `X-AI-Access` header (preferred) or `?key=` query param.
  3. Verify with `hash_equals(config('ai_access.key'), $provided)` (timing-safe). Mismatch → abort 404.
  4. Resolve target user from `?as=` (numeric → `user_id`, else → `battletag`); default to the static owner.
  5. `Auth::login($user)`.
  6. Set session marker `session(['ai_tester' => true])` — consumed by §3b billing guard and useful for tagging activity.
  7. Redirect to `?redirect=` (validated, same-host only) or the dashboard.

- **Security layers (defense in depth):** (a) route only exists when key set + non-prod; (b) controller re-checks non-prod; (c) timing-safe secret comparison; (d) `.env.example` ships `AI_ACCESS=` **blank** with an explanatory comment; never commit a real value.

## §2 — Test data & sync

- The local 22-person static + its characters are used as-is.
- **Re-sync of existing characters' profile data** (equipment, specs, raids, M+, vault, avatar) runs on the **app client-credentials token** (`BlizzardAuthService::getAccessToken()` → `FetchBnetRawDataJob` → `BnetSyncService`), keyed by `region + realm + name`. No per-user OAuth token required.
- Because local `QUEUE_CONNECTION=sync`, a triggered re-sync executes **inline**, so an agent can re-sync and immediately verify the result in the same pass.
- **Out of scope:** character *discovery* (`/profile/user/wow` — listing a bnet account's characters) is the one genuinely OAuth-bound step and cannot be exercised through the gate. Documented and expected.

## §2b — Cross-source data validation

After a fresh re-sync, agents open the live public pages via Playwright and compare
extracted fields against what our app displays:

- **Sources:** Blizzard Armory, Raider.io, Warcraft Logs.
- **Timing:** run the comparison **immediately after re-sync** to minimise skew.
- **Delta classification (severity):**
  - `structural` — class, race, active spec, equipped items, ilvl mismatch → **bug**.
  - `volatile` — M+ rating, raid progress, lastModified timestamps → flagged as **info** (expected to drift between our snapshot and the live site).
- Every delta is recorded (per the "strict" decision); severity is what makes the report actionable.
- External sites change layout over time — extraction selectors are treated as
  maintenance-prone and isolated in the auditor helper, not scattered.

## §3 — Phase A harness (Claude + MCP Playwright, no new deps)

1. **Page inventory:** parse `php artisan route:list --json`, keep GET routes inside the
   authed middleware group, expand route params (`{static}`→real slug, `{character}`→real
   id, `{event}`→a real event id) into a concrete URL list.
2. **Login once** by navigating to the gate URL (`?as=…&key=…`), then sweep pages.
3. **Per-page health checks:** HTTP status (no 4xx/5xx), JS console errors, failed
   (non-2xx) network requests, render sanity (not blank / not an error boundary),
   screenshot, and Claude's reasoning over the snapshot (empty states where data should
   exist, broken layout).
4. **Key interactive flows:** open modals, switch tabs, run create/RSVP/roster edits,
   verify outcomes. **Full interaction allowed; Discord may fire (personal channel).**
5. Findings recorded as they surface.

## §3b — AI billing guard (the one hard rule)

When `session('ai_tester') === true`, the paid Gemini analysis path is intercepted and
returns a **canned stub response** instead of calling the API. Effect: the AI-report UI
flow (button → loader → rendered report) is fully testable, but **no paid call happens**.
Raidbots integration is **deferred** until simc-string export exists; not in Phase A.

## §4 — Phase B harness (parallel fleet)

- Add `@playwright/test` as a devDependency.
- **Global setup** hits the gate once and saves an authenticated `storageState` (cookies)
  reused by every worker.
- **Auditor helper:** `login(as)` and `visitAndAudit(url) → { status, consoleErrors, networkErrors, screenshotPath }`. External-site extraction selectors live here.
- **Dispatch:** Claude (head tester, Opus) shards the page inventory and dispatches N
  cheaper-model subagents (Sonnet/Haiku). Each runs the auditor over its slice via Bash in
  its **own headless browser process** (true parallelism), reasons over results, writes a
  partial findings file. Claude aggregates the partials into the run report.

## §5 — Reporting + improvement proposals

- **Inline:** Claude narrates findings to the user during the run.
- **Artifact per run:** `docs/ai-testing/reports/YYYY-MM-DD-HHMM-run.md`, with a table:
  `page | severity | symptom | repro | screenshot | console/network errors`.
  Screenshots under `docs/ai-testing/screenshots/<run>/`.
- **Improvement proposals section (required):** at the end of each report, a prioritised
  list of UX / quality / feature improvement suggestions — not just bugs. This is an
  explicit deliverable, not optional.
- User decides what graduates to a ticket.

## §6 — Repeatability

Package the flow as a **project skill** (e.g. `/ai-test`) so a run can be kicked off
without re-deriving setup, plus a **memory entry** capturing the canonical flow, env
keys, and gotchas. Memory is saved once the spec is finalised.

## Build order

1. §1 gate (+ `config/ai_access.php`, `.env.example` entry).
2. §3b billing-guard stub.
3. §3 Phase A loop, validated end-to-end on a handful of pages.
4. §2b cross-source validation.
5. §4 fleet.
6. §6 skill + memory.

## Out of scope

- Character discovery (OAuth-bound).
- Raidbots (until simc export exists).
- Any path that incurs a paid Gemini call.
- Production enablement of any kind.

## Risks / notes

- **External-site fragility:** Armory/RIO/WCL DOM changes will break extraction; isolate selectors in the auditor helper and treat breakage as expected maintenance.
- **Strict cross-check noise:** mitigated by post-resync timing + severity classification, not by loosening the comparison.
- **Gate secret hygiene:** `AI_ACCESS` must never land in `.env.example` with a value or in committed config.
- **Local env assumption:** Discord firing is intentional (personal channel); if local webhooks are ever repointed at a shared channel, mutating runs would notify it.
