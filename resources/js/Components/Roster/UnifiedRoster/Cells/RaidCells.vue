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

const getBossData = (raidName, bossName) => {
    return (props.char?.raids?.[raidName] ?? []).find(b => b.name === bossName);
};
</script>

<template>
    <template v-for="raid in raidColumns" :key="raid.name">
        <td v-for="bossName in raid.bosses"
            :key="bossName"
            :class="rh"
            class="p-0 text-center border-l border-white/[0.04] min-w-[60px]"
            :title="`${bossName} (${raid.name}) – ${getBossData(raid.name, bossName)?.[selectedDifficulty] ? __('Killed') : __('Not killed')} (${selectedDifficulty})`">
            <div class="flex items-center justify-center px-1" :class="isAlt ? 'py-0' : 'py-2.5'">
                <span v-if="getBossData(raid.name, bossName)?.[selectedDifficulty]"
                      class="font-black leading-none"
                      :class="[killMarkClass, isAlt ? 'text-sm opacity-70' : 'text-base']">✔</span>
                <span v-else
                      class="text-red-500 font-black leading-none"
                      :class="isAlt ? 'text-sm opacity-30' : 'text-base opacity-50'">✖</span>
            </div>
        </td>
    </template>
</template>
