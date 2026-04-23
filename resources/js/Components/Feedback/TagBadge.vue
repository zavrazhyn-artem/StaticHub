<script setup>
import { computed } from 'vue';
import { tagMeta } from './api.js';

const props = defineProps({
    tag: { type: String, required: true },
    size: { type: String, default: 'sm' }, // sm | md
});

const meta = computed(() => tagMeta(props.tag));
const paddingClass = computed(() =>
    props.size === 'md' ? 'px-2.5 py-1 text-3xs' : 'px-2 py-0.5 text-4xs',
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full border font-semibold uppercase tracking-wider"
        :class="[meta.classes, paddingClass]"
    >
        <span class="material-symbols-outlined text-3xs leading-none" style="font-size: 0.875em">{{ meta.icon }}</span>
        {{ __(meta.label) }}
    </span>
</template>
