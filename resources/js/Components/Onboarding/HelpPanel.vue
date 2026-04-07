<template>
    <GlassModal :show="show" max-width="max-w-lg" @close="$emit('close')">
        <!-- Header -->
        <div class="px-8 pt-8 pb-4 border-b border-white/5">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl text-primary">help</span>
                </div>
                <div>
                    <h3 class="font-headline text-lg font-bold text-white uppercase tracking-wider">
                        {{ __(currentHelp.title) }}
                    </h3>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary">
                        {{ __('Onboarding Help') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="px-8 py-6 space-y-6">
            <p class="text-on-surface-variant text-sm leading-relaxed">
                {{ __(currentHelp.description) }}
            </p>

            <div v-if="currentHelp.tips.length" class="space-y-3">
                <h4 class="font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/60">
                    {{ __('Tips') }}
                </h4>
                <div
                    v-for="(tip, i) in currentHelp.tips"
                    :key="i"
                    class="flex items-start gap-3 bg-white/[0.02] border border-white/5 rounded-lg px-4 py-3"
                >
                    <span class="material-symbols-outlined text-lg text-primary shrink-0 mt-0.5">{{ tip.icon }}</span>
                    <span class="text-on-surface-variant text-sm leading-relaxed">{{ __(tip.text) }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-8 pb-6">
            <button
                @click="$emit('close')"
                class="w-full bg-white/5 border border-white/10 text-on-surface-variant font-headline font-bold text-[10px] uppercase tracking-[0.2em] py-3 rounded-lg hover:bg-white/10 hover:text-white transition-all"
            >
                {{ __('Got it') }}
            </button>
        </div>
    </GlassModal>
</template>

<script setup>
import { computed } from 'vue';
import { useTranslation } from '../../composables/useTranslation';
import GlassModal from '../UI/GlassModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    show: { type: Boolean, default: false },
    step: { type: Number, default: 0 },
    choice: { type: String, default: null },
});

defineEmits(['close']);

const helpContent = {
    choice: {
        title: 'Getting Started',
        description: 'BlastR is a tool for coordinating your WoW raid team. You can create your own team or join an existing one using an invite link from your Raid Leader.',
        tips: [
            { icon: 'add_circle', text: 'Choose "Create New Team" if you are the Raid Leader or want to start a new group.' },
            { icon: 'group_add', text: 'Choose "Join Existing Team" if someone sent you an invite link.' },
        ],
    },
    create: {
        title: 'Create Your Team',
        description: 'Enter your raid group name and select a region (EU or US). After creating the team, you can invite other players.',
        tips: [
            { icon: 'edit', text: 'The team name can be your guild name or any other name you prefer.' },
            { icon: 'public', text: 'Select the region matching your WoW account (EU or US).' },
        ],
    },
    join: {
        title: 'Join a Team',
        description: 'Paste the invite link your Raid Leader sent you. It looks like: blastr.gg/join/TOKEN. You can paste the full link or just the token part.',
        tips: [
            { icon: 'content_paste', text: 'Paste the full invite link — we\'ll extract the token automatically.' },
            { icon: 'verified', text: 'After pasting, you\'ll see the team name and details before joining.' },
        ],
    },
    characters: {
        title: 'Select Your Characters',
        description: 'Your characters are synced from Battle.net. Choose your main character (required) and optionally select alts for raiding.',
        tips: [
            { icon: 'star', text: 'Your main character is the one you primarily raid with.' },
            { icon: 'group', text: 'Alts are optional — you can add or change them later.' },
            { icon: 'sync', text: 'If characters are missing, click "Re-sync from Battle.net".' },
        ],
    },
};

const currentHelp = computed(() => {
    if (props.step === 0) return helpContent.choice;
    if (props.step === 1 && props.choice === 'create') return helpContent.create;
    if (props.step === 1 && props.choice === 'join') return helpContent.join;
    if (props.step === 2) return helpContent.characters;
    return helpContent.choice;
});
</script>
