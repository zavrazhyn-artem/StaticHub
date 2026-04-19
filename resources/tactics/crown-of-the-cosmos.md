---
boss: Crown of the Cosmos
wcl_encounter_id: 3181
difficulty_variants: [normal, heroic, mythic]
phases:
  - id: phase_1
    name: "Phase 1: Sentinels"
    type: add_management
    summary: "Three Undying Sentinels with death immunity removed via Silverstrike Arrows; lieutenants Morium, Demiar, Vorelus active."
  - id: intermission_1
    name: "Intermission 1: Platform Fracture"
    type: intermission
    summary: "Stellar Emission pull with stacking raid damage; dodge Silverstrike Barrage; arrows clear stacks."
  - id: phase_2
    name: "Phase 2: Void Clone"
    type: burn_phase
    summary: "Void clone shares Alleria's health pool; keep bosses 30+ yards apart; manage puddles and ricochet adds."
  - id: intermission_2
    name: "Intermission 2: Full Platform Split"
    type: intermission
    summary: "Rotating orbs instead of stationary; platform splits into three separate sections after."
  - id: phase_3
    name: "Phase 3: Final Burn"
    type: burn_phase
    summary: "Kill Alleria before 3 Devouring Cosmos casts; grab feathers to cross platforms; manage Aspect of the End tethers."
mechanics:
  silverstrike_arrow:
    name: Silverstrike Arrow
    ability_ids: [1233602]
    type: positioning
    phase: all
    description: "Piercing arrow used to remove death immunity from adds and clear stacks from allies."
  grasp_of_emptiness:
    name: Grasp of Emptiness
    ability_ids: [1260026, 1260027]
    type: positioning
    phase: all
    description: "Targeted blue circle with obelisk beams; target angles beams away from raid, others clear out."
  corrupting_essence:
    name: Corrupting Essence
    ability_ids: [1261531]
    type: add_management
    phase: phase_1
    description: "Void Droplet death splash applies 30% increased damage taken; kill near Sentinels for amp."
  void_expulsion:
    name: Void Expulsion
    ability_ids: [1233819]
    type: puddle_placement
    phase: [phase_2]
    description: "Orbs spawn on ranged, explode, leave persistent puddles; consolidate in dead zones."
  stellar_emission:
    name: Stellar Emission
    ability_ids: [1234570]
    type: raid_damage
    phase: [intermission_1, intermission_2]
    description: "Pull toward center with stacking raid damage every 2s; arrows remove stacks."
  silverstrike_barrage:
    name: Silverstrike Barrage
    ability_ids: [1243981]
    type: dodge
    phase: [intermission_1, intermission_2]
    description: "Waves of arrows; on Heroic applies 800% arrow vulnerability for 8s — alternate soaking/dodging."
  dimensional_slash:
    name: Dimensional Slash
    ability_ids: [1260482, 1260427, 1260448]  # cast variants only — WCL doesn't attribute damage under this spell ID
    severity: minor  # downgraded — pure positional dodge, damage attributed under other spells in WCL
    type: dodge
    phase: phase_2
    description: "Cleave/slash pattern from the void clone; sidestep telegraphed arcs."
  voidstalker_sting:
    name: Voidstalker Sting
    ability_ids: [1237035, 1237038]
    type: raid_damage
    phase: [phase_2, phase_3]
    description: "Stacking DoT on multiple players; cleanse via arrows in P2, heal through in P3."
  dark_hand:
    name: Dark Hand
    ability_ids: [1233787]
    type: tankbuster
    phase: phase_1
    description: "Morium tank buster with knockback; use defensives and mind positioning."
  ravenous_abyss:
    name: Ravenous Abyss
    ability_ids: [1243753]
    type: dodge
    phase: phase_1
    description: "Vorelus 15yd AoE applying 70% damage reduction debuff; clear out immediately."
  null_corona:
    name: Null Corona
    ability_ids: [1233865]
    type: absorb_shield
    phase: all
    description: "Random player absorb shield; heal through, do NOT dispel (transfers to another player)."
  rift_slash:
    name: Rift Slash
    ability_ids: [1246461, 1246462]
    type: tank_swap
    phase: phase_2
    description: "Clone tank buster applying stacking 10% stat reduction; swap at ~3 stacks."
  umbral_tether:
    name: Umbral Tether
    ability_ids: [1237844, 1233470]
    type: tether
    phase: phase_2
    description: "Tethers between players must be managed via positioning to avoid snap damage."
  ranger_captains_mark:
    name: Ranger Captain's Mark
    ability_ids: [1259856, 1259861]  # cast + debuff on player
    type: positioning
    phase: phase_2
    description: "Marks players for Silverstrike Ricochet; coordinate positioning to bounce arrow through adds."
  echoing_darkness:
    name: Echoing Darkness
    ability_ids: [1233778]
    type: energy_ramp
    phase: [phase_2, phase_3]
    description: "Stacking buff on boss/adds that ramps damage — burn priority target to prevent overrun."
  void_barrage:
    name: Void Barrage
    ability_ids: [1260000]
    type: dodge
    phase: phase_2
    description: "Clone barrage pattern; dodge telegraphed projectile spread."
  empowering_darkness:
    name: Empowering Darkness
    ability_ids: [1237251, 1281454]
    type: shield_interrupt
    phase: phase_2
    description: "Cosmic Barrier / empowering cast — DPS switch to shield break."
  cosmic_barrier:
    name: Cosmic Barrier
    ability_ids: [1261287]
    type: absorb_shield
    phase: phase_2
    description: "15% health shield on clone that pulses raid damage until broken; full DPS swap."
  cosmic_radiation:
    name: Cosmic Radiation
    ability_ids: [1260766]
    type: raid_damage
    phase: phase_3
    description: "Pulsing raid-wide damage during the final burn; healer cooldown rotation."
  aspect_of_the_end:
    name: Aspect of the End
    ability_ids: [1239080, 1239111]
    type: tether
    phase: phase_3
    description: "Tethers multiple players (one always on tank) with stacking healing reduction; break sequentially with tank swaps."
  devouring_cosmos:
    name: Devouring Cosmos
    ability_ids: [1238843]
    type: phase_transition
    phase: phase_3
    description: "Consumes platform; grab a feather to jump to next section. 3 casts = soft enrage."
  berserk:
    name: Berserk
    ability_ids: [27680]
    type: raid_damage
    phase: phase_3
    description: "Hard enrage — unrecoverable raid-wide damage if boss is not killed in time."
avoidable_abilities:
  - silverstrike_barrage
  - grasp_of_emptiness
  - void_expulsion
  - ravenous_abyss
  - dimensional_slash
  - void_barrage
  - devouring_cosmos
  - corrupting_essence
role_mechanics:
  tanks:
    - dark_hand
    - rift_slash
    - aspect_of_the_end
  healers:
    - null_corona
    - voidstalker_sting
    - stellar_emission
    - cosmic_radiation
    - cosmic_barrier
  dps:
    - silverstrike_arrow
    - corrupting_essence
    - empowering_darkness
    - cosmic_barrier
    - ranger_captains_mark
    - echoing_darkness
    - devouring_cosmos
---

# Crown of the Cosmos

## Overview
Three-phase encounter with two intermissions. The raid platform progressively fractures: it starts as one piece, cracks during the first intermission, splits further during the second, and becomes three fully separate sections by Phase 3. The fight features Alleria and three lieutenant-style adds (Phase 1), a void clone of Alleria (Phase 2), and a burn phase with platform-hopping (Phase 3). The core throughline mechanic is Silverstrike Arrows, which players must aim to remove void effects from enemies and debuffs from allies.

## Phases

### Phase 1: Sentinels
- Three Undying Sentinels are active alongside Alleria's lieutenants (Morium, Demiar, Vorelus).
- Sentinels pulse raid-wide damage that increases if no melee is nearby.
- Sentinels have death immunity that must be removed by aiming Silverstrike Arrows through them.
- Void Droplets spawn throughout the phase; killing them near Sentinels amplifies damage on the Sentinels.
- Phase ends when all three Sentinels are killed, triggering Intermission 1.

### Intermission 1: Platform Fracture
- All players are knocked up; Alleria smashes the platform, creating cracks.
- Stellar Emission pulls players toward the center with increasing force while dealing stacking raid-wide damage every 2 seconds.
- Silverstrike Barrage fires multiple waves of arrows that players must dodge.
- Silverstrike Arrows can remove Stellar Emission damage stacks from players.

### Phase 2: Void Clone
- Alleria summons a void clone that shares her health pool.
- Both Alleria and the clone gain a 10% stacking damage buff when within 30 yards of each other -- they must be kept separated.
- Void Expulsion orbs act as a soft enrage by filling the room with persistent puddles.
- Silverstrike Ricochet bounces a single arrow between marked players to break add death immunity.
- Phase ends when the clone is killed (shared health pool).

### Intermission 2: Full Platform Split
- Identical to Intermission 1 but with orbs spinning around the room instead of stationary explosions.
- On Heroic, rotating walls of orbs force players to cross platform cracks, taking Volatile Fissure DoT damage.
- After this intermission, the platform splits into three fully separate sections.

### Phase 3: Final Burn
- Kill Alleria before she casts Devouring Cosmos three times.
- Devouring Cosmos consumes the current platform with void energy; players must grab feathers to jump to a new platform.
- Aspect of the End tethers players together, reducing healing and creating dangerous tether-break windows.
- No adds; pure execution and burn phase.

## Key Mechanics

### Silverstrike Arrow (silverstrike_arrow)
- **Type**: Core raid mechanic (all roles)
- **What it does**: White-line projectile fired from the boss toward marked players. Pierces through all enemies and allies, dealing damage on impact. Removes void effects (death immunity, DoTs, buffs) from enemies hit.
- **Correct response**: Aim the arrow so it pierces through Sentinels (P1) or adds (P2) to remove their death immunity. In intermissions, use arrows to remove Stellar Emission stacks from allies.
- **Failure indicator**: Sentinels retain death immunity (cannot be killed); adds reach 100 energy and enrage.
- **Common mistakes**: Standing where the arrow hits nothing useful; not positioning to thread arrows through multiple targets; taking unnecessary arrow damage when not the intended target.

### Grasp of Emptiness (grasp_of_emptiness)
- **Type**: Targeted raid mechanic (random player)
- **What it does**: A blue circle appears around the targeted player with three obelisks that emit beams. The beams follow the player's movement, dealing damage to anyone struck by the circle or beams.
- **Correct response**: The targeted player repositions to angle beams away from the raid. All other players move out of the circle and away from beam paths.
- **Failure indicator**: Multiple players taking beam damage (damage_taken entries for Grasp of Emptiness on non-targeted players).
- **Common mistakes**: Not moving quickly enough; angling beams into the raid stack; other players not moving out of the circle.

### Void Expulsion (void_expulsion)
- **Type**: Raid-wide area denial (targets ranged)
- **What it does**: An orb spawns near ranged players, explodes after a short delay dealing raid-wide damage, and leaves a persistent damaging puddle on the ground.
- **Correct response**: Ranged players bait orbs near existing puddles to consolidate area denial. Move away before explosion.
- **Failure indicator**: Puddles spread across the entire platform (soft enrage); players standing in puddles taking sustained damage.
- **Common mistakes**: Baiting orbs in clean areas; not moving out before explosion; poor puddle consolidation leading to no room remaining.

### Interrupting Tremor (interrupting_tremor)
- **Type**: Raid mechanic (Demiar ability, 40-yard range)
- **What it does**: Pulsing AoE from Demiar that deals damage and silences all players within 40 yards.
- **Correct response**: Move out of 40-yard range immediately to avoid silence.
- **Failure indicator**: Players silenced (silence debuff applied); healers unable to cast during critical moments.
- **Common mistakes**: Not tracking Demiar's position; staying in range while casting important abilities.

### Ravenous Abyss (ravenous_abyss)
- **Type**: DPS mechanic (Vorelus ability, 15-yard range)
- **What it does**: Vorelus pulses damage in a 15-yard AoE. Players hit have their damage output reduced by 70%.
- **Correct response**: Move away from the affected zone immediately.
- **Failure indicator**: Players with 70% damage reduction debuff; significantly lower DPS output during the phase.
- **Common mistakes**: Standing in the abyss while tunneling damage on Sentinels; not noticing the debuff application.

### Corrupting Essence (corrupting_essence)
- **Type**: Add management mechanic
- **What it does**: Void Droplets spawn throughout Phase 1. When killed, they splash a puddle that applies a 30% increased damage taken debuff for 30 seconds to all entities hit (players and enemies).
- **Correct response**: Kill Void Droplets near the Undying Sentinels to amplify damage against them.
- **Failure indicator**: Droplets killed away from Sentinels (wasted damage amp); players standing in death splash taking unnecessary debuff.
- **Common mistakes**: Killing droplets in the raid stack; not positioning droplet kills strategically near Sentinels.

### Null Corona (null_corona)
- **Type**: Healer mechanic (random player)
- **What it does**: Applies a massive absorb shield to a random player. If dispelled, the remaining absorb amount transfers to another player.
- **Correct response**: Heal through the absorb shield. Do NOT dispel unless the player is in immediate danger of dying.
- **Failure indicator**: Absorb bouncing between multiple players (repeated dispels); player deaths under absorb when healing is insufficient.
- **Common mistakes**: Reflexively dispelling the absorb, causing it to jump; not prioritizing healing on the affected target.

### Dark Hand (dark_hand)
- **Type**: Tank buster (Morium, Phase 1)
- **What it does**: Deals heavy physical and magic damage to the tank with a knockback.
- **Correct response**: Active tank uses defensive cooldowns. Position with knockback path in mind.
- **Failure indicator**: Tank death to Dark Hand; tank knocked into hazardous areas.
- **Common mistakes**: Not using defensives; poor positioning leading to knockback into puddles or off platform.

### Stellar Emission (stellar_emission)
- **Type**: Intermission raid-wide mechanic
- **What it does**: Players are slowly pulled toward the platform center with increasing force. Deals stacking raid-wide damage every 2 seconds. Orbs mark explosion zones that deal damage and knock players up.
- **Correct response**: Fight the pull mechanic; dodge orb explosion zones; use Silverstrike Arrows to remove damage stacks.
- **Failure indicator**: High stack count of Stellar Emission debuff; players pulled into center; deaths from orb explosions.
- **Common mistakes**: Not actively countering the pull; ignoring orb positions; not using arrows to clear stacks.

### Silverstrike Barrage (silverstrike_barrage)
- **Type**: Intermission dodge mechanic
- **What it does**: Multiple waves of arrows fired from Alleria across the platform.
- **Correct response**: Move to safe spaces between arrow lines. On Heroic, being hit applies 800% increased arrow damage taken for 8 seconds -- alternate between soaking and dodging.
- **Failure indicator**: Players hit by multiple arrow waves; on Heroic, players with the 800% vulnerability debuff being hit again.
- **Common mistakes**: Panicking and running into arrows; on Heroic, not tracking the vulnerability debuff timer.

### Cosmic Barrier (cosmic_barrier)
- **Type**: DPS check (Phase 2)
- **What it does**: Applies a shield equal to 15% of the clone's health. While the shield is active, the entire raid takes pulsing damage.
- **Correct response**: All DPS switch to break the shield as quickly as possible.
- **Failure indicator**: Extended shield duration; high raid damage from prolonged pulsing; healer mana drain.
- **Common mistakes**: Not switching to the clone when barrier is applied; continuing to damage Alleria instead of the shielded clone.

### Silverstrike Ricochet (silverstrike_ricochet)
- **Type**: Coordinated DPS/positioning mechanic (Phase 2)
- **What it does**: Several players are marked with Ranger Captain's Mark. A single arrow bounces between marked players. The bouncing arrow can break death immunity on adds.
- **Correct response**: Marked players coordinate positioning to aim the bouncing arrow through adds. Kill adds before they reach 100 energy.
- **Failure indicator**: Adds reaching 100 energy (gain 300% movement speed, 500% damage, CC immunity -- effectively a wipe mechanic). Arrow bounces missing adds entirely.
- **Common mistakes**: Marked players not aligning properly; ignoring add energy levels; poor communication on arrow bounce direction.

### Voidstalker Sting (voidstalker_sting)
- **Type**: Healer/raid DoT (multiple players)
- **What it does**: Applies a stackable DoT to multiple players. Lasts 10 seconds on Normal, 25 seconds on Heroic.
- **Correct response**: In Phase 2, stand in Silverstrike Arrow paths together to remove the DoT. In Phase 3, heal through (no arrow removal available).
- **Failure indicator**: High stack count on players; deaths from sustained DoT damage; failure to use arrows for removal in P2.
- **Common mistakes**: Not grouping for arrow cleanse in P2; healers not prioritizing stung targets in P3.

### Rift Slash (rift_slash)
- **Type**: Tank buster / tank swap (Phase 2, clone ability)
- **What it does**: Deals physical damage and applies a 10% stat reduction debuff for 20 seconds. Debuff stacks.
- **Correct response**: Tank swap at approximately 3 stacks. Execute swaps quickly to minimize the time both bosses are near each other (they gain 10% damage buff within 30 yards).
- **Failure indicator**: Tank with 4+ stacks; both bosses within 30 yards for extended periods (stacking damage buff); tank death from stat reduction.
- **Common mistakes**: Late tank swaps; tanks running through the middle (bringing bosses close together); not tracking debuff stacks.

### Volatile Fissure (volatile_fissure)
- **Type**: Environmental hazard (Phase 2+)
- **What it does**: Crossing the cracks/splits in the platform applies a DoT debuff.
- **Correct response**: Avoid crossing platform splits whenever possible.
- **Failure indicator**: Players with Volatile Fissure DoT debuff; unnecessary damage taken from crossing.
- **Common mistakes**: Careless movement across cracks; not planning movement paths to avoid splits.

### Aspect of the End (aspect_of_the_end)
- **Type**: Tether mechanic / tank swap (Phase 3)
- **What it does**: Arrows tether several players together (range shown as circles). While tethered, players receive 10% reduced healing (stacks). Breaking a tether by moving out of range deals raid-wide damage and applies 300% physical damage vulnerability debuff. One tether is always on the active tank.
- **Correct response**: Break tethers one at a time in a controlled sequence. Coordinate tank swaps when the tank tether needs breaking. Ensure the raid can survive each break's damage.
- **Failure indicator**: Multiple tethers broken simultaneously (raid-wide damage spike); players with 300% physical vulnerability dying to subsequent damage; prolonged tethers causing healing deficit.
- **Common mistakes**: Breaking multiple tethers at once; not coordinating tank swaps with tether breaks; healers not preparing for break damage.

### Devouring Cosmos (devouring_cosmos)
- **Type**: Platform transition / soft enrage (Phase 3)
- **What it does**: Alleria calls void energy to consume the current platform section. Feathers scatter from her quiver. Players must grab a feather and use the jump buff to reach a new platform. Void energy deals massive damage to anyone touching it. Boss casts this a maximum of 3 times (soft enrage).
- **Correct response**: Every player grabs a feather immediately and jumps to the next safe platform section.
- **Failure indicator**: Players failing to grab feathers (death to void energy); players left behind on consumed platform; boss reaching third cast (enrage).
- **Common mistakes**: Not grabbing feather quickly enough; jumping to wrong platform; low DPS causing all three platforms to be consumed.

## Adds

### Undying Sentinel (x3, Phase 1)
- **When**: Active from Phase 1 start
- **Priority**: Primary kill target in Phase 1; all three must die to trigger intermission
- **Abilities**: Constant raid-wide pulse damage that increases if no melee is nearby. Death immunity that must be removed by Silverstrike Arrows.
- **Failure indicator**: Extended Phase 1 duration; escalating raid damage from pulses; Sentinels not losing death immunity (arrows not aimed properly).

### Void Droplet (Phase 1)
- **When**: Spawn throughout Phase 1
- **Priority**: Secondary; kill near Sentinels for damage amplification
- **Abilities**: Death splash applies 30% increased damage taken debuff for 30 seconds (Corrupting Essence) to all nearby entities.
- **Failure indicator**: Droplets killed away from Sentinels (no damage amp value); players accidentally hit by death splash.

### Void Clone of Alleria (Phase 2)
- **When**: Summoned at Phase 2 start
- **Priority**: Primary kill target (shares health pool with Alleria)
- **Abilities**: Cosmic Barrier (15% health shield with raid-wide pulsing damage), Rift Slash (tank buster with stat reduction). Gains 10% stacking damage buff when within 30 yards of Alleria.
- **Failure indicator**: Clone and Alleria within 30 yards (damage buff stacking); Cosmic Barrier not broken quickly; room filled with Void Expulsion puddles before clone dies.

### Ricochet Adds (Phase 2)
- **When**: Spawn during Phase 2
- **Priority**: High -- must die before reaching 100 energy
- **Abilities**: Build energy over time. At 100 energy: gain 300% movement speed, 500% damage, CC immunity.
- **Failure indicator**: Add reaching 100 energy (effectively a wipe); Silverstrike Ricochet not aimed through adds.

## Role-Specific

### Tanks
- **Phase 1**: Use defensives for Dark Hand (heavy physical+magic damage with knockback). Keep melee near Sentinels to prevent pulse damage escalation.
- **Phase 2**: Keep Alleria and void clone separated (30+ yards apart). Tank swap on Rift Slash at ~3 stacks. Execute swaps quickly to minimize proximity time.
- **Phase 3**: Aspect of the End always tethers the active tank. Coordinate tank swaps with tether breaks. Use defensives when breaking tethers due to 300% physical vulnerability debuff.

### Healers
- **All Phases**: Manage Null Corona absorb shields -- heal through, do NOT dispel unless target will die.
- **Phase 1**: Heal through Sentinel pulse damage and Void Expulsion raid hits. Watch for Interrupting Tremor silence (move out of 40-yard range).
- **Phase 2**: Heavy healing during Cosmic Barrier (pulsing raid damage). Manage Voidstalker Sting -- coordinate with DPS for arrow cleanse.
- **Intermissions**: Heal through Stellar Emission stacking damage. Manage cooldowns for increasing raid damage.
- **Phase 3**: Voidstalker Sting has no arrow removal -- must heal through. Prepare cooldowns for each Aspect of the End tether break. Dispel priorities shift here as healing reduction stacks.

### DPS
- **Phase 1**: Aim Silverstrike Arrows through Sentinels to remove death immunity. Kill Void Droplets near Sentinels for damage amp. Avoid Ravenous Abyss (70% damage reduction).
- **Phase 2**: Break Cosmic Barrier ASAP. Coordinate Silverstrike Ricochet bounces to hit adds. Kill adds before 100 energy. Consolidate Void Expulsion puddles.
- **Phase 3**: Maximum burn on Alleria. Target priority is boss-only (no adds). Break Aspect of the End tethers one at a time. Grab feathers immediately during Devouring Cosmos.

## Difficulty Differences

### Normal
- Void Expulsion orbs are less frequent, fewer puddles to manage.
- Grasp of Emptiness targets only 1 player at a time.
- Voidstalker Sting lasts 10 seconds (manageable without arrow cleanse).
- Intermission 2 has stationary orb explosions (simpler to dodge).
- Silverstrike Barrage has no vulnerability debuff on hit.

### Heroic
- Void Expulsion frequency increased -- significantly more area denial, especially in Phase 2.
- Grasp of Emptiness targets 2 players simultaneously (double the beam management).
- Voidstalker Sting extended to 25 seconds (must use arrow cleanse in P2).
- Silverstrike Barrage hit applies 800% increased arrow damage taken for 8 seconds (cannot be hit by consecutive waves).
- Intermission 2 features rotating walls of orbs that force players to cross platform cracks, taking Volatile Fissure DoT.

### Mythic
- Mythic-specific mechanics are not yet documented on MythicTrap as of this writing. Expected changes based on encounter design patterns:
  - Additional mechanic overlaps and tighter timing windows.
  - Possible new abilities or mechanic mutations per phase.
  - Stricter DPS checks on Sentinels, Clone, and Phase 3 burn.
  - This section should be updated once mythic data becomes available.

## Common Wipe Causes
1. **Phase 1 -- Sentinels not dying**: Arrows not aimed through Sentinels to remove death immunity; raid damage from Sentinel pulses becomes unhealable.
2. **Phase 2 -- Room filled with puddles**: Poor Void Expulsion bait positioning fills the platform with persistent damage puddles (soft enrage).
3. **Phase 2 -- Adds reaching 100 energy**: Silverstrike Ricochet not aimed properly; add enrages with 300% speed and 500% damage.
4. **Phase 2 -- Bosses too close**: Alleria and void clone within 30 yards, gaining stacking 10% damage buff that overwhelms tanks and healers.
5. **Phase 3 -- Devouring Cosmos failures**: Players not grabbing feathers in time; deaths to void energy consuming the platform.
6. **Phase 3 -- Uncontrolled tether breaks**: Multiple Aspect of the End tethers broken simultaneously, causing unsurvivable raid damage.
7. **Intermissions -- Stellar Emission stacks**: Not using arrows to clear damage stacks; healers unable to keep up with escalating raid damage.
8. **All Phases -- Null Corona dispels**: Reflexive dispelling causes the absorb to bounce between players, draining healer resources.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:

- **damage_taken: Void Expulsion** -- Players standing in puddles or not moving from orb explosions. High damage_taken here indicates poor puddle placement.
- **damage_taken: Grasp of Emptiness** -- Non-targeted players hit by beams. Indicates poor positioning by the targeted player or slow reactions by the raid.
- **damage_taken: Interrupting Tremor** -- Players silenced within 40 yards of Demiar. Indicates positioning failures.
- **damage_taken: Ravenous Abyss** -- Players hit, indicating they stood in the 15-yard AoE. Check if they also have the 70% damage reduction debuff.
- **damage_taken: Volatile Fissure** -- Unnecessary platform crack crossings.
- **damage_taken: Stellar Emission** -- High stack counts during intermissions. Check if arrows were used for stack removal.
- **damage_taken: Silverstrike Barrage** -- Players hit by arrow waves. On Heroic, check for 800% vulnerability debuff followed by second hit.
- **damage_taken: Devouring Cosmos** -- Players failing to grab feathers and jump. Usually results in death.
- **debuffs: Null Corona** -- Track dispel events. Multiple applications in quick succession indicate improper dispelling.
- **debuffs: Voidstalker Sting** -- High stack counts, especially in P3 where no arrow cleanse is available. Deaths with active stings indicate healing gaps.
- **debuffs: Rift Slash** -- Tank stacks exceeding 3. Indicates late tank swaps.
- **debuffs: Aspect of the End** -- Multiple simultaneous tether breaks (raid damage spikes). Track 300% physical vulnerability debuff applications.
- **debuffs: Corrupting Essence** -- 30% increased damage taken on players (not Sentinels). Indicates Void Droplets killed in wrong position.
- **debuffs: Ravenous Abyss** -- 70% damage reduction on DPS players. Indicates standing in Vorelus AoE.
- **add energy: Ricochet adds** -- Track energy gain rate. Adds reaching 100 energy is a critical failure.
- **Cosmic Barrier duration** -- Time from shield application to break. Longer durations mean more raid damage taken.
- **boss proximity: Alleria + Clone** -- If both bosses are within 30 yards, check for stacking damage buff. Correlate with tank swap timing.
- **death timing patterns**: Deaths during Intermission suggest Stellar Emission stack management failure. Deaths in late P2 suggest puddle/space management failure. Deaths during Devouring Cosmos suggest feather pickup failure. Deaths after tether breaks suggest uncoordinated break timing.
- **Phase 3 cast count: Devouring Cosmos** -- If boss reaches 3 casts, the raid has hit soft enrage. Check overall DPS and time lost to mechanics.
