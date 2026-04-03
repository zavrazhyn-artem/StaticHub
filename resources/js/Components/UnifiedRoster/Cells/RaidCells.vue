<script setup>
import { computed } from 'vue';
const props = defineProps({
    char: { type: Object, required: true },
    groupedRoster: { type: Object, required: true },
    raidColumns: { type: Array, required: true },
    selectedDifficulty: { type: String, required: true },
    killMarkClass: { type: [String, Object], required: true },
    isAlt: { type: Boolean, default: false },
});

const getBossData = (raidName, bossName) => {
    return (props.char?.raids?.[raidName] ?? []).find(b => b.name === bossName);
};

const allRaids = computed(() => {
    // Find the first character that actually has raid data
    const charWithRaids = props.groupedRoster && Object.values(props.groupedRoster).flat().find(m => m.main_character?.raids !== undefined && m.main_character?.raids !== null)?.main_character;
    if (!charWithRaids) return {};

    const raidsObj = charWithRaids.raids;
    if (Array.isArray(raidsObj)) return {}; // Failsafe if backend returned empty array instead of object

    return raidsObj;
});
</script>

<template>
    <template v-for="(bosses, raidName) in allRaids" :key="raidName">
        <td v-for="boss in bosses"
            :key="boss.name"
            :class="isAlt ? 'h-[42px]' : 'h-[72px]'"
            class="p-0 text-center border-l border-white/[0.04] min-w-[60px]"
            :title="`${boss.name} (${raidName}) – ${getBossData(raidName, boss.name)?.[selectedDifficulty] ? 'Killed' : 'Not killed'} (${selectedDifficulty})`">
            <div class="flex items-center justify-center px-1" :class="isAlt ? 'py-1.5' : 'py-2.5'">
                <span v-if="getBossData(raidName, boss.name)?.[selectedDifficulty]"
                      class="font-black leading-none"
                      :class="[killMarkClass, isAlt ? 'text-sm opacity-70' : 'text-base']">✔</span>
                <span v-else
                      class="text-red-500 font-black leading-none"
                      :class="isAlt ? 'text-sm opacity-30' : 'text-base opacity-50'">✖</span>
            </div>
        </td>
    </template>
</template>
