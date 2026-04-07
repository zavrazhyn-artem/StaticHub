<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-5">
            {{ __('Static Achievements') }}
        </h2>

        <div v-if="raidProgression.length" class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-black/20 text-gray-500 text-[9px] uppercase tracking-widest font-bold border-b border-white/5">
                        <template v-for="wing in raidProgression" :key="wing.instance">
                            <th :colspan="wing.bosses.length" class="p-2 text-center border-l border-white/5 first:border-l-0">
                                {{ wing.instance }}
                            </th>
                        </template>
                    </tr>
                    <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
                        <th
                            v-for="boss in allBosses"
                            :key="boss.name"
                            class="p-3 text-center border-l border-white/5 first:border-l-0 text-[9px]"
                        >
                            {{ boss.name }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="hover:bg-white/[0.02] transition-colors">
                        <td
                            v-for="boss in allBosses"
                            :key="boss.name"
                            class="p-3 text-center border-l border-white/5 first:border-l-0"
                        >
                            <span
                                v-if="boss.difficulty"
                                :class="['font-bold font-mono text-sm', diffColors[boss.difficulty]]"
                            >{{ boss.difficulty }}</span>
                            <span v-else class="text-gray-800">-</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="text-center py-6 text-on-surface-variant italic border border-dashed border-white/5 rounded-xl">
            {{ __('No raid progression data available.') }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    raidProgression: { type: Array, default: () => [] },
})

const diffColors = {
    M:   'text-orange-400',
    H:   'text-purple-400',
    N:   'text-blue-400',
    LFR: 'text-green-400',
}

const allBosses = computed(() => props.raidProgression.flatMap(w => w.bosses))
</script>
