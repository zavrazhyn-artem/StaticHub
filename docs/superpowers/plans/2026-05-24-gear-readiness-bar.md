# Gear Readiness Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a compact, full-width "raid readiness" strip above the Current Equipment paperdoll that surfaces missing/weak enchants, empty sockets, the weakest slot, ilvl and tier count — reusing the existing `GearAuditService`.

**Architecture:** Backend adds one aggregation method to `GearViewService` (`buildReadinessSummary`) that calls the existing `App\Services\Roster\GearAuditService` on the spec's raw Blizzard `equipped_items`, and includes a `readiness` key in the `activeList()` payload for the current-equipment list only. Frontend adds a `GearReadinessBar.vue` rendered by `GearGrid.vue` above the columns (audit mode only); clicking a chip scrolls/highlights the matching slot card.

**Tech Stack:** Laravel 11 (PHPUnit Feature test, `app(...)` container), Vue 3 `<script setup>`, the `@/composables/useTranslation` `__()` helper, MD3 Tailwind tokens, lang JSON (`lang/en.json` / `lang/uk.json`).

**Spec:** `docs/superpowers/specs/2026-05-24-gear-readiness-bar-design.md`

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/Gear/GearViewService.php` (modify) | Inject `GearAuditService`; add `buildReadinessSummary()`; include `readiness` in `activeList()`. |
| `tests/Feature/Gear/GearReadinessSummaryTest.php` (create) | Verify the readiness aggregation. |
| `lang/en.json` + `lang/uk.json` (modify) | New UI strings. |
| `resources/js/Components/Gear/Tab/GearReadinessBar.vue` (create) | The bar UI + clickable chips. |
| `resources/js/Components/Gear/Tab/GearGrid.vue` (modify) | Mount the bar (audit only); `focusSlot` highlight; `data-slot` wrappers. |
| `resources/js/Components/Gear/Tab/GearTab.vue` (modify) | Pass `:readiness` to `<GearGrid>`. |

---

## Task 1: Backend — readiness summary in GearViewService

**Files:**
- Modify: `app/Services/Gear/GearViewService.php`
- Test: `tests/Feature/Gear/GearReadinessSummaryTest.php`

Reference — `GearAuditService` (in `app/Services/Roster/GearAuditService.php`) public methods used here:
- `resolveMissingEnchants(array $equippedItems): array` → list of UPPERCASE slot types missing a permanent enchant.
- `resolveLowQualityEnchants(array $equippedItems): array` → list of UPPERCASE slot types with a sub-quality enchant.
- `resolveEmptySockets(array $equippedItems): int`.
- `resolveTierPieces(array $equippedItems): array` → `[abbrev => trackName|'-']` (always full skeleton).
- `resolveEquipment(array $equippedItems): ?array` → list of `['slot'(UPPERCASE), 'id', 'name', 'ilvl', 'quality', 'enchant_id', 'gem_ids', 'bonus_ids', 'upgrade', 'socket_count', 'is_crafted', 'is_set_piece']`.

Raw equipped items live at `Character->bnet_equipment_by_spec[$specName]['equipped_items']` (keyed by spec name string; canonical accessor used in `app/Services/Gear/GearListService.php`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Gear/GearReadinessSummaryTest.php`:

```php
<?php

namespace Tests\Feature\Gear;

use App\Models\Character;
use App\Services\Gear\GearViewService;
use Tests\TestCase;

class GearReadinessSummaryTest extends TestCase
{
    /** Build a minimal Blizzard-shaped equipped item. */
    private function item(string $slot, int $ilvl, array $opts = []): array
    {
        return [
            'slot'         => ['type' => $slot],
            'item'         => ['id' => $opts['id'] ?? (1000 + $ilvl)],
            'name'         => $opts['name'] ?? ($slot.' Item'),
            'level'        => ['value' => $ilvl],
            'quality'      => ['type' => 'EPIC'],
            'enchantments' => $opts['enchantments'] ?? [],
            'sockets'      => $opts['sockets'] ?? [],
            'bonus_list'   => [],
        ];
    }

    private function character(): Character
    {
        // Non-enchantable slots (NECK/BACK/WRIST/HANDS/WAIST/TRINKET_*) avoid
        // entangling the weakest-slot + socket checks with enchant logic.
        $equipped = [
            $this->item('CHEST', 276),                                   // enchantable, no enchant -> missing
            $this->item('NECK', 289),
            $this->item('BACK', 276),
            $this->item('WRIST', 250, ['name' => 'Weak Bracers']),        // weakest
            $this->item('HANDS', 282, ['sockets' => [                     // one empty socket
                ['socket_type' => ['type' => 'PRISMATIC'], 'item' => null],
            ]]),
            $this->item('WAIST', 278),
            $this->item('TRINKET_1', 298),
            $this->item('TRINKET_2', 298),
        ];

        $char = new Character();
        $char->bnet_equipment_by_spec = ['Elemental' => ['equipped_items' => $equipped]];
        $char->equipped_item_level = 280;

        return $char;
    }

    public function test_builds_readiness_summary_from_equipped_items(): void
    {
        $svc = app(GearViewService::class);

        $r = $svc->buildReadinessSummary($this->character(), 'Elemental');

        $this->assertNotNull($r);
        $this->assertSame(280, $r['ilvl']);

        // Missing enchant: CHEST (lowercased slot + item name)
        $this->assertSame([['slot' => 'chest', 'name' => 'CHEST Item']], $r['missing_enchants']);

        $this->assertSame(1, $r['empty_sockets']);

        // Weakest: WRIST 250, well below the median -> flagged
        $this->assertNotNull($r['weakest']);
        $this->assertSame('wrist', $r['weakest']['slot']);
        $this->assertSame(250, $r['weakest']['ilvl']);
        $this->assertLessThan(0, $r['weakest']['delta']);

        // problems = 1 missing + 0 low-quality + 1 sockets + 1 weakest
        $this->assertSame(3, $r['problems']);

        $this->assertSame(count(config('wow_season.tier_slots')), $r['tier_total']);
        $this->assertSame(0, $r['tier_have']); // no set pieces in the fixture
    }

    public function test_returns_null_when_spec_has_no_equipment(): void
    {
        $char = new Character();
        $char->bnet_equipment_by_spec = [];

        $this->assertNull(app(GearViewService::class)->buildReadinessSummary($char, 'Elemental'));
        $this->assertNull(app(GearViewService::class)->buildReadinessSummary($char, null));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Gear/GearReadinessSummaryTest.php`
Expected: FAIL — `Call to undefined method ...::buildReadinessSummary()`.

- [ ] **Step 3: Inject GearAuditService into GearViewService**

In `app/Services/Gear/GearViewService.php`, add the import near the other `use` statements:

```php
use App\Services\Roster\GearAuditService;
```

Add the dependency to the constructor (it currently injects `CharacterStatsExtractor $statsExtractor` and others around line 62). Add this parameter to the promoted-property constructor list:

```php
        private readonly GearAuditService $gearAudit,
```

- [ ] **Step 4: Add the readiness methods**

Add these two methods to `GearViewService` (e.g. just after `activeList()`):

```php
    /**
     * Compact "raid readiness" summary for the current-equipment view.
     * Returns null when the spec has no synced equipment.
     */
    public function buildReadinessSummary(?Character $char, ?string $specName): ?array
    {
        if ($char === null || $specName === null) {
            return null;
        }

        $equipped = $char->bnet_equipment_by_spec[$specName]['equipped_items'] ?? [];
        if (! is_array($equipped) || $equipped === []) {
            return null;
        }

        return $this->readinessFromEquipped($equipped, (int) ($char->equipped_item_level ?? 0));
    }

    private function readinessFromEquipped(array $equipped, int $ilvl): array
    {
        $equipment = $this->gearAudit->resolveEquipment($equipped) ?? [];

        $nameBySlot = [];
        foreach ($equipment as $e) {
            $nameBySlot[$e['slot']] = $e['name'];
        }
        $nameFor = static fn (string $slotType): string => $nameBySlot[$slotType] ?? strtolower($slotType);

        $toChip = static fn (string $slotType): array => [
            'slot' => strtolower($slotType),
            'name' => $nameBySlot[$slotType] ?? strtolower($slotType),
        ];

        $missing = array_map($toChip, $this->gearAudit->resolveMissingEnchants($equipped));
        $lowQ    = array_map($toChip, $this->gearAudit->resolveLowQualityEnchants($equipped));
        $sockets = $this->gearAudit->resolveEmptySockets($equipped);

        $tier      = $this->gearAudit->resolveTierPieces($equipped);
        $tierTotal = count($tier);
        $tierHave  = count(array_filter($tier, static fn ($t) => $t !== '-'));

        $weakest = null;
        if ($equipment !== []) {
            $ilvls = array_map(static fn ($e) => $e['ilvl'], $equipment);
            sort($ilvls);
            $n = count($ilvls);
            $median = $n % 2
                ? $ilvls[intdiv($n, 2)]
                : (int) (($ilvls[$n / 2 - 1] + $ilvls[$n / 2]) / 2);

            $low = null;
            foreach ($equipment as $e) {
                if ($low === null || $e['ilvl'] < $low['ilvl']) {
                    $low = $e;
                }
            }
            $delta = $low['ilvl'] - $median;
            if ($delta <= -13) {
                $weakest = [
                    'slot'  => strtolower($low['slot']),
                    'name'  => $low['name'],
                    'ilvl'  => $low['ilvl'],
                    'delta' => $delta,
                ];
            }
        }

        $problems = count($missing) + count($lowQ) + ($sockets > 0 ? 1 : 0) + ($weakest !== null ? 1 : 0);

        return [
            'ilvl'                 => $ilvl,
            'tier_have'            => $tierHave,
            'tier_total'           => $tierTotal,
            'problems'             => $problems,
            'missing_enchants'     => array_values($missing),
            'low_quality_enchants' => array_values($lowQ),
            'empty_sockets'        => $sockets,
            'weakest'              => $weakest,
        ];
    }
```

Confirm `use App\Models\Character;` is already imported in this file (it is used by `activeList`/relations); if not, add it.

- [ ] **Step 5: Wire `readiness` into the `activeList()` payload**

In `GearViewService::activeList()`, the return array (currently ending with `'slots' => $slots, 'stats' => $this->resolveStats($list)`) — add one key:

```php
            'slots'       => $slots,
            'stats'       => $this->resolveStats($list),
            'readiness'   => $list->type === GearList::TYPE_CURRENT
                ? $this->buildReadinessSummary($list->character, $list->specialization?->name)
                : null,
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Gear/GearReadinessSummaryTest.php`
Expected: PASS (2 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Gear/GearViewService.php tests/Feature/Gear/GearReadinessSummaryTest.php
git commit -m "feat(gear): readiness summary in GearViewService (reuses GearAuditService)"
```

---

## Task 2: Translation strings

**Files:**
- Modify: `lang/en.json`
- Modify: `lang/uk.json`

- [ ] **Step 1: Add English keys**

Add these entries to `lang/en.json` (anywhere in the top-level object; keep valid JSON — mind trailing commas):

```json
    "Ready for raid": "Ready for raid",
    "issues": "issues",
    "Tier": "Tier",
    "No enchant": "No enchant",
    "Weak enchant": "Weak enchant",
    "Empty sockets": "Empty sockets",
    "Weakest slot": "Weakest slot",
```

- [ ] **Step 2: Add Ukrainian keys**

Add to `lang/uk.json`:

```json
    "Ready for raid": "Готово до рейду",
    "issues": "проблеми",
    "Tier": "Тір",
    "No enchant": "Без чарів",
    "Weak enchant": "Слабкі чари",
    "Empty sockets": "Порожні сокети",
    "Weakest slot": "Найслабший",
```

- [ ] **Step 3: Verify both files are valid JSON**

Run: `php -r "json_decode(file_get_contents('lang/en.json'), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('lang/uk.json'), true, 512, JSON_THROW_ON_ERROR); echo 'OK';"`
Expected: `OK`.

- [ ] **Step 4: Commit**

```bash
git add lang/en.json lang/uk.json
git commit -m "i18n(gear): readiness bar strings (en/uk)"
```

---

## Task 3: GearReadinessBar.vue component

**Files:**
- Create: `resources/js/Components/Gear/Tab/GearReadinessBar.vue`

- [ ] **Step 1: Create the component**

```vue
<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    readiness: { type: Object, required: true },
});

const emit = defineEmits(['focus-slot']);

const r = computed(() => props.readiness);
const ok = computed(() => (r.value.problems ?? 0) === 0);

const chips = computed(() => {
    const out = [];
    for (const m of r.value.missing_enchants ?? []) {
        out.push({ key: `me-${m.slot}`, slot: m.slot, icon: 'block', label: __('No enchant'), detail: m.name });
    }
    for (const m of r.value.low_quality_enchants ?? []) {
        out.push({ key: `lq-${m.slot}`, slot: m.slot, icon: 'trending_down', label: __('Weak enchant'), detail: m.name });
    }
    if ((r.value.empty_sockets ?? 0) > 0) {
        out.push({ key: 'sockets', slot: null, icon: 'data_object', label: __('Empty sockets'), detail: String(r.value.empty_sockets) });
    }
    if (r.value.weakest) {
        const w = r.value.weakest;
        out.push({ key: 'weak', slot: w.slot, icon: 'south', label: __('Weakest slot'), detail: `${w.name} ${w.ilvl} (${w.delta})` });
    }
    return out;
});

function onChip(chip) {
    if (chip.slot) emit('focus-slot', chip.slot);
}
</script>

<template>
    <div
        class="mb-4 rounded-xl border bg-surface-container-high px-4 py-3"
        :class="ok ? 'border-success-neon/30' : 'border-error/30'"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <div class="flex items-center gap-2 font-headline">
                <span
                    class="material-symbols-outlined text-xl"
                    :class="ok ? 'text-success-neon' : 'text-error'"
                >{{ ok ? 'verified' : 'warning' }}</span>
                <span :class="ok ? 'text-success-neon' : 'text-error'">
                    {{ ok ? __('Ready for raid') : `${r.problems} ${__('issues')}` }}
                </span>
            </div>
            <span v-if="r.ilvl" class="text-sm text-on-surface-variant">
                ilvl <span class="font-semibold text-on-surface">{{ r.ilvl }}</span>
            </span>
            <span class="text-sm text-on-surface-variant">
                {{ __('Tier') }} <span class="font-semibold text-on-surface">{{ r.tier_have }}/{{ r.tier_total }}</span>
            </span>
        </div>

        <div v-if="chips.length" class="mt-2 flex flex-wrap gap-2">
            <button
                v-for="chip in chips"
                :key="chip.key"
                type="button"
                :disabled="!chip.slot"
                class="inline-flex items-center gap-1.5 rounded-lg bg-error/10 px-2.5 py-1 text-xs text-error transition hover:bg-error/20 disabled:cursor-default disabled:opacity-80"
                @click="onChip(chip)"
            >
                <span class="material-symbols-outlined text-sm">{{ chip.icon }}</span>
                <span class="font-medium">{{ chip.label }}:</span>
                <span class="text-on-surface-variant">{{ chip.detail }}</span>
            </button>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/Gear/Tab/GearReadinessBar.vue
git commit -m "feat(gear): GearReadinessBar component"
```

---

## Task 4: Mount the bar in GearGrid.vue + slot highlight

**Files:**
- Modify: `resources/js/Components/Gear/Tab/GearGrid.vue`

- [ ] **Step 1: Replace the file contents**

Overwrite `resources/js/Components/Gear/Tab/GearGrid.vue` with (adds the `readiness` prop, `GearReadinessBar`, a `gridRoot` ref + `focusSlot`, and `data-slot` wrappers around each slot card):

```vue
<script setup>
import { ref } from 'vue';
import GearSlotCard from './GearSlotCard.vue';
import CharacterStatsPanel from './CharacterStatsPanel.vue';
import GearReadinessBar from './GearReadinessBar.vue';

const props = defineProps({
    slots: { type: Object, required: true },
    stats: { type: Object, default: null },
    statsPlaceholder: { type: String, default: '' },
    enchantableSlots: { type: Array, default: () => [] },
    audit: { type: Boolean, default: false },
    editable: { type: Boolean, default: false },
    readiness: { type: [Object, null], default: null },
    // Forwarded to GearSlotCard so wowhead tooltips resolve hybrid stats
    // and tier-set bonuses against the character's actual class+spec.
    classId: { type: [Number, null], default: null },
    specId: { type: [Number, null], default: null },
});

const emit = defineEmits(['edit', 'clear']);

const LEFT_COL = ['head', 'neck', 'shoulder', 'back', 'chest', null, null, 'wrist'];
const RIGHT_COL = ['hands', 'waist', 'legs', 'feet', 'finger_1', 'finger_2', 'trinket_1', 'trinket_2'];
const BOTTOM_ROW = ['main_hand', 'off_hand'];

const gridRoot = ref(null);

function focusSlot(slot) {
    const root = gridRoot.value;
    if (!root) return;
    const el = root.querySelector(`[data-slot="${slot}"]`);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.classList.add('ring-2', 'ring-primary', 'rounded-lg');
    setTimeout(() => el.classList.remove('ring-2', 'ring-primary', 'rounded-lg'), 1600);
}
</script>

<template>
    <div ref="gridRoot">
        <GearReadinessBar
            v-if="audit && readiness"
            :readiness="readiness"
            @focus-slot="focusSlot"
        />

        <!-- Side cards comfortably fit item names; center stays compact. -->
        <div class="grid gap-x-24 gap-y-3 justify-center" style="grid-template-columns: minmax(220px, 260px) minmax(220px, 280px) minmax(220px, 260px);">
            <!-- Left column -->
            <div class="space-y-1.5 pt-1">
                <template v-for="(slot, idx) in LEFT_COL" :key="`L${idx}`">
                    <div v-if="slot" :data-slot="slot">
                        <GearSlotCard
                            :slot="slot"
                            :item="slots[slot]"
                            :enchantable-slots="enchantableSlots"
                            :audit="audit"
                            :editable="editable"
                            :class-id="classId"
                            :spec-id="specId"
                            @edit="emit('edit', slot)"
                            @clear="emit('clear', slot)"
                        />
                    </div>
                    <div v-else class="h-[40px]"></div>
                </template>
            </div>

            <!-- Center: stats panel -->
            <div class="pt-1">
                <CharacterStatsPanel :stats="stats" :placeholder-text="statsPlaceholder || undefined" />
            </div>

            <!-- Right column -->
            <div class="space-y-1.5 pt-1">
                <div v-for="slot in RIGHT_COL" :key="slot" :data-slot="slot">
                    <GearSlotCard
                        :slot="slot"
                        :item="slots[slot]"
                        :enchantable-slots="enchantableSlots"
                        :audit="audit"
                        :editable="editable"
                        :class-id="classId"
                        :spec-id="specId"
                        mirror
                        @edit="emit('edit', slot)"
                        @clear="emit('clear', slot)"
                    />
                </div>
            </div>

            <!-- Bottom row: weapons centered under the model. -->
            <div class="col-span-3 flex justify-center mt-2">
                <div class="grid grid-cols-2 gap-3 w-full max-w-md">
                    <div v-for="slot in BOTTOM_ROW" :key="slot" :data-slot="slot">
                        <GearSlotCard
                            :slot="slot"
                            :item="slots[slot]"
                            :enchantable-slots="enchantableSlots"
                            :audit="audit"
                            :editable="editable"
                            :class-id="classId"
                            :spec-id="specId"
                            @edit="emit('edit', slot)"
                            @clear="emit('clear', slot)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/Gear/Tab/GearGrid.vue
git commit -m "feat(gear): mount readiness bar + slot highlight in GearGrid"
```

---

## Task 5: Pass `readiness` from GearTab.vue

**Files:**
- Modify: `resources/js/Components/Gear/Tab/GearTab.vue`

- [ ] **Step 1: Add the prop on `<GearGrid>`**

In the `<GearGrid ... />` usage (around line 360-368), add the `:readiness` binding alongside the existing props:

```html
                    <GearGrid
                        :slots="activeList.slots"
                        :stats="activeList.stats"
                        :stats-placeholder="activeList.type === 'current' ? __('Sync your character to populate stats.') : __('Pick items via the slot picker to see set totals.')"
                        :readiness="activeList.readiness"
                        :enchantable-slots="enchantableSlots"
                        :audit="activeList.type === 'current'"
                        :editable="isOwnContext && activeList.editable"
                        :class-id="activeList.class_id"
                        :spec-id="activeList.spec_id"
```

(Keep the rest of the existing attributes/handlers unchanged — only the `:readiness` line is new. Note: `enchantableSlots`/`audit` here reflect the existing bindings; do not duplicate them.)

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/Gear/Tab/GearTab.vue
git commit -m "feat(gear): pass readiness payload to GearGrid"
```

---

## Task 6: Build + live verification

This is verification (per superpowers:verification-before-completion). Run it and confirm before claiming done.

- [ ] **Step 1: Build the frontend**

Run: `npm run build`
Expected: builds without errors; `public/build/` updated.

- [ ] **Step 2: Confirm backend tests still green**

Run: `php artisan test tests/Feature/Gear/`
Expected: PASS (includes `GearReadinessSummaryTest`).

- [ ] **Step 3: Live check via the browser (MCP Playwright)**

Log in through the gate (`/auth/ai-tester?key=<AI_ACCESS>&as=195`) and open `/gear`. Confirm on the Current Equipment view (Raghaar/Elemental):
- the readiness bar renders above the paperdoll,
- it shows `⚠ <n> проблеми`, `ilvl`, `Тір x/5`,
- a `Без чарів: Viryx's Indomitable Bulwark` chip and a `Найслабший: … 250` chip appear (matching the known gaps),
- clicking a chip scrolls to + highlights that slot card,
- switching to the BiS list hides the bar (audit-only).
Capture a screenshot to `docs/ai-testing/screenshots/<run>/` (mkdir the dir first) and send it to the user.

- [ ] **Step 4: Commit any screenshot artifact (optional)** — screenshots are gitignored under `docs/ai-testing/screenshots/`; nothing to commit unless a report is added.

---

## Notes / out of scope

- Only the readiness bar's own strings are localized here; the broader gear localization (built-in list names, the `toLocaleString()` sync timestamp at `GearTab.vue:344`) is a separate follow-up (P1 #2).
- If the Tailwind config defines a softer `warning`/amber token, the error-red used for the problem state and chips can be swapped to it for a less alarming look.
- No JS test harness exists in the repo, so the Vue pieces are verified via build + live browser (Step 3), not unit tests.
