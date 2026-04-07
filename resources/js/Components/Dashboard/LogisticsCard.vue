<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-4">{{ __('Logistics Snapshot') }}</h2>
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <!-- Guild Bank -->
                <a :href="routes.treasury" class="flex items-center justify-between p-3 bg-black/20 rounded-xl border border-white/5 hover:bg-white/5 transition-colors group">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-tertiary-dim/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-tertiary-dim text-lg">savings</span>
                        </div>
                        <div>
                            <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">{{ __('Guild Bank') }}</div>
                            <div class="text-sm font-black text-[#FFD700]">{{ reserves }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[8px] text-on-surface-variant font-bold uppercase tracking-[0.2em]">{{ __('Autonomy') }}</div>
                        <div class="text-xs font-black" :class="autonomy > 2 ? 'text-success-neon' : 'text-error'">
                            {{ autonomy }} <span class="text-[8px] font-normal opacity-60">{{ __('WK') }}</span>
                        </div>
                    </div>
                </a>

                <!-- Collection Progress -->
                <div class="p-3 bg-black/20 rounded-xl border border-white/5">
                    <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mb-2 text-center">
                        {{ __('Collection Progress') }} ({{ weekRange }})
                    </div>
                    <div class="grid grid-cols-10 gap-1">
                        <div
                            v-for="(status, i) in weeklyStatus"
                            :key="'paid-' + i"
                            class="h-1.5 rounded-full"
                            :class="status.is_paid ? 'bg-success-neon shadow-[0_0_5px_rgba(0,255,153,0.3)]' : 'bg-white/10'"
                            :title="status.name + ': ' + (status.is_paid ? __('Paid') : __('Pending'))"
                        ></div>
                        <div
                            v-for="i in emptySlots"
                            :key="'empty-' + i"
                            class="h-1.5 rounded-full bg-white/5 opacity-50 border border-dashed border-white/10"
                            title="Empty Slot"
                        ></div>
                    </div>
                    <div class="flex justify-between mt-2">
                        <span class="text-[9px] font-bold text-on-surface-variant uppercase tracking-widest">
                            {{ __('Paid:') }} {{ paidCount }} / 20
                        </span>
                        <a :href="routes.treasury" class="text-[9px] font-bold text-primary hover:text-white uppercase tracking-widest flex items-center gap-1">
                            {{ __('Full Ledger') }}
                            <span class="material-symbols-outlined text-[10px]">chevron_right</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div v-for="recipe in recipes" :key="recipe.name" class="p-3 bg-black/20 rounded-xl border border-white/5 flex items-center gap-3">
                    <img :src="recipe.icon" class="w-8 h-8 rounded border border-white/10 shrink-0" :alt="recipe.name">
                    <div class="min-w-0">
                        <div class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest truncate" :title="__(recipe.name)">{{ __(recipe.name) }}</div>
                        <div class="text-lg font-black text-white mt-0.5 whitespace-nowrap">
                            {{ recipe.quantity * raidDays }}
                            <span class="text-[10px] font-normal text-on-surface-variant uppercase ml-1">{{ __('/ Week') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    reserves:      { type: String, default: '0' },
    autonomy:      { type: Number, default: 0 },
    recipes:       { type: Array,  default: () => [] },
    raidDays:      { type: Number, default: 3 },
    weeklyStatus:  { type: Array,  default: () => [] },
    paidCount:     { type: Number, default: 0 },
    weekRange:     { type: String, default: '' },
    routes:        { type: Object, default: () => ({}) },
})

const emptySlots = computed(() => Math.max(0, 20 - props.weeklyStatus.length))
</script>
