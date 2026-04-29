<script setup>
import { inject, computed, getCurrentInstance } from 'vue';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const rowHeights = inject('rowHeights');

const props = defineProps({
    char: { type: Object, required: true },
    groupedRoster: { type: Object, required: true },
    raidColumns: { type: Array, required: true },
    selectedDifficulty: { type: String, required: true },
    killMarkClass: { type: [String, Object], required: true },
    isAlt: { type: Boolean, default: false },
});

const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);

const DIFF_COLORS = { M: '#a855f7', H: '#3a8dff', N: '#4ade80', LFR: '#22d3ee' };
const diffColor = computed(() => DIFF_COLORS[props.selectedDifficulty] ?? '#adaaad');

const getBossData = (raidName, bossName) => {
    return (props.char?.raids?.[raidName] ?? []).find(b => b.name === bossName);
};

const isKilled = (raidName, bossName) =>
    !!getBossData(raidName, bossName)?.[props.selectedDifficulty];
</script>

<template>
    <template v-for="raid in raidColumns" :key="raid.name">
        <td v-for="bossName in raid.bosses"
            :key="bossName"
            :class="rh"
            class="p-0 text-center border-l border-white/[0.04] min-w-[60px] transition-colors"
            :style="isKilled(raid.name, bossName) ? { background: diffColor + '0d' } : {}"
            :title="`${bossName} – ${isKilled(raid.name, bossName) ? __('Killed') : __('Not killed')} (${selectedDifficulty})`">
            <div class="flex items-center justify-center h-full"
                 :class="isAlt ? 'py-0' : 'py-2.5'">
                <span v-if="isKilled(raid.name, bossName)"
                      class="material-symbols-outlined font-black leading-none"
                      :class="[killMarkClass, isAlt ? 'text-base' : 'text-lg']">
                    check
                </span>
                <span v-else
                      class="font-black leading-none select-none"
                      :class="isAlt ? 'text-base' : 'text-xl'"
                      style="color: rgba(255,255,255,0.08);">
                    ·
                </span>
            </div>
        </td>
    </template>
</template>
