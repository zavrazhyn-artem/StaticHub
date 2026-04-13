<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    plannerData: { type: Object, default: () => ({}) },
    bossPlannerUrl: { type: String, required: true },
    selectedEncounter: { type: String, default: null },
    assignedPlans: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    csrfToken: { type: String, default: '' },
    assignUrl: { type: String, default: '' },
});

const emit = defineEmits(['view-plan']);

const encounters = computed(() => props.plannerData.encounters || []);

const currentEncounter = computed(() => {
    if (!props.selectedEncounter) return null;
    return encounters.value.find(e => e.slug === props.selectedEncounter) || null;
});

const plans = computed(() => currentEncounter.value?.plans || []);

const localAssigned = ref({ ...props.assignedPlans });

const assignedPlanId = computed(() => {
    if (!props.selectedEncounter) return null;
    return localAssigned.value[props.selectedEncounter] || null;
});

const assignedPlan = computed(() => {
    if (!assignedPlanId.value) return null;
    return plans.value.find(p => p.id === assignedPlanId.value) || null;
});

const assignPlan = async (planId) => {
    if (!props.assignUrl || !props.selectedEncounter) return;
    localAssigned.value[props.selectedEncounter] = planId;
    try {
        await fetch(props.assignUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ encounter_slug: props.selectedEncounter, plan_id: planId }),
        });
    } catch (e) { console.error('Assign failed:', e); }
};

const unassignPlan = async () => {
    if (!props.assignUrl || !props.selectedEncounter) return;
    delete localAssigned.value[props.selectedEncounter];
    localAssigned.value = { ...localAssigned.value };
    try {
        await fetch(props.assignUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ encounter_slug: props.selectedEncounter, plan_id: null }),
        });
    } catch (e) { console.error('Unassign failed:', e); }
};
</script>

<template>
    <div>
        <!-- No boss selected -->
        <div v-if="!selectedEncounter" class="flex flex-col items-center justify-center py-20 text-center">
            <span class="material-symbols-outlined text-5xl text-on-surface-variant/15">touch_app</span>
            <p class="text-sm text-on-surface-variant/40 mt-4">{{ __('Select a boss from the sidebar to view plans') }}</p>
        </div>

        <!-- Boss selected -->
        <div v-else class="space-y-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img v-if="currentEncounter?.portrait" :src="currentEncounter.portrait" class="w-9 h-9 rounded-lg border border-white/10 object-cover">
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-tight">{{ currentEncounter?.name }}</h3>
                        <span class="text-[9px] text-on-surface-variant/50">{{ plans.length }} {{ __('plans available') }}</span>
                    </div>
                </div>
                <a :href="bossPlannerUrl"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 border border-orange-500/20 text-orange-400 text-[9px] font-bold uppercase tracking-widest hover:bg-orange-500/20 transition-all">
                    <span class="material-symbols-outlined text-xs">open_in_new</span>
                    {{ __('Open Planner') }}
                </a>
            </div>

            <!-- Currently assigned plan -->
            <div v-if="assignedPlan" class="bg-green-500/5 border border-green-500/20 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-sm text-green-400">check_circle</span>
                        </div>
                        <div>
                            <div class="text-[9px] font-black uppercase tracking-widest text-green-400/60">{{ __('Assigned Plan') }}</div>
                            <div class="text-xs font-bold text-white">{{ assignedPlan.title || currentEncounter?.name }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded"
                                    :class="{
                                        'bg-orange-500/10 text-orange-400': assignedPlan.difficulty === 'mythic',
                                        'bg-purple-500/10 text-purple-400': assignedPlan.difficulty === 'heroic',
                                        'bg-green-500/10 text-green-400': assignedPlan.difficulty === 'normal',
                                        'bg-blue-500/10 text-blue-400': assignedPlan.difficulty === 'raid_finder',
                                    }">{{ assignedPlan.difficulty === 'raid_finder' ? 'LFR' : assignedPlan.difficulty }}</span>
                                <span class="text-[8px] text-on-surface-variant/40">{{ assignedPlan.steps?.length || 0 }} {{ __('phases') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="emit('view-plan', assignedPlan)"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-orange-500/10 border border-orange-500/20 text-orange-400 text-[9px] font-bold hover:bg-orange-500/20 transition-all">
                            <span class="material-symbols-outlined text-xs">visibility</span>
                            {{ __('View') }}
                        </button>
                        <button v-if="canManage" @click="unassignPlan"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 text-[9px] font-bold hover:bg-red-500/20 transition-all">
                            <span class="material-symbols-outlined text-xs">close</span>
                            {{ __('Remove') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Plans list (select to assign) -->
            <div v-if="plans.length > 0" class="space-y-1.5">
                <div class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/40 px-1">
                    {{ assignedPlan ? __('Other Plans') : __('Select a plan to assign') }}
                </div>
                <template v-for="plan in plans" :key="plan.id">
                    <div v-if="plan.id !== assignedPlanId"
                        class="flex items-center gap-3 px-4 py-3 bg-surface-container/60 border border-white/5 rounded-xl transition-all"
                        :class="canManage ? 'hover:border-orange-500/30 cursor-pointer' : ''"
                    >
                        <div class="w-8 h-8 rounded-lg bg-orange-500/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-sm text-orange-400">map</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-bold text-white truncate">{{ plan.title || currentEncounter?.name }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[8px] font-bold uppercase px-1.5 py-0.5 rounded"
                                    :class="{
                                        'bg-orange-500/10 text-orange-400': plan.difficulty === 'mythic',
                                        'bg-purple-500/10 text-purple-400': plan.difficulty === 'heroic',
                                        'bg-green-500/10 text-green-400': plan.difficulty === 'normal',
                                        'bg-blue-500/10 text-blue-400': plan.difficulty === 'raid_finder',
                                    }">{{ plan.difficulty === 'raid_finder' ? 'LFR' : plan.difficulty }}</span>
                                <span class="text-[8px] text-on-surface-variant/40">{{ plan.steps?.length || 0 }} {{ __('phases') }}</span>
                            </div>
                        </div>
                        <button v-if="canManage" @click="assignPlan(plan.id)"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-fuchsia-400/10 border border-fuchsia-400/20 text-fuchsia-400 text-[9px] font-bold hover:bg-fuchsia-400/20 transition-all shrink-0">
                            <span class="material-symbols-outlined text-xs">add_circle</span>
                            {{ __('Assign') }}
                        </button>
                    </div>
                </template>
            </div>

            <!-- No plans -->
            <div v-if="plans.length === 0" class="flex flex-col items-center py-12 text-center">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant/15">map</span>
                <p class="text-xs text-on-surface-variant/30 mt-3">{{ __('No plans created for this boss') }}</p>
            </div>
        </div>
    </div>
</template>
