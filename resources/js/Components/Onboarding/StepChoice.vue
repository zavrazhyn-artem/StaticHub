<template>
    <div>
        <div class="text-center mb-10">
            <span class="text-primary text-3xs font-semibold uppercase tracking-[0.3em] mb-2 block">
                {{ __('— Welcome to BlastR') }}
            </span>
            <h2 class="font-headline text-3xl font-black text-white uppercase tracking-tight italic">
                {{ __('How would you like to start?') }}
            </h2>
            <p class="text-on-surface-variant text-sm mt-3 max-w-lg mx-auto">
                {{ __('Create your own raid team or join an existing one with an invite link from your Raid Leader.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Create Team Card -->
            <button
                @click="$emit('select', 'create')"
                class="group bg-surface-container-high p-8 rounded-xl border border-white/5 text-left relative overflow-hidden hover:border-primary/30 transition-all duration-300 active:scale-[0.98]"
            >
                <div class="absolute top-0 right-0 w-40 h-40 bg-primary/5 blur-3xl -mr-20 -mt-20 group-hover:bg-primary/10 transition-colors duration-300"></div>

                <div class="relative">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-6 group-hover:bg-primary/20 transition-colors">
                        <span class="material-symbols-outlined text-3xl text-primary">add_circle</span>
                    </div>

                    <h3 class="font-headline text-white text-lg font-bold uppercase tracking-widest mb-3">
                        {{ __('Create New Team') }}
                    </h3>

                    <p class="text-on-surface-variant text-sm leading-relaxed">
                        {{ __('Start your own raid group. You\'ll be the team leader and can invite others.') }}
                    </p>

                    <div class="mt-6 flex items-center gap-2 text-primary text-3xs font-semibold uppercase tracking-[0.2em] opacity-0 group-hover:opacity-100 transition-opacity">
                        <span>{{ __('Get Started') }}</span>
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </div>
                </div>
            </button>

            <!-- Join Team Card -->
            <button
                @click="$emit('select', 'join')"
                class="group bg-surface-container-high p-8 rounded-xl border border-white/5 text-left relative overflow-hidden hover:border-primary/30 transition-all duration-300 active:scale-[0.98]"
            >
                <div class="absolute top-0 right-0 w-40 h-40 bg-cyan-400/5 blur-3xl -mr-20 -mt-20 group-hover:bg-cyan-400/10 transition-colors duration-300"></div>

                <div class="relative">
                    <div class="w-16 h-16 bg-cyan-400/10 rounded-full flex items-center justify-center mb-6 group-hover:bg-cyan-400/20 transition-colors">
                        <span class="material-symbols-outlined text-3xl text-cyan-400">group_add</span>
                    </div>

                    <h3 class="font-headline text-white text-lg font-bold uppercase tracking-widest mb-3">
                        {{ __('Join Existing Team') }}
                    </h3>

                    <p class="text-on-surface-variant text-sm leading-relaxed">
                        {{ __('Your Raid Leader sent you an invite link? Paste it here to join the team.') }}
                    </p>

                    <div class="mt-6 flex items-center gap-2 text-cyan-400 text-3xs font-semibold uppercase tracking-[0.2em] opacity-0 group-hover:opacity-100 transition-opacity">
                        <span>{{ __('Join Now') }}</span>
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
import { useTranslation } from '../../composables/useTranslation';

const { __ } = useTranslation();

defineEmits(['select']);
</script>
