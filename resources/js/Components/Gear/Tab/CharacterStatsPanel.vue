<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    stats: { type: Object, default: null },
    placeholderText: { type: String, default: '' },
});

const placeholderLabel = computed(() => props.placeholderText || __('Stats are only available for the Current Equipment list.'));

const fmt = (v) => Number(v ?? 0).toLocaleString();
const fmtPercent = (v) => `${Number(v ?? 0).toFixed(2)}%`;
// Signed delta: "+150" / "−50" (Unicode minus, not hyphen — looks better
// in the cyan tabular-nums context). Zero stays bare so 0-deltas don't
// shout for attention.
const fmtDelta = (v) => {
    const n = Math.round(Number(v ?? 0));
    if (n === 0) return '0';
    return (n > 0 ? '+' : '−') + fmt(Math.abs(n));
};

const hasStats = computed(() => !!props.stats);

// Render modes:
//  - deltaMode: BiS/Custom — absolute value plus signed delta in parens.
//               Backend backfilled missing slots with current's items so
//               value reflects the full equipped-after-swap loadout.
//  - setTotal:  Current/raw aggregate — plain integers from rating-based
//               sum (no real % conversion since we don't have BNet data).
//  - real:      Current equipment from BNet (legacy path) — secondaries
//               are %, rest are absolute. Kept for backward compat in case
//               anything still feeds the panel that shape.
const isDeltaMode = computed(() => !!props.stats?.is_delta_mode);
const isSetTotal = computed(() => !!props.stats?.is_set_total);

const fmtAttribute = (v) => fmt(v);
const fmtEnhancement = (v) => {
    if (isDeltaMode.value || isSetTotal.value) return fmt(v);
    return fmtPercent(v);
};
const fmtItemLevel = (v) => fmt(v);

// Tailwind colour for a signed delta — green when the new build adds,
// red when it loses, neutral on zero.
const deltaClass = (v) => {
    const n = Math.round(Number(v ?? 0));
    if (n > 0) return 'text-green-400';
    if (n < 0) return 'text-red-400';
    return 'text-on-surface-variant/60';
};
</script>

<template>
    <aside class="bg-surface-container/40 border border-white/5 rounded-xl p-4 space-y-3 backdrop-blur-sm">
        <!-- Empty state -->
        <div v-if="!hasStats" class="py-8 text-center">
            <span class="material-symbols-outlined text-3xl text-on-surface-variant/30">bar_chart</span>
            <p class="text-[11px] text-on-surface-variant/60 mt-2 px-2">{{ placeholderLabel }}</p>
        </div>

        <template v-else>
            <!-- Item Level -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3 text-center">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-1">{{ __('Item Level') }}</h4>
                <div class="text-3xl font-black font-headline tabular-nums leading-none text-cyan-300">
                    {{ fmtItemLevel(stats.item_level) }}<span v-if="isDeltaMode && stats.item_level_delta !== 0" :class="['text-base font-bold ml-1', deltaClass(stats.item_level_delta)]">({{ fmtDelta(stats.item_level_delta) }})</span>
                </div>
            </section>

            <!-- Attributes -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">{{ __('Attributes') }}</h4>
                <ul class="space-y-1">
                    <li
                        v-for="a in stats.attributes"
                        :key="a.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span :class="['font-medium', a.is_main ? 'text-cyan-200' : 'text-on-surface-variant']">{{ __(a.label) }}</span>
                        <span class="font-mono tabular-nums">
                            <span :class="a.is_main ? 'text-cyan-100 font-bold' : 'text-white'">{{ fmtAttribute(a.value) }}</span>
                            <span v-if="isDeltaMode && a.delta !== 0" :class="['ml-1', deltaClass(a.delta)]">({{ fmtDelta(a.delta) }})</span>
                        </span>
                    </li>
                </ul>
            </section>

            <!-- Enhancements -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">{{ __('Enhancements') }}</h4>
                <ul class="space-y-1">
                    <li
                        v-for="e in stats.enhancements"
                        :key="e.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span class="text-on-surface-variant font-medium">{{ __(e.label) }}</span>
                        <span class="font-mono tabular-nums">
                            <span class="text-white">{{ fmtEnhancement(e.value) }}</span>
                            <span v-if="isDeltaMode && e.delta !== 0" :class="['ml-1', deltaClass(e.delta)]">({{ fmtDelta(e.delta) }})</span>
                        </span>
                    </li>
                </ul>
            </section>
        </template>
    </aside>
</template>
