<template>
    <div
        :title="unavailable ? `${name} · ${__('Service unavailable')}` : `${name} · ${__('Last synced')} ${timeAgo}`"
        :class="[
            'w-[150px] flex items-center gap-2.5 pl-1.5 pr-3 py-1.5 rounded-2xl bg-white/[0.03] border border-white/[0.06]',
            unavailable ? 'opacity-60' : '',
        ]"
    >
        <!-- Mini progress ring -->
        <div class="relative w-[26px] h-[26px] shrink-0">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 30 30">
                <circle cx="15" cy="15" r="11" stroke="rgba(255,255,255,0.08)" stroke-width="3" fill="none"/>
                <circle
                    cx="15" cy="15" r="11"
                    :stroke="effectiveColor"
                    stroke-width="3"
                    fill="none"
                    :stroke-dasharray="circ"
                    :stroke-dashoffset="circ - (progress / 100) * circ"
                    stroke-linecap="round"
                    :style="{ filter: `drop-shadow(0 0 4px ${effectiveColor}88)`, transition: 'stroke-dashoffset 0.9s linear' }"
                />
            </svg>
        </div>

        <div class="flex flex-col items-start leading-tight min-w-0 flex-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant truncate w-full text-left">{{ name }}</span>
            <span class="text-[10px] text-on-surface-variant font-mono">{{ nextRefresh }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTranslation } from '@/composables/useTranslation'

const { __ } = useTranslation()

const props = defineProps({
    name:         { type: String, required: true },
    shortName:    { type: String, required: true },
    color:        { type: String, default: '#4fd3f7' },
    lastSyncedAt: { type: String, default: null },
    intervalMin:  { type: Number, default: 60 },
    now:          { type: Number, required: true }, // ms-epoch driven by parent
    unavailable:  { type: Boolean, default: false }, // service offline → grey state
})

// When unavailable, force the desaturated grey palette regardless of `color`.
const effectiveColor = computed(() => props.unavailable ? '#9a9a9a' : props.color)

const circ = 2 * Math.PI * 11

const lastSyncedMs = computed(() => {
    if (!props.lastSyncedAt) return null
    const d = new Date(props.lastSyncedAt).getTime()
    return Number.isNaN(d) ? null : d
})

const intervalMs = computed(() => props.intervalMin * 60 * 1000)

// Progress = how much of the interval remains until next sync (100% = just synced).
const progress = computed(() => {
    if (!lastSyncedMs.value) return 0
    const elapsed = props.now - lastSyncedMs.value
    return Math.max(0, Math.min(100, 100 - (elapsed / intervalMs.value) * 100))
})

const timeAgo = computed(() => {
    if (!lastSyncedMs.value) return __('Never')
    const diff = props.now - lastSyncedMs.value
    const m = Math.floor(diff / 60000)
    if (m < 1)  return __('Just now')
    if (m < 60) return `${m}${__('minute_short')}`
    const h = Math.floor(m / 60)
    if (h < 24) return `${h}${__('hour_short')}`
    const d = Math.floor(h / 24)
    return `${d}${__('day_short')}`
})

// Live countdown to next sync — ticks every second since `now` updates.
const nextRefresh = computed(() => {
    if (!lastSyncedMs.value) return __('Soon')
    const nextMs = lastSyncedMs.value + intervalMs.value
    const diff   = nextMs - props.now
    if (diff <= 0) return __('In queue')
    const totalSec = Math.floor(diff / 1000)
    const m = Math.floor(totalSec / 60)
    const s = totalSec % 60
    if (m > 0) return `${m}${__('minute_short')} ${s}${__('second_short')}`
    return `${s}${__('second_short')}`
})
</script>
