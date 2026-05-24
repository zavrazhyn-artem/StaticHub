# AI Tester — Phase A Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a local-only, env-secret-gated login bypass plus a stubbed Gemini billing guard, then package a repeatable Claude-piloted (MCP Playwright) testing flow that sweeps every authed page, cross-checks our synced data against live source sites, and reports bugs + improvement proposals.

**Architecture:** A new `GET /auth/ai-tester` route — registered only when `AI_ACCESS` is set and we are not in production — calls `AiTesterAuthController`, which delegates secret verification and user resolution to `AiTesterAuthService` (queries via `UserBuilder`/`StaticGroupBuilder`), then `Auth::login()`s and marks the session `ai_tester=true`. That session flag makes `GeminiService` short-circuit all 7 paid Gemini POST paths with a canned stub. The "harness" itself is Claude's runtime behaviour, codified as the `.claude/skills/ai-test` project skill, with results written under `docs/ai-testing/`.

**Tech Stack:** Laravel 11 (PHPUnit Feature tests, `Http::fake`), the existing Controller→Service→Builder layering, MCP Playwright (Phase A driving), Claude Code project skill + persistent memory.

**Spec:** `docs/superpowers/specs/2026-05-24-ai-tester-design.md` (this plan covers Phase A; Phase B — the parallel `@playwright/test` fleet — is a follow-up plan.)

---

## File Structure

| File | Responsibility |
|---|---|
| `config/ai_access.php` (create) | Holds the `AI_ACCESS` secret as cache-safe config. |
| `.env.example` (modify) | Documents the blank `AI_ACCESS` key. |
| `phpunit.xml` (modify) | Sets a known `AI_ACCESS` so the gate route registers under test. |
| `app/Builders/UserBuilder.php` (modify) | `findById`, `findByBattletag` lookups. |
| `app/Builders/StaticGroupBuilder.php` (modify) | `firstWithOwner` lookup for the default identity. |
| `app/Services/Auth/AiTesterAuthService.php` (create) | Secret verification + identity resolution (business logic). |
| `app/Http/Controllers/Auth/AiTesterAuthController.php` (create) | HTTP entry: guard, login, session marker, redirect. |
| `routes/web.php` (modify) | Conditional gate route registration. |
| `app/Services/Analysis/GeminiService.php` (modify) | Billing guard on all 7 paid POST methods. |
| `database/factories/StaticGroupFactory.php` (create) | Test fixture for statics. |
| `tests/Feature/Auth/AiTesterAuthServiceTest.php` (create) | Service unit-ish coverage. |
| `tests/Feature/Auth/AiTesterGateTest.php` (create) | Gate route behaviour. |
| `tests/Feature/Analysis/GeminiBillingGuardTest.php` (create) | Proves no paid call under `ai_tester`. |
| `docs/ai-testing/` (create) | Run reports, screenshots, report template. |
| `.claude/skills/ai-test/SKILL.md` (create) | The repeatable testing procedure. |
| `~/.claude/projects/-var-www-html/memory/feedback_ai_tester_flow.md` (create) | Canonical flow in persistent memory. |

---

## Task 1: AI-access config + env placeholders

**Files:**
- Create: `config/ai_access.php`
- Modify: `.env.example` (after the `GHOST_USER_ID=` line)
- Modify: `phpunit.xml` (inside the `<php>` `<env>` block, ~line 21-35)

- [ ] **Step 1: Create the config file**

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Tester Access Gate
    |--------------------------------------------------------------------------
    |
    | Secret that unlocks the AI-tester login gate (GET /auth/ai-tester),
    | which establishes an authenticated session WITHOUT Battle.net OAuth so
    | automated testing agents can drive the app locally. When this key is
    | empty the gate route is never registered (404), and the gate is hard-
    | blocked in production regardless. NEVER set this in production.
    |
    */

    'key' => env('AI_ACCESS'),
];
```

- [ ] **Step 2: Add the blank key to `.env.example`**

Add directly below the existing `GHOST_USER_ID=` line:

```
# AI Tester gate — local-only automated-testing login bypass.
# Leave BLANK everywhere except local dev. When empty the gate route does
# not exist; it is also hard-blocked in production. See config/ai_access.php.
AI_ACCESS=
```

- [ ] **Step 3: Add a known key to `phpunit.xml`**

Add inside the `<php>` block alongside the other `<env>` entries:

```xml
<env name="AI_ACCESS" value="ai-tester-secret-test"/>
```

- [ ] **Step 4: Verify config resolves**

Run: `php artisan config:clear && php artisan tinker --execute="echo config('ai_access.key') === null ? 'NULL' : 'SET';"`
Expected: `NULL` (local `.env` has no value yet — correct; gate is off until you opt in).

- [ ] **Step 5: Enable the gate in your local `.env` (not committed)**

Append to the local `/var/www/html/.env` (this file is gitignored — do NOT commit):

```
AI_ACCESS=local-ai-tester-<pick-a-random-string>
```

Run: `php artisan config:clear`

- [ ] **Step 6: Commit**

```bash
git add config/ai_access.php .env.example phpunit.xml
git commit -m "feat(ai-tester): add AI_ACCESS gate config + env placeholders"
```

---

## Task 2: Identity-resolution service + builder lookups

**Files:**
- Modify: `app/Builders/UserBuilder.php`
- Modify: `app/Builders/StaticGroupBuilder.php`
- Create: `app/Services/Auth/AiTesterAuthService.php`
- Create: `database/factories/StaticGroupFactory.php`
- Test: `tests/Feature/Auth/AiTesterAuthServiceTest.php`

- [ ] **Step 1: Create a minimal StaticGroup factory (test fixture)**

The `statics` table requires `name`, `slug`, `server`, `owner_id` (region defaults to `eu`). Create `database/factories/StaticGroupFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaticGroupFactory extends Factory
{
    protected $model = StaticGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(),
            'region' => 'eu',
            'server' => fake()->word(),
            'owner_id' => User::factory(),
        ];
    }
}
```

If `StaticGroup` does not already `use HasFactory`, add the trait import `use Illuminate\Database\Eloquent\Factories\HasFactory;` and `use HasFactory;` to `app/Models/StaticGroup.php`.

- [ ] **Step 2: Write the failing service test**

Create `tests/Feature/Auth/AiTesterAuthServiceTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\StaticGroup;
use App\Models\User;
use App\Services\Auth\AiTesterAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTesterAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AiTesterAuthService
    {
        return app(AiTesterAuthService::class);
    }

    public function test_verify_key_matches_configured_secret(): void
    {
        config(['ai_access.key' => 'ai-tester-secret-test']);
        $this->assertTrue($this->service()->verifyKey('ai-tester-secret-test'));
        $this->assertFalse($this->service()->verifyKey('wrong'));
        $this->assertFalse($this->service()->verifyKey(null));
    }

    public function test_verify_key_is_false_when_unconfigured(): void
    {
        config(['ai_access.key' => '']);
        $this->assertFalse($this->service()->verifyKey(''));
        $this->assertFalse($this->service()->verifyKey('anything'));
    }

    public function test_resolves_user_by_id_and_battletag(): void
    {
        $user = User::factory()->create(['battletag' => 'Zavrikk#2956']);
        $this->assertEquals($user->id, $this->service()->resolveUser((string) $user->id)?->id);
        $this->assertEquals($user->id, $this->service()->resolveUser('Zavrikk#2956')?->id);
        $this->assertNull($this->service()->resolveUser('Nope#0000'));
    }

    public function test_defaults_to_first_static_owner_when_no_identity(): void
    {
        $owner = User::factory()->create();
        StaticGroup::factory()->create(['owner_id' => $owner->id]);
        $this->assertEquals($owner->id, $this->service()->resolveUser(null)?->id);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Auth/AiTesterAuthServiceTest.php`
Expected: FAIL — `Class "App\Services\Auth\AiTesterAuthService" not found`.

- [ ] **Step 4: Add the builder lookups**

In `app/Builders/UserBuilder.php`, add:

```php
public function findById(int $id): ?\App\Models\User
{
    return $this->where('id', $id)->first();
}

public function findByBattletag(string $battletag): ?\App\Models\User
{
    return $this->where('battletag', $battletag)->first();
}
```

In `app/Builders/StaticGroupBuilder.php`, add (the class already imports `StaticGroup`):

```php
public function firstWithOwner(): ?StaticGroup
{
    return $this->with('owner')->first();
}
```

- [ ] **Step 5: Create the service**

Create `app/Services/Auth/AiTesterAuthService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\StaticGroup;
use App\Models\User;

class AiTesterAuthService
{
    /**
     * Timing-safe check of the provided secret against the configured key.
     * Returns false when the gate is unconfigured (empty key) so an empty
     * config can never be matched.
     */
    public function verifyKey(?string $provided): bool
    {
        $expected = (string) config('ai_access.key');

        if ($expected === '' || $provided === null) {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    /**
     * Resolve the user the gate should authenticate as.
     * Numeric $as -> user id; non-numeric $as -> battletag; null/empty ->
     * owner of the first static group. Null when no match is found.
     */
    public function resolveUser(?string $as): ?User
    {
        if ($as === null || $as === '') {
            return StaticGroup::query()->firstWithOwner()?->owner;
        }

        if (ctype_digit($as)) {
            return User::query()->findById((int) $as);
        }

        return User::query()->findByBattletag($as);
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Auth/AiTesterAuthServiceTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Builders/UserBuilder.php app/Builders/StaticGroupBuilder.php app/Services/Auth/AiTesterAuthService.php database/factories/StaticGroupFactory.php app/Models/StaticGroup.php tests/Feature/Auth/AiTesterAuthServiceTest.php
git commit -m "feat(ai-tester): identity-resolution service + builder lookups"
```

---

## Task 3: Gate controller + conditional route

**Files:**
- Create: `app/Http/Controllers/Auth/AiTesterAuthController.php`
- Modify: `routes/web.php` (add import near line 29; add route after the Battle.net routes ~line 250)
- Test: `tests/Feature/Auth/AiTesterGateTest.php`

- [ ] **Step 1: Write the failing gate test**

Create `tests/Feature/Auth/AiTesterGateTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\StaticGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiTesterGateTest extends TestCase
{
    use RefreshDatabase;

    // phpunit.xml sets AI_ACCESS=ai-tester-secret-test, so the route registers.
    private const KEY = 'ai-tester-secret-test';

    public function test_logs_in_target_user_by_id_and_marks_session(): void
    {
        $user = User::factory()->create();

        $response = $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('ai_tester', true);
        $this->assertAuthenticatedAs($user);
    }

    public function test_resolves_user_by_battletag(): void
    {
        $user = User::factory()->create(['battletag' => 'Zavrikk#2956']);

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.urlencode('Zavrikk#2956'))
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_defaults_to_first_static_owner(): void
    {
        $owner = User::factory()->create();
        StaticGroup::factory()->create(['owner_id' => $owner->id]);

        $this->get('/auth/ai-tester?key='.self::KEY)->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
    }

    public function test_honours_relative_redirect_only(): void
    {
        $user = User::factory()->create();

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id.'&redirect=/roster')
            ->assertRedirect('/roster');

        $this->get('/auth/ai-tester?key='.self::KEY.'&as='.$user->id.'&redirect=https://evil.test')
            ->assertRedirect(route('dashboard'));
    }

    public function test_404_when_key_wrong(): void
    {
        User::factory()->create();
        $this->get('/auth/ai-tester?key=wrong')->assertNotFound();
        $this->assertGuest();
    }

    public function test_404_when_key_unconfigured(): void
    {
        config(['ai_access.key' => '']);
        $this->get('/auth/ai-tester?key='.self::KEY)->assertNotFound();
        $this->assertGuest();
    }

    public function test_404_in_production_even_with_key(): void
    {
        $this->app['env'] = 'production';
        User::factory()->create();
        $this->get('/auth/ai-tester?key='.self::KEY)->assertNotFound();
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Auth/AiTesterGateTest.php`
Expected: FAIL — route `/auth/ai-tester` returns 404 for all (controller/route not created yet).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Auth/AiTesterAuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AiTesterAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiTesterAuthController extends Controller
{
    public function __construct(private readonly AiTesterAuthService $service) {}

    public function login(Request $request): RedirectResponse
    {
        // Defense in depth: never in production, never without a configured key.
        abort_if(app()->isProduction(), 404);
        abort_unless(filled(config('ai_access.key')), 404);

        $provided = $request->header('X-AI-Access') ?? $request->query('key');
        abort_unless(
            $this->service->verifyKey(is_string($provided) ? $provided : null),
            404
        );

        $as = $request->query('as');
        $user = $this->service->resolveUser(is_string($as) ? $as : null);
        abort_if($user === null, 404);

        Auth::login($user);
        $request->session()->put('ai_tester', true);

        $redirect = $request->query('redirect');
        if (is_string($redirect) && str_starts_with($redirect, '/')) {
            return redirect($redirect);
        }

        return redirect()->route('dashboard');
    }
}
```

- [ ] **Step 4: Register the conditional route**

In `routes/web.php`, add the import next to the existing `use App\Http\Controllers\Auth\BattleNetController;` (line 29):

```php
use App\Http\Controllers\Auth\AiTesterAuthController;
```

Then add immediately after the Battle.net OAuth routes (after the `battlenet.callback` route, ~line 250):

```php
// AI Tester gate — local-only automated-testing login bypass. Registered
// ONLY when AI_ACCESS is set and we're not in production; the controller
// re-checks both as defense in depth. See config/ai_access.php.
if (filled(config('ai_access.key')) && ! app()->isProduction()) {
    Route::get('/auth/ai-tester', [AiTesterAuthController::class, 'login'])
        ->name('ai-tester.login');
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Auth/AiTesterGateTest.php`
Expected: PASS (7 tests). If `test_404_when_key_unconfigured` fails because the route was not registered, confirm `phpunit.xml` has the `AI_ACCESS` env from Task 1 (the route must exist for the controller guard to be exercised).

- [ ] **Step 6: Confirm the route only exists when configured**

Run: `php artisan route:list --name=ai-tester`
Expected: lists `GET /auth/ai-tester` (local `.env` has `AI_ACCESS` set from Task 1 Step 5). With `AI_ACCESS` blank it should list nothing.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Auth/AiTesterAuthController.php routes/web.php tests/Feature/Auth/AiTesterGateTest.php
git commit -m "feat(ai-tester): env-gated /auth/ai-tester login bypass (404 in prod/unconfigured)"
```

---

## Task 4: Gemini billing guard (the one hard rule)

`GeminiService` has 7 paid POST sites with no single chokepoint (`createCachedContext`:125, `executeWithCache`:218, `executeRequest`:291, `executeApiRequest`:332, `generateMainReportBlocks`:537, `generatePlayerReportBlocks`:811, `executeRequestWithModel`:1042). Guard each owning method at its top; higher-level methods delegate to these, so they are covered transitively. When `session('ai_tester') === true`, return a canned stub matching each method's return shape so the report/chat UI flow stays exercisable.

**Files:**
- Modify: `app/Services/Analysis/GeminiService.php`
- Test: `tests/Feature/Analysis/GeminiBillingGuardTest.php`

- [ ] **Step 1: Write the failing guard test**

Create `tests/Feature/Analysis/GeminiBillingGuardTest.php`:

```php
<?php

namespace Tests\Feature\Analysis;

use App\Services\Analysis\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiBillingGuardTest extends TestCase
{
    private function service(): GeminiService
    {
        return app(GeminiService::class);
    }

    public function test_no_paid_gemini_call_is_made_when_ai_tester_active(): void
    {
        Http::fake();                       // any real call would be recorded
        session()->put('ai_tester', true);

        $svc = $this->service();
        $url = $svc->buildModelUrl('gemini-2.5-flash');

        $this->assertStringContainsString('AI TESTER STUB', $svc->executeWithCache('cachedContents/x', 'p', $url));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeRequest('p'));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeApiRequest(['contents' => []]));
        $this->assertStringContainsString('AI TESTER STUB', $svc->executeRequestWithModel('p', $url));

        $main = $svc->generateMainReportBlocks('ctx');
        $this->assertArrayHasKey('title', $main);
        $this->assertArrayHasKey('main', $main);

        $players = $svc->generatePlayerReportBlocks('ctx', ['Zavrikk']);
        $this->assertSame('ok', $players['Zavrikk']['status']);

        $this->assertNotNull($svc->createCachedContext('ctx', 'gemini-2.5-flash'));

        Http::assertNothingSent();
    }

    public function test_calls_gemini_when_not_ai_tester(): void
    {
        Http::fake(['*' => Http::response(
            ['candidates' => [['content' => ['parts' => [['text' => '{"blocks":[]}']]]]]],
            200
        )]);

        $svc = $this->service();
        $svc->executeRequest('hello');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'generativelanguage.googleapis.com'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Analysis/GeminiBillingGuardTest.php`
Expected: FAIL — `test_no_paid_gemini_call_is_made_when_ai_tester_active` fails on `Http::assertNothingSent()` (or stub assertions), because guards don't exist yet.

- [ ] **Step 3: Add the guard helpers**

In `app/Services/Analysis/GeminiService.php`, add two private methods (place them near the other private helpers):

```php
private function aiTesterActive(): bool
{
    return session()->get('ai_tester') === true;
}

private function aiTesterStubText(): string
{
    return '{"blocks":[{"type":"paragraph","text":"[AI TESTER STUB] No paid Gemini call was made."}]}';
}
```

- [ ] **Step 4: Guard each paid method at its top**

Add as the FIRST statement inside each method body:

`createCachedContext()` (line ~102):
```php
if ($this->aiTesterActive()) {
    return ['cache_id' => 'cachedContents/ai-tester-stub', 'expires_at' => now()->addSeconds($ttlSeconds)->toIso8601String()];
}
```

`deleteCachedContext()` (line ~54) — keep the stubbed cache from triggering a stray DELETE:
```php
if ($this->aiTesterActive()) {
    return true;
}
```

`executeWithCache()`, `executeRequest()`, `executeApiRequest()`, `executeRequestWithModel()` — each returns `string`:
```php
if ($this->aiTesterActive()) {
    return $this->aiTesterStubText();
}
```

`generateMainReportBlocks()` (line ~487) — returns `array`:
```php
if ($this->aiTesterActive()) {
    return ['title' => '[AI TESTER STUB] Raid Analysis', 'main' => [['type' => 'paragraph', 'text' => 'Stubbed — no paid Gemini call.']]];
}
```

`generatePlayerReportBlocks()` (line ~667) — returns `array` keyed by player name:
```php
if ($this->aiTesterActive()) {
    $stub = [];
    foreach ($playerNames as $name) {
        $stub[$name] = ['status' => 'ok', 'blocks' => [['type' => 'paragraph', 'text' => '[AI TESTER STUB]']]];
    }
    return $stub;
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Analysis/GeminiBillingGuardTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Confirm nothing else broke**

Run: `php artisan test --filter=Analysis`
Expected: PASS (no regressions in existing analysis tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Analysis/GeminiService.php tests/Feature/Analysis/GeminiBillingGuardTest.php
git commit -m "feat(ai-tester): stub all paid Gemini paths when ai_tester session active"
```

---

## Task 5: Reporting scaffolding

**Files:**
- Create: `docs/ai-testing/.gitignore`
- Create: `docs/ai-testing/reports/.gitkeep`
- Create: `docs/ai-testing/REPORT_TEMPLATE.md`

- [ ] **Step 1: Ignore screenshots, keep reports**

Create `docs/ai-testing/.gitignore`:

```
screenshots/
```

Create empty `docs/ai-testing/reports/.gitkeep`.

- [ ] **Step 2: Create the run-report template**

Create `docs/ai-testing/REPORT_TEMPLATE.md`:

```markdown
# AI Tester Run — <YYYY-MM-DD HH:MM>

**Identity:** <battletag / user_id used via the gate>
**Scope:** <pages swept / flows exercised>
**App URL:** <https://local.blastr.pro | http://localhost>

## Findings

| Page / Flow | Severity | Symptom | Repro | Screenshot | Console / Network errors |
|---|---|---|---|---|---|
| | blocker / high / med / low / info | | | | |

## Cross-source data validation

| Character | Field | Our value | Live source | Source | Delta class |
|---|---|---|---|---|---|
| | | | | Armory / RIO / WCL | structural (bug) / volatile (info) |

## Improvement proposals

1. **<title>** — <what + why + rough effort>. Priority: P1/P2/P3.
```

- [ ] **Step 3: Commit**

```bash
git add docs/ai-testing/.gitignore docs/ai-testing/reports/.gitkeep docs/ai-testing/REPORT_TEMPLATE.md
git commit -m "feat(ai-tester): reporting scaffolding (template + dirs)"
```

---

## Task 6: The `/ai-test` project skill

Author the repeatable procedure as a Claude Code project skill. **Use the `superpowers:writing-skills` skill** to refine structure/frontmatter before finalising.

**Files:**
- Create: `.claude/skills/ai-test/SKILL.md`

- [ ] **Step 1: Write the skill**

Create `.claude/skills/ai-test/SKILL.md`:

```markdown
---
name: ai-test
description: Use when running the AI tester flow on the local app — drives the browser via MCP Playwright as a logged-in member through the env-gated /auth/ai-tester bypass, sweeps every authed page for errors, cross-checks our synced data against live Armory/RIO/WCL, and writes a findings + improvement-proposals report. Phase A (Claude-piloted, single browser).
---

# AI Tester (Phase A — Claude-piloted)

You are the head tester. Drive the REAL local app in a browser and report what's broken plus how to improve it. NEVER trigger paid AI (Gemini) report generation — the `ai_tester` session flag stubs it automatically, but do not rely on that as an excuse to spam it.

## Pre-run

1. Confirm the app is up: `curl -sk https://local.blastr.pro/ping` (or `http://localhost/ping`) returns `{"message":"ok"}`. If not, the user starts it with `composer run dev` or `docker compose up -d`.
2. Confirm the gate is enabled: `php artisan route:list --name=ai-tester` lists the route. If empty, `AI_ACCESS` is unset in local `.env` — ask the user to set it (see config/ai_access.php) and `php artisan config:clear`.
3. Read `AI_ACCESS` from local `.env` (it is the `?key=` value). Discord/webhooks firing locally is acceptable (user's personal channel).

## Log in via the gate

Navigate the MCP Playwright browser to:
`<APP_URL>/auth/ai-tester?key=<AI_ACCESS>&as=<user_id-or-battletag>`

Pick `as` per the perspective you want to test (owner vs officer vs member). Default (no `as`) logs in as the first static's owner. A 302 → /dashboard means success.

## Build the page inventory

Run `php artisan route:list --json`. Keep GET routes whose `middleware` includes `auth` AND `ensure_has_static` (the main authed group). Expand path params from real local data:
- `{static}` → the static slug (from the roster/url after login)
- `{character}` → a real character id (Characters page)
- `{event}` / `{raidPlan}` → a real id (Schedule / Boss Planner)
- `{report}` → a real report id (Logs)
- `{user}`, `{list}`, `{wishlist}`, `{config}`, `{transaction}` → real ids from the relevant page
Skip POST/PATCH/DELETE for the read-only sweep; exercise those in the interactive-flow pass.

## Per-page health sweep

For each URL: navigate, then collect via MCP Playwright —
- HTTP status (no 4xx/5xx),
- `browser_console_messages` (flag errors/warnings),
- `browser_network_requests` (flag non-2xx XHR/fetch),
- render sanity (not blank, not an error boundary),
- `browser_take_screenshot` into `docs/ai-testing/screenshots/<run>/`,
- your own read of the page: empty states where data should exist, broken layout, untranslated strings.

## Interactive flows (full interaction allowed)

Exercise the key journeys end-to-end: create/edit/delete an event, RSVP, edit roster access-role, a treasury transaction, trigger a character re-sync (verify it completes inline since local queue is sync), open the AI-report UI (confirm the stub renders — NO paid call). Discord side effects are fine.

## Cross-source data validation (strict + classified)

For a sample of characters, after a fresh re-sync: open the live Blizzard Armory, Raider.io, and Warcraft Logs pages via Playwright, extract key fields, and compare to our UI. Flag EVERY delta, classified:
- `structural` (class, race, active spec, equipped items, ilvl mismatch) → BUG severity.
- `volatile` (M+ rating, raid progress, lastModified) → INFO (expected drift).

## Report

Copy `docs/ai-testing/REPORT_TEMPLATE.md` to `docs/ai-testing/reports/<YYYY-MM-DD-HHMM>-run.md`, fill the findings table, the cross-source table, and — required — the improvement-proposals section (UX/quality/feature ideas, prioritised). Narrate findings inline to the user as you go.

## Phase B (not yet)

Parallel `@playwright/test` subagent fleet is a separate plan; do not attempt it here.
```

- [ ] **Step 2: Verify the skill is discoverable**

Run: `php artisan route:list --name=ai-tester` is unrelated; instead confirm the file exists and frontmatter is valid YAML:
Run: `head -5 .claude/skills/ai-test/SKILL.md`
Expected: shows the `---` frontmatter with `name: ai-test`.

- [ ] **Step 3: Commit**

```bash
git add .claude/skills/ai-test/SKILL.md
git commit -m "feat(ai-tester): /ai-test project skill (Phase A procedure)"
```

---

## Task 7: Persist the flow to memory

**Files:**
- Create: `/home/zavrik/.claude/projects/-var-www-html/memory/feedback_ai_tester_flow.md`
- Modify: `/home/zavrik/.claude/projects/-var-www-html/memory/MEMORY.md` (add one index line)

- [ ] **Step 1: Write the memory file**

Create `feedback_ai_tester_flow.md`:

```markdown
---
name: feedback_ai_tester_flow
description: How to run the AI tester flow — env-gated login bypass + Claude-piloted Playwright sweep of the local app, regular cadence
metadata:
  type: feedback
---

We test the local app regularly by having Claude act as head tester. Flow lives in the `/ai-test` project skill (`.claude/skills/ai-test/SKILL.md`).

**Why:** real-browser coverage of every authed page + cross-source data validation, without Battle.net OAuth and without spending money on AI reports.

**How to apply:**
- Gate: `GET /auth/ai-tester?key=<AI_ACCESS>&as=<user_id|battletag>` logs in as any member. Registered only when `AI_ACCESS` set in local `.env` and not production (404 otherwise). Config in `config/ai_access.php`.
- Data: local DB already holds the real 22-person static; re-sync of existing chars runs via app token inline (local queue is sync). Character discovery is the only OAuth-bound step (out of scope).
- Hard rule: paid Gemini paths are stubbed when `session('ai_tester')===true` (guarded in `GeminiService` — all 7 POST methods). Discord may fire (personal channel) — that's fine.
- Cross-source: compare vs Armory/RIO/WCL strictly, classify structural (bug) vs volatile (info).
- Output: `docs/ai-testing/reports/<date>-run.md` + improvement proposals, not just bugs.
- Phase B (parallel @playwright/test subagent fleet) is a separate, not-yet-built plan.
```

- [ ] **Step 2: Add the index pointer to MEMORY.md**

Add this row to the table in `MEMORY.md`:

```
| [feedback_ai_tester_flow.md](./feedback_ai_tester_flow.md) | How to run the regular AI tester flow — env-gated login bypass + Claude-piloted Playwright sweep, Gemini stubbed |
```

- [ ] **Step 3: No commit** — the memory directory is outside the repo. Confirm both files saved.

---

## Task 8: Smoke-validate the flow end-to-end

This is verification, not new code. Per superpowers:verification-before-completion, run it and confirm output before claiming Phase A done.

- [ ] **Step 1: Start the app (if not running)**

Run: `docker compose up -d` (or `composer run dev`). Confirm: `curl -sk https://local.blastr.pro/ping` → `{"message":"ok"}`.

- [ ] **Step 2: Confirm the gate is live**

Run: `php artisan route:list --name=ai-tester`
Expected: `GET /auth/ai-tester` is listed. If not, set `AI_ACCESS` in local `.env`, `php artisan config:clear`.

- [ ] **Step 3: Invoke the skill on a tiny scope**

Invoke the `ai-test` skill. Drive MCP Playwright through the gate, then sweep just 3 pages (dashboard, roster, characters). Confirm: login redirects to dashboard, health checks run, screenshots land in `docs/ai-testing/screenshots/`, and a report file is written.

- [ ] **Step 4: Confirm the AI-report stub path**

Open the AI-report UI for one report; confirm it renders the `[AI TESTER STUB]` content and that no Gemini call was attempted (check `storage/logs` for absence of Gemini request logs / no errors).

- [ ] **Step 5: Run the full suite once**

Run: `php artisan test`
Expected: PASS, including the three new test files.

- [ ] **Step 6: Final commit (report artifact, if you want it tracked)**

```bash
git add docs/ai-testing/reports/
git commit -m "test(ai-tester): first Phase A smoke-validation run report"
```

---

## Notes / out of scope for Phase A

- **Phase B** — `@playwright/test` + global-setup `storageState` + parallel cheaper-model subagent fleet — is a follow-up plan, built on Tasks 3/6 here.
- **Raidbots / simc export** — deferred until simc-string export exists.
- **Character discovery** (`/profile/user/wow`) — OAuth-bound, cannot be exercised through the gate.
- The gate is local-only by construction; never set `AI_ACCESS` in any production-like environment.
