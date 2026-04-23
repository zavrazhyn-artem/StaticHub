<script setup>
const props = defineProps({
    active: { type: String, default: 'feedback' }, // feedback | roadmap | changelog | help
});

const tabs = [
    { key: 'feedback', label: 'Feedback', href: '/feedback', icon: 'forum' },
    { key: 'roadmap', label: 'Roadmap', href: '/roadmap', icon: 'map' },
    { key: 'changelog', label: 'Changelog', href: '/changelog', icon: 'campaign', disabled: true },
    { key: 'help', label: 'Help', href: '/help', icon: 'help', disabled: true },
];
</script>

<template>
    <nav class="flex items-center gap-1 mb-6 border-b border-white/5">
        <a
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.disabled ? undefined : tab.href"
            class="inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold transition border-b-2 -mb-px"
            :class="[
                active === tab.key
                    ? 'text-primary border-primary'
                    : 'text-on-surface-variant border-transparent hover:text-on-surface',
                tab.disabled ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
            ]"
        >
            <span class="material-symbols-outlined text-base">{{ tab.icon }}</span>
            {{ __(tab.label) }}
            <span v-if="tab.disabled" class="text-3xs uppercase tracking-wider text-on-surface-variant/60">{{ __('soon') }}</span>
        </a>
    </nav>
</template>
