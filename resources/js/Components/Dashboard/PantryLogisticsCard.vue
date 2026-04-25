<template>
    <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_2fr] gap-2.5">

        <!-- HERO: Guild Bank + sparkline -->
        <a
            :href="routes.treasury"
            class="block p-5 rounded-xl border border-tertiary-dim/[0.30] relative overflow-hidden transition hover:brightness-110"
            :style="{ background: `linear-gradient(135deg, rgba(252,242,102,0.16) 0%, rgba(252,242,102,0.03) 100%), var(--surface-low, #131315)` }"
        >
            <div
                class="absolute pointer-events-none"
                style="right: -30px; top: -30px; width: 160px; height: 160px; background: radial-gradient(circle, rgba(252,242,102,0.20), transparent 70%);"
            ></div>

            <div class="flex items-center gap-2 mb-1.5">
                <span class="material-symbols-outlined text-tertiary-dim text-xl">savings</span>
                <span class="text-[9.5px] text-on-surface-variant uppercase tracking-[0.14em] font-bold">{{ __('Guild Bank') }}</span>
            </div>

            <div class="text-[28px] font-extrabold text-tertiary-dim font-mono leading-none -tracking-wider">
                {{ reservesText }}
            </div>

            <div
                class="text-[10px] mt-1.5 font-semibold"
                :class="deltaClass"
            >{{ deltaText }}</div>

            <!-- Sparkline -->
            <svg v-if="hasSeries" width="100%" height="42" viewBox="0 0 220 42" preserveAspectRatio="none" class="mt-2.5">
                <polyline
                    :points="linePoints"
                    fill="none"
                    stroke="#fcf266"
                    stroke-width="2"
                    style="filter: drop-shadow(0 0 4px #fcf266aa);"
                />
                <polyline
                    :points="areaPoints"
                    fill="rgba(252,242,102,0.10)"
                />
            </svg>

            <div v-if="hasSeries" class="flex justify-between text-[9px] text-on-surface-variant font-mono mt-1">
                <span v-for="(d, i) in sparkline.days" :key="i">{{ dayShort(d) }}</span>
            </div>
        </a>

        <!-- MINI cards: weekly tax (top, full width) + 3 recipes (bottom, 3-col) -->
        <div class="flex flex-col gap-1.5">
            <!-- Weekly tax — full-width horizontal bar -->
            <a
                :href="routes.treasury"
                class="flex items-center gap-3 px-3.5 py-3 rounded-xl bg-surface-container-low border border-white/[0.06] hover:bg-white/[0.04] transition"
            >
                <span class="material-symbols-outlined text-success-neon text-lg shrink-0">payments</span>
                <div class="text-[10px] text-on-surface-variant uppercase tracking-wide font-bold shrink-0">{{ __('Weekly Tax') }}</div>
                <div class="flex-1 h-1.5 rounded-full bg-white/[0.06] overflow-hidden">
                    <div
                        class="h-full bg-success-neon"
                        :style="{ width: paidPct + '%', boxShadow: '0 0 6px #39FF14aa' }"
                    ></div>
                </div>
                <div class="text-[14px] font-extrabold text-on-surface font-mono leading-none whitespace-nowrap">
                    {{ paidCount }}<span class="text-on-surface-variant">/20</span>
                </div>
            </a>

            <!-- 3 recipes — equal-width grid, fills remaining space -->
            <div class="grid grid-cols-3 gap-1.5 flex-1">
                <div
                    v-for="r in recipesEnriched"
                    :key="r.name"
                    class="flex flex-col items-center text-center px-2.5 py-3 rounded-xl bg-surface-container-low border border-white/[0.06]"
                >
                    <img v-if="r.icon" :src="r.icon" :alt="r.name" class="w-8 h-8 rounded-md border border-white/10">
                    <div class="text-[9px] text-on-surface-variant uppercase tracking-wide font-bold mt-2 leading-tight" :title="r.name">
                        {{ r.shortName }}
                    </div>
                    <div class="text-[18px] font-extrabold text-on-surface font-mono mt-1 leading-none">{{ r.weeklyQty }}</div>
                    <div class="text-[9px] text-on-surface-variant font-mono mt-0.5">/{{ __('week_short') }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'

const { __ } = useTranslation()

const props = defineProps({
    reserves:     { type: String, default: '0' },
    autonomy:     { type: Number, default: 0 },
    recipes:      { type: Array,  default: () => [] },
    raidDays:     { type: Number, default: 3 },
    paidCount:    { type: Number, default: 0 },
    sparkline:    { type: Object, default: () => ({ days: [], series: [], delta: 0 }) },
    routes:       { type: Object, default: () => ({}) },
})

const reservesText = computed(() => props.reserves)
const paidPct = computed(() => Math.min(100, Math.round((props.paidCount / 20) * 100)))

const hasSeries = computed(() => Array.isArray(props.sparkline.series) && props.sparkline.series.length > 1)

// Build SVG points string from the running-balance series.
const linePoints = computed(() => {
    const s = props.sparkline.series
    if (!s.length) return ''
    const max = Math.max(...s)
    const min = Math.min(...s)
    const range = Math.max(1, max - min)
    const W = 220, H = 38, padTop = 4
    return s.map((v, i) => {
        const x = (i / (s.length - 1)) * W
        const y = padTop + (1 - (v - min) / range) * (H - padTop)
        return `${x.toFixed(1)},${y.toFixed(1)}`
    }).join(' ')
})

const areaPoints = computed(() => {
    if (!linePoints.value) return ''
    return `${linePoints.value} 220,42 0,42`
})

// Delta: green if up, red if down, neutral for 0. Format using the same gold-like locale.
function formatGold(copperOrGold) {
    // bankSparkline.delta is in copper (matches reserves storage). 100 copper = 1 silver = 0.0001 gold.
    // BlastR stores reserves in COPPER (as in CurrencyHelper::formatGold).
    const gold = Math.round(copperOrGold / 10000)
    const sign = gold > 0 ? '↑ +' : (gold < 0 ? '↓ ' : '')
    const abs  = Math.abs(gold).toLocaleString('uk').replace(/,/g, ' ')
    return `${sign}${abs} G`
}

const deltaText = computed(() => {
    const d = props.sparkline?.delta ?? 0
    return d === 0 ? __('No change this week') : `${formatGold(d)} ${__('this week')}`
})

const deltaClass = computed(() => {
    const d = props.sparkline?.delta ?? 0
    if (d > 0) return 'text-success-neon'
    if (d < 0) return 'text-error'
    return 'text-on-surface-variant'
})

// Localised weekday short labels — convert "Mon"/"Tue"/etc. to lowercase 2-letter.
const dayMap = {
    Mon: 'пн', Tue: 'вт', Wed: 'ср', Thu: 'чт', Fri: 'пт', Sat: 'сб', Sun: 'нд',
    Пн: 'пн', Вт: 'вт', Ср: 'ср', Чт: 'чт', Пт: 'пт', Сб: 'сб', Нд: 'нд',
}
function dayShort(d) {
    return (dayMap[d] ?? d).toLowerCase()
}

// Short recipe label — first 2-3 words, fits under the icon.
const SHORT_RECIPE = {
    'Voidlight Potion Cauldron':       'КОТЛИ',
    'Cauldron of Sin\'dorei Flasks':   'ФЛАКОНИ',
    'Hearty Harandar Celebration':     'ЇЖА',
}

const recipesEnriched = computed(() => {
    return (props.recipes ?? []).map(r => ({
        name:      r.name,
        icon:      r.icon,
        weeklyQty: (r.quantity ?? 0) * (props.raidDays ?? 3),
        shortName: SHORT_RECIPE[r.name] ?? r.name.split(' ').slice(0, 2).join(' ').toUpperCase(),
    }))
})
</script>
