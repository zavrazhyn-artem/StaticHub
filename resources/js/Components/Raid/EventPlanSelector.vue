<script setup>
import { computed } from 'vue';

const props = defineProps({
    plannerData: { type: Object, default: () => ({}) },
    bossPlannerUrl: { type: String, required: true },
});

const encounters = computed(() => props.plannerData.encounters || []);

const instanceGroups = computed(() => {
    const groups = {};
    encounters.value.forEach(enc => {
        if (!groups[enc.instance]) {
            groups[enc.instance] = [];
        }
        groups[enc.instance].push(enc);
    });
    return groups;
});

const plansCount = computed(() => encounters.value.filter(e => e.has_plan).length);
</script>

<template>
    <div class="space-y-6">
        <!-- Header with link to standalone planner -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-xl text-orange-400">map</span>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest text-white">Boss Plans</h3>
                    <p class="text-[9px] text-on-surface-variant mt-0.5">{{ plansCount }} of {{ encounters.length }} encounters have plans</p>
                </div>
            </div>
            <a
                :href="bossPlannerUrl"
                class="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 text-[10px] font-black uppercase tracking-widest hover:bg-orange-500/20 transition-all"
            >
                <span class="material-symbols-outlined text-sm">open_in_new</span>
                Open Planner
            </a>
        </div>

        <!-- Encounter plan list -->
        <div v-for="(bosses, instanceName) in instanceGroups" :key="instanceName" class="space-y-2">
            <div class="px-1">
                <span class="text-[8px] font-black uppercase tracking-[0.2em] text-on-surface-variant/50">{{ instanceName }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <div
                    v-for="enc in bosses"
                    :key="enc.slug"
                    class="bg-surface-container/60 border rounded-xl p-4 transition-all"
                    :class="enc.has_plan
                        ? 'border-white/10 hover:border-orange-500/30'
                        : 'border-white/5 opacity-50'"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-surface-container-high border border-white/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-lg" :class="enc.has_plan ? 'text-orange-400' : 'text-on-surface-variant/30'">swords</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-white truncate">{{ enc.name }}</div>
                            <div v-if="enc.has_plan" class="flex items-center gap-2 mt-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                <span class="text-[8px] text-on-surface-variant">
                                    {{ enc.plan.steps?.length || 0 }} phase{{ (enc.plan.steps?.length || 0) !== 1 ? 's' : '' }}
                                </span>
                                <span class="text-[8px] text-on-surface-variant/40">&bull;</span>
                                <span class="text-[8px] text-orange-400/60 font-bold uppercase">{{ enc.plan.difficulty }}</span>
                            </div>
                            <div v-else class="mt-1">
                                <span class="text-[8px] text-on-surface-variant/40">No plan</span>
                            </div>
                        </div>
                    </div>

                    <!-- Plan preview: player count per step -->
                    <div v-if="enc.has_plan && enc.plan.steps" class="mt-3 flex items-center gap-1.5 flex-wrap">
                        <div
                            v-for="(step, i) in enc.plan.steps"
                            :key="i"
                            class="flex items-center gap-1 px-2 py-0.5 rounded bg-white/5 border border-white/5"
                        >
                            <span class="text-[7px] font-black text-on-surface-variant/60 uppercase">{{ step.label }}</span>
                            <span class="text-[7px] font-bold text-primary">
                                {{ (step.players || []).length }} <span class="text-on-surface-variant/40">players</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="encounters.length === 0" class="text-center py-12">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant/20">map</span>
            <p class="text-xs text-on-surface-variant/40 mt-2">No encounters configured</p>
        </div>
    </div>
</template>
