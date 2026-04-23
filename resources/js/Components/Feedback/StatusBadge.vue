<script setup>
import { computed } from 'vue';
import { STATUS_META } from './api.js';

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'sm' }, // sm | md
});

const meta = computed(() => STATUS_META[props.status] || STATUS_META.under_review);

const paddingClass = computed(() => (props.size === 'md' ? 'px-2.5 py-1 text-3xs' : 'px-2 py-0.5 text-4xs'));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-semibold uppercase tracking-wider"
        :class="[meta.classes, paddingClass]"
    >
        <span class="h-1.5 w-1.5 rounded-full" :class="meta.dotClass"></span>
        {{ __(meta.label) }}
    </span>
</template>
