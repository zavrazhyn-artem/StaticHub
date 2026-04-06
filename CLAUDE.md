# System Instructions

You are working on a Laravel 11 + Vue 3 hybrid application. Follow these rules strictly for every code change.

## Architecture: How Code Flows

Every request follows this exact chain. Never skip layers.

```
Controller → Service → Builder
```

- **Controller** receives the request, authorizes via Gate/Policy, calls a Service, returns a response. Zero business logic. Zero DB queries. Max 50 lines per method.
- **Service** contains ALL business logic. Inject other Services via constructor. Use Builders for any database interaction. Lives in `app/Services/{Domain}/`.
- **Builder** contains ALL database queries. Extends `Illuminate\Database\Eloquent\Builder`. Lives in `app/Builders/`. Wired to Models via `newEloquentBuilder()`.
- **Model** defines relationships, casts, fillable. No business logic. Simple accessors OK (e.g. `hasMember()`, `getMainCharacterForStatic()`).

## Where to Put Code

When writing PHP code, always place it in the correct layer:

| If you need to... | Put it in... | Never in... |
|---|---|---|
| Query the database (`::where`, `::find`, `::create`, `::update`) | Builder method | Service, Controller, Job, Helper |
| Execute business logic, orchestrate operations | Service method | Controller, Model, Job |
| Authorize a request | Controller via `Gate::authorize()` | Service |
| Format data for a Vue component | Service method returning array | Blade `@php` block |
| Validate input | FormRequest or `$request->validate()` in Controller | Service |
| Dispatch async work | Job (that calls a Service inside `handle()`) | Controller directly |

## Service Organization

Services live in domain subfolders. Never create a service in `app/Services/` root.

```
Services/
  Analysis/      — AI, Gemini, WCL, Raider.io, log analysis
  Auction/       — Auction house sync
  Auth/          — Users, Battle.net OAuth
  Blizzard/      — Blizzard API (BlizzardAuthService, BlizzardCharacterApiService, BlizzardGuildApiService, BlizzardGameDataApiService)
  Character/     — Character sync, raw data sync
  Discord/       — Bot API, webhooks, automations, interactions
  Raid/          — Events, RSVP, calendar, attendance, scheduling
  Realm/         — Realm sync
  Roster/        — Roster compilation split by concern (GearAuditService, InstanceDataService, VaultDataService, ProgressionDataService, CollectionDataService)
  StaticGroup/   — Static CRUD, roster management, treasury, settings, consumables, dashboard
```

When creating a new service, pick the domain folder it belongs to. If none fits and you have 3+ services for a new domain, create a new folder.

## Size Limits

If a service exceeds 350 lines, split it by sub-concern into the same folder. If a Vue component exceeds 350 lines, extract sub-components. Never create god-files.

## Builders

Every model has a custom Builder in `app/Builders/`. There are currently 15:

CharacterBuilder, CharacterStaticSpecBuilder, EventBuilder, ItemBuilder, PersonalTacticalReportBuilder, PriceSnapshotBuilder, RaidAttendanceBuilder, RealmBuilder, RecipeBuilder, ServiceRawDataBuilder, SpecializationBuilder, StaticGroupBuilder, TacticalReportBuilder, TransactionBuilder, UserBuilder.

When you need a new query, add a method to the existing Builder. Method naming:
- Scopes (chainable): return `self` — `forStatic(int $id)`, `pendingAnalysis(int $hours)`
- Lookups (single): return `?Model` — `findById(int $id)`, `findByWclReportId(string $id)`
- Results (collection): return `Collection` — `recentlyEnded(int $minutes)`
- Mutations: return `Model` — `upsertFromWclLog(array $data, Event $raid)`

If a model doesn't have a Builder yet, create one in `app/Builders/`, wire it in the model via `newEloquentBuilder()`.

## Helpers

`app/Helpers/` is for pure stateless utilities only: formatting, constants, string transforms. No business logic, no DB access, no conditionals with side effects. If your helper has an `if` that changes application state — it's a Service.

Existing helpers you should reuse: `CurrencyHelper` (gold formatting), `SyncIntervalHelper` (config lookup), `DiscordConstants`, `WclQueryBuilder` (GraphQL templates), `GeminiResponseFormatter`, `GeminiPromptBuilder`, `RaidAnalysisPromptBuilder`, `DiscordWebhookBuilder`, `DiscordMessageBuilder`.

## Forbidden Patterns

Never use these patterns. They were deliberately eliminated from this codebase:
- `app/Actions/` — absorbed into Services
- `app/Tasks/` — absorbed into Services  
- Direct Eloquent queries in Controllers, Jobs, or Helpers — use Builders
- `@php` blocks in Blade with data transformation — move to Service
- Alpine.js — use Vue for all interactivity
- Vue Options API — use `<script setup>` Composition API only
- Inline modal markup in Vue — use `GlassModal` or `ConfirmationModal` from `UI/`

## Frontend Rules

**Blade templates** are thin wrappers. They contain `<x-app-layout>`, a Vue component tag with props, and `@can` authorization checks. Nothing else. All data formatting for Vue happens in the Service layer (methods like `buildDashboardViewPayload()`, `buildLogShowPayload()`).

**Vue components** use `<script setup>` exclusively. They live in `resources/js/Components/{Domain}/`:

```
Character/, Dashboard/, Profile/, Raid/, Roster/, Schedule/, Statics/, Treasury/, UI/
```

Page-level components are registered globally in `app.js` as kebab-case tags (`<event-details>`, `<treasury-dashboard>`). Sub-components are imported locally.

**Before creating any UI element**, check `Components/UI/` for existing primitives:
- Modals → `GlassModal`, `ConfirmationModal`
- Stats display → `StatCard`
- Empty states → `EmptyState`
- Headers → `SectionHeader`
- Labels/tags → `Badge`
- Toasts → `ToastNotification`
- Inputs → `SearchableSelect`, `TimePickerCarousel`, `TimezoneSelector`, `SpecPicker`

**Design system**: Material Design 3 tokens via Tailwind — `bg-surface-container-high`, `text-on-surface-variant`, `text-primary`, `text-success-neon`, `text-error`, `font-headline`. Icons: Material Symbols Outlined.

## Naming

- Services: `{Concern}Service.php` — `EventService`, `GearAuditService`
- Builders: `{Model}Builder.php` — `EventBuilder`, `CharacterBuilder`
- Helpers: `{Purpose}Helper.php` — `CurrencyHelper`
- Jobs: `{Verb}{Noun}Job.php` — `SyncBnetDataJob`, `CompileCharacterDataJob`
- Vue components: PascalCase files, kebab-case registration — `EventDetails.vue` → `<event-details>`
- Tables: snake_case plural — `events`, `raid_attendances`
- Foreign keys: `{singular}_id` — `event_id`, `character_id`

## Before Writing Code

1. Identify which domain the code belongs to
2. Check if a Service already exists for that domain
3. Check if the query you need already exists in a Builder
4. Check if a UI component already exists before building markup
5. Verify your code follows the Controller → Service → Builder chain
6. Ensure no file exceeds 350 lines

## Build & Verify

After making changes, always verify:
```bash
php artisan route:list  # All routes resolve
npm run build           # Frontend compiles
```
