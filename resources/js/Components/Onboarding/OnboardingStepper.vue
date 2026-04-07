<template>
    <div class="max-w-3xl mx-auto py-8 relative">
        <!-- Help Button (top-right, large, pulsing) -->
        <button
            @click="showHelp = !showHelp"
            class="absolute -top-2 -right-24 w-14 h-14 bg-primary/10 border border-primary/30 rounded-full flex items-center justify-center text-primary hover:bg-primary/20 transition-all shadow-lg z-40 help-pulse"
        >
            <span class="material-symbols-outlined text-3xl">help</span>
        </button>

        <!-- Step Progress Bar -->
        <div class="mb-12 px-8">
            <div class="flex items-start">
                <template v-for="(step, index) in steps" :key="index">
                    <!-- Step node: circle + label stacked -->
                    <div class="flex flex-col items-center shrink-0">
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300"
                            :class="stepCircleClass(index)"
                        >
                            <span v-if="index < currentStep" class="material-symbols-outlined text-lg">check</span>
                            <span v-else class="material-symbols-outlined text-lg">{{ step.icon }}</span>
                        </div>
                        <span
                            class="mt-2 font-headline text-[9px] font-bold uppercase tracking-[0.15em] transition-colors duration-300 whitespace-nowrap"
                            :class="index <= currentStep ? 'text-primary' : 'text-on-surface-variant/40'"
                        >
                            {{ __(step.label) }}
                        </span>
                    </div>

                    <!-- Connector line between steps -->
                    <div
                        v-if="index < steps.length - 1"
                        class="flex-1 h-0.5 rounded-full transition-all duration-500 ease-out mt-5"
                        :class="index < currentStep ? 'bg-primary' : 'bg-white/10'"
                    ></div>
                </template>
            </div>
        </div>

        <!-- Step Content -->
        <Transition :name="transitionName" mode="out-in">
            <StepChoice
                v-if="currentStep === 0"
                key="choice"
                @select="handleChoice"
            />
            <StepCreate
                v-else-if="currentStep === 1 && choice === 'create'"
                key="create"
                :csrf-token="csrfToken"
                :is-guild-master="isGuildMaster"
                :guild-name="guildName"
                @back="goBack"
                @created="handleStaticCreated"
            />
            <StepJoin
                v-else-if="currentStep === 1 && choice === 'join'"
                key="join"
                :csrf-token="csrfToken"
                :initial-token="pendingJoinToken"
                :initial-static="pendingJoinStatic"
                @back="goBack"
                @joined="handleStaticJoined"
            />
            <StepCharacters
                v-else-if="currentStep === 2"
                key="characters"
                :csrf-token="csrfToken"
                :characters="characters"
                :specializations="specializations"
                :static-id="staticId"
                :static-name="staticName"
                @complete="handleComplete"
            />
        </Transition>

        <!-- Help Modal -->
        <HelpPanel
            :show="showHelp"
            :step="currentStep"
            :choice="choice"
            @close="showHelp = false"
        />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useTranslation } from '../../composables/useTranslation';
import StepChoice from './StepChoice.vue';
import StepCreate from './StepCreate.vue';
import StepJoin from './StepJoin.vue';
import StepCharacters from './StepCharacters.vue';
import HelpPanel from './HelpPanel.vue';

const { __ } = useTranslation();

const props = defineProps({
    pendingJoinToken: { type: String, default: '' },
    pendingJoinStatic: { type: Object, default: null },
    isGuildMaster: { type: Boolean, default: false },
    guildName: { type: String, default: '' },
    csrfToken: { type: String, required: true },
});

const steps = [
    { label: 'Choose', icon: 'swap_horiz' },
    { label: 'Setup', icon: 'tune' },
    { label: 'Characters', icon: 'group' },
    { label: 'Done', icon: 'check_circle' },
];

const currentStep = ref(props.pendingJoinToken ? 1 : 0);
const choice = ref(props.pendingJoinToken ? 'join' : null);
const showHelp = ref(false);
const transitionName = ref('slide-left');

// Data passed between steps
const characters = ref([]);
const specializations = ref([]);
const staticId = ref(null);
const staticName = ref('');

function stepCircleClass(index) {
    if (index < currentStep.value) {
        return 'bg-primary border-primary text-on-primary';
    }
    if (index === currentStep.value) {
        return 'bg-primary/20 border-primary text-primary';
    }
    return 'bg-surface-container-high border-white/10 text-on-surface-variant/40';
}

function handleChoice(selected) {
    choice.value = selected;
    transitionName.value = 'slide-left';
    currentStep.value = 1;
}

function goBack() {
    transitionName.value = 'slide-right';
    currentStep.value = 0;
    choice.value = null;
}

function handleStaticCreated(data) {
    staticId.value = data.static.id;
    staticName.value = data.static.name;
    characters.value = data.characterData.characters;
    specializations.value = data.characterData.specializations;
    transitionName.value = 'slide-left';
    currentStep.value = 2;
}

function handleStaticJoined(data) {
    staticId.value = data.static.id;
    staticName.value = data.static.name;
    characters.value = data.characterData.characters;
    specializations.value = data.characterData.specializations;
    transitionName.value = 'slide-left';
    currentStep.value = 2;
}

function handleComplete() {
    transitionName.value = 'slide-left';
    currentStep.value = 3;
    setTimeout(() => {
        window.location.href = '/dashboard';
    }, 1000);
}
</script>

<style scoped>
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.3s ease;
}
.slide-left-enter-from {
    opacity: 0;
    transform: translateX(30px);
}
.slide-left-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}
.slide-right-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}
.slide-right-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

@keyframes help-pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(79, 211, 247, 0.4);
    }
    50% {
        transform: scale(1.08);
        box-shadow: 0 0 20px 4px rgba(79, 211, 247, 0.2);
    }
}

.help-pulse {
    animation: help-pulse 2.5s ease-in-out infinite;
}
</style>
