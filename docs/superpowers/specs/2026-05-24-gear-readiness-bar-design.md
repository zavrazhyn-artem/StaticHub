# Gear Readiness Bar — Design Spec

**Date:** 2026-05-24
**Status:** Approved (brainstorming complete, pre-implementation)
**Author:** Claude (head tester / UX) + Artem
**Origin:** P1 #1 from `docs/ai-testing/reports/2026-05-24-gear-ux-review.md`

## Goal

Turn the Gear → Спорядження (Current Equipment) view from a passive "what's equipped" display into an at-a-glance **"what to fix before raid" checklist**, by adding a compact, full-width readiness strip above the paperdoll. It surfaces gear gaps that are currently invisible or buried (a missing enchant is only a tiny "!" on one item; a 30-ilvl-low slot has no indicator at all).

## Decisions (locked)

1. **Form:** compact full-width strip, always visible, above the 3-column paperdoll.
2. **Scope:** shown only for the **Current Equipment** list (audit mode); hidden for BiS/custom lists. Updates on character/spec switch.
3. **Content:** overall ilvl + Tier X/5, a status (✔ ready / ⚠ N problems), and problem chips for missing enchants, low-quality enchants, empty sockets, and the weakest slot.
4. **Interaction:** chips are clickable → scroll the matching slot into view + brief highlight.
5. **Localization:** fully Ukrainian, consistent with the rest of the UI.
6. **Reuse:** all gap analysis comes from the existing `GearAuditService` (already powering the roster gear audit) — no new analysis logic.

## Content & States

**Headline:** `ilvl <n>` · `Tier <have>/5`. Status badge: `✔ Готово до рейду` (success tone) when `problems === 0`, else `⚠ <n> проблем(и)` (warning tone).

**Problem chips** — render only those that apply:
- **Без чарів** — one chip per missing-enchant slot, labelled with the item name (e.g. `Без чарів: Viryx's Indomitable Bulwark`).
- **Слабкі чари** — low-quality (outdated) enchant slots, item-named.
- **Порожні сокети** — single chip with the empty-socket count (e.g. `Порожні сокети: 2`).
- **Найслабший слот** — the lowest-ilvl equipped item with its delta vs the equipped median (e.g. `Найслабший: Ноги · Lithiel's 250 (−30)`). Shown as a problem chip only when the delta is significant (≥ 13 ilvl below median ≈ one upgrade tier); otherwise the weakest slot is omitted (not every char has a weak slot).

**Problem count** = `missing_enchants.length + low_quality_enchants.length + (empty_sockets > 0 ? 1 : 0) + (weakest_is_problem ? 1 : 0)`. (Raghaar: 1 missing enchant + 1 weak slot = `⚠ 2 проблеми`.)

**States:**
- All good → green status, no chips.
- Character/spec has no synced equipment data → bar hidden.
- Officer viewing another member's gear → same bar, read-only (uses the viewed character).

## Placement & Interaction

- Rendered at the top of the Current Equipment view, full-width, above the left/center/right paperdoll columns.
- Clicking a chip emits `focus-slot(slotType)`; the grid scrolls that `GearSlotCard` into view and applies a brief highlight ring (reusing the per-slot audit visual state that already exists in `GearSlotCard`).
- Chips wrap to multiple rows on narrow viewports (so the bar is mobile-safe from the start).

## Architecture

Reuses existing logic end-to-end; the only new analysis is aggregation + weakest-slot.

**Backend** — `app/Services/Gear/GearViewService.php`:
- New method `buildReadinessSummary(array $equippedItems): array` (or `(Character $char, string $specName)` resolving the spec's equipped items, matching how the current-equipment payload is already built). It calls the existing `App\Services\Roster\GearAuditService`:
  - `resolveMissingEnchants()`, `resolveLowQualityEnchants()` → slot lists; map each slot → item name via `resolveEquipment()` (indexed by slot).
  - `resolveEmptySockets()` → int.
  - `resolveTierPieces()` → tier slots present → `tier_have` (count) / `tier_total` (5).
  - `resolveEquipment()` → per-slot ilvls → compute `weakest` (lowest ilvl item) and its delta vs the median of equipped ilvls; flag `weakest_is_problem` when delta ≥ 13.
- The `readiness` array is included in the **current-equipment list payload** (the same payload that already carries `slots` + `stats`), so it ships and refreshes wherever that payload is produced for a char+spec. No logic/queries added to the controller.

**Readiness payload shape:**
```
readiness: {
  ilvl: int,
  tier_have: int,
  tier_total: int,            // 5
  problems: int,
  missing_enchants:     [{ slot: string, name: string }],
  low_quality_enchants: [{ slot: string, name: string }],
  empty_sockets: int,
  weakest: { slot: string, name: string, ilvl: int, delta: int } | null  // delta negative; only present when significant
}
```

**Frontend** — `resources/js/Components/Gear/Tab/GearReadinessBar.vue` (new):
- Props: `readiness` (the payload above).
- Rendered by `GearGrid.vue` directly above the columns, **only when `audit` is true** (current-equipment list). Hidden when `readiness` is null/absent.
- Emits `focus-slot(slotType)` on chip click; `GearGrid` handles it (ref to the slot card → `scrollIntoView` + transient highlight class).
- Uses MD3 tokens (`bg-surface-container-high`, `text-warning`/`text-success-neon`, `Badge`) and Material Symbols, matching the surrounding components. All strings via the app's i18n.

## Edge cases

- No equipment / unsynced spec → `readiness` null → bar not rendered.
- Zero problems → green "Готово до рейду", ilvl + tier only.
- A slot in `missing_enchants` whose item can't be name-resolved → fall back to the localized slot label.
- Median over an even number of items → standard median; deterministic.

## Testing

- **Backend (PHPUnit Feature):** `buildReadinessSummary` given a crafted `equippedItems` fixture with one missing enchant + one low-ilvl slot + one empty socket → assert `problems`, `missing_enchants`, `empty_sockets`, and `weakest` (slot/ilvl/delta). Reuses `GearAuditService` (already exercised by the roster path).
- **Frontend:** no JS test harness exists in the repo → verify live via the `/ai-test` browser flow: load `/gear` as a synced character, confirm the bar renders with the real gaps (Raghaar: `⚠ 2 проблеми`, `Без чарів: Viryx's Bulwark`, `Найслабший: Ноги 250`), and that clicking a chip highlights the slot.

## Out of scope (separate follow-ups)

- The other gear-UX items (localization of built-in list names + sync timestamp = P1 #2; mobile paperdoll reflow = P1 #3; current→target diff = P2 #4; ilvl colour-grading = P2 #5; stats de-noise = P2 #6). The readiness bar's own strings are localized, but the broader localization sweep is separate.
