<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-4 flex flex-col gap-3 flex-1">
        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">{{ __('Upcoming Objectives') }}</h2>
        <div class="space-y-1.5 overflow-y-auto pr-1 custom-scrollbar">
            <template v-if="events.length">
                <a
                    v-for="event in events"
                    :key="event.id"
                    :href="eventUrl(event.id)"
                    class="flex items-center justify-between p-2 bg-black/10 rounded-lg border border-white/5 hover:bg-white/5 transition-colors group cursor-pointer"
                >
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-md bg-surface-container-highest flex flex-col items-center justify-center border border-white/5 group-hover:border-primary/50 transition-colors">
                            <span class="text-5xs font-semibold text-on-surface-variant uppercase tracking-tighter leading-none">{{ event.month }}</span>
                            <span class="text-sm font-black text-white leading-none">{{ event.day }}</span>
                        </div>
                        <div>
                            <div class="text-4xs font-semibold text-white uppercase tracking-wider">{{ event.dayOfWeek }}</div>
                            <div class="text-4xs text-on-surface-variant flex items-center gap-1">
                                <span class="material-symbols-outlined text-4xs">schedule</span>
                                {{ event.time }}
                                <span class="opacity-20">|</span>
                                <span class="flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-4xs text-success-neon">groups</span>
                                    {{ event.rsvpCount }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <span class="bg-surface-container-highest group-hover:bg-primary/20 group-hover:text-primary text-on-surface-variant px-2 py-0.5 rounded text-5xs font-black uppercase tracking-wider transition-all">{{ __('INTEL') }}</span>
                </a>
            </template>
            <div v-else class="text-center py-3 text-on-surface-variant italic text-3xs border border-dashed border-white/5 rounded-lg">
                {{ __('No objectives detected.') }}
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    events:        { type: Array,  default: () => [] },
    eventBaseUrl:  { type: String, default: '' },
})

function eventUrl(id) {
    return props.eventBaseUrl.replace('__ID__', id)
}
</script>
