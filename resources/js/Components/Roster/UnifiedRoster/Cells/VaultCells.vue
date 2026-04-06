<script setup>
const props = defineProps({
    char: { type: Object, required: true },
    isAlt: { type: Boolean, default: false },
});

/**
 * Vault slot logic:
 *   M+ → vault_weekly_runs (sorted desc): slots at runs[0], runs[3], runs[7]
 *   Raid → vault_raid_slots: precomputed [slot1, slot2, slot3]
 *   World → vault_world_runs (sorted desc): slots at runs[1], runs[3], runs[7]
 */
const getVaultSlot = (category, slotIndex) => {
    if (category === 'mythic') {
        const runs = props.char?.vault_weekly_runs || [];
        const needed = [1, 4, 8][slotIndex];
        if (runs.length >= needed) {
            const run = runs[needed - 1];
            return { ilvl: run.ilvl, track: run.track };
        }
        return null;
    }

    if (category === 'raid') {
        const slots = props.char?.vault_raid_slots;
        if (slots && slots[slotIndex]) {
            return { ilvl: slots[slotIndex].ilvl, track: slots[slotIndex].track };
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

const trackColor = {
    Myth:       'text-orange-400 font-bold',
    Hero:       'text-purple-400 font-bold',
    Champion:   'text-blue-400 font-bold',
    Veteran:    'text-green-400 font-bold',
    Adventurer: 'text-teal-400 font-bold',
};

const slotStyle = (slot) => {
    if (!slot) return 'text-gray-700';
    return trackColor[slot.track] || 'text-white font-bold';
};
</script>

<template>
    <!-- Raid Slots -->
    <td v-for="i in [0, 1, 2]" :key="'vr-' + i"
        :class="[isAlt ? 'h-[42px]' : 'h-[72px]', i === 0 ? 'border-l border-white/5' : '']"
        class="p-2 text-center font-mono text-sm">
        <span :class="slotStyle(getVaultSlot('raid', i))">
            {{ getVaultSlot('raid', i)?.ilvl || '-' }}
        </span>
    </td>

    <!-- M+ Slots -->
    <td v-for="i in [0, 1, 2]" :key="'vm-' + i"
        :class="[isAlt ? 'h-[42px]' : 'h-[72px]', i === 0 ? 'border-l border-white/10' : '']"
        class="p-2 text-center font-mono text-sm">
        <span :class="slotStyle(getVaultSlot('mythic', i))">
            {{ getVaultSlot('mythic', i)?.ilvl || '-' }}
        </span>
    </td>

    <!-- World Slots -->
    <td v-for="i in [0, 1, 2]" :key="'vw-' + i"
        :class="[isAlt ? 'h-[42px]' : 'h-[72px]', i === 0 ? 'border-l border-white/10' : '']"
        class="p-2 text-center font-mono text-sm">
        <span :class="slotStyle(getVaultSlot('world', i))">
            {{ getVaultSlot('world', i)?.ilvl || '-' }}
        </span>
    </td>
</template>
