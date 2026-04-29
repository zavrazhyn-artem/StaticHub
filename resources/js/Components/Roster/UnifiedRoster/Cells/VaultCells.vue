<script setup>
import { inject, computed } from 'vue';

const rowHeights = inject('rowHeights');

const props = defineProps({
    char: { type: Object, required: true },
    isAlt: { type: Boolean, default: false },
});

const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);

// Vault slot accessors — same logic as before
const getVaultSlot = (category, slotIndex) => {
    if (category === 'raid') {
        const s = props.char?.vault_raid_slots;
        return (s && s[slotIndex]) ? { ilvl: s[slotIndex].ilvl, track: s[slotIndex].track } : null;
    }
    if (category === 'mythic') {
        const runs = props.char?.vault_weekly_runs || [];
        const needed = [1, 4, 8][slotIndex];
        if (runs.length >= needed) {
            const run = runs[needed - 1];
            return { ilvl: run.ilvl, track: run.track };
        }
        return null;
    }
    if (category === 'world') {
        const runs = props.char?.vault_world_runs || [];
        const needed = [2, 4, 8][slotIndex];
        if (runs.length >= needed) {
            const run = runs[needed - 1];
            return { ilvl: run.ilvl, track: run.track };
        }
        return null;
    }
    return null;
};

// Track → colour (matches gear scale)
const TRACK_COLORS = {
    Myth: '#FB923C', Hero: '#C084FC', Champion: '#60A5FA',
    Veteran: '#4ADE80', Adventurer: '#2dd4bf',
};
const trackColor = (track) => TRACK_COLORS[track] ?? '#9ca3af';
</script>

<template>
    <!-- Raid Slots (0-2) -->
    <td v-for="i in [0, 1, 2]" :key="'vr-' + i"
        :class="[rh, 'text-center align-middle']"
        :style="{ padding: '6px 8px', borderLeft: i === 0 ? '1px solid rgba(255,255,255,0.06)' : 'none' }">
        <template v-if="getVaultSlot('raid', i)">
            <span :style="{
                fontSize: isAlt ? '13px' : '16px',
                fontWeight: 800,
                fontFamily: '\'JetBrains Mono\', monospace',
                letterSpacing: '-0.02em',
                color: trackColor(getVaultSlot('raid', i)?.track),
            }">{{ getVaultSlot('raid', i)?.ilvl }}</span>
        </template>
        <template v-else>
            <span style="font-size:14px; color:#767577; opacity:0.4;">—</span>
        </template>
    </td>

    <!-- M+ Slots (0-2) -->
    <td v-for="i in [0, 1, 2]" :key="'vm-' + i"
        :class="[rh, 'text-center align-middle']"
        :style="{ padding: '6px 8px', borderLeft: i === 0 ? '1px solid rgba(255,255,255,0.06)' : 'none' }">
        <template v-if="getVaultSlot('mythic', i)">
            <span :style="{
                fontSize: isAlt ? '13px' : '16px',
                fontWeight: 800,
                fontFamily: '\'JetBrains Mono\', monospace',
                letterSpacing: '-0.02em',
                color: trackColor(getVaultSlot('mythic', i)?.track),
            }">{{ getVaultSlot('mythic', i)?.ilvl }}</span>
        </template>
        <template v-else>
            <span style="font-size:14px; color:#767577; opacity:0.4;">—</span>
        </template>
    </td>

    <!-- World/Delve Slots (0-2) -->
    <td v-for="i in [0, 1, 2]" :key="'vw-' + i"
        :class="[rh, 'text-center align-middle']"
        :style="{ padding: '6px 8px', borderLeft: i === 0 ? '1px solid rgba(255,255,255,0.06)' : 'none' }">
        <template v-if="getVaultSlot('world', i)">
            <span :style="{
                fontSize: isAlt ? '13px' : '16px',
                fontWeight: 800,
                fontFamily: '\'JetBrains Mono\', monospace',
                letterSpacing: '-0.02em',
                color: trackColor(getVaultSlot('world', i)?.track),
            }">{{ getVaultSlot('world', i)?.ilvl }}</span>
        </template>
        <template v-else>
            <span style="font-size:14px; color:#767577; opacity:0.4;">—</span>
        </template>
    </td>
</template>
