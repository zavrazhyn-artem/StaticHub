<template>
    <div
        class="bg-surface-container-high rounded-2xl border border-white/5 p-6 relative overflow-hidden group"
        :class="{ 'border-l-4 border-error': taxStatus === 'danger' }"
    >
        <div class="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 transition-opacity">
            <span class="material-symbols-outlined text-6xl text-[#FFD700]">payments</span>
        </div>

        <div class="flex items-center justify-between mb-6">
            <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">{{ __('Treasury Overview') }}</h2>
            <span
                v-if="taxStatus !== 'success'"
                class="material-symbols-outlined text-lg"
                :class="taxStatus === 'danger' ? 'text-error animate-pulse' : 'text-warning'"
                :title="taxDescription"
            >{{ taxStatus === 'danger' ? 'warning' : 'info' }}</span>
        </div>

        <div class="space-y-6">
            <div>
                <div
                    class="text-3xs font-semibold uppercase tracking-wider mb-1 flex items-center gap-1"
                    :class="taxStatus === 'danger' ? 'text-error' : taxStatus === 'warning' ? 'text-warning' : 'text-on-surface-variant'"
                >
                    <span v-if="taxStatus !== 'success'" class="material-symbols-outlined text-xs">
                        {{ taxStatus === 'danger' ? 'trending_up' : 'trending_down' }}
                    </span>
                    {{ __('Weekly Tax Goal') }}
                </div>
                <div class="flex items-baseline gap-2">
                    <span
                        class="text-3xl font-black font-headline tracking-tight tabular-nums"
                        :class="taxStatus === 'danger' ? 'text-error' : taxStatus === 'warning' ? 'text-warning' : 'text-white'"
                    >{{ targetTax }}</span>
                    <span class="text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Gold / Player') }}</span>
                </div>
                <div
                    v-if="taxStatus !== 'success'"
                    class="mt-1 text-5xs font-semibold uppercase tracking-tighter"
                    :class="taxStatus === 'danger' ? 'text-error' : 'text-warning'"
                >{{ taxDescription }}</div>
            </div>

            <div class="pt-4 border-t border-white/5">
                <div class="text-3xs text-on-surface-variant font-semibold uppercase tracking-wider mb-3 text-center">
                    {{ __('Collection Progress') }} ({{ weekRange }})
                </div>
                <div class="grid grid-cols-10 gap-1">
                    <div
                        v-for="(status, i) in weeklyStatus"
                        :key="'paid-' + i"
                        class="h-1.5 rounded-full"
                        :class="status.is_paid ? 'bg-success-neon shadow-[0_0_5px_rgba(0,255,153,0.3)]' : 'bg-white/10'"
                        :title="status.display_name + ': ' + (status.is_paid ? __('Paid') : __('Pending'))"
                    ></div>
                    <div
                        v-for="i in emptySlots"
                        :key="'empty-' + i"
                        class="h-1.5 rounded-full bg-white/5 opacity-50 border border-dashed border-white/10"
                        :title="__('Empty Slot')"
                    ></div>
                </div>
                <div class="flex justify-between mt-2">
                    <span class="text-4xs font-semibold text-on-surface-variant uppercase tracking-wider">
                        {{ __('Paid:') }} {{ paidCount }} / 20
                    </span>
                    <a :href="routes.treasury" class="text-4xs font-semibold text-primary hover:text-white uppercase tracking-wider flex items-center gap-1">
                        {{ __('Full Ledger') }}
                        <span class="material-symbols-outlined text-3xs">chevron_right</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    taxStatus:    { type: String, default: 'success' },
    taxDescription: { type: String, default: '' },
    targetTax:    { type: String, default: '0' },
    weeklyStatus: { type: Array,  default: () => [] },
    paidCount:    { type: Number, default: 0 },
    weekRange:    { type: String, default: '' },
    routes:       { type: Object, default: () => ({}) },
})

const emptySlots = computed(() => Math.max(0, 20 - props.weeklyStatus.length))
</script>
