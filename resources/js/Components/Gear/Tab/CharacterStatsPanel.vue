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

// Three render modes the panel supports:
//  - delta:    BiS/Custom vs current. Signed integers, colour-coded.
//  - setTotal: rare fallback when there's no current to baseline against.
//              Plain integers, no sign.
//  - real:    current equipment from BNet — secondaries are real %, the
//              rest are absolute values from the in-game sheet.
const isDelta = computed(() => !!props.stats?.is_delta);
const isSetTotal = computed(() => !!props.stats?.is_set_total);

const fmtAttribute = (v) => isDelta.value ? fmtDelta(v) : fmt(v);
const fmtEnhancement = (v) => {
    if (isDelta.value) return fmtDelta(v);
    if (isSetTotal.value) return fmt(v);
    return fmtPercent(v);
};
const fmtItemLevel = (v) => isDelta.value ? fmtDelta(v) : fmt(v);

// Tailwind colour for a signed delta — green when the new build adds,
// red when it loses. Zero stays neutral so the eye groups them with
// "no change" rather than wins or losses.
const deltaClass = (v) => {
    if (!isDelta.value) return '';
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
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-1">
                    {{ isDelta ? __('Item Level Δ') : __('Item Level') }}
                </h4>
                <div :class="['text-3xl font-black font-headline tabular-nums leading-none', isDelta ? deltaClass(stats.item_level) || 'text-cyan-300' : 'text-cyan-300']">{{ fmtItemLevel(stats.item_level) }}</div>
            </section>

            <!-- Attributes -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">
                    {{ isDelta ? __('Attributes Δ') : __('Attributes') }}
                </h4>
                <ul class="space-y-1">
                    <li
                        v-for="a in stats.attributes"
                        :key="a.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span :class="['font-medium', a.is_main ? 'text-cyan-200' : 'text-on-surface-variant']">{{ __(a.label) }}</span>
                        <span :class="['font-mono tabular-nums', isDelta ? deltaClass(a.value) : (a.is_main ? 'text-cyan-100 font-bold' : 'text-white')]">{{ fmtAttribute(a.value) }}</span>
                    </li>
                </ul>
            </section>

            <!-- Enhancements -->
            <section class="rounded-lg bg-surface-container border border-white/10 px-4 py-3">
                <h4 class="text-[10px] font-headline font-black uppercase tracking-widest text-on-surface-variant mb-2 text-center">
                    {{ isDelta ? __('Enhancements Δ') : __('Enhancements') }}
                </h4>
                <ul class="space-y-1">
                    <li
                        v-for="e in stats.enhancements"
                        :key="e.label"
                        class="flex items-center justify-between text-[12px]"
                    >
                        <span class="text-on-surface-variant font-medium">{{ __(e.label) }}</span>
                        <span :class="['font-mono tabular-nums', isDelta ? deltaClass(e.value) : 'text-white']">{{ fmtEnhancement(e.value) }}</span>
                    </li>
                </ul>
            </section>
        </template>
    </aside>
</template>
