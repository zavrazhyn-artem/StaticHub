<template>
    <!-- One CSS-grid row per all bosses; instance labels span their own boss columns. -->
    <div
        class="grid gap-x-1.5 gap-y-2"
        :style="{ gridTemplateColumns: `repeat(${totalBosses}, minmax(0, 1fr))` }"
    >
        <!-- Instance labels (top row) -->
        <template v-for="g in groups" :key="`lbl-${g.instance}`">
            <div
                :style="{ gridColumn: `span ${g.bosses.length} / span ${g.bosses.length}` }"
                class="text-[9px] uppercase tracking-[0.18em] font-bold text-on-surface-variant text-center pb-1"
            >{{ g.instance }}</div>
        </template>

        <!-- Boss pips (second row) -->
        <template v-for="g in groups" :key="`pips-${g.instance}`">
            <div
                v-for="(b, i) in g.bosses"
                :key="`${g.instance}-${i}`"
                class="p-2.5 rounded-xl relative overflow-hidden min-h-[64px] border"
                :style="b.killed
                    ? { background: tone(b.difficulty) + '10', borderColor: tone(b.difficulty) + '33' }
                    : { background: 'rgba(255,255,255,0.02)', borderColor: 'rgba(255,255,255,0.06)' }"
            >
                <!-- Active = pulsing dot only, no card-style change -->
                <span
                    v-if="b.active"
                    class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-tertiary-dim animate-pulse-dot"
                ></span>

                <div
                    class="text-[10px] font-semibold leading-tight overflow-hidden"
                    style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;"
                    :class="b.killed ? 'text-on-surface' : 'text-on-surface-variant'"
                    :title="b.name"
                >{{ b.name }}</div>

                <div class="flex items-baseline justify-between mt-1.5">
                    <span
                        class="text-[13px] font-extrabold font-mono"
                        :style="{ color: tone(b.difficulty) }"
                    >{{ b.difficulty || '—' }}</span>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    raidProgression: { type: Array, default: () => [] },
})

// Difficulty palette (per user's palette decision):
//   N   = blue, H   = purple, M   = orange, LFR = green
const TONES = {
    M:    '#fa7902', // orange (Mythic)
    H:    '#a855f7', // purple (Heroic)
    N:    '#3a8dff', // blue   (Normal)
    LFR:  '#39FF14', // green  (LFR)
}
function tone(diff) {
    return TONES[diff] ?? '#5a5a5a' // grey for not-killed
}

const RANK = { M: 4, H: 3, N: 2, LFR: 1 }

const groups = computed(() => {
    const flat = []
    const wings = props.raidProgression.map(inst => {
        const bosses = (inst.bosses ?? []).map(b => {
            const item = { name: b.name, difficulty: b.difficulty, killed: !!b.difficulty }
            flat.push(item)
            return item
        })
        return { instance: inst.instance, bosses }
    })

    // Active = first boss whose best diff is below the roster's current best.
    const bestRank = Math.max(0, ...flat.map(b => RANK[b.difficulty] ?? 0))
    let activeIdx = flat.findIndex(b => (RANK[b.difficulty] ?? 0) < bestRank)
    if (activeIdx === -1) activeIdx = flat.findIndex(b => !b.killed)
    if (activeIdx !== -1) flat[activeIdx].active = true

    return wings
})

const totalBosses = computed(() => groups.value.reduce((sum, g) => sum + g.bosses.length, 0))
</script>
