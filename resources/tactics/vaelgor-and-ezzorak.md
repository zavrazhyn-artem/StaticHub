---
boss: Vaelgor & Ezzorak
wcl_encounter_id: 3178
difficulty_variants: [normal, heroic, mythic]
mechanics:
  - name: Vaelwing
    type: tank_swap
    ability_ids: [1265131, 1265146]
    severity: high
    description: Stacking tank buff on Vaelgor that increases melee damage dealt; requires tank swap before stacks become lethal.
  - name: Rakfang
    type: tank_swap
    ability_ids: [1245645, 1264892]
    severity: high
    description: Tank swap debuff applied by Ezzorak that leaves a lingering vulnerability; swap tanks to let it fall off.
  - name: Twilight Bond
    type: tether
    severity: high
    description: Invisible health bond between Vaelgor and Ezzorak; if their HP differs by more than 10%, the lower boss heals and the raid takes escalating damage. Balance DPS between both bosses.
  - name: Nullzone
    type: positioning
    ability_ids: [1244672, 1252157]
    severity: moderate
    description: Ground zones that must be avoided or used to break tethers depending on assignment; coordinate with Nullbeam soakers.
  - name: Nullbeam
    type: soak
    ability_ids: [1262651, 1262623]
    severity: high
    description: Targeted beam that must be soaked by an assigned tank or immune player to prevent raid-wide damage.
  - name: Dread Breath
    type: fear
    ability_ids: [1244221, 1255979, 1255612]  # cast + 2 fear debuffs
    severity: high
    description: Frontal fear cone from Ezzorak; face the boss away from the raid and use fear immunities or trinkets.
  - name: Gloom
    type: soak
    ability_ids: [1245391]
    severity: high
    description: Stacking soak mechanic that applies Gloomtouched vulnerability; rotate soakers to avoid lethal stacks.
  - name: Void Howl
    type: orb_management
    ability_ids: [1244917]
    severity: high
    description: Spawns void orbs that must be managed and soaked or kited by assigned players before they reach the boss.
  - name: Voidbolt
    type: interrupt
    ability_ids: [1245175]
    severity: high
    description: Cast by Ezzorak and must be interrupted on rotation; missed interrupts cause heavy raid damage.
  - name: Midnight Manifestation
    type: add_management
    ability_ids: [1258744, 1255763]
    severity: moderate
    description: Persistent DoT and intermission add that must be handled by the raid; cleave down or kite per assignment.
  - name: Tail Lash
    type: dodge
    ability_ids: [1264467]
    severity: moderate
    description: Rear cone melee swipe from Vaelgor; melee must stay out of the tail arc.
  - name: Midnight Flames
    type: intermission
    ability_ids: [1249748]
    severity: high
    description: Triggers the intermission at 100 energy; raid must burn the Unbound Shadow add while surviving Midnight Flames raid damage.
  - name: Radiant Barrier
    type: shield_interrupt
    ability_ids: [1249595]
    severity: high
    description: Protective barrier cast during intermission that must be broken with coordinated burst or shield interrupts to allow damage to the add.
  - name: Cosmosis
    type: raid_damage
    ability_ids: [1263623, 1263626]
    severity: high
    description: Mythic-only cross-realm raid damage that requires precise positioning and cooldown rotations to survive.
avoidable_abilities:
  - Tail Lash
  - Dread Breath
  - Nullzone
  - Midnight Flames
  - Void Howl
role_mechanics:
  tank:
    - Vaelwing
    - Rakfang
    - Nullbeam
    - Impale
    - Shadowmark
    - Twilight Bond
  healer:
    - Gloom
    - Midnight Flames
    - Dread Breath
    - Cosmosis
    - Twilight Bond
  dps:
    - Voidbolt
    - Void Howl
    - Midnight Manifestation
    - Radiant Barrier
    - Gloom
    - Tail Lash
---

# Vaelgor & Ezzorak

## Overview
A two-boss encounter featuring two twilight dragons that must be tanked separately, kept within 10% health of each other, and killed simultaneously. The fight is a single phase with an intermission triggered at 100 energy (Midnight Flames). Key challenges include balancing boss health, coordinating Gloom orb soakers, managing Nullzone tethers, and burning the intermission add before the dragons return. Both dragons are attackable during the main phase but not during the intermission.

## Phases

### Phase 1: Main Combat
- Both dragons are active and must be tanked at least 15 yards apart.
- Tanks swap on both bosses after each tankbuster (Vaelwing / Rakfang).
- Raid must balance dragon health within 10% to avoid Twilight Bond damage buff.
- Phase continues until both dragons reach 100 energy.
- Transition: Midnight Flames -- dragons fly away, dealing raid-wide damage for 25 seconds.

### Intermission: Radiant Barrier
- A large Radiant Barrier spawns in the room. Standing inside reduces Midnight Flames damage.
- All Midnight Manifestation DoTs are removed from players.
- A Manifestation of Midnight add spawns and must be killed before the dragons return.
- Several players are marked with circles that pulse damage and explode after 4 seconds -- marked players move to edge of barrier.
- After the intermission, Phase 1 resumes.

## Key Mechanics

### Vaelwing (vaelwing)
- **Type**: Tank mechanic (Vaelgor)
- **What it does**: Deals physical and shadow damage to the tank with a knockback. Increases Vaelgor's melee damage with a stacking buff until a new target is hit.
- **Correct response**: Tank swap after each cast. The new tank taunts Vaelgor to reset the stacking melee buff.
- **Failure indicator**: Stacking melee damage buff on Vaelgor not being reset; tank deaths from escalating auto-attack damage.
- **Common mistakes**: Delayed tank swap leading to excessive stacking damage; getting knocked into the raid.

### Rakfang (rakfang)
- **Type**: Tank mechanic (Ezzorak)
- **What it does**: Deals massive physical and shadow damage to the tank. Applies a healing absorb shield on the current tank that persists until the ability hits a new target.
- **Correct response**: Tank swap after each cast so the healing absorb transfers to the fresh tank and the previous tank can be healed.
- **Failure indicator**: Tank death with active healing absorb debuff; healer mana drain from unswapped absorbs.
- **Common mistakes**: Not swapping, causing the healing absorb to stack and become unhealable.

### Twilight Bond (twilight_bond)
- **Type**: Raid-wide / positioning mechanic (Both dragons)
- **What it does**: If dragon health differs by more than 10% or if the dragons are tanked within 15 yards of each other, both dragons gain a 100% damage buff. If one dragon dies before the other, the surviving dragon gains a stacking 30% damage buff.
- **Correct response**: Keep dragons at least 15 yards apart. Balance DPS to keep health within 10%. Kill both at roughly the same time.
- **Failure indicator**: Twilight Bond buff appearing on boss frames; sudden spike in tank/raid damage; one dragon dying well before the other.
- **Common mistakes**: Unbalanced cleave damage; tanks drifting too close together during movement.

### Nullzone (nullzone)
- **Type**: Raid-wide mechanic (Vaelgor)
- **What it does**: Tethers all players to Vaelgor with a strong pull effect. Tethered players take escalating damage over time. Each broken tether deals raid-wide damage. On Heroic, breaking all tethers triggers an additional raid-wide explosion and short DoT. On Mythic, ground circles spawn after the first tether breaks.
- **Correct response**: Break tethers by running away from Vaelgor. Dip into Nullbeam to gain a debuff that weakens the pull, making it easier to break free. Stagger tether breaks to avoid overwhelming healers.
- **Failure indicator**: Players dying while tethered (damage_taken from Nullzone); large spikes of raid damage from simultaneous tether breaks; players pulled into Vaelgor's melee.
- **Common mistakes**: Not using Nullbeam to reduce pull strength; breaking all tethers simultaneously causing massive raid damage; slow classes failing to break free.

### Nullbeam (nullbeam)
- **Type**: Tank / raid utility mechanic (Vaelgor)
- **What it does**: A large cone-shaped beam aimed at the tank lasting 4 seconds. Standing in the beam applies a stacking debuff that reduces Nullzone pull strength.
- **Correct response**: Tank soaks the beam with cooldowns. During Nullzone, other players briefly dip into the beam to gain the pull-reduction debuff, then move out.
- **Failure indicator**: Excessive damage_taken from Nullbeam on non-tank players who stand in it too long.
- **Common mistakes**: Ignoring the beam during Nullzone; standing in beam too long and dying; beam aimed at raid due to poor tank positioning.

### Dread Breath (dread_breath)
- **Type**: Raid mechanic (Ezzorak)
- **What it does**: A cone-shaped blast toward a random marked player. Deals damage and fears all players hit. On Mythic, feared players move 50% faster.
- **Correct response**: Marked player moves away from the raid so the cone does not hit others. Raid avoids standing in the cone. Dispel feared players immediately.
- **Failure indicator**: Multiple players feared simultaneously; feared players running off the platform or into danger; fear debuff duration in logs.
- **Common mistakes**: Marked player standing in the raid causing mass fear; slow dispels on feared targets; on Mythic, feared players running out of range before dispel.

### Gloom (gloom)
- **Type**: Soak mechanic (Ezzorak)
- **What it does**: Ezzorak fires an orb toward the tank. The orb can be soaked by players to shrink it (up to 4-5 soaks on Normal, 5 on Heroic, 7 on Mythic). Each soak deals damage and applies a 12-second stacking DoT. If not fully soaked, the orb explodes at the room edge dealing massive raid damage and leaving a permanent puddle. On Heroic, soakers gain a 1-minute vulnerability debuff (500% increased damage from future soaks). On Mythic, soakers create AoE cleave and gain Gloomtouched, then Diminish (78-second debuff causing 1000% increased soak damage).
- **Correct response**: Assign soak rotation groups. Multiple players soak each orb to minimize puddle size. On Heroic/Mythic, rotate soakers so debuffed players do not soak again.
- **Failure indicator**: Large Gloom puddles on the floor; players dying to Gloom DoT stacks; players soaking with active vulnerability debuff (Diminish/Gloomtouched); raid damage from unsoaked explosion.
- **Common mistakes**: Same players soaking repeatedly and dying to stacking DoT; not enough soakers assigned; soaking with active vulnerability debuff on Heroic/Mythic.

### Void Howl (void_howl)
- **Type**: Raid-wide spread mechanic (Both dragons)
- **What it does**: Every player is marked with a small AoE circle. After a few seconds, circles explode and spawn Voidorb adds at each location.
- **Correct response**: Spread out so circles do not overlap. After circles resolve, group the Voidorbs together using knockbacks/grips, then AoE them down.
- **Failure indicator**: Overlapping circle damage; Voidorbs alive for extended periods casting Voidbolt.
- **Common mistakes**: Players stacking during Void Howl causing overlapping damage; ignoring Voidorb adds.

### Voidbolt (voidbolt)
- **Type**: Add mechanic (Voidorbs)
- **What it does**: Voidorbs cast Voidbolt dealing damage to random targets. Can be interrupted and crowd controlled.
- **Correct response**: Interrupt Voidbolt casts. Use crowd control (stuns, knockbacks) to group Voidorbs. Kill them quickly with AoE.
- **Failure indicator**: Sustained Voidbolt damage_taken entries in logs; Voidorbs alive past the next major mechanic.
- **Common mistakes**: Not assigning interrupts; letting Voidorbs free-cast while raid focuses bosses.

### Midnight Manifestation (midnight_manifestation)
- **Type**: Healer mechanic (Both dragons)
- **What it does**: Several random players are debuffed with a stacking DoT that persists throughout the entire phase. Applied repeatedly and constantly.
- **Correct response**: Healers maintain HoTs and throughput on afflicted targets. No player action required beyond staying in healing range.
- **Failure indicator**: Players dying to Midnight Manifestation DoT ticks; high stacks on individual players.
- **Common mistakes**: Healers tunnel-visioning other mechanics and ignoring persistent DoT damage; players with high stacks not using personal defensives.

### Tail Lash / Impale (tail_lash)
- **Type**: Positioning mechanic (Both dragons)
- **What it does**: Players caught behind either dragon take damage, knockback, and a short bleed DoT.
- **Correct response**: Never stand behind the dragons. Melee DPS and tanks position at the front/sides.
- **Failure indicator**: damage_taken from Tail Lash; players knocked into other mechanics.
- **Common mistakes**: Melee accidentally standing behind dragon during repositioning.

### Midnight Flames (midnight_flames)
- **Type**: Phase transition / raid-wide damage (Both dragons)
- **What it does**: At 100 energy, both dragons fly away and deal sustained raid-wide damage for 25 seconds, triggering the intermission.
- **Correct response**: Use raid cooldowns and healing cooldowns. Move into the Radiant Barrier as soon as it spawns.
- **Failure indicator**: Deaths during the 25-second damage window before reaching the barrier.
- **Common mistakes**: Not pre-positioning near barrier spawn location; running out of healing cooldowns.

### Radiant Barrier (radiant_barrier)
- **Type**: Intermission safety zone
- **What it does**: Large protective barrier that spawns during intermission. Standing inside reduces Midnight Flames damage significantly.
- **Correct response**: Entire raid stacks inside the barrier. Players with circle debuffs move to the barrier edge.
- **Failure indicator**: Players dying outside the barrier; taking full Midnight Flames damage.
- **Common mistakes**: Standing outside the barrier; debuffed players exploding in the center of the raid.

### Cosmosis (cosmosis)
- **Type**: Mythic-only mechanic
- **What it does**: Dragon clones spawn during the fight and replicate boss mechanics.
- **Correct response**: Treat clone mechanics the same as the originals -- dodge breath, soak orbs, break tethers from clones.
- **Failure indicator**: Deaths to clone-replicated abilities that were not dodged/soaked.
- **Common mistakes**: Tunnel-visioning real bosses and ignoring clone mechanics.

## Adds

### Voidorbs
- **When**: Spawn after Void Howl circles explode (one per player position)
- **Priority**: High -- kill immediately before they free-cast
- **Abilities**: Voidbolt (interruptible ranged damage)
- **Failure indicator**: Sustained Voidbolt damage in logs; adds alive for more than 10-15 seconds

### Manifestation of Midnight
- **When**: Spawns during the intermission when Radiant Barrier forms
- **Priority**: Top priority -- must die before dragons return
- **Abilities**: Marks several players with pulsing circles that explode after 4 seconds. Gains Unbound Shadow buff every 30 seconds (75% attack speed increase, 50% slow resistance).
- **Failure indicator**: Add still alive when dragons return; high Unbound Shadow stacks; player deaths from circle explosions inside the barrier group

## Role-Specific

### Tanks
- Tank dragons at least 15 yards apart at all times to avoid Twilight Bond.
- Swap on Vaelwing (Vaelgor) to reset the stacking melee damage buff.
- Swap on Rakfang (Ezzorak) to transfer the healing absorb debuff.
- Use cooldowns for Nullbeam cone soak.
- Position dragons so Dread Breath and Nullbeam cones face away from the raid.
- Coordinate health balancing -- call for DPS to switch targets if health diverges.

### Healers
- Maintain throughput on Midnight Manifestation DoT targets throughout the phase.
- Dispel Dread Breath fear immediately -- on Mythic, feared targets move 50% faster so speed is critical.
- Prepare raid cooldowns for Nullzone tether breaks (especially if multiple break simultaneously).
- Heavy healing during Midnight Flames transition and intermission.
- Watch for Gloom soakers taking high DoT stacks.
- Heal through circle explosions on marked players during intermission.

### DPS
- Balance damage on both dragons -- keep health within 10% to avoid Twilight Bond.
- Switch to Voidorbs immediately on Void Howl -- use AoE and cleave.
- Assigned soakers must soak Gloom orbs on rotation (respect vulnerability debuffs on Heroic/Mythic).
- Hard switch to Manifestation of Midnight during intermission -- this is the top priority add.
- Spread for Void Howl, stack for healing during Midnight Flames.

## Difficulty Differences

### Normal
- Nullzone tethers break individually without a final explosion.
- Gloom soakers have no vulnerability debuff -- same players can soak repeatedly.
- Voidorbs are less dangerous and can be cleaved down more casually.
- Intermission add has no Unbound Shadow stacking buff (or it stacks more slowly).
- Overall damage and health values are reduced.

### Heroic
- Nullzone: Breaking all tethers triggers an additional raid-wide explosion and a short DoT on the entire raid.
- Gloom: Soakers receive a 1-minute vulnerability debuff causing 500% increased damage from future soaks, requiring a soaker rotation.
- Intermission add gains Unbound Shadow buff every 30 seconds (75% attack speed, 50% slow resistance).
- Higher boss health and damage across all abilities.
- Tighter DPS check on intermission add.

### Mythic
- Nullzone: Ground circles (void zones) spawn after the first tether breaks, reducing available space.
- Gloom: Soakers create AoE cleave damage around them. Soakers gain Gloomtouched, then Diminish (78-second debuff causing 1000% increased soak damage), requiring even larger soak rotations.
- Dread Breath: Feared players move 50% faster, making dispels far more urgent.
- Cosmosis: Dragon clones spawn and replicate boss mechanics, effectively doubling the mechanics the raid must handle.
- Significantly higher health and damage values; tighter enrage timer.
- Intermission add is much more dangerous with Unbound Shadow stacking.

## Common Wipe Causes
1. Twilight Bond activation -- dragons health diverges past 10% or tanks position them too close, causing 100% damage buff and rapid tank/raid deaths.
2. Unsoaked or poorly soaked Gloom orbs -- room fills with permanent puddles, reducing available space until the raid runs out of room.
3. Intermission add not dying before dragons return -- Unbound Shadow stacks make the add increasingly lethal, and Midnight Flames resumes without barrier protection.
4. Mass fear from Dread Breath -- marked player stands in the raid, fearing multiple players into mechanics or off the platform.
5. Nullzone deaths -- players fail to break tethers and die to escalating tether damage, or simultaneous tether breaks overwhelm healers.
6. Voidorb free-casting -- Voidorbs left alive too long deal sustained damage that overwhelms healers during other mechanic overlaps.
7. One dragon dying before the other -- surviving dragon gains stacking 30% damage buff, quickly becoming unhealable.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **damage_taken from Nullzone**: Players failing to break tethers or taking excessive tether damage.
- **damage_taken from Gloom / Gloomtouched / Diminish**: Players soaking with active vulnerability debuffs.
- **damage_taken from Voidbolt**: Indicates Voidorbs lived too long or were not interrupted.
- **damage_taken from Tail Lash**: Players positioned behind dragons incorrectly.
- **damage_taken from Dread Breath**: Players standing in the cone or not moving out.
- **debuff: Twilight Bond**: Boss health imbalance or positioning error by tanks.
- **debuff: Midnight Manifestation stacks**: Track stack counts -- high stacks indicate healing deficiency.
- **debuff: Dread Breath (fear)**: Duration of fear debuffs indicates dispel speed.
- **debuff: Diminish / Gloomtouched (Mythic)**: Players soaking Gloom with active vulnerability.
- **damage_taken from Midnight Flames outside Radiant Barrier**: Players not inside the barrier during intermission.
- **Manifestation of Midnight kill timing**: How long the intermission add lives; compare to Unbound Shadow stack count.
- **death timing during Nullzone**: Deaths coinciding with Nullzone casts indicate tether mechanic failure.
- **death timing during Gloom**: Deaths shortly after Gloom soak indicate too many stacks or soaking with debuff.
- **boss health differential at any point**: Large health gaps between Vaelgor and Ezzorak indicate DPS balance issues.
- **Voidorb lifespan**: Time from spawn to death -- long-lived orbs indicate poor add management.
- **Cosmosis damage_taken (Mythic)**: Deaths to clone-replicated abilities indicate players ignoring clone mechanics.
