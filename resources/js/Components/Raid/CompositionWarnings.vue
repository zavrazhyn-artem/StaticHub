<script setup>
import { computed } from 'vue';

const props = defineProps({
    mainRoster: { type: Object, required: true },
    roleLimits: { type: Object, default: () => ({}) },
    buffConfig: { type: Object, default: () => ({}) },
    rosterClasses: { type: Array, default: () => [] },
    benchHistory: { type: Object, default: () => ({}) },
});

const warnings = computed(() => {
    const w = [];
    const tanks = (props.mainRoster.tank || []).length;
    const heals = (props.mainRoster.heal || []).length;
    const melee = (props.mainRoster.mdps || []).length;
    const ranged = (props.mainRoster.rdps || []).length;
    const total = tanks + heals + melee + ranged;
    const classes = new Set(props.rosterClasses);

    // Role warnings
    const tankLimit = props.roleLimits.tank ?? 2;
    if (tanks < tankLimit) {
        w.push({ type: 'error', icon: 'shield', text: `Only ${tanks} tank${tanks !== 1 ? 's' : ''} (need ${tankLimit})` });
    }

    const healMin = props.roleLimits.heal?.min ?? 3;
    const healMax = props.roleLimits.heal?.max ?? 5;
    if (heals < healMin) {
        w.push({ type: 'error', icon: 'healing', text: `Only ${heals} healer${heals !== 1 ? 's' : ''} (need ${healMin}+)` });
    } else if (heals > healMax) {
        w.push({ type: 'warning', icon: 'healing', text: `${heals} healers (max ${healMax} recommended)` });
    }

    if (melee > 8) {
        w.push({ type: 'warning', icon: 'swords', text: `${melee} melee DPS (>8 can be problematic)` });
    }

    const totalLimit = props.roleLimits.total ?? 20;
    if (total > totalLimit) {
        w.push({ type: 'warning', icon: 'group', text: `${total} players (limit ${totalLimit})` });
    }

    // Essential utility checks
    const utility = props.buffConfig.utility || {};
    const lustProviders = utility['Bloodlust'] || [];
    if (!lustProviders.some(c => classes.has(c))) {
        w.push({ type: 'error', icon: 'bolt', text: 'No Bloodlust / Heroism' });
    }

    const bresProviders = utility['Combat Resurrection'] || [];
    if (!bresProviders.some(c => classes.has(c))) {
        w.push({ type: 'error', icon: 'restart_alt', text: 'No Battle Res' });
    }

    // Bench rotation
    const benchedChars = [];
    for (const roleKey of ['tank', 'heal', 'mdps', 'rdps']) {
        for (const char of (props.mainRoster[roleKey] || [])) {
            const h = props.benchHistory[char.id];
            if (h && h.bench_count >= 2) {
                benchedChars.push(char.name);
            }
        }
    }
    if (benchedChars.length > 0) {
        w.push({ type: 'info', icon: 'airline_seat_recline_normal', text: `Frequently benched: ${benchedChars.join(', ')}` });
    }

    return w;
});
</script>

<template>
    <div v-if="warnings.length" class="flex flex-wrap gap-1.5">
        <div
            v-for="(warn, i) in warnings"
            :key="i"
            class="flex items-center gap-1 px-2 py-1 rounded-lg text-4xs font-semibold border"
            :class="{
                'bg-red-500/10 border-red-500/20 text-red-400': warn.type === 'error',
                'bg-yellow-500/10 border-yellow-500/20 text-yellow-400': warn.type === 'warning',
                'bg-blue-500/10 border-blue-500/20 text-blue-400': warn.type === 'info',
            }"
        >
            <span class="material-symbols-outlined text-xs">{{ warn.icon }}</span>
            <span>{{ warn.text }}</span>
        </div>
    </div>
</template>
