---
boss: Imperator Averzian
wcl_encounter_id: 3176
difficulty_variants: [normal, heroic, mythic]
mechanics:
  - name: "Shadow's Advance"
    type: add_management
    ability_ids: [1251361, 1262776]
    add_npcs: ["Abyssal Voidshaper", "Voidmaw"]
    severity: critical
    description: "Summons 3 Abyssal Voidshapers that channel Void Rupture to claim board spaces. Raid must kill 2 of 3 per cycle to prevent 3-in-a-row."
  - name: "Umbral Collapse"
    type: soak
    ability_ids: [1249265, 1249266, 1260206]
    expected_soakers: 4
    severity: critical
    description: "Marked player positions soak circle on priority Voidshaper to remove its 99% damage reduction. Raid stacks to share damage."
  - name: "Cosmic Shell"
    type: shield_interrupt
    ability_ids: [1280035]
    protects_ability: "Abyssal Voidshaper damage immunity"
    mythic_only: true
    severity: critical
    description: "Mythic only. Voidshapers spawn with 2 stacks of immunity. Dispel debuffed players near the add to remove stacks before DPS."
  - name: "Void Rupture"
    type: dodge
    ability_ids: [1261249, 1262036]
    severity: major
    description: "Voidshaper channel that claims a space. On completion, 12-yard explosion plus outward beams from the claimed space."
  - name: "Imperator's Glory"
    type: positioning
    ability_ids: [1270853]
    severity: critical
    description: "Boss gains 75% increased damage dealt and 99% damage reduction within 10 yards of claimed spaces (and adds on Heroic+). Tanks must reposition."
  - name: "Dark Upheaval"
    type: raid_damage
    ability_ids: [1249251]
    severity: major
    description: "Raid-wide burst damage followed by a ticking DOT on the whole raid. Primary healing check."
  - name: "Oblivion's Wrath"
    type: dodge
    ability_ids: [1260712, 1260718]  # 1260712 = cast, 1260718 = damage
    severity: major
    description: "Void beam spears shoot outward from Averzian in multiple directions, dealing damage and knocking players back."
  - name: "Void Fall"
    type: dodge
    ability_ids: [1258880]
    severity: major
    description: "Knocks players back and spawns ground void zones that must be avoided."
  - name: "Shadow Phalanx"
    type: dodge
    ability_ids: [1249321]
    severity: critical
    description: "Untargetable troops march across arena dealing massive damage. Raid must move through the safe corridor between formations."
  - name: "Blackening Wounds"
    type: tank_swap
    ability_ids: [1265540]
    stack_swap_threshold: 3
    critical_death_stacks: 6
    severity: critical
    description: "Tank melee debuff reduces max HP by 4% per stack (20s duration). Adds fixate the highest-stack tank on Shadow's Advance, forcing a swap."
avoidable_abilities:
  - {name: "Void Rupture", source_mechanic: "Shadow's Advance"}
  - {name: "Oblivion's Wrath", source_mechanic: "Oblivion's Wrath"}
  - {name: "Void Fall", source_mechanic: "Void Fall"}
  - {name: "Shadow Phalanx", source_mechanic: "Shadow Phalanx"}
  - {name: "Umbral Collapse", source_mechanic: "Umbral Collapse"}
  - {name: "Umbral Barrier", source_mechanic: "Shadow's Advance"}
  - {name: "Dark Barrage", source_mechanic: "Shadow's Advance"}
  - {name: "Lingering Darkness", source_mechanic: "Void Fall"}
role_mechanics:
  tank:
    - "Blackening Wounds"
    - "Imperator's Glory"
    - "Shadow's Advance"
  healer:
    - "Dark Upheaval"
    - "Umbral Collapse"
    - "Cosmic Shell"
    - "Blackening Wounds"
  dps:
    - "Shadow's Advance"
    - "Umbral Collapse"
    - "Cosmic Shell"
    - "Void Rupture"
    - "Shadow Phalanx"
    - "Oblivion's Wrath"
    - "Void Fall"
---

# Imperator Averzian

## Overview
Imperator Averzian is a single-phase encounter in VS-DR-MQD where the boss attempts to claim three adjacent spaces on a tic-tac-toe grid on the arena floor. If Averzian successfully claims three spaces in a row, the raid wipes. Each cycle, three Abyssal Voidshapers spawn and move to claim spaces -- the raid can only stop two of the three per cycle, so space management and prioritization are the central challenge. The fight layers dodging mechanics, tank management, and soak coordination on top of this core puzzle.

## Phases
### Single Phase (No Phase Transitions)
- The encounter is a continuous single-phase fight with repeating add spawn cycles.
- Each cycle, Shadow's Advance summons 3 Abyssal Voidshapers that attempt to claim board spaces.
- The raid must prevent Averzian from ever getting 3 claimed spaces in a row (horizontally, vertically, or diagonally).
- At 35% boss HP on Heroic+, Voidmaws begin spawning and running toward claimed spaces to heal the boss.

## Key Mechanics

### Shadow's Advance (shadows_advance)
- **Type**: Raid mechanic (add spawn)
- **What it does**: Summons 3 Abyssal Voidshapers that move to random board spaces and begin channeling Void Rupture to claim those spaces. The adds have 99% damage reduction until affected by Umbral Collapse soaks.
- **Correct response**: Identify which 2 of the 3 Voidshapers to prioritize (based on which spaces would give Averzian 3-in-a-row). Apply Umbral Collapse soaks to those 2 adds to remove their damage reduction, then burn them down before their channel completes. The third add will inevitably claim its space.
- **Failure indicator**: A space is claimed that gives Averzian 3 in a row, triggering a wipe. Or damage on shielded adds (99% DR) showing wasted DPS.
- **Common mistakes**: Soaking the wrong Voidshaper, not coordinating which 2 to kill, splitting DPS across all 3 instead of focusing 2.

### Umbral Collapse (umbral_collapse)
- **Type**: Raid mechanic (soak)
- **What it does**: A random player is marked with a large soak circle. The entire raid should help soak it. When the soak circle is positioned on top of an Abyssal Voidshaper, it removes the add's 99% damage reduction buff. Two soaks occur per Voidshaper set.
- **Correct response**: The marked player must move their soak circle onto the priority Voidshaper target. Other raid members stack in the circle to share damage. On Mythic, dispelled players receive a 10-second DOT.
- **Failure indicator**: Voidshaper retains 99% DR (damage_taken on the add remains negligible). Soak damage kills players if not enough people participate.
- **Common mistakes**: Marked player not positioning on the correct add, raid members not stacking for soak, soaking on the wrong Voidshaper.

### Cosmic Shell (cosmic_shell) -- Mythic Only
- **Type**: Raid mechanic (dispel coordination)
- **What it does**: On Mythic, Abyssal Voidshapers spawn with 2 stacks of an immunity buff (Cosmic Shell). Several raid members receive a dispellable debuff. Dispelling this debuff near a Voidshaper removes one immunity stack.
- **Correct response**: Assign 2 debuffed players to each targeted Voidshaper. Each dispels near the add to remove both immunity stacks before DPS can begin.
- **Failure indicator**: Voidshaper retains immunity stacks, preventing damage. Dispelled players take a 10-second DOT -- deaths from unhealed DOT.
- **Common mistakes**: Dispelling too far from the add, not coordinating 2 dispels per target, dispelling on the wrong Voidshaper.

### Void Rupture (void_rupture)
- **Type**: Raid mechanic (dodge)
- **What it does**: The channeled cast by Abyssal Voidshapers. When the cast finishes, the Voidshaper claims its space. The area explodes in a 12-yard radius and fires outward beams from the claimed space.
- **Correct response**: If the Voidshaper was not killed in time, move away from the 12-yard explosion radius and dodge the outward beams.
- **Failure indicator**: damage_taken from Void Rupture explosion or beam hits. Deaths near claimed spaces.
- **Common mistakes**: Standing too close to the add when it finishes casting, not dodging beams after the explosion.

### Imperator's Glory (imperators_glory)
- **Type**: Boss positioning mechanic
- **What it does**: When Averzian is within 10 yards of a claimed space, he gains 75% increased damage dealt and 99% reduced damage taken. On Heroic+, this also triggers when within 10 yards of any adds (not just claimed spaces).
- **Correct response**: Tanks must keep the boss positioned away from all claimed spaces and (on Heroic+) away from active adds.
- **Failure indicator**: Boss damage_taken drops to near zero (99% DR active). Tank/raid damage spikes from 75% buff. Buff uptime on boss in logs.
- **Common mistakes**: Tanking boss near a claimed space, pulling boss through add positions on Heroic+.

### Dark Upheaval (dark_upheaval)
- **Type**: Healer mechanic (raid damage)
- **What it does**: Deals an initial burst of raid-wide damage followed by a ticking damage-over-time effect on the entire raid.
- **Correct response**: Healers use cooldowns and throughput healing. No positional counterplay available.
- **Failure indicator**: Deaths during or immediately after Dark Upheaval, especially on players already low from other mechanics.
- **Common mistakes**: Healers not pre-planning cooldown rotation for each Dark Upheaval cast, raid members taking unnecessary damage from other mechanics before the burst.

### Oblivion's Wrath (oblivions_wrath)
- **Type**: Raid mechanic (dodge)
- **What it does**: Several void beam spears shoot outward from Averzian in multiple directions, dealing damage and knocking players back.
- **Correct response**: Dodge the beam spear patterns. Be aware of knockback direction to avoid being pushed into claimed spaces or other hazards.
- **Failure indicator**: damage_taken from Oblivion's Wrath, deaths from knockback into hazards.
- **Common mistakes**: Standing directly in beam paths, being knocked into claimed spaces triggering Imperator's Glory on the boss.

### Void Fall (void_fall)
- **Type**: Raid mechanic (dodge)
- **What it does**: Knocks players back and then spawns ground circles (void zones) that must be avoided.
- **Correct response**: After the knockback, immediately move out of the ground circles.
- **Failure indicator**: damage_taken from Void Fall ground effects after the initial knockback.
- **Common mistakes**: Not repositioning after knockback, standing in void zones.

### Shadow Phalanx (shadow_phalanx)
- **Type**: Raid mechanic (dodge)
- **What it does**: Untargetable troops march from one end of the arena to the other, dealing massive damage to any player they walk over. There is a safe corridor with no troops.
- **Correct response**: Quickly identify the safe gap between troop formations and move through it. Do not try to tank or heal through the troop damage.
- **Failure indicator**: Instant deaths or massive damage_taken from Shadow Phalanx. Multiple deaths at the same timestamp.
- **Common mistakes**: Not identifying the safe corridor quickly enough, being caught mid-movement between formations.

### Blackening Wounds (blackening_wounds)
- **Type**: Tank mechanic (debuff)
- **What it does**: Averzian's melee attacks apply a stacking debuff that reduces the target's maximum health by 4% per stack. Each stack lasts 20 seconds. When adds spawn (Shadow's Advance), they fixate on the tank with the highest stacks.
- **Correct response**: Tank swap when adds spawn -- the high-stack tank kites fixated adds while the fresh tank picks up the boss. Stacks reset naturally over 20 seconds.
- **Failure indicator**: Tank deaths from health reduction at high stacks. Adds fixating on the wrong tank if swap is mistimed.
- **Common mistakes**: Not swapping at add spawn, letting stacks get too high before swap, both tanks having similar stack counts.

## Adds

### Abyssal Voidshaper
- **When**: Spawns in sets of 3 during Shadow's Advance (repeating throughout the fight)
- **Priority**: Kill 2 of 3 per set (the 2 that would give Averzian dangerous board positions). The third is allowed to claim its space.
- **Abilities**: Channels Void Rupture to claim a board space. Has 99% damage reduction until hit by Umbral Collapse soak. On Mythic, also protected by Cosmic Shell (2 immunity stacks requiring dispels).
- **Failure indicator**: If all 3 claim spaces, or if the wrong 2 are killed allowing 3-in-a-row, the raid wipes. Low damage on adds with DR still active indicates missed soaks.

### Voidmaw (Heroic+ only)
- **When**: Spawns at 35% boss HP
- **Priority**: High -- must be intercepted/interrupted before reaching claimed spaces
- **Abilities**: Runs toward claimed spaces to heal the boss
- **Failure indicator**: Boss health increasing, Voidmaw reaching claimed spaces

## Role-Specific

### Tanks
- **Blackening Wounds management**: Swap when Shadow's Advance is cast. The high-stack tank takes add fixate, the fresh tank takes the boss.
- **Boss positioning**: Keep Averzian at least 10 yards from all claimed spaces at all times. On Heroic+, also keep him 10+ yards from active adds.
- **Cooldown usage**: Use active mitigation when stacks are high (3+ stacks = 12%+ max HP reduction).

### Healers
- **Dark Upheaval**: Rotate raid cooldowns for each cast. The burst + DOT is the primary healing check.
- **Umbral Collapse soaks**: Ensure enough raid members participate in soaks to spread damage.
- **Dispels (Mythic)**: Coordinate dispels of Cosmic Shell debuffs near Voidshapers. Dispelled players take a 10-second DOT that needs healing.
- **Tank healing**: Watch for tanks at high Blackening Wounds stacks -- their effective HP pool shrinks significantly.

### DPS
- **Target priority**: When Shadow's Advance occurs, immediately switch to the 2 designated Voidshapers (after soaks remove their DR). Burn them before Void Rupture completes.
- **Switch timing**: Pre-position near the priority adds before soaks land to maximize uptime on the vulnerable window.
- **Avoid tunneling boss**: Missing the add kill window means a space is claimed that could cause a wipe.

## Difficulty Differences

### Normal
- Imperator's Glory only triggers near claimed spaces (not near adds).
- No Voidmaw spawns at 35%.
- No Cosmic Shell on Voidshapers.
- Fewer overall mechanics to track -- serves as the tic-tac-toe board management tutorial.

### Heroic
- **Imperator's Glory expanded**: Boss now also gains the 75% damage / 99% DR buff when within 10 yards of any adds, not just claimed spaces. This makes boss positioning significantly harder during add phases.
- **Voidmaw adds at 35%**: New add type spawns at 35% boss HP that runs toward claimed spaces to heal the boss. Must be intercepted.
- Tighter DPS and healing checks overall.

### Mythic
- **Cosmic Shell**: Abyssal Voidshapers spawn with 2 stacks of immunity. Requires coordinated dispels from debuffed players standing near the adds before any damage can be done. This compresses the kill window significantly.
- **Dispel DOT**: Players who are dispelled take a 10-second damage-over-time effect, adding healing pressure.
- **New interrupt-required add**: Additional add type spawns requiring interrupts.
- **What makes it hardest**: The Cosmic Shell dispel coordination layer on top of the soak positioning creates an extremely tight execution window to kill Voidshapers before they claim spaces. Any miscommunication on dispel assignments or soak targets likely results in a lost space and potential wipe.

## Common Wipe Causes
1. **Three-in-a-row**: Failing to prevent the correct Voidshapers from claiming spaces, allowing Averzian to get 3 adjacent claimed spaces.
2. **Boss buffed by Imperator's Glory**: Tank positioning boss too close to claimed spaces or adds (Heroic+), causing 75% increased boss damage and 99% DR making him unkillable.
3. **Missed soaks on priority adds**: Umbral Collapse not positioned on the correct Voidshaper, leaving its 99% DR active and making it unkillable in time.
4. **Shadow Phalanx deaths**: Multiple raid members killed by marching troops from failing to find the safe corridor.
5. **Tank death from Blackening Wounds**: Stacks too high without a swap, tank HP reduced to a point where a melee + any other damage is lethal.
6. **Cosmic Shell miscoordination (Mythic)**: Dispels not landing near the Voidshaper, or only 1 of 2 stacks removed, leaving immunity up and wasting the kill window.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **damage_taken entries for Void Rupture, Oblivion's Wrath, Shadow Phalanx, Void Fall**: These indicate players failing dodge mechanics. High counts = poor spatial awareness.
- **Imperator's Glory buff uptime on boss**: Any uptime indicates tank positioning errors. Check boss proximity to claimed spaces/adds.
- **Blackening Wounds stack counts on tanks**: Stacks above 5 (20% HP reduction) are dangerous. Check if swaps align with Shadow's Advance timing.
- **Umbral Collapse soak participation**: Low soak participant counts indicate people not stacking. Check if soaks landed on the correct Voidshapers by cross-referencing add damage reduction buff removal timing.
- **Abyssal Voidshaper kill timing vs Void Rupture cast completion**: If Void Rupture completes on a targeted add, the soak/DPS was too slow. Track which specific adds claimed spaces.
- **Dark Upheaval healing**: Check if healing cooldowns are used and if deaths correlate with Dark Upheaval casts.
- **Cosmic Shell stack removal timing (Mythic)**: Delays in removing immunity stacks compress the DPS window. Track dispel events near Voidshaper positions.
- **Death timing patterns**: Deaths clustered during Shadow Phalanx = safe corridor failures. Deaths during add phases = soak/priority failures. Deaths after Dark Upheaval = healing gaps. Tank deaths with high Blackening Wounds stacks = swap failures.
- **Voidmaw proximity to claimed spaces (Heroic+)**: Track if Voidmaws reach their destination, indicating missed intercepts at 35% boss HP.
- **Board state tracking**: Map which spaces are claimed over time to identify if the raid's strategic space denial plan is sound or if they're allowing dangerous configurations.
