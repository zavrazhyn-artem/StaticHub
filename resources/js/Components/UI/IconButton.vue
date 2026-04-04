<script setup>
const props = defineProps({
    icon: { type: String, required: true },
    title: { type: String, default: '' },
    variant: { type: String, default: 'default' },
    tag: { type: String, default: 'button' },
    type: { type: String, default: 'button' },
    size: { type: String, default: 'md' }, // 'md' = w-10 h-10, 'sm' = w-8 h-8
});
defineEmits(['click']);

const VARIANTS = {
    default:  'bg-white/5 hover:bg-white/10 text-on-surface-variant border-white/10',
    success:  'bg-green-600/20 hover:bg-green-600/30 text-green-500 border-green-600/30',
    warning:  'bg-yellow-600/20 hover:bg-yellow-600/30 text-yellow-500 border-yellow-600/30',
    danger:   'bg-red-600/20 hover:bg-red-600/30 text-red-500 border-red-600/30',
    discord:  'bg-[#5865F2]/20 hover:bg-[#5865F2]/30 text-[#5865F2] border-[#5865F2]/30',
    white:    'bg-white hover:bg-white/90 text-surface-container-highest border-white/10',
};
const SIZES = {
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
};
</script>

<template>
    <component
        :is="tag"
        :type="tag === 'button' ? type : undefined"
        :title="title"
        class="rounded-xl transition-all border flex items-center justify-center shadow-lg"
        :class="[VARIANTS[variant], SIZES[size]]"
        @click="$emit('click')"
    >
        <slot>
            <span class="material-symbols-outlined text-xl">{{ icon }}</span>
        </slot>
    </component>
</template>
