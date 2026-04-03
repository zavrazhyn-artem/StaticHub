<script setup>
const props = defineProps({
    char: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    tierCount: { type: Function, required: true },
    isAlt: { type: Boolean, default: false },
});

const trackColorMap = {
    M: 'text-yellow-400',
    H: 'text-purple-400',
    C: 'text-blue-400',
    V: 'text-green-400',
    A: 'text-gray-400'
};
</script>

<template>
    <td :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-2.5 text-center font-bold text-white border-l border-white/5">
        {{ tierCount(char?.tier_pieces) }}
    </td>
    <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="'ms-' + slot"
        :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-1.5 text-center">
        <span :class="trackColorMap[char?.tier_pieces?.[slot]] ?? tierColors[char?.tier_pieces?.[slot]] ?? 'text-gray-700'" class="text-[10px] font-bold">
            {{ char?.tier_pieces?.[slot] || '-' }}
        </span>
    </td>
    <td :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-2.5 text-center font-mono font-bold text-white border-l border-white/5">
        {{ char?.weekly_runs_count ?? 0 }}
    </td>
    <td :class="[
            isAlt ? 'h-[42px]' : 'h-[72px]',
            (char?.mythic_rating ?? 0) > 0 ? 'text-purple-400' : 'text-gray-600'
        ]"
        class="p-2.5 text-center font-mono font-bold border-l border-white/5">
        {{ char?.mythic_rating != null
            ? Math.round(char.mythic_rating)
            : '—' }}
    </td>
</template>
