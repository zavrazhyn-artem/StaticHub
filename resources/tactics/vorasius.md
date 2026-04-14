# Vorasius

## Overview
Vorasius is a single-phase, fast-paced encounter centered around Crystal Wall management and beam avoidance. The boss spawns Void Crystal Walls that trap players in the center of the room, and the raid must use Blistercreep add explosions to destroy those walls before the deadly Void Breath beam sweeps across the platform. Failure to break walls in time results in the raid being trapped in the beam's path. The fight also features a knockback/pull channel, tank soak circles, and a hard melee-range requirement to prevent a wipe mechanic.

## Phases
### Phase 1 (Entire Fight)
- This is a single-phase encounter with no phase transitions.
- The fight loops through a repeating ability rotation: Shadowclaw Slam spawns walls, Blisterburst spawns adds to destroy them, and Void Breath sweeps the room requiring cleared walls for safe movement.
- Primordial Roar and Overpowering Pulse occur throughout as persistent threats.

## Key Mechanics

### Primordial Roar (primordial_roar)
- **Type**: Raid-wide mechanic
- **What it does**: The boss channels, pulling all players toward it while dealing sustained raid-wide damage. The channel ends with a knockback and additional burst of raid damage.
- **Correct response**: Stay centered on the platform; position so the knockback does not push you off the edge. Use movement abilities or position against safe edges.
- **Failure indicator**: Deaths from falling off the platform; high damage-taken entries from the pull/knockback burst.
- **Common mistakes**: Standing too close to platform edges before the cast; not pre-positioning for the knockback direction; healers not topping the raid before the final burst.

### Shadowclaw Slam (shadowclaw_slam)
- **Type**: Tank mechanic
- **What it does**: Marks a circle on the floor that must be soaked by a tank. The first two soaks apply a stacking 150% physical damage vulnerability debuff. After soaking, creates an expanding ring (Aftershock) that explodes multiple times outward. After the first two casts, spawns Void Crystal Walls on the left and right sides of the room.
- **Correct response**: Active tank moves into the marked circle to soak. Tank swap after every two soaks due to the vulnerability stacks. All players dodge the expanding ring explosions. DPS must plan to break the spawned Crystal Walls using add explosions before Void Breath.
- **Failure indicator**: Unsoaked slam causes massive raid damage or wipe. Deaths from Aftershock ring damage. Tank deaths from taking slams with too many vulnerability stacks.
- **Common mistakes**: Failing to tank swap after two stacks; raid members standing in the expanding ring path; not recognizing wall spawn timing.

### Void Breath (void_breath)
- **Type**: Raid-wide lethal beam mechanic
- **What it does**: Vorasius sweeps a deadly beam slowly across the room, dealing massive pulsing raid damage for 15 seconds. The beam randomly starts from the left or right side.
- **Correct response**: Move to the opposite side of the room from where the beam originates. Use gaps in destroyed Crystal Walls to navigate safely. Walls MUST be broken before this ability or the raid will be trapped.
- **Failure indicator**: Deaths during the 15-second beam window; high damage-taken from Void Breath ticks; multiple deaths clustered at the same timestamp indicating wall trap.
- **Common mistakes**: Not destroying enough Crystal Walls before the beam; moving too slowly across the room; not identifying which side the beam starts from quickly enough.

### Blisterburst (blisterburst)
- **Type**: Add management / raid mechanic
- **What it does**: Spawns Blistercreep adds that fixate on random raid members. Fixated adds deal damage to their target, apply a dispellable slow debuff, and create small AoE damage circles on the ground. When killed, each add explodes in an 8-yard radius dealing shadow damage to the raid.
- **Correct response**: Fixated players kite their adds toward Crystal Walls. Coordinate add kills so explosions happen next to walls to destroy them. Healers dispel the slow debuff on fixated players. Raid spreads to minimize explosion splash damage.
- **Failure indicator**: Crystal Walls still standing when Void Breath begins; deaths from add explosion damage (players too close); high damage-taken from fixate damage on kiting players.
- **Common mistakes**: Killing adds in random locations instead of near walls; not dispelling slows causing fixated players to get caught; stacking too many add explosions on the raid simultaneously.

### Overpowering Pulse (overpowering_pulse)
- **Type**: Tank / raid wipe mechanic
- **What it does**: The boss deals lethal raid-wide damage if no player is within melee range. This is a passive check that occurs continuously.
- **Correct response**: At least one tank must remain in melee range of the boss at all times. During movement-heavy phases (Void Breath, Primordial Roar knockback), the tank must immediately return to melee.
- **Failure indicator**: Instant raid wipe with Overpowering Pulse as the damage source; typically shows as a single massive damage event killing the entire raid.
- **Common mistakes**: Both tanks getting knocked out of melee by Primordial Roar; tank dying and no one stepping into melee; tank moving out to dodge mechanics and leaving melee empty.

## Adds
### Blistercreep (blistercreep)
- **When**: Spawned by Blisterburst throughout the fight on a regular timer
- **Priority**: High priority -- must be killed near Crystal Walls before Void Breath
- **Abilities**:
  - Fixate: Locks onto a random raid member and chases them
  - Slow debuff: Applies a dispellable slow to the fixated target
  - Ground AoE: Creates small damaging circles on the ground while alive
  - Death explosion: 8-yard radius shadow damage explosion on death
- **Failure indicator**: Walls not destroyed in time (too few adds killed near walls); raid members dying to uncontrolled add damage; adds still alive during Void Breath causing chaotic movement

## Role-Specific
### Tanks
- Soak every Shadowclaw Slam circle -- never let it go unsoaked
- Tank swap after every 2 Shadowclaw Slam soaks due to 150% physical vulnerability stacks
- Maintain melee range at ALL times to prevent Overpowering Pulse
- After Primordial Roar knockback, immediately return to melee range
- Dodge Aftershock expanding rings while staying near the boss

### Healers
- Heavy raid healing during Primordial Roar channel and knockback burst
- Dispel Blistercreep slow debuffs on fixated players promptly
- Top the raid before Void Breath begins (15 seconds of unavoidable pulsing damage)
- Watch for tank damage spikes during Shadowclaw Slam soaks, especially with vulnerability stacks
- Heal through Blistercreep death explosions when adds are killed

### DPS
- Primary job: Kill Blistercreep adds next to Crystal Walls to destroy them
- Coordinate add kill timing -- do not randomly kill adds away from walls
- Fixated players must kite adds to walls, not away from them
- Switch to adds immediately on spawn; wall destruction is the #1 priority
- Dodge Aftershock rings and ground AoE circles from adds

## Difficulty Differences
### Normal
- Crystal Walls require only 1 Blistercreep explosion to destroy
- No puddles left by dead adds
- More forgiving beam damage and timing
- Fewer Blistercreep adds spawned per wave

### Heroic
- Crystal Walls require 2 Blistercreep explosions to destroy (up from 1)
- Tighter coordination required for add positioning and kill timing
- Higher damage across all abilities
- More adds spawned, requiring better kiting discipline

### Mythic
- Crystal Walls require 3 Blistercreep explosions to destroy (up from 2)
- Dead Blistercreep adds leave persistent void puddles on the ground, reducing safe space
- Significantly higher damage on all abilities
- Puddle management becomes a secondary space concern alongside wall destruction
- The combination of needing 3 explosions per wall and puddle placement makes add kill positioning critical

## Common Wipe Causes
1. Crystal Walls not destroyed before Void Breath -- raid trapped in beam path with nowhere to go
2. Overpowering Pulse triggered because no player was in melee range (tank death, knockback, or poor positioning)
3. Tank dying to Shadowclaw Slam with too many physical vulnerability stacks (missed swap)
4. Raid members knocked off the platform by Primordial Roar
5. Adds killed in bad positions -- explosions hitting the raid instead of walls, or walls left intact
6. (Mythic) Void puddles from dead adds covering safe zones, leaving no room to dodge beam

## AI Analysis Notes
Key data points the AI should look for in WCL logs:
- **Void Breath damage taken**: High ticks on multiple players indicates wall destruction failure or slow movement
- **Overpowering Pulse cast**: Any cast of this ability means the melee range check failed -- immediate wipe indicator
- **Shadowclaw Slam vulnerability stacks**: Check tank debuff stacks -- more than 2 stacks means missed swap
- **Aftershock damage taken**: Players hit by expanding rings from Shadowclaw Slam -- avoidable damage
- **Blistercreep fixate damage**: Sustained high damage on individual players suggests poor kiting or missed dispels
- **Blistercreep explosion damage**: Multiple players hit by single explosion means poor spread during add kills
- **Primordial Roar deaths**: Deaths immediately after Primordial Roar cast indicate platform falls
- **Death timing during Void Breath**: Clustered deaths during the 15-second beam window suggest wall trap scenario
- **Add kill locations**: Compare Blistercreep death positions with Crystal Wall positions to assess coordination
- **(Mythic) Void puddle damage**: Damage from persistent ground effects shows puddle placement issues
- **Dispel timing**: Track how quickly Blistercreep slow debuffs are dispelled from fixated players
