<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, default: null },
    variant: { type: String, default: 'default' },
});

const variantClasses = computed(() => {
    const map = {
        success: 'bg-success-neon/10 text-success-neon',
        error: 'bg-error/10 text-error',
        warning: 'bg-amber-500/10 text-amber-500',
        info: 'bg-primary/10 text-primary',
        default: 'bg-white/5 text-on-surface-variant',
        gold: 'bg-[#FFD700]/10 text-[#FFD700]',
    };
    return map[props.variant] || map.default;
});
</script>

<template>
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-4xs font-bold uppercase tracking-widest" :class="variantClasses">
        <slot>{{ label }}</slot>
    </span>
</template>
