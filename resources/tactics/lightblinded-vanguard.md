# Lightblinded Vanguard

## Overview
Three-boss Paladin-themed council fight featuring Lightblood, Bellamy, and Senn. Single phase encounter with no phase transitions. All three bosses must be killed evenly to prevent enrage. The fight revolves around energy-based Aura casts (at 100 energy), coordinated tank swaps, soak management, and increasingly dangerous mechanic overlaps. Space management becomes critical on Heroic+ due to Consecration puddles.

## Phases
### Single Phase (No Phase Transitions)
- All three bosses are active from pull to kill.
- Each boss builds to 100 energy independently and casts their Aura at full energy.
- Light Infused raid damage increases by 25% per Aura cast, creating a soft enrage as the fight progresses.
- Bosses must die at roughly the same time to prevent hard enrage.

## Key Mechanics

### Judgment (judgment)
- **Type**: Tank mechanic (tankbuster)
- **What it does**: Two-part attack dealing holy damage, then immediately following with Shield of the Righteous. Applies a debuff increasing damage taken by Shield of the Righteous by 500%.
- **Correct response**: Tank swap between Judgment and Shield of the Righteous. The tank taking Judgment must NOT take the Shield of the Righteous hit.
- **Failure indicator**: Tank death immediately after Judgment cast. Look for Shield of the Righteous damage on the same target that received Judgment debuff.
- **Common mistakes**: Late tank swap, both tanks stacking and both getting hit, forgetting to swap back for next cycle.

### Exorcism (exorcism)
- **Type**: Tank mechanic (tankbuster)
- **What it does**: Single large hit of holy damage on the current tank target.
- **Correct response**: Active mitigation or defensive cooldown before the cast completes.
- **Failure indicator**: Tank death or very high single-hit damage taken without any absorb/mitigation buff active.
- **Common mistakes**: No defensive used, healer not topping tank before cast.

### Execution Sentence (execution_sentence)
- **Type**: Raid mechanic (soak)
- **What it does**: Marks multiple players with damage circles that must be soaked by other raid members. Each player can only soak one circle per set. After each soak completes, three spinning hammers emerge and travel outward, dealing damage to anyone hit.
- **Correct response**: Assign soak groups. Multiple players stack in each marked circle. After soaking, dodge the spinning hammers. Only soak one circle per set.
- **Failure indicator**: Unsoaked circles dealing massive raid damage. Players hit by hammer projectiles (avoidable damage taken). Players soaking more than one circle (taking stacking damage).
- **Common mistakes**: Not enough soakers per circle, standing in hammer paths after soak, soaking two circles in one set.

### Avenger's Shield (avengers_shield)
- **Type**: Raid mechanic (spread + dispel)
- **What it does**: Marks players with circles and throws shields at them. Shield impact deals damage and applies a DoT to nearby players. On Mythic (empowered): targets ALL players instead of several.
- **Correct response**: Spread out before impact to avoid cleaving DoT to allies. Dispel the DoT debuff.
- **Failure indicator**: Multiple players gaining the DoT debuff simultaneously (clumped during impact). High stacking DoT damage on players not dispelled quickly.
- **Common mistakes**: Clumping with other players, slow dispels, not recognizing empowered version on Mythic.

### Divine Toll (divine_toll)
- **Type**: Raid mechanic (dodge)
- **What it does**: Bellamy throws waves of traveling shields for 18 seconds. Players hit take damage and are silenced for 6 seconds.
- **Correct response**: Dodge all incoming shield waves for the full 18-second duration.
- **Failure indicator**: Silence debuff on players. Damage taken from Divine Toll shields. Healers silenced during critical healing windows.
- **Common mistakes**: Tunnel-visioning DPS and getting hit, healers silenced during Searing Radiance overlap.

### Searing Radiance (searing_radiance)
- **Type**: Healer mechanic (raid-wide pulsing damage)
- **What it does**: Raid-wide pulsing damage for 15 seconds. On Mythic (empowered): damage increases by 10% every second.
- **Correct response**: Use healing cooldowns and personal defensives. Coordinate raid cooldown rotation for each cast.
- **Failure indicator**: Raid deaths during 15-second window, especially in later ticks. No healing cooldowns visible during the channel.
- **Common mistakes**: No cooldown assigned, overlapping with other heavy damage mechanics, Mythic empowered version catching healers off guard.

### Sacred Toll (sacred_toll)
- **Type**: Raid mechanic (burst raid damage)
- **What it does**: Large single hit of raid-wide damage. Especially dangerous when overlapping with other mechanics.
- **Correct response**: Ensure raid is topped before cast. Use raid cooldown if overlapping with Searing Radiance or other damage.
- **Failure indicator**: Multiple raid deaths on a single damage event. Deaths coinciding with other mechanic overlaps.
- **Common mistakes**: Raid not topped before cast, no cooldown for dangerous overlaps.

### Sacred Shield (sacred_shield)
- **Type**: Raid mechanic (dodge)
- **What it does**: Senn gains an absorb shield and charges forward on an elekk mount, dealing damage to all players in the charge path.
- **Correct response**: Dodge out of the charge path.
- **Failure indicator**: Players taking Sacred Shield charge damage (avoidable). Knockback positioning deaths.
- **Common mistakes**: Being caught mid-cast, not watching Senn's facing direction before charge.

### Blinding Light (blinding_light)
- **Type**: Raid mechanic (face away / interrupt)
- **What it does**: Disorients all players facing Senn when the cast completes.
- **Correct response**: Turn character model away from Senn OR interrupt the cast.
- **Failure indicator**: Disoriented debuff on players. Players facing Senn at cast completion.
- **Common mistakes**: Melee not turning away, missed interrupt, ranged not paying attention to which boss is casting.

### Divine Shield (divine_shield)
- **Type**: Raid mechanic (dispel)
- **What it does**: All three bosses activate Divine Shield (full immunity) when the raid uses Bloodlust/Heroism.
- **Correct response**: Have a Priest ready to Mass Dispel immediately after Bloodlust is cast.
- **Failure indicator**: Extended boss immunity uptime. Wasted Bloodlust duration. No Mass Dispel cast within seconds of Divine Shield application.
- **Common mistakes**: Using Bloodlust without Mass Dispel available, Priest not ready, no pre-assigned Priest for dispel duty.

### Divine Storm (divine_storm)
- **Type**: Melee mechanic (AoE around Lightblood)
- **What it does**: 8-yard AoE damage around Lightblood. On Mythic (empowered): spawns tornadoes at distance that slowly move toward the boss, slowing and damaging players hit.
- **Correct response**: Melee step out briefly during cast. On Mythic, dodge incoming tornadoes as well.
- **Failure indicator**: Melee damage taken from Divine Storm. Players hit by tornado damage (Mythic).
- **Common mistakes**: Melee staying in during cast, not tracking tornado positions on Mythic.

### Aura of Wrath (aura_of_wrath)
- **Type**: Raid mechanic (positioning)
- **What it does**: Lightblood casts at 100 energy. Buffs all allied (boss) damage by 100% within 40 yards for 15 seconds.
- **Correct response**: Ensure other bosses are tanked more than 40 yards from Lightblood so they do not receive the buff.
- **Failure indicator**: Other bosses dealing double damage during Aura window. Tank deaths from buffed melee/abilities.
- **Common mistakes**: Bosses too close together, tanks not pre-positioning before energy reaches 100.

### Aura of Peace (aura_of_peace)
- **Type**: DPS mechanic (positioning)
- **What it does**: Senn casts at 100 energy. Pacifies players dealing damage to any Vanguard within the aura range for 5 seconds.
- **Correct response**: Stop attacking and move out of the 40-yard aura range before dealing damage.
- **Failure indicator**: Players gaining Pacify debuff. DPS downtime from being pacified.
- **Common mistakes**: Continuing to DPS inside the aura, not moving out quickly enough.

### Protective Aura (protective_aura)
- **Type**: DPS mechanic (positioning)
- **What it does**: Bellamy casts at 100 energy. Reduces damage taken by all allied bosses within 40 yards by 75%.
- **Correct response**: Move other bosses out of the aura range, or stop DPS on bosses inside the aura to avoid wasting damage.
- **Failure indicator**: Low DPS output during aura window. Bosses receiving 75% damage reduction buff.
- **Common mistakes**: Wasting cooldowns into damage-reduced bosses, not repositioning.

### Consecration (consecration)
- **Type**: Raid mechanic (ground denial, Heroic+)
- **What it does**: Large persistent ground puddle spawns under each boss after their Aura channel completes. Deals continuous damage to anyone standing in it. On Mythic: also pacifies players and increases damage taken by 100%.
- **Correct response**: Move out immediately. Plan boss positioning to leave room for future Consecration puddles.
- **Failure indicator**: Sustained Consecration damage ticks on players. On Mythic, Pacify debuff from Consecration.
- **Common mistakes**: Poor space management leading to no safe areas, tanks not planning where to drop puddles.

### Light Infused (light_infused)
- **Type**: Healer mechanic (soft enrage)
- **What it does**: Constant raid-wide ticking damage that increases by 25% each time any Vanguard's Aura is cast.
- **Correct response**: Heal through increasing damage. Kill bosses before stacks become unmanageable.
- **Failure indicator**: Steadily increasing raid damage taken per tick. Deaths accelerating in later portions of the fight.
- **Common mistakes**: Fight lasting too long due to uneven boss damage, running out of healing cooldowns.

### Zealous Spirit (zealous_spirit) - Mythic Only
- **Type**: Raid mechanic (empowerment rotation)
- **What it does**: A Spirit buffs one Vanguard member at a time, increasing their damage by 30%, empowering one of their abilities, and causing a new mechanic overlap.
- **Correct response**: Track which boss is empowered and prepare for the specific overlap and empowered ability.
- **Failure indicator**: Raid not adjusting to empowered abilities. Deaths during forced overlaps.
- **Common mistakes**: Not tracking the Spirit's target, using wrong cooldowns for the wrong overlap.

#### Zealous Spirit Combinations:
- **Buffing Bellamy**: Tyr's Wrath casts alongside Divine Toll. Avenger's Shield becomes empowered (targets ALL players).
- **Buffing Lightblood**: Divine Toll casts alongside Execution Sentence. Divine Storm becomes empowered (spawns tornadoes).
- **Buffing Senn**: Execution Sentence casts alongside Tyr's Wrath. Searing Radiance becomes empowered (damage increases 10%/sec).

## Adds
No dedicated add spawns in this encounter. The fight is purely a three-boss council with no additional enemies.

## Role-Specific
### Tanks
- Tank swap on Judgment -- the tank who receives Judgment MUST NOT take the following Shield of the Righteous (500% increased damage).
- Use active mitigation for Exorcism hits.
- Position bosses more than 40 yards apart to prevent Aura cross-buffing.
- On Heroic+, plan Consecration puddle placement carefully to preserve platform space.
- Track all three bosses' energy bars to anticipate Aura casts and reposition in advance.

### Healers
- Assign healing cooldown rotation for Searing Radiance (15-second pulsing damage).
- Watch for Sacred Toll overlaps with other damage -- these are the most lethal moments.
- Dispel Avenger's Shield DoT quickly to prevent stacking damage.
- Light Infused damage increases throughout the fight; save strongest cooldowns for later Aura cycles.
- On Mythic, empowered Searing Radiance (10%/sec increase) requires strong cooldown stacking.

### DPS
- Keep all three bosses within 2-3% HP of each other at all times to prevent enrage.
- Assign multi-dot or cleave players to equalize health.
- Stop DPS or move out during Aura of Peace to avoid Pacify.
- Do not waste cooldowns into Protective Aura (75% damage reduction).
- On Mythic, track Zealous Spirit target to know which abilities are empowered.
- Dodge Divine Toll shields -- silence prevents DPS for 6 seconds.

## Difficulty Differences
### Normal
- No Consecration puddles after Aura casts.
- No Zealous Spirit mechanic.
- Avenger's Shield targets only a few players.
- Divine Storm is a simple 8-yard AoE with no tornadoes.
- Searing Radiance does flat pulsing damage (no escalation per tick).
- Overall mechanic overlaps are more forgiving with fewer simultaneous dangers.

### Heroic
- **Consecration** added: large damage puddles spawn after each Aura channel, creating permanent ground denial and making space management critical.
- All Normal mechanics remain with tuned-up damage values.
- Tighter DPS check due to space constraints from Consecration puddles.

### Mythic
- **Zealous Spirit** added: a Spirit rotates between bosses, increasing their damage by 30%, empowering a specific ability, and forcing dangerous mechanic overlaps:
  - Bellamy empowered: Avenger's Shield hits ALL players; Tyr's Wrath overlaps with Divine Toll.
  - Lightblood empowered: Divine Storm spawns tornadoes; Divine Toll overlaps with Execution Sentence.
  - Senn empowered: Searing Radiance damage escalates 10%/sec; Execution Sentence overlaps with Tyr's Wrath.
- **Consecration** on Mythic also pacifies players and increases damage taken by 100%.
- Empowered ability overlaps create the most dangerous moments in the fight, requiring precise cooldown coordination.

## Common Wipe Causes
1. **Botched tank swap on Judgment** -- tank eats Shield of the Righteous with 500% damage increase debuff and dies, leading to boss loose on raid.
2. **Uneven boss damage** -- one boss dies early, triggering enrage on remaining bosses.
3. **Unsoaked Execution Sentence circles** -- massive raid damage from failed soaks wiping healers' mana or killing players outright.
4. **Searing Radiance + Sacred Toll overlap with no cooldown** -- double raid damage without healing cooldowns kills multiple players.
5. **Running out of space from Consecration puddles** (Heroic+) -- poor puddle placement leaves no safe ground, forcing players into damage zones.
6. **Divine Shield not Mass Dispelled during Bloodlust** -- wasted DPS window leads to extended fight and soft enrage from Light Infused stacks.
7. **Zealous Spirit empowered overlaps** (Mythic) -- failing to handle forced mechanic overlaps (e.g., Divine Toll + Execution Sentence simultaneously).

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **damage_taken: Consecration** -- players standing in puddles, indicates poor positioning or space management
- **damage_taken: Divine Toll** -- players hit by shield waves, check for Silence debuff application
- **damage_taken: Sacred Shield** -- players hit by Senn's charge, avoidable damage
- **damage_taken: Divine Storm** -- melee standing too close to Lightblood during cast
- **debuff: Judgment** -- track tank swap timing; if same player has Judgment debuff AND takes Shield of the Righteous damage = failed swap
- **debuff: Blinding Light (Disoriented)** -- players who failed to face away from Senn
- **debuff: Silence (from Divine Toll)** -- players hit by traveling shields, especially healers
- **debuff: Pacify (from Aura of Peace or Consecration)** -- players who did not move out in time
- **damage_taken: Execution Sentence** -- unsoaked circles show as high damage on marked players with no split
- **damage_taken: Light Infused ticks** -- compare early vs late fight to track soft enrage scaling
- **buff: Divine Shield on bosses** -- duration indicates how quickly Mass Dispel was used (long = wasted Bloodlust)
- **death timing during Searing Radiance** -- deaths during 15s channel indicate missing healing cooldowns
- **death timing near Sacred Toll** -- deaths coinciding with Sacred Toll + another mechanic = dangerous overlap not handled
- **boss health differential** -- check boss HP% at death timestamps; large gaps indicate poor damage balancing
- **Zealous Spirit tracking (Mythic)** -- correlate empowered ability damage spikes with deaths to identify which overlap is problematic
- **Avenger's Shield DoT duration** -- long DoT uptime indicates slow dispels
- **tornado damage_taken (Mythic)** -- Divine Storm empowered tornado hits indicate poor awareness
