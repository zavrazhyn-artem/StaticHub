<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();
import RaidMapCanvas from './RaidMapCanvas.vue';
import StepNavigator from './StepNavigator.vue';

const props = defineProps({
    plan: { type: Object, required: true },
    bossName: { type: String, default: '' },
    maps: { type: Array, default: () => [] },
    portrait: { type: String, default: '' },
    staticName: { type: String, default: '' },
    myCharacterIds: { type: Array, default: () => [] },
});

const currentStepIndex = ref(0);
const currentStep = computed(() => props.plan.steps?.[currentStepIndex.value] || props.plan.steps?.[0] || null);
const currentGroups = computed(() => currentStep.value?.groups || {});

// Show Me
const showingMe = ref(false);

const highlightedMarkerIds = computed(() => {
    if (!showingMe.value || !props.myCharacterIds.length) return new Set();
    const myIds = new Set(props.myCharacterIds);
    const markers = currentStep.value?.markers || [];
    const groups = currentStep.value?.groups || {};
    const ids = new Set();

    markers.forEach((m, i) => {
        if (m.playerData && myIds.has(m.playerData.id)) ids.add(i);
    });

    const myGroupIds = new Set();
    for (const [gId, g] of Object.entries(groups)) {
        if ((g.members || []).some(id => myIds.has(id))) myGroupIds.add(Number(gId));
    }
    if (myGroupIds.size > 0) {
        markers.forEach((m, i) => {
            if (m.type === 'group-token' && myGroupIds.has(m.groupId)) ids.add(i);
        });
    }

    return ids;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img v-if="portrait" :src="portrait" class="w-10 h-10 rounded-lg border border-white/10 object-cover">
                <div>
                    <h2 class="text-lg font-black text-white uppercase tracking-tight font-headline">{{ bossName }}</h2>
                    <p class="text-[10px] text-on-surface-variant">{{ plan.title || plan.difficulty }}</p>
                </div>
            </div>
            <button
                v-if="myCharacterIds.length"
                @click="showingMe = !showingMe"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border transition-all"
                :class="showingMe
                    ? 'bg-cyan-400/10 border-cyan-400/30 text-cyan-400'
                    : 'bg-white/5 border-white/10 text-on-surface-variant hover:text-white'"
            >
                <span class="material-symbols-outlined text-sm">person_pin</span>
                {{ __('Show Me') }}
            </button>
        </div>

        <!-- Steps -->
        <StepNavigator
            :steps="plan.steps || []"
            :current-index="currentStepIndex"
            :can-manage="false"
            @select="currentStepIndex = $event"
        />

        <!-- Canvas (read-only) -->
        <div class="border border-white/10 rounded-xl overflow-hidden" style="aspect-ratio: 16/9;">
            <RaidMapCanvas
                :step="currentStep"
                active-tool="select"
                :can-manage="false"
                :groups="currentGroups"
                :maps="maps"
                :has-pending-placement="false"
                :highlighted-markers="highlightedMarkerIds"
            />
        </div>
    </div>
</template>
