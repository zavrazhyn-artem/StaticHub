---
boss: Fallen-King Salhadaar
wcl_encounter_id: 3179
difficulty_variants: [normal, heroic, mythic]

mechanics:
  - name: "Destabilizing Strikes"
    type: tank_swap
    ability_ids: [1271579, 1271577]
    stack_swap_threshold: 6
    critical_death_stacks: 10
    severity: critical
    description: "Stacking debuff on active tank. Swap at 5-7 stacks."

  - name: "Shadow Fracture"
    type: interrupt
    ability_ids: [1254088]
    caster_name: "Fractured Image"
    miss_threshold_pct: 5
    severity: critical
    description: "Cast by clones of Fractured Projection. Must be interrupted."

  - name: "Nexus Shield"
    type: shield_interrupt
    ability_ids: [1275056]
    protects_ability: "Shadow Fracture"
    severity: critical
    mythic_only: true
    description: "Buffs clones — wasted interrupts hit shield. Only unshielded clone is interruptible."

  - name: "Despotic Command"
    type: absorb_shield
    ability_ids: [1260823, 1248697]
    natural_duration_ms: 12000
    expected_clear_ms: 4000
    puddle_drop: true
    puddle_ability: "Dark Radiation"
    severity: major
    description: "Healing absorb debuff on random players. Healers must clear. On expire, drops Dark Radiation puddle."

  - name: "Entropic Unraveling"
    type: burn_phase
    ability_ids: [1246175, 1260030]  # Umbral Beams (1260030) is the actual damage during burn
    aliases: ["Umbral Beams"]
    energy_trigger: 100
    duration_s: 20
    beam_ability: "Umbral Beams"
    severity: critical
    description: "100-energy burst window with 25% damage taken. Dodge Umbral Beams during the window."

  - name: "Shattering Twilight"
    type: positioning
    ability_ids: [1253032]
    spike_ability: "Twilight Spikes"
    spike_ability_ids: [1251213]
    severity: major
    description: "Tank must position boss away from raid during cast. Spikes fire in lines."

  - name: "Twisting Obscurity"
    type: raid_damage
    ability_ids: [1250686]
    duration_ms: 23000
    severity: major
    description: "23s raid-wide DoT. Healers pop cooldowns."

  - name: "Void Convergence"
    type: orb_management
    ability_ids: [1243453]
    orb_npcs: ["Concentrated Void", "Enduring Void"]
    stagger_interval_ms: 5000
    severity: critical
    description: "Orbs spawn and advance to boss — wipe if contact. Kill staggered to avoid DoT stacks."

  - name: "Void Exposure"
    type: proximity_damage
    ability_ids: [1250828]
    severity: minor
    description: "Damage taken from standing too close to orbs."

  - name: "Torturous Extract"
    type: raid_damage
    ability_ids: [1245592]
    severity: major
    description: "High-tick damage on players during fight cycles."

avoidable_abilities:
  - {name: "Dark Radiation", source_mechanic: "Despotic Command"}
  - {name: "Umbral Beams", source_mechanic: "Entropic Unraveling"}
  - {name: "Twilight Spikes", source_mechanic: "Shattering Twilight"}
  - {name: "Void Exposure", source_mechanic: "Void Convergence"}
  - {name: "Void Infusion", source_mechanic: "Void Convergence"}
  - {name: "Shadow Fracture", source_mechanic: "Fractured Projection"}

role_mechanics:
  tank:
    - "Destabilizing Strikes"
    - "Shattering Twilight"
  healer:
    - "Despotic Command"
    - "Twisting Obscurity"
    - "Torturous Extract"
  dps:
    - "Shadow Fracture"
    - "Nexus Shield"
    - "Void Convergence"
    - "Entropic Unraveling"
---

# Fallen King Salhadaar

## Overview
Fallen King Salhadaar is a single-phase encounter in Void Scar: Depths of the Ruined Keep (VS-DR-MQD). The boss has no distinct phase transitions but features an energy-based damage amplification window at 100 energy (Entropic Unraveling). The arena gradually fills with permanent puddles throughout the fight, creating an increasing spatial pressure that serves as a soft enrage. The key challenge is managing orb spawns (Void Convergence) that cause an instant wipe if they reach the boss, while coordinating interrupts on clone casts and preserving arena space.

## Phases
### Single Phase (Energy-Based Cycle)
- The boss repeats its ability rotation continuously throughout the fight.
- At 100 energy, the boss enters a stationary damage amplification window (Entropic Unraveling) lasting 20 seconds.
- After Entropic Unraveling, energy resets and the cycle repeats.
- The fight is a race against permanent puddle accumulation consuming the arena.

## Key Mechanics

### Void Convergence (void_convergence)
- **Type**: Raid mechanic (all players)
- **What it does**: Several Concentrated Void orbs spawn around the room and slowly advance toward the boss. Orbs deal pulsing AoE damage to players standing within a small radius of them.
- **Correct response**: Tanks must kite the boss away from the orbs. DPS must kill the orbs before they reach the boss. On Heroic/Mythic, kill orbs one at a time to avoid stacking the raid-wide DoT applied when an orb dies.
- **Failure indicator**: If any orb reaches the boss, the raid wipes instantly. On Heroic+, high stacks of the post-kill DoT indicate orbs are being killed too quickly/simultaneously.
- **Common mistakes**: Ignoring orbs or tunneling boss during orb spawns. Killing multiple orbs at once on Heroic+ causing lethal DoT stacks. Tanks not kiting the boss far enough from orb paths.

### Concentrated Void (concentrated_void)
- **Type**: Environmental hazard (proximity damage from orbs)
- **What it does**: The orbs spawned by Void Convergence pulse damage to nearby players as they travel.
- **Correct response**: Stay out of melee range of orbs unless actively killing them. Ranged DPS should handle orbs when possible.
- **Failure indicator**: Unnecessary damage taken from orb proximity shown in damage_taken logs.
- **Common mistakes**: Melee stacking on orbs and taking excessive pulsing damage.

### Fractured Projection (fractured_projection)
- **Type**: Raid mechanic (interrupt/CC check)
- **What it does**: The boss creates multiple clones of himself. Each clone begins casting Shadow Fracture, which deals massive raid-wide damage if completed.
- **Correct response**: Interrupt or crowd-control every clone's Shadow Fracture cast. Clones disappear once their cast is stopped. Assign interrupt groups or CC rotations.
- **Failure indicator**: Shadow Fracture damage appearing in the log indicates a missed interrupt. Multiple completed casts will likely cause a wipe.
- **Common mistakes**: Not having enough interrupts assigned. Overlapping interrupts on the same clone while others finish casting.

### Shadow Fracture (shadow_fracture)
- **Type**: Raid-wide damage (cast by clones)
- **What it does**: Cast by Fractured Projection clones. Deals massive raid-wide shadow damage if the cast completes.
- **Correct response**: Must be interrupted or the clone must be CC'd before the cast finishes.
- **Failure indicator**: Any Shadow Fracture damage in logs means a clone was not stopped.
- **Common mistakes**: Letting even one clone finish casting during hectic moments like overlapping with Void Convergence.

### Nexus Shield (nexus_shield)
- **Type**: Mythic-only clone mechanic
- **What it does**: All clones except one are buffed with Nexus Shield, making them immune to interrupts and CC. When the unshielded clone is stopped, the shield transfers to another random clone, revealing the next interruptible target.
- **Correct response**: Quickly identify the unshielded clone and stop its cast first. Then find the next unshielded clone sequentially. Requires fast target switching and communication.
- **Failure indicator**: Shadow Fracture casts completing because players could not identify or reach the unshielded clone in time.
- **Common mistakes**: Wasting interrupts on shielded clones. Not calling out which clone is vulnerable.

### Despotic Command (despotic_command)
- **Type**: Raid mechanic (targeted debuff on multiple players)
- **What it does**: Several players receive a debuff causing them to pulse AoE damage in a circle around them for 12 seconds. When the debuff expires, each affected player drops a permanent puddle at their location and receives a healing absorb shield.
- **Correct response**: Affected players must move to the edges of the arena before the debuff expires to drop puddles in low-traffic areas. Healers must dispel/heal through the absorb shield after puddles drop.
- **Failure indicator**: Puddles placed in the center of the arena (visible in replay). Players dying to the healing absorb. Raid members taking damage from poorly placed puddles throughout the fight.
- **Common mistakes**: Dropping puddles in the center of the room, rapidly shrinking available space. Not moving early enough (the 12-second timer is generous). Standing in existing puddles while placing new ones.

### Entropic Unraveling (entropic_unraveling)
- **Type**: Raid-wide burn phase / damage amplification
- **What it does**: Triggered at 100 energy. Boss becomes stationary and pulses raid-wide damage for 20 seconds. Rotating beams sweep the arena dealing massive damage to anyone hit. The boss takes 25% increased damage during this window. A permanent puddle is left at the boss's location when the ability ends.
- **Correct response**: Tanks position the boss near the arena edge before 100 energy so the post-ability puddle is in a safe spot. All DPS use cooldowns to maximize damage during the 25% vulnerability window. All players dodge the rotating beams. Healers use raid cooldowns for the sustained pulsing damage.
- **Failure indicator**: Deaths to rotating beam damage. Boss not positioned at edge (puddle in center of room). Low DPS during the vulnerability window extending the fight.
- **Common mistakes**: Tank not pre-positioning the boss before 100 energy. Players hit by rotating beams. Healers not planning cooldown rotation for the 20-second sustained damage. Wasting DPS cooldowns before the window.

### Shattering Twilight (shattering_twilight)
- **Type**: Tank mechanic (Heroic+: also targets additional players)
- **What it does**: The current tank is marked and takes a damage hit. Multiple spike lines then radiate outward from the marked player's location, dealing heavy damage to anyone struck. On Heroic+, additional non-tank players are also marked.
- **Correct response**: Marked players should be positioned away from the raid. All other players must dodge the spike lines radiating outward. On Heroic, marked non-tanks should move to safe positions away from the group.
- **Failure indicator**: Multiple players hit by spike lines (damage_taken entries for Shattering Twilight on non-marked players). Deaths from spike damage.
- **Common mistakes**: Tank standing in the middle of the raid when spikes go out. Raid members not watching for spike patterns. On Heroic, marked players not spreading out.

### Twisting Obscurity (twisting_obscurity)
- **Type**: Healer mechanic (raid-wide damage + sustained DoT)
- **What it does**: Deals immediate raid-wide damage followed by a 23-second damage-over-time effect on the entire raid.
- **Correct response**: Healers must manage both the initial burst and the extended 23-second DoT. Plan healing cooldowns accordingly, especially when overlapping with other mechanics.
- **Failure indicator**: Deaths during the 23-second DoT window, especially players already low from other mechanics. High overheal suggesting poor cooldown timing.
- **Common mistakes**: Using all healing cooldowns on the initial hit and having nothing for the 23-second DoT. Not topping players before the next mechanic overlap.

### Destabilizing Strikes (destabilizing_strikes)
- **Type**: Tank mechanic (stacking debuff)
- **What it does**: Each melee attack from the boss applies a stacking damage-over-time debuff to the active tank.
- **Correct response**: Tanks must swap at high stacks to allow the debuff to fall off the previous tank. Coordinate swaps around other tank-targeted mechanics (Shattering Twilight).
- **Failure indicator**: Tank deaths with high Destabilizing Strikes stacks. Stacks going significantly higher than swap threshold before swapping.
- **Common mistakes**: Late tank swaps leading to excessive DoT damage. Swapping during Shattering Twilight causing confusion. Not having a consistent stack count for swapping.

### Enduring Void (enduring_void)
- **Type**: Mythic-only orb mechanic
- **What it does**: On Mythic, killed Concentrated Void orbs respawn as Enduring Void orbs in subsequent Void Convergence waves. This causes the total number of orbs to increase progressively with each wave.
- **Correct response**: DPS must scale their orb-killing efficiency as the fight progresses. Save AoE cooldowns for later waves. Continue killing one at a time to manage DoT stacks.
- **Failure indicator**: Orbs reaching the boss in later waves due to overwhelming numbers. Raid unable to handle increasing orb counts.
- **Common mistakes**: Not planning for escalating orb counts. Burning all DPS cooldowns early and lacking damage for later waves.

## Adds
### Fractured Projection Clones
- **When**: Periodically throughout the fight via Fractured Projection
- **Priority**: Immediate -- must be interrupted/CC'd before Shadow Fracture finishes casting
- **Abilities**: Shadow Fracture (massive raid-wide damage cast). On Mythic, most clones are protected by Nexus Shield.
- **Failure indicator**: Shadow Fracture damage appearing in logs. On Mythic, wasted interrupts on shielded clones.

### Concentrated Void Orbs
- **When**: Periodically throughout the fight via Void Convergence
- **Priority**: Highest -- reaching the boss causes an instant wipe
- **Abilities**: Pulsing AoE damage to nearby players while traveling toward the boss. On Heroic+, killing an orb applies a stacking raid-wide DoT.
- **Failure indicator**: Orbs reaching the boss (instant wipe). On Heroic+, high DoT stacks from killing orbs too quickly.

### Enduring Void Orbs (Mythic Only)
- **When**: Respawn from previously killed orbs during each new Void Convergence wave
- **Priority**: Same as Concentrated Void -- must be killed before reaching boss
- **Abilities**: Same as Concentrated Void orbs but adds to the total count each wave
- **Failure indicator**: Escalating orb counts overwhelming DPS in later phases of the fight

## Role-Specific
### Tanks
- Swap on Destabilizing Strikes at a consistent stack count (coordinate with co-tank).
- Kite the boss away from Void Convergence orb paths.
- Position the boss near the arena edge before 100 energy so the Entropic Unraveling puddle drops in a safe location.
- Be prepared for Shattering Twilight spike lines -- face away from the raid.
- On Heroic+, coordinate positioning for additional Shattering Twilight targets.

### Healers
- Plan cooldown rotation for Entropic Unraveling (20 seconds of sustained raid damage).
- Manage the 23-second Twisting Obscurity DoT -- stagger healing cooldowns.
- Heal through Despotic Command absorb shields promptly.
- On Heroic+, be aware of raid-wide DoT from orb kills during Void Convergence.
- Dispel priorities: Despotic Command healing absorb after puddle drops.

### DPS
- Orbs are the #1 kill priority -- never let an orb reach the boss.
- On Heroic+, kill orbs one at a time and wait for the 8-second DoT to fall off between kills.
- Save major DPS cooldowns for the Entropic Unraveling 25% damage window.
- Interrupt/CC Fractured Projection clones immediately -- assign interrupt groups.
- On Mythic, identify unshielded clones quickly during Fractured Projection.
- On Mythic, plan for escalating Enduring Void orb counts in later waves.

## Difficulty Differences
### Normal
- Void Convergence orbs do not apply a raid-wide DoT when killed (can AoE them down freely).
- Shattering Twilight only targets the current tank (no additional marked players).
- No Nexus Shield mechanic on clones.
- No Enduring Void orb respawning mechanic.
- Generally more forgiving on puddle placement and beam dodging during Entropic Unraveling.

### Heroic
- Killing Concentrated Void orbs now applies an 8-second stacking raid-wide DoT, requiring staggered kills.
- Shattering Twilight marks additional non-tank players, creating multiple spike patterns.
- All other mechanics hit harder with tighter DPS/healing checks.
- Puddle management becomes more critical due to higher damage from standing in them.

### Mythic
- Nexus Shield added to Fractured Projection: all clones except one are shielded, requiring sequential identification and interrupting of the vulnerable clone.
- Enduring Void mechanic: killed orbs respawn as Enduring Void orbs in subsequent waves, causing progressive orb count escalation throughout the fight.
- Significantly tighter DPS check due to escalating orb waves.
- All damage values increased substantially.
- Arena space management becomes the primary challenge as puddle placement must be near-perfect.

## Common Wipe Causes
1. Void Convergence orb reaching the boss (instant wipe -- most common on progression).
2. Multiple Shadow Fracture casts completing due to missed interrupts on Fractured Projection clones.
3. Running out of arena space from poorly placed Despotic Command puddles and Entropic Unraveling puddles.
4. Raid deaths during Entropic Unraveling from rotating beams combined with sustained pulsing damage.
5. Tank death from excessive Destabilizing Strikes stacks due to late tank swap.
6. On Heroic+, raid deaths from stacking orb-kill DoTs by killing orbs too quickly.
7. On Mythic, being overwhelmed by escalating Enduring Void orb counts in later waves.
8. On Mythic, failing to identify and interrupt unshielded clones during Nexus Shield sequences.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **damage_taken: Void Convergence / Concentrated Void** -- any instance means orbs were too close to players or not killed fast enough.
- **damage_taken: Shadow Fracture** -- any instance means a clone cast was not interrupted; count occurrences to gauge interrupt discipline.
- **damage_taken: Shattering Twilight** (on non-marked players) -- indicates players failed to dodge spike lines.
- **damage_taken: Entropic Unraveling beam hits** -- players hit by rotating beams during the burn phase.
- **debuff stacks: Destabilizing Strikes** -- high stack counts before swap indicate late tank swaps; correlate with tank deaths.
- **debuff: Despotic Command** -- check player positions at debuff expiry to evaluate puddle placement quality.
- **buff: Entropic Unraveling (25% damage taken)** -- compare raid DPS during this window vs. overall; low burst here extends the fight.
- **death timing correlation** -- deaths during Twisting Obscurity DoT window suggest insufficient healing cooldown planning.
- **death timing correlation** -- deaths shortly after Despotic Command expiry suggest healing absorb was not healed through.
- **Heroic+: debuff stacks from orb kills** -- high simultaneous stacks indicate orbs killed too quickly/simultaneously.
- **Mythic: Shadow Fracture casts completed** -- if casts complete, check for wasted interrupts on Nexus Shield-protected clones.
- **Mythic: orb count per wave** -- track escalating Enduring Void orb counts and correlate with DPS performance over fight duration.
- **Puddle count over time** -- compare puddle accumulation rate to fight length; excessive puddles early indicate poor placement strategy.
