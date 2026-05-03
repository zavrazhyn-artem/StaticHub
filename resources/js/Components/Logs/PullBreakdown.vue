<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useAbilityLinker } from '@/composables/useAbilityLinker';

const { __ } = useTranslation();

const props = defineProps({
    encounter: { type: String, default: '' },
    pulls: { type: Array, default: () => [] },
    abilityMap: { type: Object, default: () => ({}) },
});

const { linkify } = useAbilityLinker(props.abilityMap);

const safePulls = computed(() => Array.isArray(props.pulls) ? props.pulls : []);

const activeIdx = ref(safePulls.value.findIndex(p => p?.outcome === 'kill'));
if (activeIdx.value < 0) activeIdx.value = 0;

const activePull = computed(() => safePulls.value[activeIdx.value] ?? null);

function selectPull(i) {
    if (i < 0 || i >= safePulls.value.length) return;
    activeIdx.value = i;
}

function step(delta) {
    selectPull(Math.max(0, Math.min(safePulls.value.length - 1, activeIdx.value + delta)));
}

function pullClasses(pull, idx) {
    const isActive = idx === activeIdx.value;
    const isKill = pull?.outcome === 'kill';
    if (isActive && isKill) return 'bg-success-neon text-black border-success-neon';
    if (isActive) return 'bg-indigo-500 text-white border-indigo-400';
    if (isKill) return 'bg-success-neon/20 text-success-neon border-success-neon/40 hover:bg-success-neon/30';
    return 'bg-white/5 text-on-surface-variant border-white/10 hover:bg-white/10 hover:text-white';
}

function compassLabel(d) {
    if (!d) return '';
    const parts = [];
    if (d.compass) parts.push(d.compass);
    if (d.distance) parts.push(`@ ${d.distance}`);
    return parts.join(' ');
}

const compassMap = {
    N: '↑', NE: '↗', E: '→', SE: '↘',
    S: '↓', SW: '↙', W: '←', NW: '↖',
    center: '•',
};
</script>

<template>
    <div v-if="safePulls.length"
         class="bg-surface-container-low border border-white/5 rounded-xl p-4 space-y-4">

        <!-- Header -->
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <div class="text-3xs font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ __('Pull Breakdown') }}
                </div>
                <div v-if="encounter" class="text-sm font-black font-headline text-white uppercase tracking-wider mt-0.5">
                    {{ encounter }}
                </div>
            </div>
            <div class="text-3xs text-on-surface-variant font-semibold uppercase tracking-wider">
                {{ activeIdx + 1 }} / {{ safePulls.length }}
            </div>
        </div>

        <!-- Slider -->
        <div class="flex items-center gap-3">
            <button type="button"
                    @click="step(-1)"
                    :disabled="activeIdx <= 0"
                    class="w-8 h-8 rounded-lg border border-white/10 text-on-surface-variant hover:text-white hover:border-white/30 transition-all disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-base">chevron_left</span>
            </button>

            <div class="flex-1 flex items-center gap-1 overflow-x-auto py-1">
                <button v-for="(pull, idx) in safePulls"
                        :key="idx"
                        type="button"
                        @click="selectPull(idx)"
                        :class="['min-w-[36px] h-8 rounded-md border text-xs font-bold transition-all flex-shrink-0', pullClasses(pull, idx)]">
                    {{ pull?.pull_number ?? (idx + 1) }}
                </button>
            </div>

            <button type="button"
                    @click="step(1)"
                    :disabled="activeIdx >= safePulls.length - 1"
                    class="w-8 h-8 rounded-lg border border-white/10 text-on-surface-variant hover:text-white hover:border-white/30 transition-all disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-base">chevron_right</span>
            </button>
        </div>

        <!-- Active pull body -->
        <div v-if="activePull" class="space-y-4 pt-2 border-t border-white/5">

            <!-- Top row: outcome + meta -->
            <div class="flex items-center gap-3 flex-wrap">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-3xs font-black uppercase tracking-widest border',
                    activePull.outcome === 'kill'
                        ? 'bg-success-neon/15 text-success-neon border-success-neon/40'
                        : 'bg-red-500/10 text-red-400 border-red-500/30']">
                    <span class="material-symbols-outlined text-sm">
                        {{ activePull.outcome === 'kill' ? 'flag' : 'close' }}
                    </span>
                    {{ activePull.outcome === 'kill' ? __('Kill') : __('Wipe') }}
                    <span v-if="activePull.outcome === 'wipe' && activePull.wipe_pct != null" class="opacity-80">
                        @ {{ Number(activePull.wipe_pct).toFixed(1) }}%
                    </span>
                </span>
                <span v-if="activePull.duration_s"
                      class="text-3xs font-semibold uppercase tracking-wider text-on-surface-variant">
                    {{ Math.floor(activePull.duration_s / 60) }}:{{ String(activePull.duration_s % 60).padStart(2, '0') }}
                </span>
                <span v-if="activePull.phase"
                      class="text-3xs font-semibold uppercase tracking-wider text-indigo-400">
                    {{ activePull.phase }}
                </span>
            </div>

            <!-- Summary -->
            <p v-if="activePull.summary"
               class="text-sm text-gray-300 leading-relaxed"
               v-html="linkify(activePull.summary)"></p>

            <!-- Pattern note -->
            <div v-if="activePull.pattern_note"
                 class="px-4 py-3 rounded-lg bg-amber-500/5 border border-amber-500/20 flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-400 text-base flex-shrink-0 mt-0.5">repeat</span>
                <p class="text-xs text-amber-300 leading-relaxed font-medium" v-html="linkify(activePull.pattern_note)"></p>
            </div>

            <!-- Key deaths -->
            <div v-if="Array.isArray(activePull.key_deaths) && activePull.key_deaths.length"
                 class="space-y-2">
                <div class="text-4xs font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ __('Key deaths') }}
                </div>
                <div class="space-y-1.5">
                    <div v-for="(d, j) in activePull.key_deaths" :key="j"
                         class="flex items-center gap-3 px-3 py-2 rounded-lg bg-black/20 border border-white/5">
                        <span class="text-xs font-bold text-white min-w-[100px]">{{ d.player }}</span>
                        <span v-if="d.time" class="text-3xs font-mono text-on-surface-variant">{{ d.time }}</span>
                        <span v-if="d.compass"
                              class="text-3xs font-bold text-indigo-400 inline-flex items-center gap-1">
                            <span>{{ compassMap[d.compass] || d.compass }}</span>
                            <span class="opacity-70">{{ compassLabel(d) }}</span>
                        </span>
                        <span class="text-xs text-gray-300 flex-1" v-html="linkify(d.ability || '')"></span>
                        <span v-if="d.cause"
                              class="text-3xs text-on-surface-variant italic">— {{ d.cause }}</span>
                    </div>
                </div>
            </div>

            <!-- Mechanic failures -->
            <div v-if="Array.isArray(activePull.mechanic_failures) && activePull.mechanic_failures.length"
                 class="space-y-2">
                <div class="text-4xs font-bold uppercase tracking-wider text-on-surface-variant">
                    {{ __('Mechanic failures') }}
                </div>
                <div class="space-y-1.5">
                    <div v-for="(m, j) in activePull.mechanic_failures" :key="j"
                         class="px-3 py-2 rounded-lg bg-red-500/5 border border-red-500/20">
                        <div class="text-xs font-bold text-red-300" v-html="linkify(m.mechanic || '')"></div>
                        <div v-if="m.detail" class="text-3xs text-gray-300 mt-1 leading-relaxed" v-html="linkify(m.detail)"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
