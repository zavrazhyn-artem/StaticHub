---
boss: Chimaerus the Undreamt God
wcl_encounter_id: 3306
difficulty_variants: [normal, heroic, mythic]
mechanics:
  - name: Alndust Upheaval
    ability_ids: [1262289]
    type: soak
    description: Soak circle on tank; soakers are sent to the Rift realm. Two groups alternate to manage Rift Vulnerability on Heroic+.
  - name: Rending Tear
    ability_ids: [1272689]
    type: positioning
    description: Frontal cone dealing heavy physical damage plus bleed and knockback. Tank faces boss away from raid.
  - name: Caustic Phlegm
    ability_ids: [1246621, 1246653]
    type: raid_damage
    description: Raid-wide nature DoT lasting 12 seconds. Healers ramp throughput; defensives if overlapping.
  - name: Rift Sickness
    ability_ids: [1250953]
    type: absorb_shield
    description: Per-add raid-wide healing absorb. Kill adds quickly to prevent stacking absorbs.
  - name: Alnshroud
    ability_ids: [1245727, 1245698]
    type: shield_interrupt
    description: Manifestation add absorb shield. Break in Rift, finish add in Reality. Leaves puddle on break.
  - name: Consume
    ability_ids: [1245396, 1245844, 1252863, 1252956]
    type: phase_transition
    description: At 100 energy, 10s channel of raid-wide pulsing damage; all surviving adds are eaten, empowering the boss.
  - name: Corrupted Devastation
    ability_ids: [1252933]
    type: dodge
    description: Boss soars across a marked line, stunning and damaging players hit, spawning adds and puddles.
  - name: Consuming Miasma
    ability_ids: [1258192]
    type: puddle_placement
    description: Heroic+ dispellable debuff creating 10-yard explosions that destroy nearby puddles. Dispel on puddles.
  - name: Ravenous Dive
    ability_ids: [1245396]
    type: phase_transition
    description: Boss smashes down dealing raid damage, eating remaining adds, and restarting Stage 1.
  - name: Dissonance
    ability_ids: [1252933]
    type: realm_split
    description: Mythic only. Players pulse massive damage to nearby allies in the opposite realm. Maintain permanent realm separation.
  - name: Rift Madness
    ability_ids: [1264756]
    type: tank_swap
    description: Mythic only. Two Rift players (one always a healer) take escalating damage until Reality players physically rescue them by standing on them to trigger a realm swap.
  - name: Colossal Strikes
    ability_ids: [1262053, 1262059, 1262020]
    type: tankbuster
    description: Three-hit tankbuster from Colossal Horror adds dealing heavy physical and nature damage. Requires active mitigation.
  - name: Discordant Roar
    ability_ids: [1249207]
    type: add_management
    description: Raid-wide physical damage on Colossal Horror spawn with stacking 10% per-stack damage amp on subsequent Roars.
  - name: Essence Bolt
    ability_ids: [1261997]
    type: interrupt
    description: Single-target nature damage from Haunting Essences. Interrupt priority when Fearsome Cry is not up.
  - name: Fearsome Cry
    ability_ids: [1261997]
    type: interrupt
    description: Haunting Essence raid-wide fear and nature damage. Must be interrupted every cast.
  - name: Rift Vulnerability
    ability_ids: [1253744]
    type: energy_ramp
    description: Heroic+ stacking debuff from Alndust Upheaval soaks causing 600% increased soak damage. Rotate two groups strictly.
avoidable_abilities:
  - ability_id: 1272689
    name: Rending Tear
    notes: Frontal cone; only the tank should be hit.
  - ability_id: 1252933
    name: Corrupted Devastation
    notes: Marked beam line; dodge the flight path.
  - ability_id: 1258192
    name: Lingering Miasma
    notes: Puddles left by broken Alnshroud shields and Corrupted Devastation.
  - ability_id: 1262289
    name: Alndust Upheaval
    notes: Only the designated soak group should enter the circle; others stay clear.
  - ability_id: 1253744
    name: Rift Vulnerability
    notes: Heroic+ -- same group must not soak twice in a row while debuff is active.
  - ability_id: 1264756
    name: Rift Madness
    notes: Mythic -- debuffed players must be rescued quickly; untouched escalation is fatal.
role_mechanics:
  tank:
    - ability_id: 1272689
      name: Rending Tear
      action: Face boss away from raid; use active mitigation.
    - ability_ids: [1262053, 1262059, 1262020]
      name: Colossal Strikes
      action: Active mitigation on the three-hit combo from Colossal Horror adds.
    - ability_id: 1245727
      name: Alnshroud
      action: Pick up Manifestation adds when they teleport to Reality after shield break.
    - ability_id: 1262289
      name: Alndust Upheaval
      action: Position for the soak circle; coordinate realm responsibilities on Mythic.
  healer:
    - ability_ids: [1246621, 1246653]
      name: Caustic Phlegm
      action: Ramp throughput for the 12s raid DoT; stack defensives on overlaps.
    - ability_id: 1245396
      name: Consume
      action: Assign cooldowns across the 10s pulsing channel.
    - ability_id: 1250953
      name: Rift Sickness
      action: Track healing absorb stacks; push healing or prioritize add deaths.
    - ability_id: 1258192
      name: Consuming Miasma
      action: Heroic+ -- dispel afflicted players ON puddles to clear them.
    - ability_id: 1264756
      name: Rift Madness
      action: Mythic -- one healer is always targeted; pre-assign rescue partners.
  dps:
    - ability_id: 1245727
      name: Alnshroud
      action: Break Manifestation shields fast in the Rift realm.
    - ability_id: 1245396
      name: Consume
      action: Kill all adds before the 100-energy transition so the boss does not empower.
    - ability_id: 1261997
      name: Fearsome Cry / Essence Bolt
      action: Interrupt Fearsome Cry on every cast; interrupt Essence Bolt when available.
    - ability_id: 1252933
      name: Corrupted Devastation
      action: Dodge the beam and swap to spawned adds immediately.
    - ability_id: 1249207
      name: Discordant Roar
      action: Burst Colossal Horror adds down before stacks amplify further Roars.
    - ability_id: 1264756
      name: Rift Madness
      action: Mythic Reality-side DPS execute rescue assignments on debuffed Rift players.
---

# Chimaerus the Undreamt God

## Overview
Chimaerus the Undreamt God is a two-phase encounter that alternates between the Reality realm and the Rift realm. The fight revolves around managing soak groups to send players into the Rift, killing Manifestation adds before phase transitions consume them (buffing the boss), and clearing puddles via dispel mechanics. The core challenge is add prioritization and realm coordination, with Mythic adding permanent realm separation and rescue mechanics.

## Phases
### Phase 1: Reality Realm (Stage 1)
- The raid splits into two groups that alternate soaking Alndust Upheaval circles on the tank.
- Soaking sends players to the Rift realm where Manifestation adds spawn.
- Adds must have their Alnshroud shields broken in the Rift, then be finished off in Reality.
- At 100 energy, the boss casts Consume to transition to Phase 2. All surviving adds are eaten.

### Phase 2: Rift Realm (Stage 2)
- The boss marks lines across the room and soars over them (Corrupted Devastation), spawning adds and puddles.
- The raid must dodge the flight path, kill spawned adds, and clear puddles.
- Phase ends with Ravenous Dive -- the boss smashes down, eating remaining adds, and Stage 1 restarts.

## Key Mechanics

### Alndust Upheaval (alndust_upheaval)
- **Type**: Raid soak / realm transition mechanic
- **What it does**: Places a soak circle on the current tank. Damage is split among all soakers. Players who soak are sent to the Rift realm where Manifestation adds spawn.
- **Correct response**: Designated soak group moves into the circle. Two groups alternate soaking to manage the Rift Vulnerability debuff (Heroic+).
- **Failure indicator**: Too few soakers results in lethal damage. On Heroic+, soaking with Rift Vulnerability (600% increased soak damage) causes deaths.
- **Common mistakes**: Wrong group soaking (double-dipping vulnerability), not enough soakers splitting damage, DPS missing the soak entirely.

### Rending Tear (rending_tear)
- **Type**: Tank mechanic (frontal cone)
- **What it does**: Large frontal cone dealing massive physical damage, applying a short bleed, and knocking back all players hit.
- **Correct response**: Tank faces the boss away from the raid. Active mitigation required.
- **Failure indicator**: Non-tank players taking Rending Tear damage in logs. Multiple players knocked back.
- **Common mistakes**: Tank not facing boss away from raid, melee standing in front of boss, tank not using cooldowns.

### Caustic Phlegm (caustic_phlegm)
- **Type**: Raid-wide healing check
- **What it does**: Applies a raid-wide nature DoT lasting 12 seconds.
- **Correct response**: Healers ramp healing throughput. Defensive cooldowns if overlapping with other damage.
- **Failure indicator**: Deaths during the DoT window, especially when combined with other mechanics.
- **Common mistakes**: Healers not pre-positioning for throughput, raid not using personal defensives when multiple mechanics overlap.

### Rift Sickness (rift_sickness)
- **Type**: Raid-wide healing absorb
- **What it does**: Each spawned Manifestation add applies a raid-wide healing absorb to the entire raid.
- **Correct response**: Healers must push through the absorb shields. Kill adds quickly to prevent stacking absorbs.
- **Failure indicator**: Healing absorb stacks climbing too high, raid health not recovering between damage events.
- **Common mistakes**: Ignoring adds (letting absorb stack), healers not aware of reduced effective healing.

### Alnshroud (alnshroud)
- **Type**: Add shield / puddle mechanic
- **What it does**: Manifestation adds spawn with an absorb shield. When the shield is broken, the add teleports to the Reality realm and leaves behind a damaging/slowing puddle in the Rift.
- **Correct response**: Break shields in the Rift realm, then finish adds in Reality. Dispel allies onto puddles to clear them.
- **Failure indicator**: Puddles accumulating and reducing available space. Adds surviving too long in Reality.
- **Common mistakes**: Not focusing shield break, leaving puddles uncleaned, dispelling players away from puddles.

### Consume (consume)
- **Type**: Phase transition (Phase 1 to Phase 2)
- **What it does**: At 100 energy, Chimaerus channels for 10 seconds dealing raid-wide pulsing damage. At channel end, all players are knocked back and all remaining adds are eaten by the boss.
- **Correct response**: Kill ALL adds before the channel completes. Heal through pulsing damage. Position for knockback.
- **Failure indicator**: Adds still alive when Consume completes (boss gains power from eaten adds). Deaths during channel from insufficient healing.
- **Common mistakes**: Not prioritizing add kills before energy reaches 100, poor healing cooldown assignment for the channel.

### Corrupted Devastation (corrupted_devastation)
- **Type**: Raid positioning / add spawn (Phase 2)
- **What it does**: Boss marks a line across the room and soars over it, stunning and damaging players hit. Spawns Manifestation adds along the line and leaves puddles behind. On Mythic, also spawns adds in the Rift realm.
- **Correct response**: Avoid the marked line. Bait the beam to manage room space. Kill spawned adds immediately. Use dispels to clear puddles.
- **Failure indicator**: Players taking stun damage from the flyover. Puddles covering too much of the arena.
- **Common mistakes**: Standing in the beam path, not baiting the line to a good location, ignoring spawned adds.

### Consuming Miasma (consuming_miasma)
- **Type**: Dispel / puddle management (Heroic+ only)
- **What it does**: Several players receive a dispellable debuff. When dispelled, it creates a 10-yard AoE explosion that destroys nearby puddles. Allies caught in the explosion take damage and are knocked back.
- **Correct response**: Dispel afflicted players while they stand on or near puddles to clear them. Other players stay 10+ yards away from the dispel target.
- **Failure indicator**: Puddles not being cleared (dispels wasted away from puddles). Allies taking unnecessary explosion damage.
- **Common mistakes**: Dispelling too early (away from puddles), dispelling in the raid stack, not communicating dispel timing.

### Ravenous Dive (ravenous_dive)
- **Type**: Phase transition (Phase 2 to Phase 1)
- **What it does**: Boss smashes to the ground dealing raid-wide damage and knocking all players up. All remaining adds are eaten. Stage 1 restarts.
- **Correct response**: Eliminate all adds before impact. Heal through the landing damage.
- **Failure indicator**: Adds alive when Ravenous Dive hits (boss powered up). Deaths from impact damage.
- **Common mistakes**: Not killing adds in time, insufficient healing cooldowns for the impact.

### Dissonance (dissonance)
- **Type**: Raid positioning (Mythic only)
- **What it does**: Players pulse massive damage to nearby allies who are in the opposite realm.
- **Correct response**: Split the raid into two permanent groups. Maintain distance between groups. Stay close within your assigned group.
- **Failure indicator**: Cross-realm damage appearing in logs between nearby players of different realms.
- **Common mistakes**: Groups drifting too close together, players ending up near the wrong group after mechanics.

### Rift Madness (rift_madness)
- **Type**: Debuff rescue mechanic (Mythic only)
- **What it does**: Two players in the Rift realm are debuffed, taking massive increasing damage over time. One debuff always targets a healer. Players from the Reality realm can stand on top of debuffed players to rescue them, causing the debuffed players to swap realms.
- **Correct response**: Designate rescue players from Reality to run to debuffed Rift players. Stand on them to trigger the realm swap and end the debuff.
- **Failure indicator**: Debuffed players dying to escalating damage. Healer deaths from unrescued Rift Madness.
- **Common mistakes**: Rescue players not reacting fast enough, losing a healer to the debuff, not pre-assigning rescue responsibilities.

## Adds

### Manifestation (Alnshroud adds)
- **When**: Spawned via Rift Emergence (Phase 1) and Corrupted Devastation (Phase 2). On Mythic, also spawned in Rift during Phase 2.
- **Priority**: Highest priority -- kill before phase transitions (Consume / Ravenous Dive) or boss eats them.
- **Abilities**: Protected by Alnshroud absorb shield. When shield breaks, teleports to Reality realm and leaves a puddle.
- **Failure indicator**: Adds surviving to be eaten during Consume or Ravenous Dive, empowering the boss.

### Haunting Essence
- **When**: Spawns during Phase 1 in the Rift realm.
- **Priority**: High -- must be interrupted and killed quickly.
- **Abilities**:
  - Fearsome Cry: Raid-wide fear and nature damage. **Must be interrupted.**
  - Essence Bolt: Single-target nature damage on a random player. Interrupt when possible.
- **Failure indicator**: Fearsome Cry going off (fear on multiple players causing chaos), high Essence Bolt damage on random targets.

### Colossal Horror
- **When**: Spawns during Phase 1.
- **Priority**: High -- dangerous stacking damage buff on the raid.
- **Abilities**:
  - Discordant Roar: Raid-wide physical damage on spawn with a stacking debuff that increases subsequent Roar damage by 10% per stack.
  - Colossal Strikes: Three-hit tankbuster dealing heavy physical and nature damage. Requires active mitigation.
- **Failure indicator**: High Discordant Roar stack count (adds living too long), tank deaths to Colossal Strikes without mitigation.

## Role-Specific

### Tanks
- Face Rending Tear away from the raid at all times.
- Use active mitigation for Colossal Strikes (three-hit combo from Colossal Horror adds).
- Pick up adds that teleport to Reality realm after Alnshroud breaks.
- On Mythic, coordinate which tank handles which realm's adds.
- Tank swap is not explicitly required but positioning for Alndust Upheaval soak matters.

### Healers
- Ramp healing for Caustic Phlegm (12-second raid DoT) and Consume channel (10 seconds of pulsing damage).
- Manage Rift Sickness healing absorb stacks -- the more adds alive, the harder healing becomes.
- On Heroic+, coordinate Consuming Miasma dispels to clear puddles (dispel ON puddles, not randomly).
- On Mythic, one healer will always be targeted by Rift Madness -- rescue assignments are critical.
- Assign healing cooldowns for Consume and Ravenous Dive transitions.

### DPS
- Add kill priority is the single most important DPS responsibility. Never let adds survive to phase transitions.
- Break Alnshroud shields in the Rift realm as fast as possible.
- Interrupt Fearsome Cry from Haunting Essences (mandatory) and Essence Bolt when possible.
- In Phase 2, switch immediately to adds spawned by Corrupted Devastation.
- On Mythic, coordinate DPS between realms to handle adds spawning in both.

## Difficulty Differences

### Normal
- No Rift Vulnerability debuff from Alndust Upheaval soaking (groups can be more flexible).
- No Consuming Miasma mechanic (puddles cannot be actively cleared via dispel explosions).
- No Dissonance (no cross-realm damage penalty, realm positioning is lenient).
- No Rift Madness (no rescue mechanic needed).
- Lower overall damage and health values on adds and boss.

### Heroic
- Alndust Upheaval applies Rift Vulnerability (600% increased soak damage), requiring strict two-group rotation.
- Consuming Miasma added: dispellable debuff that creates puddle-clearing explosions. Must be used strategically.
- Higher add health and raid damage across all mechanics.
- Tighter DPS check on killing adds before phase transitions.

### Mythic
- Dissonance: Players pulse massive damage to nearby allies in the opposite realm. Raid must maintain permanent realm group separation.
- Rift Madness: Two Rift players (one always a healer) are debuffed with escalating damage. Reality players must physically rescue them.
- Corrupted Devastation also spawns adds in the Rift realm (not just Reality).
- Boss casts Alndust Upheaval immediately before Consume, compressing the transition window.
- Significantly higher damage, health, and tighter timing on all mechanics.

## Common Wipe Causes
1. Adds surviving phase transitions (Consume / Ravenous Dive) -- boss eats them and becomes empowered, leading to unhealable damage.
2. Wrong soak group taking Alndust Upheaval on Heroic+ -- Rift Vulnerability causes instant deaths.
3. Fearsome Cry not interrupted -- raid-wide fear causes chain deaths as players scatter into puddles or mechanics.
4. Puddle accumulation consuming the arena -- not using Consuming Miasma dispels on puddles (Heroic+).
5. Healers dying to Rift Madness on Mythic -- losing a healer during the rescue window cascades into raid deaths.
6. Raid stacking too close across realms on Mythic -- Dissonance pulses kill grouped players.
7. Players hit by Corrupted Devastation beam -- stun plus damage often fatal, especially combined with other mechanics.
8. Insufficient healing during Consume channel -- 10 seconds of pulsing damage with potential Caustic Phlegm overlap.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **damage_taken from Alndust Upheaval**: Players soaking with Rift Vulnerability debuff active (should never happen to same group twice in a row)
- **damage_taken from Rending Tear on non-tanks**: Indicates boss facing wrong direction or melee standing in front
- **damage_taken from Corrupted Devastation**: Players hit by the beam flyover -- positional failure
- **damage_taken from Dissonance (Mythic)**: Cross-realm proximity damage -- groups too close
- **debuff Rift Madness duration**: Long durations before rescue indicate slow response from Reality players
- **debuff Rift Vulnerability stacks**: Should never exceed 1 on any player between soaks
- **debuff Caustic Phlegm deaths**: Players dying during the DoT window suggests healing gaps
- **interrupt casts on Fearsome Cry**: Missed interrupts are critical failures -- check interrupt assignments
- **Essence Bolt damage frequency**: High frequency indicates poor interrupt rotation on secondary priority
- **add death timing vs Consume/Ravenous Dive cast**: Adds dying after transition starts means they were eaten
- **Discordant Roar stack count**: High stacks mean Colossal Horror adds lived too long
- **Colossal Strikes damage on tanks**: Unmitigated hits indicate missing active mitigation
- **Consuming Miasma dispel locations**: Dispels far from puddles indicate wasted clearing opportunities
- **Alnshroud shield break timing**: Slow shield breaks delay add kills and cause puddle buildup
- **death timing correlation**: Deaths clustered around Consume or Ravenous Dive suggest add management failure; deaths during Caustic Phlegm suggest healing failure; deaths to Corrupted Devastation suggest positional failure
