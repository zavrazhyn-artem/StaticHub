<script setup>
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, default: null },
    placeholderText: { type: String, default: 'Stats are only available for the Current Equipment list.' },
});

const fmt = (v) => Number(v ?? 0).toLocaleString();
const fmtPercent = (v) => `${Number(v ?? 0).toFixed(2)}%`;

const hasStats = computed(() => !!props.stats);

// Set-total mode (BiS / Custom lists): values are summed raw stat budget
// points scaled by ilvl, not actual rating percentages — render as integers.
const isSetTotal = computed(() => !!props.stats?.is_set_total);
const fmtEnhancement = (v) => isSetTotal.value ? fmt(v) : fmtPercent(v);
</script>

<template>
    <aside class="bg-surface-container/40 border border-white/5 rounded-xl p-4 space-y-3 backdrop-blur-sm">
        <!-- Empty state -->
        <div v-if="!hasStats" class="py-8 text-center">
            <span class="material-symbols-outlined text-3xl text-on-surface-variant/30">bar_chart</span>
            <p class="text-[11px] text-on-surface-variant/60 mt-2 px-2">{{ placeholderText }}</p>
        </div>

        <template v-else>
            <!-- Item Level -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3 text-center">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-1">Item Level</h4>
                <div class="text-3xl font-black font-headline text-cyan-300 tabular-nums leading-none">{{ stats.item_level }}</div>
            </section>

            <!-- Attributes -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">Attributes</h4>
                <ul class="space-y-1">
                    <li
                        v-for="a in stats.attributes"
                        :key="a.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span :class="['font-medium', a.is_main ? 'text-cyan-200' : 'text-on-surface-variant']">{{ a.label }}</span>
                        <span :class="['font-mono tabular-nums', a.is_main ? 'text-cyan-100 font-bold' : 'text-white']">{{ fmt(a.value) }}</span>
                    </li>
                </ul>
            </section>

            <!-- Enhancements -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">Enhancements</h4>
                <ul class="space-y-1">
                    <li
                        v-for="e in stats.enhancements"
                        :key="e.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span class="text-on-surface-variant font-medium">{{ e.label }}</span>
                        <span class="font-mono tabular-nums text-white">{{ fmtEnhancement(e.value) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </aside>
</template>
