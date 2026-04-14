# Midnight Falls

## Overview
Midnight Falls is a 3-phase encounter against L'ura with an intermission between Phase 1 and Phase 2. The fight revolves around a memory game mechanic (Death's Dirge), a crystal lifecycle system (Dusk Crystals healed into Dawn Crystals that provide light protection), and progressive environmental hazards. The key challenge is managing Dawn Crystals throughout all phases while executing precise positioning for the memory game, dodging glaives and beams, and coordinating soaks. Dawn Crystals carried from Phase 1 become essential for survival in Phase 3.

## Phases

### Phase 1: L'ura (Open Arena)
- Fight takes place around a central Darkwell that instantly kills on contact.
- Beams spin outward from the Darkwell (Dark Quasar) while glaives (Heaven's Glaives) bounce around the room.
- Players must execute the Death's Dirge memory game, interrupt Safeguard Matrix adds, kill enemy Midnight Crystals before Cosmic Fracture completes, and heal Dusk Crystals into Dawn Crystals.
- Tank swaps required on Heaven's Lance.
- Transition: After sufficient boss damage, the intermission (Total Eclipse) begins.

### Intermission: Total Eclipse
- Darkwell remains lethal at center.
- Beams shoot outward but do NOT spin (stationary danger zones).
- Players are continually marked, shooting beams from their character.
- All players receive a small healing absorb shield every second, stacking continuously.
- Transition: After surviving the intermission, players are pulled Into the Darkwell for Phase 2.

### Phase 2: Into the Darkwell
- Players are transported inside the Darkwell. Iris of Oblivion kills anyone who crosses the room boundary.
- Void Cores orbit the room perimeter. Players use Galvanize (a targeted line mechanic) to destroy them.
- Destroyed cores trigger Cosmic Fission -- planets pull inward and deal massive damage in their paths.
- Phase ends when all Void Cores are absorbed by L'ura.

### Phase 3: Midnight Darkness
- The arena is consumed by Midnight darkness. Only players near Dawn Crystal holders (Torchbearer) are protected.
- L'ura fires Dark Archangel blasts that must be shielded by crystal holders using their Extra Action Button.
- Star orbs (Dark Constellation) land and connect with damaging beams.
- Light Siphon soak circles must be drained or they explode and wipe the raid.

## Key Mechanics

### Heaven's Lance (heavens_lance)
- **Type**: Tank mechanic
- **What it does**: 5-hit tankbuster sequence. Each hit applies a stack of Impaled, increasing Heaven's Lance damage taken by 50% for 25 seconds.
- **Correct response**: Tank swap after each cast completes. The off-tank taunts before the next cast begins.
- **Failure indicator**: Tank death from stacking Impaled debuffs. Look for high Impaled stack counts (3+) in debuff logs.
- **Common mistakes**: Not swapping after every cast, taunting mid-cast instead of after completion.

### Death's Dirge (deaths_dirge)
- **Type**: Raid mechanic (memory game / positioning puzzle)
- **What it does**: L'ura flashes a sequence of runes around herself. Players are then marked with these runes above their heads. L'ura shoots a laser and spins clockwise, destroying runes off players' heads in the order they are touched.
- **Correct response**: Marked players must form a semicircle in the exact order matching L'ura's displayed sequence so the clockwise laser destroys runes in correct order.
- **Failure indicator**: Massive raid damage when sequence is incorrect. Look for Death's Dirge damage events on players who were out of position.
- **Common mistakes**: Wrong player order in the semicircle, being too slow to position, confusing rune assignments.

### Safeguard Matrix (safeguard_matrix)
- **Type**: Add / interrupt mechanic
- **What it does**: Three adds spawn around the boss. Each add has Safeguard stacks that reduce L'ura's damage taken by 33% per add. Stacks must be removed via interrupts. Add dies when all stacks are depleted.
- **Correct response**: Assign interrupt rotations to each add. Interrupt continuously to remove all Safeguard stacks and destroy the adds.
- **Failure indicator**: Boss damage reduction remaining too long (prolonged phase). Safeguard buff uptime on adds in logs.
- **Common mistakes**: Missing interrupts, not splitting interrupters across all three adds, focusing only one add.

### Disintegration (disintegration)
- **Type**: Raid mechanic (crystal spawn event)
- **What it does**: Large burst of raid damage. Spawns both enemy Midnight Crystals and friendly Dusk Crystals.
- **Correct response**: DPS kill Midnight Crystals before Cosmic Fracture cast completes. Healers heal Dusk Crystals to full to transform them into Dawn Crystals.
- **Failure indicator**: Cosmic Fracture cast completing (raid wipe). Unhealed Dusk Crystals (lost Dawn Crystal resources for Phase 3).
- **Common mistakes**: Ignoring Midnight Crystals, not pre-assigning healers to Dusk Crystals.

### Cosmic Fracture (cosmic_fracture)
- **Type**: Add cast (wipe mechanic)
- **What it does**: Cast by Midnight Crystals. If the cast completes, it wipes the raid.
- **Correct response**: Kill the Midnight Crystal before the cast finishes.
- **Failure indicator**: Cosmic Fracture damage event in logs = wipe.
- **Common mistakes**: Slow target switching, not having DPS pre-assigned to crystals.

### Dawn Crystal (dawn_crystal)
- **Type**: Raid mechanic (pickup / management item)
- **What it does**: Fully healed Dusk Crystals transform into Dawn Crystals that players can pick up via Extra Action Button. Holding a Dawn Crystal deals DOT damage to the carrier. If left on the ground for more than 3 seconds, the crystal pulses massive raid-wide damage.
- **Correct response**: Designated players pick up Dawn Crystals immediately. Carriers can briefly drop and re-pick within the 3-second window to manage DOT damage. Crystals must be carried into Phase 3.
- **Failure indicator**: Dawn Crystal ground pulse damage in logs (crystal left unattended). Carrier deaths from DOT.
- **Common mistakes**: No designated crystal carriers, dropping crystal and forgetting to pick it back up within 3 seconds, crystal carrier dying.

### Dark Quasar (dark_quasar)
- **Type**: Environmental hazard
- **What it does**: Beams shoot out from the central Darkwell and spin clockwise around the room, dealing massive damage to anyone hit. The Darkwell itself instantly kills on contact.
- **Correct response**: Dodge the spinning beams. Never touch the Darkwell.
- **Failure indicator**: Dark Quasar damage taken entries. Darkwell instant death.
- **Common mistakes**: Running through the center to dodge other mechanics, not tracking beam rotation timing.

### Heaven's Glaives (heavens_glaives)
- **Type**: Dodge mechanic (persistent)
- **What it does**: Multiple glaives shoot from the boss and bounce around the room continuously until Phase 1 ends.
- **Correct response**: Dodge continuously while handling other mechanics.
- **Failure indicator**: Heaven's Glaives damage taken entries. High hit count per player.
- **Common mistakes**: Tunnel-visioning boss rotation and ignoring glaive paths.

### Shattered Sky (shattered_sky)
- **Type**: Healer mechanic (raid-wide rot)
- **What it does**: Continuous raid-wide damage throughout Phase 1.
- **Correct response**: Healers maintain throughput. Use healing cooldowns during overlap with other damage events.
- **Failure indicator**: Deaths during periods of no other mechanic -- indicates insufficient healing throughput.
- **Common mistakes**: Healers not pacing cooldowns, using major CDs too early.

### Tears of L'ura (tears_of_lura)
- **Type**: Soak mechanic (Heroic+ only)
- **What it does**: When Dawn Crystals are hit by Cosmic damage, several soak circles spawn nearby. Each must be caught by a player.
- **Correct response**: Assign players to soak circles near each Dawn Crystal. Crystal holders must drop crystals before Cosmic damage or avoid soaking entirely.
- **Failure indicator**: Missed soak damage events (massive raid damage per missed circle).
- **Common mistakes**: Not pre-assigning soakers, crystal holder trying to soak while holding crystal (takes extra Cosmic damage).

### Total Eclipse (total_eclipse)
- **Type**: Intermission mechanic
- **What it does**: Darkwell stays lethal. Beams shoot outward but remain stationary. Players are continually marked, shooting beams from their character. All players receive a stacking healing absorb shield every second.
- **Correct response**: Dodge stationary beams and player-emitted beams. Healers use cooldowns to counteract the stacking absorb.
- **Failure indicator**: Player deaths from accumulated absorb shields not being healed through. Beam damage taken.
- **Common mistakes**: Stacking too tightly (overlapping player beams), not rotating healer cooldowns.

### Iris of Oblivion (iris_of_oblivion)
- **Type**: Environmental boundary (Phase 2)
- **What it does**: Instantly kills any player who crosses the room boundary inside the Darkwell.
- **Correct response**: Stay within arena boundaries at all times.
- **Failure indicator**: Instant death from Iris of Oblivion in death logs.
- **Common mistakes**: Being knocked or pulled into the boundary during Cosmic Fission.

### Galvanize (galvanize)
- **Type**: Raid mechanic (targeted line)
- **What it does**: Players receive a targeted line ability used to destroy Void Cores orbiting the room perimeter. On Heroic+, Galvanize is also a high-damage soak mechanic requiring pre-assigned groups.
- **Correct response**: Aim the line at Void Cores to destroy them. On Heroic+, coordinate soak groups. Crystal holders must drop Dawn Crystals before soaking (Cosmic damage interaction) or skip soaking entirely.
- **Failure indicator**: Void Cores remaining alive too long. On Heroic+, unsplit soak damage (too few soakers).
- **Common mistakes**: Missing cores with line aim, crystal holders not dropping crystals before soak, uneven soak group sizes.

### Cosmic Fission (cosmic_fission)
- **Type**: Raid mechanic (dodge / pull)
- **What it does**: When Void Cores are destroyed, players are briefly pulled inward. L'ura then absorbs the destroyed planets, pulling them in with a burst of raid-wide damage. Any player in a planet's marked path takes massive damage.
- **Correct response**: After core destruction, move to a safe spot away from planet absorption paths.
- **Failure indicator**: Cosmic Fission damage taken on players standing in planet paths.
- **Common mistakes**: Not repositioning after the pull, being in the path of multiple planets.

### Abyssal Pool (abyssal_pool)
- **Type**: Healer mechanic (rot damage)
- **What it does**: Continuous damage to all players throughout Phase 2.
- **Correct response**: Healers maintain throughput and pace cooldowns across the phase.
- **Failure indicator**: Deaths with no other mechanic active, indicating insufficient healing.
- **Common mistakes**: Blowing healing CDs early in the phase.

### Torchbearer (torchbearer)
- **Type**: Raid survival mechanic (Phase 3)
- **What it does**: Dawn Crystal holders emit a light aura that protects nearby players from the Midnight darkness. Players outside this light take lethal damage.
- **Correct response**: Entire raid stays within the light radius of crystal holders at all times. Crystal holders position centrally.
- **Failure indicator**: Midnight darkness damage on players outside light aura. Deaths far from crystal holders.
- **Common mistakes**: Spreading too far from crystal holders, crystal holder death leaving group unprotected.

### Dark Archangel (dark_archangel)
- **Type**: Raid mechanic (shield + zone)
- **What it does**: L'ura fires a deadly blast that leaves behind a zone of darkness. The blast is lethal without protection.
- **Correct response**: A Dawn Crystal holder uses their Extra Action Button to activate a protective shield against the blast. After the blast, all players must quickly exit the remaining darkness zone which deals damage to anyone inside.
- **Failure indicator**: Dark Archangel blast damage (unshielded). Darkness zone damage taken (slow to exit).
- **Common mistakes**: Crystal holder missing the shield timing, raid lingering in the darkness zone after shield.

### Dark Constellation (dark_constellation)
- **Type**: Positional dodge mechanic (Phase 3)
- **What it does**: Star orbs land on the platform dealing massive damage to nearby players on impact. Stars then connect with light beams that damage anyone standing in them.
- **Correct response**: Move away from star landing zones. After landing, position in safe spaces between stars, avoiding connecting beams.
- **Failure indicator**: Dark Constellation impact damage. Beam damage taken between stars.
- **Common mistakes**: Not moving from star landing indicators, getting trapped between beam connections.

### Light Siphon (light_siphon)
- **Type**: Soak mechanic (Phase 3)
- **What it does**: Soak circles spawn that must be drained by players standing inside them. If not fully soaked in time, they explode and wipe the raid.
- **Correct response**: Players stand inside circles to drain them before the timer expires.
- **Failure indicator**: Light Siphon explosion = raid wipe. Partial soak damage if circles nearly expire.
- **Common mistakes**: Not enough players assigned to soaking, prioritizing other mechanics over soak circles.

## Adds

### Safeguard Matrix Adds
- **When**: Phase 1, periodically
- **Priority**: High -- must be interrupted to remove boss damage reduction
- **Abilities**: Safeguard buff (33% boss damage reduction per add, removed via interrupts)
- **Failure indicator**: Prolonged Safeguard buff uptime on boss damage reduction. Extended Phase 1 duration.

### Midnight Crystals
- **When**: Phase 1, spawned by Disintegration
- **Priority**: Immediate -- must die before Cosmic Fracture cast completes (wipe mechanic)
- **Abilities**: Cosmic Fracture (lethal cast, must be killed before completion)
- **Failure indicator**: Cosmic Fracture cast completing in logs.

### Dusk Crystals (Friendly)
- **When**: Phase 1, spawned by Disintegration alongside Midnight Crystals
- **Priority**: Must be healed to full by healers to transform into Dawn Crystals
- **Abilities**: None (friendly unit). Transforms into Dawn Crystal pickup when fully healed.
- **Failure indicator**: Dusk Crystal dying or despawning before being healed = fewer Dawn Crystals for Phase 3.

### Void Cores
- **When**: Phase 2, orbiting room perimeter
- **Priority**: Primary Phase 2 objective -- destroy all to end the phase
- **Abilities**: None directly, but destruction triggers Cosmic Fission pull and planet paths.
- **Failure indicator**: Extended Phase 2 duration. Missed Galvanize aims.

## Role-Specific

### Tanks
- Swap on Heaven's Lance every cast (5-hit sequence applies stacking Impaled debuff, 50% increased damage per stack, 25s duration).
- Position boss away from Darkwell center.
- In Phase 3, maintain position near Dawn Crystal holders for light protection while keeping boss faced away from raid.
- Use active mitigation for each Heaven's Lance cast.

### Healers
- Phase 1: Manage Shattered Sky rot damage. Heal Dusk Crystals to full to create Dawn Crystals. Dispel/heal through Dawn Crystal carrier DOT.
- Intermission: Rotate cooldowns for stacking healing absorb shields during Total Eclipse.
- Phase 2: Sustain through Abyssal Pool rot damage. Coordinate cooldowns for Cosmic Fission bursts.
- Phase 3: Heavy throughput required. Heal through Torchbearer DOT on crystal carriers. Coordinate cooldowns for Dark Archangel and Light Siphon overlaps.
- Heroic+: Assign healers to soak Tears of L'ura circles near Dawn Crystals.

### DPS
- Phase 1: Kill Midnight Crystals immediately when they spawn (before Cosmic Fracture completes). Interrupt Safeguard Matrix adds on rotation. Dodge Heaven's Glaives while maintaining uptime.
- Phase 2: Aim Galvanize lines accurately at Void Cores. Avoid Cosmic Fission planet paths.
- Phase 3: Maintain tight positioning near crystal holders. Prioritize Light Siphon soaks over boss damage. Switch awareness to Dark Constellation star positions.

## Difficulty Differences

### Normal
- Death's Dirge memory game is present but more forgiving (fewer runes, slower sequence).
- Galvanize is a simple targeted line at Void Cores (no soak component).
- Tears of L'ura does NOT exist -- no soak circles spawn from Dawn Crystals.
- Light Siphon may not be present or is more forgiving in timing.
- Fewer Heaven's Glaives bouncing simultaneously.
- Overall damage tuning is lower across all mechanics.

### Heroic
- Tears of L'ura added: Dawn Crystals spawn soak circles when hit by Cosmic damage. Missed soaks deal massive raid damage.
- Galvanize becomes a high-damage soak mechanic requiring pre-assigned groups. Crystal holders must drop crystals before soaking.
- Light Siphon soak circles in Phase 3 have tighter timers.
- More glaives, faster beam rotations, tighter tuning on all damage.
- Death's Dirge sequences are longer and faster.

### Mythic
- All Heroic mechanics present with tighter tuning.
- Expected additional overlaps between mechanics (e.g., Death's Dirge during Disintegration, Dark Archangel during Dark Constellation).
- Likely additional Void Cores in Phase 2 requiring more precise Galvanize coordination.
- Phase 3 darkness zones from Dark Archangel likely persist longer or stack, reducing safe space.
- Dawn Crystal DOT damage increased, making carrier management more demanding.
- Tighter DPS checks on Midnight Crystals and phase transitions.
- Note: Specific Mythic-only mechanics beyond Heroic were not fully detailed on MythicTrap at time of writing. Update this section as Mythic data becomes available from logs.

## Common Wipe Causes
1. Cosmic Fracture completing on Midnight Crystals (Phase 1) -- DPS too slow to kill enemy crystals.
2. Death's Dirge memory game failed -- players in wrong order cause massive raid damage.
3. Dawn Crystal left on ground too long (3+ seconds) -- massive pulsing raid damage.
4. Light Siphon soak circles not drained in Phase 3 -- raid wipe explosion.
5. Crystal carrier death in Phase 3 -- group loses Torchbearer light protection, exposed to Midnight darkness.
6. Dark Archangel blast without crystal shield activation -- lethal unmitigated damage.
7. Players crossing Iris of Oblivion boundary in Phase 2 -- instant death reducing raid numbers.
8. Intermission healing absorb shields overwhelming healers during Total Eclipse.
9. Tank death from stacked Impaled debuffs on Heaven's Lance (missed swap).
10. Cosmic Fission planet paths killing players in Phase 2 who failed to reposition.

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **Cosmic Fracture cast success**: Any successful cast = wipe. Check cast events on Midnight Crystal NPCs.
- **Death's Dirge damage taken**: High damage events from Death's Dirge indicate failed memory game positioning.
- **Dawn Crystal pulse damage**: Damage events from Dawn Crystal ground pulses indicate crystal management failures (left on ground > 3s).
- **Heaven's Lance / Impaled stacks**: Check debuff stacks on tanks. 3+ stacks indicate missed tank swap.
- **Tears of L'ura missed soaks**: Damage events from missed Tears of L'ura soaks (Heroic+).
- **Light Siphon explosion**: Any Light Siphon explosion damage = failed soak.
- **Iris of Oblivion deaths**: Instant deaths from boundary contact in Phase 2.
- **Dark Archangel unmitigated damage**: High Dark Archangel damage indicates shield was not activated.
- **Torchbearer / Midnight darkness damage**: Damage from darkness exposure indicates players outside crystal light radius.
- **Safeguard buff uptime**: Duration of Safeguard Matrix buff on boss -- longer uptime means slow interrupts.
- **Cosmic Fission path damage**: Players hit by planet absorption paths in Phase 2.
- **Total Eclipse absorb accumulation**: Track healing absorb stacking rate vs healer throughput during intermission.
- **Death timing patterns**: Deaths clustered during Disintegration + Death's Dirge overlap suggest mechanic overload. Deaths in Phase 3 near Dark Archangel casts suggest shield timing issues. Early Phase 2 deaths suggest boundary awareness problems.
- **Crystal carrier deaths**: Track which players held Dawn Crystals and when they died -- crystal carrier death in Phase 3 is a cascading failure.
- **Heaven's Glaives hit frequency**: High hit counts per player suggest poor awareness / positioning.
