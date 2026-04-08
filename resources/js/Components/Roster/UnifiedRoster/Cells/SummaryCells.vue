<script setup>
import { inject, computed } from 'vue';

const rowHeights = inject('rowHeights');

const props = defineProps({
    char: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    tierCount: { type: Function, required: true },
    isAlt: { type: Boolean, default: false },
});

const trackColorMap = {
    Myth:       'text-yellow-400',
    Hero:       'text-purple-400',
    Champion:   'text-blue-400',
    Veteran:    'text-green-400',
    Adventurer: 'text-teal-400',
    Explorer:   'text-gray-400',
};

// Display first letter as abbreviation
const trackAbbrev = (track) => {
    if (!track || track === '-') return '-';
    const map = {
        Myth: 'M', Hero: 'H', Champion: 'C',
        Veteran: 'V', Adventurer: 'A', Explorer: 'E',
    };
    return map[track] ?? track;
};

const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);
</script>

<template>
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center font-mono font-bold text-cyan-400 border-l border-white/5']">
        {{ char?.equipped_ilvl != null
        ? Number(char.equipped_ilvl).toFixed(1)
        : 'N/A' }}
    </td>
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5']" class="text-center font-bold text-white border-l border-white/5">
        {{ tierCount(char?.tier_pieces) }}
    </td>
    <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="'ms-' + slot"
        :class="[rh, isAlt ? 'px-0.5 py-0.5' : 'p-1.5']" class="text-center">
        <span :class="trackColorMap[char?.tier_pieces?.[slot]] ?? tierColors[char?.tier_pieces?.[slot]] ?? 'text-gray-700'" class="text-[10px] font-bold">
            {{ trackAbbrev(char?.tier_pieces?.[slot]) }}
        </span>
    </td>
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5']" class="text-center font-mono font-bold text-white border-l border-white/5">
        {{ char?.weekly_runs_count ?? 0 }}
    </td>
    <td :class="[
            rh, isAlt ? 'px-1 py-0.5' : 'p-2.5',
            (char?.mythic_rating ?? 0) > 0 ? 'text-purple-400' : 'text-gray-600'
        ]"
        class="text-center font-mono font-bold border-l border-white/5">
        {{ char?.mythic_rating != null
            ? Math.round(char.mythic_rating)
            : '—' }}
    </td>
</template>
