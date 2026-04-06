<script setup>
defineProps({
    icon: { type: String, default: 'folder_off' },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div class="text-center py-12 text-on-surface-variant border border-dashed border-white/5 rounded-xl">
        <span class="material-symbols-outlined text-4xl mb-3 block opacity-40">{{ icon }}</span>
        <p class="text-sm font-bold uppercase tracking-widest">{{ title }}</p>
        <p v-if="description" class="text-xs mt-2 opacity-60">{{ description }}</p>
        <slot />
    </div>
</template>
