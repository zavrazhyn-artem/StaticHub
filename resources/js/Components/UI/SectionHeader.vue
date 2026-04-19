<script setup>
defineProps({
    icon: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="flex items-center gap-3 mb-6">
        <span v-if="icon" class="material-symbols-outlined text-primary">{{ icon }}</span>
        <div>
            <h2 class="font-headline text-xs font-bold text-on-surface-variant uppercase tracking-widest">{{ title }}</h2>
            <p v-if="subtitle" class="text-3xs text-on-surface-variant/60 uppercase tracking-wider mt-0.5">{{ subtitle }}</p>
        </div>
        <slot />
    </div>
</template>
