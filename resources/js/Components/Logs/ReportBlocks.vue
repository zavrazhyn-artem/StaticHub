<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    blocks: { type: Array, default: () => [] },
});

const toneMap = {
    danger:  { text: 'text-red-400',   bg: 'bg-red-500/10',   border: 'border-red-500/30',   bar: 'bg-red-400' },
    warning: { text: 'text-amber-400', bg: 'bg-amber-500/10', border: 'border-amber-500/30', bar: 'bg-amber-400' },
    success: { text: 'text-green-400', bg: 'bg-green-500/10', border: 'border-green-500/30', bar: 'bg-green-400' },
    info:    { text: 'text-indigo-400',bg: 'bg-indigo-500/10',border: 'border-indigo-500/30',bar: 'bg-indigo-400' },
    neutral: { text: 'text-on-surface',bg: 'bg-white/5',      border: 'border-white/10',     bar: 'bg-on-surface-variant' },
};

const alertIcon = {
    danger:  'error',
    warning: 'warning',
    success: 'check_circle',
    info:    'info',
};

const severityToTone = {
    critical: 'danger',
    major:    'warning',
    minor:    'info',
};

function tone(name) {
    return toneMap[name] ?? toneMap.neutral;
}

function maxBarValue(bars) {
    if (!Array.isArray(bars) || !bars.length) return 1;
    return bars.reduce((max, b) => Math.max(max, Number(b.value) || 0), 0) || 1;
}

function barWidthPct(value, max) {
    const n = Number(value) || 0;
    return Math.max(2, Math.min(100, (n / max) * 100));
}

const empty = computed(() => !Array.isArray(props.blocks) || props.blocks.length === 0);
</script>

<template>
    <div class="space-y-6">
        <template v-for="(block, i) in blocks" :key="i">

            <!-- heading -->
            <h1 v-if="block.type === 'heading' && block.level === 1"
                class="font-headline text-3xl font-black text-white uppercase tracking-tight leading-tight">
                {{ block.text }}
            </h1>
            <h2 v-else-if="block.type === 'heading' && block.level === 2"
                class="font-headline text-xl font-black text-white uppercase tracking-wider border-b border-white/5 pb-3">
                {{ block.text }}
            </h2>
            <h3 v-else-if="block.type === 'heading' && block.level === 3"
                class="font-headline text-sm font-black text-indigo-400 uppercase tracking-[0.2em]">
                {{ block.text }}
            </h3>

            <!-- paragraph -->
            <p v-else-if="block.type === 'paragraph'"
               class="text-sm text-gray-300 leading-relaxed">
                {{ block.text }}
            </p>

            <!-- divider -->
            <div v-else-if="block.type === 'divider'" class="border-t border-white/5"></div>

            <!-- metrics_grid -->
            <div v-else-if="block.type === 'metrics_grid'"
                 class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div v-for="(item, j) in block.items" :key="j"
                     :class="['p-4 rounded-xl border', tone(item.tone).bg, tone(item.tone).border]">
                    <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant mb-2">
                        {{ item.label }}
                    </div>
                    <div :class="['text-2xl font-black font-headline', tone(item.tone).text]">
                        {{ item.value }}
                    </div>
                </div>
            </div>

            <!-- table -->
            <div v-else-if="block.type === 'table'"
                 class="bg-surface-container-low border border-white/5 rounded-xl overflow-hidden">
                <div v-if="block.title"
                     class="px-4 py-3 border-b border-white/5 bg-white/5 text-3xs font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ block.title }}
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-white/5 bg-black/20">
                                <th v-for="(col, k) in block.columns" :key="k"
                                    class="px-4 py-2 text-left text-3xs font-bold uppercase tracking-wider text-on-surface-variant">
                                    {{ col }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, r) in block.rows" :key="r"
                                class="border-b border-white/5 last:border-0 hover:bg-white/5 transition-colors">
                                <td v-for="(cell, c) in row" :key="c"
                                    class="px-4 py-2 text-gray-300">
                                    {{ cell }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- bar_chart -->
            <div v-else-if="block.type === 'bar_chart'"
                 class="bg-surface-container-low border border-white/5 rounded-xl p-4 space-y-3">
                <div v-if="block.title"
                     class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant pb-2 border-b border-white/5">
                    {{ block.title }}
                </div>
                <div v-for="(bar, j) in block.bars" :key="j" class="space-y-1">
                    <div class="flex justify-between items-baseline">
                        <span class="text-xs font-semibold text-white">{{ bar.label }}</span>
                        <span :class="['text-xs font-black font-headline', tone(bar.tone).text]">
                            {{ bar.value }}<span v-if="block.unit" class="text-3xs ml-0.5 opacity-60">{{ block.unit }}</span>
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-black/40 overflow-hidden">
                        <div :class="['h-full rounded-full transition-all duration-700', tone(bar.tone).bar]"
                             :style="{ width: barWidthPct(bar.value, maxBarValue(block.bars)) + '%' }">
                        </div>
                    </div>
                </div>
            </div>

            <!-- progress_bar -->
            <div v-else-if="block.type === 'progress_bar'"
                 class="space-y-1">
                <div class="flex justify-between items-baseline">
                    <span class="text-xs font-semibold text-white">{{ block.label }}</span>
                    <span :class="['text-xs font-black font-headline', tone(block.tone).text]">
                        {{ Math.round(block.value) }}%
                    </span>
                </div>
                <div class="h-2 rounded-full bg-black/40 overflow-hidden">
                    <div :class="['h-full rounded-full transition-all duration-700', tone(block.tone).bar]"
                         :style="{ width: Math.max(2, Math.min(100, Number(block.value) || 0)) + '%' }">
                    </div>
                </div>
                <div v-if="block.note" class="text-3xs text-on-surface-variant italic">{{ block.note }}</div>
            </div>

            <!-- alert -->
            <div v-else-if="block.type === 'alert'"
                 :class="['flex items-start gap-3 px-4 py-3 rounded-xl border', tone(block.severity).bg, tone(block.severity).border]">
                <span :class="['material-symbols-outlined text-lg flex-shrink-0 mt-0.5', tone(block.severity).text]">
                    {{ alertIcon[block.severity] || 'info' }}
                </span>
                <div class="flex-1 space-y-1">
                    <div v-if="block.title" :class="['text-xs font-bold uppercase tracking-wider', tone(block.severity).text]">
                        {{ block.title }}
                    </div>
                    <p class="text-sm text-gray-300 leading-relaxed">{{ block.text }}</p>
                </div>
            </div>

            <!-- directive_list -->
            <div v-else-if="block.type === 'directive_list'"
                 class="bg-indigo-500/5 border border-indigo-500/20 rounded-xl p-4 space-y-3">
                <div v-if="block.title"
                     class="text-3xs font-bold uppercase tracking-wider text-indigo-400">
                    {{ block.title }}
                </div>
                <ul class="space-y-2">
                    <li v-for="(item, j) in block.items" :key="j"
                        class="flex items-start gap-3 text-sm text-gray-300 leading-relaxed">
                        <span class="material-symbols-outlined text-sm text-indigo-400 flex-shrink-0 mt-0.5">arrow_forward</span>
                        <span>{{ item.text }}</span>
                    </li>
                </ul>
            </div>

            <!-- comparison -->
            <div v-else-if="block.type === 'comparison'"
                 class="bg-surface-container-low border border-white/5 rounded-xl overflow-hidden">
                <div v-if="block.title"
                     class="px-4 py-3 border-b border-white/5 bg-white/5 text-3xs font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ block.title }}
                </div>
                <div class="grid grid-cols-2 divide-x divide-white/5">
                    <div :class="['p-4 space-y-1', tone(block.left.tone).bg]">
                        <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant">{{ block.left.label }}</div>
                        <div :class="['text-lg font-black font-headline', tone(block.left.tone).text]">{{ block.left.value }}</div>
                    </div>
                    <div :class="['p-4 space-y-1', tone(block.right.tone).bg]">
                        <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant">{{ block.right.label }}</div>
                        <div :class="['text-lg font-black font-headline', tone(block.right.tone).text]">{{ block.right.value }}</div>
                    </div>
                </div>
            </div>

            <!-- rotation_issues -->
            <div v-else-if="block.type === 'rotation_issues'"
                 class="bg-surface-container-low border border-white/5 rounded-xl p-4 space-y-2">
                <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant pb-2 border-b border-white/5">
                    {{ __('Rotation Issues') }}
                </div>
                <div v-for="(issue, j) in block.issues" :key="j"
                     :class="['flex items-start gap-3 px-3 py-2 rounded-lg border', tone(severityToTone[issue.severity] || 'info').bg, tone(severityToTone[issue.severity] || 'info').border]">
                    <span class="text-xs font-bold text-white flex-shrink-0 min-w-[120px]">{{ issue.ability }}</span>
                    <span class="text-xs text-gray-300 flex-1">{{ issue.issue }}</span>
                    <span :class="['text-3xs font-bold uppercase tracking-wider flex-shrink-0', tone(severityToTone[issue.severity] || 'info').text]">
                        {{ issue.severity }}
                    </span>
                </div>
            </div>

            <!-- player_card -->
            <div v-else-if="block.type === 'player_card'"
                 class="bg-surface-container-low border border-white/5 rounded-xl p-4">
                <div class="flex items-center justify-between mb-3 pb-3 border-b border-white/5">
                    <div>
                        <div class="text-sm font-black font-headline text-white uppercase tracking-wider">{{ block.name }}</div>
                        <div class="text-3xs font-bold text-indigo-400 uppercase tracking-wider mt-0.5">
                            {{ block.spec }}<span v-if="block.ilvl" class="text-on-surface-variant ml-2">{{ block.ilvl }} ilvl</span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div v-for="(section, j) in block.sections" :key="j"
                         :class="['p-3 rounded-lg border', tone(section.tone).bg, tone(section.tone).border]">
                        <div class="text-4xs font-bold uppercase tracking-wider text-on-surface-variant mb-1">{{ section.label }}</div>
                        <div :class="['text-base font-black font-headline', tone(section.tone).text]">{{ section.value }}</div>
                    </div>
                </div>
            </div>

            <!-- unknown fallback -->
            <p v-else class="text-3xs text-on-surface-variant italic opacity-50">
                [{{ __('Unknown block type') }}: {{ block.type }}]
            </p>

        </template>

        <p v-if="empty" class="text-xs text-on-surface-variant italic">
            {{ __('No report content available.') }}
        </p>
    </div>
</template>
