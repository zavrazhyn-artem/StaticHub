<script setup>
const props = defineProps({
    char: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    tierCount: { type: Function, required: true },
    isAlt: { type: Boolean, default: false },
});
</script>

<template>
    <td :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-2.5 text-center font-bold text-white border-l border-white/5">
        {{ tierCount(char?.tier_pieces) }}
    </td>
    <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="'ms-' + slot"
        :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-1.5 text-center">
        <span :class="tierColors[char?.tier_pieces?.[slot]] ?? 'text-gray-700'" class="text-[10px]">
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
