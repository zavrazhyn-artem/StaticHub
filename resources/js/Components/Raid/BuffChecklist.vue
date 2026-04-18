<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    buffConfig: { type: Object, required: true },
    rosterClasses: { type: Array, default: () => [] }, // array of class names present in roster
});

const coverage = computed(() => {
    const classes = new Set(props.rosterClasses);
    const sections = [];

    // Buffs/Debuffs
    const buffs = Object.entries(props.buffConfig.buffs_debuffs || {}).map(([name, providers]) => ({
        name,
        providers,
        count: providers.filter(c => classes.has(c)).length,
        present: providers.some(c => classes.has(c)),
    }));
    sections.push({ label: __('Buffs'), items: buffs });

    // Utility
    const utility = Object.entries(props.buffConfig.utility || {}).map(([name, providers]) => ({
        name,
        providers,
        count: providers.filter(c => classes.has(c)).length,
        present: providers.some(c => classes.has(c)),
    }));
    sections.push({ label: __('Utility'), items: utility });

    return sections;
});

const missingItems = computed(() => {
    return coverage.value.flatMap(s => s.items.filter(i => !i.present).map(i => i.name));
});
</script>

<template>
    <div class="bg-surface-container/60 border border-white/5 rounded-xl p-3 backdrop-blur-sm">
        <div v-for="section in coverage" :key="section.label" class="mb-2 last:mb-0">
            <div class="text-5xs font-bold uppercase tracking-[0.15em] text-on-surface-variant/50 mb-1.5">{{ section.label }}</div>
            <div class="flex flex-wrap gap-1">
                <div
                    v-for="item in section.items"
                    :key="item.name"
                    class="flex items-center gap-1 px-1.5 py-0.5 rounded text-4xs font-semibold border transition-colors"
                    :class="item.present
                        ? 'bg-green-500/10 border-green-500/20 text-green-400'
                        : 'bg-red-500/10 border-red-500/20 text-red-400'"
                    :title="item.providers.join(', ')"
                >
                    <span class="material-symbols-outlined text-3xs">{{ item.present ? 'check' : 'close' }}</span>
                    <span>{{ item.name }}</span>
                    <span v-if="item.count > 1" class="opacity-60">x{{ item.count }}</span>
                </div>
            </div>
        </div>

        <!-- Missing summary -->
        <div v-if="missingItems.length" class="mt-2 pt-2 border-t border-white/5">
            <div class="flex items-center gap-1 text-4xs text-red-400">
                <span class="material-symbols-outlined text-xs">warning</span>
                <span class="font-bold">{{ __('Missing:') }}</span>
                <span class="text-red-400/70">{{ missingItems.join(', ') }}</span>
            </div>
        </div>
    </div>
</template>
