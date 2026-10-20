# Gear Module — UX/UI Review (2026-05-24)

**Reviewer:** Claude (head tester, UX focus), via the `/auth/ai-tester` gate as Zavrik (leader)
**Scope:** `/gear` — all three active tabs (Спорядження / Список бажань / Історія лута) + the disabled "Оптимізатор сховища (скоро)", desktop (1280) and mobile (390).
**Screenshots:** `docs/ai-testing/screenshots/2026-05-24-gear-ux/` (01 current-equipment, 02 wishlist, 03 loot-history, 04 mobile).

## Top-line

The module is feature-rich and visually consistent (MD3 dark, good iconography, Wowhead-linked items, Raidbots hooks). The core gap is that it is **display-oriented, not decision-oriented**: it shows what the character has, but doesn't answer the two questions a gear manager exists to answer — *"what's broken / missing?"* and *"what should I upgrade next?"* The highest-value work is surfacing gaps and current-vs-target comparison, plus localization polish and mobile reflow.

## P1 — high impact, low/medium effort

1. **Gear Readiness summary (the #1 add).** Today a missing enchant is a tiny "!" on a single item (`Viryx's Indomitable Bulwark · без чарів`). Promote gaps to a first-class, actionable strip at the top of the Спорядження tab: missing enchants, empty gem sockets, low-ilvl slots, ilvl-vs-target. e.g. `⚠ 1 слот без чарів · 2 порожні сокети · найслабший: Lithiel's Leggings 250`. Converts the page from "view" into a "to-do list." Reuse `StatCard`/`Badge`.

2. **Localization consistency (cheap, high polish).** UI is fully Ukrainian, but: sync time renders US-format `5/24/2026, 1:32:23 PM` → use localized relative time (`Синхронізовано 2 год тому`); built-in list names are English (`Current Equipment`, `Best in Slot`) and the `auto`/`manual` badges too → translate. These English strings stand out badly.

3. **Mobile reflow of the paperdoll (real layout bug).** At 390px (screenshot 04): the bottom weapon row overflows off the **left edge** (`Excavating Cudgel` is clipped), the Персонаж/Спец selector row doesn't reflow (Спец detached far-right over dead space), and the 3-column slot layout collapses inconsistently (left-column slots misplaced). Make it a single clean stacked list with explicit slot labels on narrow viewports.

## P2 — high impact, more effort

4. **Current → target comparison/diff.** BiS/wishlist exist to answer "what to upgrade," but current and BiS are separate list-cards you toggle between; the user has to diff in their head. Add a per-slot delta (current ilvl → target ilvl, Δ, source). Inline delta column or side-by-side.

5. **Slot labels + ilvl visual grading.** Items show ilvl + name but no slot label and no visual weight; ilvls range 250–298 with no color cue. Add slot icons/labels and color-grade the ilvl badge by tier so the eye jumps to the weak `250` leggings among `276–298`. Highlight lowest slots.

6. **De-noise the stats card.** It lists `Сила 0 / Спритність 0` for an Intellect caster — pure noise. Hide irrelevant primaries; lead with the secondary-stat profile (crit/haste/mastery/vers), ideally a small bar, since that's what players tune.

## P3 — polish / IA

7. **List-switcher clarity.** The Current/BiS "cards" double as a list selector but read as content cards; make the active selection unambiguous (segmented control or inner tabs).
8. **Wishlist job-to-be-done.** Clarify "my wishlist" vs "raid loot priority"; the by-boss grid is very dense — add sort/filter (slot, upgrade %, boss) and a per-item "upgrade vs current %" so it's scannable.
9. **Loot History empty state.** The five zeroed stat cards (Total/Mythic/Heroic/Normal/LFR) eat the whole viewport when empty — collapse when zero. Truncated headers (`Скл.`/`Відповідь`/`Голоси`) are terse.
10. **Disabled "Оптимізатор сховища (скоро)" tab.** A permanently-disabled main tab adds clutter; use a subtler "soon" affordance or hide until shipped.

## Note

No functional bugs blocked the review (all pages 200, no console errors). These are UX/UI improvements, prioritised by value-to-effort. P1 #1 (readiness summary) is the single biggest lever.
