<template>
    <div class="h-full flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <div class="text-[11px] text-on-surface-variant uppercase tracking-[0.16em] font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-base">psychology</span>
                {{ __('Latest AI report') }}
            </div>
            <a
                v-if="report"
                :href="report.href"
                class="text-[10px] text-primary hover:text-primary-dim font-bold uppercase tracking-wider"
            >{{ __('Open') }} →</a>
        </div>

        <!-- Has report -->
        <a
            v-if="report"
            :href="report.href"
            class="flex-1 flex flex-col rounded-xl border border-primary/[0.18] p-4 transition hover:border-primary/[0.40] hover:bg-primary/[0.04]"
            :style="{ background: 'linear-gradient(135deg, rgba(79,211,247,0.06), rgba(79,211,247,0.01) 60%)' }"
        >
            <div class="flex items-center gap-2 mb-2">
                <span
                    v-if="report.difficulty"
                    class="text-[10px] font-extrabold font-mono px-2 py-0.5 rounded"
                    :style="{ color: diffColor, background: diffColor + '22', border: `1px solid ${diffColor}55` }"
                >{{ report.difficulty }}</span>
                <span class="text-[10px] text-on-surface-variant font-mono">{{ report.createdHuman }}</span>
            </div>

            <div
                class="text-[14px] font-extrabold text-on-surface leading-tight overflow-hidden"
                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"
                :title="report.title"
            >{{ report.title }}</div>

            <div class="mt-auto pt-3 flex items-center gap-1.5 text-[10px] text-success-neon font-bold uppercase tracking-wider">
                <span class="material-symbols-outlined text-sm">check_circle</span>
                {{ __('Analyzed by AI') }}
            </div>
        </a>

        <!-- Empty state -->
        <div
            v-else
            class="flex-1 flex flex-col items-center justify-center text-center px-4 py-6 rounded-xl border border-dashed border-white/[0.08]"
        >
            <span class="material-symbols-outlined text-2xl text-on-surface-variant opacity-40">psychology</span>
            <div class="text-[11px] text-on-surface-variant font-semibold mt-2">{{ __('No AI reports yet') }}</div>
            <div class="text-[10px] text-on-surface-variant opacity-70 mt-1">{{ __('Add a WCL log to start analysing') }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'

const { __ } = useTranslation()

const props = defineProps({
    report: { type: Object, default: null },
})

const DIFF_COLORS = { M: '#fa7902', H: '#a855f7', N: '#3a8dff', LFR: '#39FF14' }
const diffColor = computed(() => DIFF_COLORS[props.report?.difficulty] ?? '#9a9a9a')
</script>
