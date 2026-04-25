<template>
    <div class="px-3 py-2.5 rounded-xl bg-surface-container-low border border-white/[0.06]">
        <div class="text-[9px] text-on-surface-variant uppercase tracking-[0.14em] font-bold whitespace-nowrap">{{ label }}</div>
        <div
            class="text-[18px] font-extrabold font-mono mt-0.5 leading-none whitespace-nowrap"
            :style="{ color: color || 'var(--tw-color-tertiary-dim, #ede45a)' }"
        >{{ value ?? '—' }}</div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: null },
    color: { type: String, default: null },
})
</script>
