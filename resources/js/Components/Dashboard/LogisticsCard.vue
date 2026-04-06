<template>
    <div class="bg-surface-container-high rounded-2xl border border-white/5 p-6">
        <h2 class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest mb-6">{{ __('Logistics Snapshot') }}</h2>
        <div class="space-y-4">
            <a :href="routes.treasury" class="flex items-center justify-between p-3 bg-black/20 rounded-xl border border-white/5 hover:bg-white/5 transition-colors group">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-tertiary-dim/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-tertiary-dim">savings</span>
                    </div>
                    <div>
                        <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest">{{ __('Guild Bank') }}</div>
                        <div class="text-sm font-black text-[#FFD700]">{{ reserves }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[8px] text-on-surface-variant font-bold uppercase tracking-[0.2em]">{{ __('Autonomy') }}</div>
                    <div class="text-xs font-black" :class="autonomy > 2 ? 'text-success-neon' : 'text-error'">
                        {{ autonomy }} <span class="text-[8px] font-normal opacity-60">{{ __('WEEKS') }}</span>
                    </div>
                    <div v-if="weeklyCost == 0" class="text-[8px] text-error font-bold uppercase tracking-tighter">{{ __('PLANNING NEEDED') }}</div>
                </div>
            </a>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div v-for="recipe in recipes" :key="recipe.name" class="p-3 bg-black/20 rounded-xl border border-white/5 flex items-center gap-3">
                    <img :src="recipe.icon" class="w-8 h-8 rounded border border-white/10 shrink-0" :alt="recipe.name">
                    <div class="min-w-0">
                        <div class="text-[9px] text-on-surface-variant font-bold uppercase tracking-widest truncate" :title="__(recipe.name)">{{ __(recipe.name) }}</div>
                        <div class="text-lg font-black text-white mt-0.5 whitespace-nowrap">
                            {{ recipe.quantity * raidDays }}
                            <span class="text-[10px] font-normal text-on-surface-variant uppercase ml-1">{{ __('Needed / Week') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    reserves:  { type: String, default: '0' },
    autonomy:  { type: Number, default: 0 },
    weeklyCost:{ type: Number, default: 0 },
    recipes:   { type: Array,  default: () => [] },
    raidDays:  { type: Number, default: 3 },
    routes:    { type: Object, default: () => ({}) },
})
</script>
