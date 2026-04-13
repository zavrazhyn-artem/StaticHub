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
});

const currentStepIndex = ref(0);
const currentStep = computed(() => props.plan.steps?.[currentStepIndex.value] || props.plan.steps?.[0] || null);
const currentGroups = computed(() => currentStep.value?.groups || {});
</script>

<template>
    <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <img v-if="portrait" :src="portrait" class="w-14 h-14 rounded-xl border border-white/10 object-cover">
            <div>
                <h1 class="text-2xl font-black text-white uppercase tracking-tight font-headline">{{ bossName }}</h1>
                <p class="text-xs text-on-surface-variant">{{ staticName }} &mdash; {{ plan.difficulty }}</p>
            </div>
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
            />
        </div>

        <!-- Footer -->
        <div class="text-center text-[10px] text-on-surface-variant/40">
            {{ __('Shared raid plan') }} &mdash; {{ __('read-only view') }}
        </div>
    </div>
</template>
