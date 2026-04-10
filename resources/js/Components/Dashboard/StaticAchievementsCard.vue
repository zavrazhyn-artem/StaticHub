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
                            class="p-3 text-center border-l border-white/5 first:border-l-0 relative"
                            @mouseenter="hoveredBoss = boss.name"
                            @mouseleave="hoveredBoss = null"
                        >
                            <span
                                v-if="boss.difficulty"
                                :class="['font-bold font-mono text-sm cursor-default', diffColors[boss.difficulty]]"
                            >{{ boss.difficulty }}</span>
                            <span v-else class="text-gray-800">-</span>

                            <Transition name="tooltip">
                                <div
                                    v-if="hoveredBoss === boss.name && boss.history?.length"
                                    class="absolute z-50 bottom-full left-1/2 -translate-x-1/2 mb-2 pointer-events-none"
                                >
                                    <div class="tooltip-glass border border-white/10 px-3 py-2 rounded-lg shadow-2xl whitespace-nowrap">
                                        <div
                                            v-for="entry in sortedHistory(boss.history)"
                                            :key="entry.difficulty"
                                            class="flex items-center gap-2 text-[10px] font-bold tracking-wide"
                                        >
                                            <span :class="diffColors[entry.difficulty]">{{ entry.difficulty }}</span>
                                            <span class="text-gray-400">{{ formatDate(entry.achieved_at) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
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
import { computed, ref } from 'vue'

const props = defineProps({
    raidProgression: { type: Array, default: () => [] },
})

const hoveredBoss = ref(null)

const diffColors = {
    M:   'text-orange-400',
    H:   'text-purple-400',
    N:   'text-blue-400',
    LFR: 'text-green-400',
}

const diffRank = { LFR: 1, N: 2, H: 3, M: 4 }

const allBosses = computed(() => props.raidProgression.flatMap(w => w.bosses))

function sortedHistory(history) {
    return [...history].sort((a, b) => (diffRank[a.difficulty] ?? 0) - (diffRank[b.difficulty] ?? 0))
}

function formatDate(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<style scoped>
.tooltip-glass {
    background: rgba(23, 23, 23, 0.92);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.tooltip-enter-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.tooltip-leave-active {
    transition: all 0.15s ease-in;
}
.tooltip-enter-from,
.tooltip-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
