<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6 flex flex-col gap-8 flex-1">
        <div>
            <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">{{ __('Upcoming Objectives') }}</h2>
            <div class="space-y-4 max-h-[360px] overflow-y-auto pr-2 custom-scrollbar">
                <template v-if="events.length">
                    <div
                        v-for="event in events"
                        :key="event.id"
                        class="flex items-center justify-between p-4 bg-black/10 rounded-xl border border-white/5 hover:bg-white/5 transition-colors group"
                    >
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg bg-surface-container-highest flex flex-col items-center justify-center border border-white/5 group-hover:border-primary/50 transition-colors">
                                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-tighter leading-none">{{ event.month }}</span>
                                <span class="text-xl font-black text-white leading-none">{{ event.day }}</span>
                                <span class="text-[10px] font-bold text-primary uppercase tracking-tighter leading-none mt-0.5">{{ event.dayOfWeek }}</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-white uppercase tracking-wider">{{ __('Raid Event') }}</div>
                                <div class="text-xs text-on-surface-variant flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[12px]">schedule</span>
                                    {{ event.time }}
                                    <span class="mx-1 opacity-20">•</span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px] text-success-neon">groups</span>
                                        {{ event.rsvpCount }} {{ __('RSVPs') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <a
                            :href="eventUrl(event.id)"
                            class="bg-surface-container-highest hover:bg-primary/20 hover:text-primary text-on-surface-variant px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all"
                        >{{ __('MISSION INTEL') }}</a>
                    </div>
                </template>
                <div v-else class="text-center py-8 text-on-surface-variant italic border border-dashed border-white/5 rounded-xl">
                    {{ __('No additional objectives detected for this cycle.') }}
                </div>
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
