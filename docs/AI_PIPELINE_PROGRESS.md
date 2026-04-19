# AI Raid Analysis Pipeline — Progress Notes

Last updated: 2026-04-18

This doc captures the state of the AI raid analysis pipeline so subsequent sessions can pick up without re-reading the entire conversation. Pair with the in-code comments in `app/Services/Analysis/`.

---

## Pipeline overview

```
┌─────────────┐   ┌─────────────────────┐   ┌────────────────────┐   ┌───────────────────┐
│   WCL API   │ → │ TacticalDataAnalyzer│ → │  GeminiService     │ → │  ReportBlocks.vue │
│             │   │  (deterministic)    │   │  (split per call)  │   │                   │
└─────────────┘   └──────────┬──────────┘   └────────────────────┘   └───────────────────┘
                             │
                             ▼
                   ┌──────────────────────┐
                   │ encounter_snapshots  │  ← per-fight history for cross-raid trends
                   └──────────────────────┘
```

**Key principle:** PHP does all numeric aggregation deterministically. AI only writes narrative + interprets. Multiple Flash-preprocessing iterations were tried earlier — they hallucinated and truncated. Don't go back.

**Generation strategy:**
- Single shared Gemini cache (3h TTL) created once per raid via `createRaidCache()`
- 1 main report call → `{title, main: [blocks]}`
- N per-player calls in parallel batches of 5 via `Http::pool` → `{playerName: [blocks]}`
- Total ~$0.30 per 23-player raid (Gemini 3 Flash Preview model)

---

## Implemented Waves

### Wave 1 — Per-encounter detail data

Added to `encounters[i].player_stats`:
- `damage_done_breakdown[name]` — top 8 abilities by damage done
- `damage_taken_breakdown[name]` — top 8 sources of damage taken
- `healing_breakdown[name]` — top 6 heal abilities + per-ability overheal %
- `cooldown_timings[name][ability]` — major CD usage with `idle_seconds` (idle calculated WITHIN fight only, not across)
- `phase_deaths` — deaths bucketed by phase

Files: `WclQueryBuilder::buildPerEncounterStatsQuery()` (extended), `WclReportParserHelper` (parsers), `RotationAnalyzer` (per-encounter view + participation_seconds normalization fix).

### Wave 2 — Defensive coordination

- `tank_mitigation` per encounter — Shield Block / Bone Shield / Ironfur / Soul Fragments / Stagger / SotR uptime per tank
- `external_cooldowns` — `{events_count, by_caster, by_target}` for Pain Suppression / Ironbark / Cocoon / BoP / Tranquility / Hymn / Spirit Link / Power Infusion / Innervate / Anti-Magic Zone / Time Dilation / Rewind / Drums…

Files: `resources/combat-references/tank-mitigation.yaml` + `external-cooldowns.yaml` + `CombatReferenceLoader`.

### Wave 3 — Cross-raid trends (premium tier)

- `encounter_snapshots` table (migration `2026_04_18_120000`) — per-(static, boss, raid) compact metrics
- `EncounterSnapshotService::saveFromPreprocessed()` — saves snapshot after each analysis (always, regardless of subscription)
- `TrendAnalyzer::buildTrends()` — computes per-boss progression verdict (`killed` / `wall_lowering` / `plateau` / `regressing`) and per-player verdict (`improving` / `plateau` / `regressing` / `mixed`)
- Gated by `analysis.cross_raid_trends_enabled` config flag — placeholder for `$static->subscription_tier` check

Files: `EncounterSnapshot` model + builder + `TrendAnalyzer`.

### Wave 4 — Pull-by-pull + burst sync + heal targets

- `pull_by_pull` per encounter — every attempt with `{outcome, last_phase, boss_pct, duration_s}`
- `burst_sync` — Lust drops + per-player `sync_pct` (was their personal CD within ±15s of lust?)
- `heal_targets` per healer — top 5 heal recipients with role tag

Files: `resources/combat-references/raid-burst.yaml`, additions to InsightsBuilder + WclReportParserHelper.

### Wave 5A — Smart analytics from existing data

8 new methods in `InsightsBuilder`:
- **A1** `correlateDeathsToMechanics` → `per_player_data[name].death_attribution = {total, mechanic_attributed, top_mechanic_killers}`
- **A2** `detectDeathClusters` → `encounters[i].death_clusters = [{fight_id, span_s, death_count, players}]`
- **A3** `analyzeWithinRaidLearning` → `encounters[i].learning_trend = {boss_pct_delta, verdict, recovered_players, persistent_failers}`
- **A4** `identifyKillerAbilities` → `encounters[i].killer_abilities = [{ability, kill_count, unique_victims, top_victims}]`
- **A5** `identifyMechanicSpecialists` → `encounters[i].mechanic_specialists = {mechName: {top_strugglers}}`
- **A6** `analyzeFatigueCurves` → `encounters[i].fatigue_signal = {first_third_avg_deaths, last_third_avg_deaths, verdict}`
- **A7** `identifyCarryPlayers` → `per_player_data[name].raid_role_label = 'carry'|'core'|'building'` (role-aware composite, parse-aware fallback)
- **A8** `analyzePhaseParticipation` → `per_player_data[name].deaths_by_phase_by_boss`

### Wave 5C — Prompt polish

- **C1** Few-shot examples added to main + player prompts (compact JSON outputs as quality anchors)
- **C2** Edge case handling: zero-kills / single-boss / quick-clear / very-short-fights tones
- **C3** Cross-reference instruction — main delegates per-player details to player reports
- **C4** Coaching plan now has 3 categorized directive_lists: Pre-Raid Drills / In-Fight Cues / Post-Fight Review
- **C5** Localization checklist (8 explicit checks) added to main prompt

### Wave 5D4 — WoWHead tooltips

- `AbilityNameIndex` service aggregates 309 ability name → spell ID from spec baselines + tactics + combat-refs (cached 1h)
- Passed as `abilityIndex` prop through `StaticLogService::buildLogShowPayload` → blade `:ability-index` → `LogShow.vue` → `ReportBlocks.vue`
- `useAbilityLinker.js` composable HTML-escapes + regex-replaces known names with anchors
- `.ability-link` CSS class in `app.css` → dotted indigo underline + hover (solid + bg tint)
- WoWHead's `tooltips.js` (already in `layouts/app.blade.php`) auto-attaches tooltips on hover

---

## Key files map

```
app/Services/Analysis/
  TacticalDataAnalyzer.php       ← orchestrator
  WclService.php                 ← API client
  RotationAnalyzer.php           ← cast-efficiency vs spec baseline
  InsightsBuilder.php            ← composite scoring + Wave 5A analytics
  TrendAnalyzer.php              ← cross-raid (premium)
  EncounterSnapshotService.php   ← persists snapshots
  CombatReferenceLoader.php      ← tank/external/burst YAMLs
  AbilityNameIndex.php           ← name→spellID for wowhead links
  GeminiService.php              ← cache + main + per-player generation
  BlockSchema.php                ← validates AI block output

app/Helpers/
  WclQueryBuilder.php            ← GraphQL query templates
  WclReportParserHelper.php      ← raw data parsing

resources/prompts/
  gemini_main_report.txt         ← raid-wide report
  gemini_player_report.txt       ← per-player coach voice
  gemini_chat_analyst.txt        ← interactive chat

resources/spec-baselines/        ← 39 specs, generated from WoWAnalyzer-midnight
resources/combat-references/     ← tank-mitigation, external-cooldowns, raid-burst
resources/tactics/               ← per-boss mechanic YAMLs

resources/js/Components/Logs/
  LogShow.vue                    ← page wrapper
  ReportBlocks.vue               ← all 12 block type renderers
  AiChatBlocks.vue               ← chat-specific blocks
  AiChatSidebar.vue              ← chat UI

resources/js/composables/
  useAbilityLinker.js            ← wowhead anchor injection

app/Console/Commands/
  GenerateSpecBaselines.php      ← extracts spec baselines from WoWAnalyzer-midnight
  ValidateGeminiDiagnostic.php   ← per-boss AI verification
  ValidateChatCoverage.php       ← chat question coverage tester
```

---

## Pricing model (agreed but not implemented)

Per-static subscriptions, all yearly = 2 months free.

| Tier | Price | Includes |
|---|---|---|
| Free (TBD) | $0 | TBD — likely 1 main report/month manual + branding |
| ThankYou | $1/mo | Badge, 6h sync, 90d retention |
| Spectator | $10/mo | Auto main reports per RT |
| Pro | $30/mo | + personal reports + 500 chat credits/mo |
| Elite | $100/mo | + manual upload anywhere + 24h chat lifespan + 5000 credits + multi-guild |

**Token economy** for chat: ~2 credits per message, packs $5/$15/$50 with bonuses on bigger packs. Counter in chat sidebar.

`ProcessRaidAnalysisJob::trendsEnabled()` is current placeholder for tier check — replace with `$static->subscription_tier` once billing is in place.

---

## Job + queue config

```php
public int $timeout = 1200;   // 20 min
public int $uniqueFor = 1800; // 30 min
public int $tries = 1;
```

`REDIS_QUEUE_RETRY_AFTER=1500` in env. Default 90s caused phantom `MaxAttemptsExceededException` while jobs were still running.

---

## Known limitations / shortcomings

- Burst sync silent if no Lust used (test raid had 0 Lust on all 3 bosses — coaching insight in itself)
- A8 (deaths_by_phase) uses `last_phase` of fight as proxy, not actual death-time phase
- Brewmaster Stagger doesn't expose as a buff with uptime → tank_mitigation list incomplete for that spec
- `services_raw_data` empty locally → no gear/trinket/stat data in personal reports
- WCL Premium not integrated → no server/region rankings yet (deferred Wave)

---

## How to resume work

1. Read `~/.claude/projects/-var-www-html/memory/project_ai_pipeline_*.md` (auto-loaded on session start)
2. Skim this doc for high-level context
3. Test reference report: `wcl_report_id = kWVzT682PLbgYvA3` on `tactical_reports.id = 81` (local static_id=1)
4. Regen with: `php artisan tinker --execute="$r=App\Models\TacticalReport::find(81);$r->update(['ai_blocks'=>null,'gemini_cache_id'=>null]);App\Models\PersonalTacticalReport::where('tactical_report_id',81)->delete();App\Jobs\Analysis\ProcessRaidAnalysisJob::dispatch($r);"`

---

## Likely next-session candidates

1. **Frontend polish** — verify all block types render well on real generated reports (mobile, all 12 types, color theme consistency)
2. **Billing/subscription tiers** — Stripe integration, token counter UI, free tier UX, replace config flag with subscription check
3. **Real-raid testing** — second raid will populate trends, full Wave 3 visible
4. **Server/region rankings** (deferred Wave) — needs WCL Premium API integration
5. **Discord post format** — auto-post key highlights to guild Discord channel
