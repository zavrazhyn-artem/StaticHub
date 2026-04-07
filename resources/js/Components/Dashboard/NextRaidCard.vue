<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 relative overflow-hidden group">
        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 blur-3xl group-hover:bg-primary/10 transition-colors"></div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
            <div>
                <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-1">{{ __('Incoming Objective') }}</h2>
                <template v-if="nextRaid">
                    <h3 class="text-3xl font-extrabold text-white tracking-tight">{{ __('Raid Event') }}</h3>
                    <p class="text-primary font-bold mt-1">{{ nextRaid.date }} @ {{ nextRaid.time }}</p>
                </template>
                <template v-else>
                    <h3 class="text-3xl font-extrabold text-on-surface-variant tracking-tight italic">{{ __('No scheduled raids') }}</h3>
                </template>
            </div>

            <div v-if="nextRaid" class="flex items-center gap-6">
                <div class="text-center">
                    <div class="text-3xl font-black text-white tabular-nums">
                        <span class="inline-block min-w-[1.5ch] text-right">{{ days }}</span>{{ __('day_short') }}
                        <span class="inline-block min-w-[2ch] text-right">{{ padTwo(hours) }}</span>{{ __('hour_short') }}
                        <span class="inline-block min-w-[2ch] text-right">{{ padTwo(mins) }}</span>{{ __('minute_short') }}
                        <span class="inline-block min-w-[2ch] text-right">{{ padTwo(secs) }}</span>{{ __('second_short') }}
                    </div>
                    <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">{{ __('Countdown to Pull') }}</div>
                </div>
                <div class="h-12 w-px bg-white/10 hidden md:block"></div>
                <div>
                    <div v-if="nextRaid.discordPosted" class="flex items-center gap-2 text-success-neon">
                        <span class="material-symbols-outlined text-sm">check_circle</span>
                        <span class="text-xs font-bold uppercase tracking-wider">{{ __('Discord Posted') }}</span>
                    </div>
                    <div v-else class="flex items-center gap-2 text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">schedule</span>
                        <span class="text-xs font-bold uppercase tracking-wider">{{ __('Pending Post') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    nextRaid: { type: Object, default: null },
})

const days = ref(0)
const hours = ref(0)
const mins = ref(0)
const secs = ref(0)

let interval = null

function padTwo(n) {
    return String(n).padStart(2, '0')
}

function calculate() {
    if (!props.nextRaid) return
    let diff = props.nextRaid.timestamp - Math.floor(Date.now() / 1000)
    if (diff < 0) diff = 0
    days.value = Math.floor(diff / 86400)
    hours.value = Math.floor((diff % 86400) / 3600)
    mins.value = Math.floor((diff % 3600) / 60)
    secs.value = diff % 60
}

onMounted(() => {
    if (props.nextRaid) {
        calculate()
        interval = setInterval(calculate, 1000)
    }
})

onUnmounted(() => clearInterval(interval))
</script>
