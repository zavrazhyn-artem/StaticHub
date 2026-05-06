<script setup>
import { useTranslation } from '@/composables/useTranslation';
import { useAbilityLinker } from '@/composables/useAbilityLinker';

const { __ } = useTranslation();

const props = defineProps({
    title: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    abilityMap: { type: Object, default: () => ({}) },
});

const { linkify } = useAbilityLinker(props.abilityMap);

const severityStyle = {
    critical: { dot: 'bg-red-400', text: 'text-red-400', border: 'border-red-500/30', bg: 'bg-red-500/5' },
    major:    { dot: 'bg-amber-400', text: 'text-amber-400', border: 'border-amber-500/30', bg: 'bg-amber-500/5' },
    minor:    { dot: 'bg-indigo-400', text: 'text-indigo-400', border: 'border-indigo-500/30', bg: 'bg-indigo-500/5' },
};

function style(sev) {
    return severityStyle[sev] ?? severityStyle.major;
}
</script>

<template>
    <div class="bg-surface-container-low border border-white/5 rounded-xl p-4 space-y-3">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-base text-amber-400">repeat</span>
            <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant">
                {{ title || __('Recurring Failures') }}
            </div>
        </div>
        <div class="space-y-2">
            <div v-for="(item, j) in items" :key="j"
                 :class="['px-4 py-3 rounded-lg border', style(item.severity).bg, style(item.severity).border]">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2">
                        <span :class="['w-2 h-2 rounded-full', style(item.severity).dot]"></span>
                        <span class="text-sm font-bold text-white">{{ item.name }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-3xs font-bold uppercase tracking-wider">
                        <span v-if="item.frequency" class="text-on-surface-variant">{{ item.frequency }}</span>
                        <span :class="style(item.severity).text">{{ item.severity }}</span>
                    </div>
                </div>
                <p v-if="item.detail"
                   class="text-xs text-gray-300 leading-relaxed mt-2"
                   v-html="linkify(item.detail)"></p>
            </div>
        </div>
    </div>
</template>
