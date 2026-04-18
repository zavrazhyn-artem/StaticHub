<script setup>
defineProps({
    icon: { type: String, required: true },
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtext: { type: String, default: null },
    iconColor: { type: String, default: 'text-primary' },
    valueColor: { type: String, default: 'text-white' },
});
</script>

<template>
    <div class="bg-surface-container-high border border-white/5 rounded-xl p-6 shadow-2xl backdrop-blur-sm relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <span class="material-symbols-outlined text-6xl" :class="iconColor">{{ icon }}</span>
        </div>
        <h3 class="text-on-surface-variant text-3xs font-semibold uppercase tracking-wider mb-4">{{ label }}</h3>
        <div class="flex items-baseline gap-2">
            <span class="text-4xl font-black tracking-tight font-headline" :class="valueColor">{{ value }}</span>
            <span v-if="subtext" class="text-xs font-bold text-on-surface-variant uppercase tracking-widest">{{ subtext }}</span>
        </div>
        <slot />
    </div>
</template>
