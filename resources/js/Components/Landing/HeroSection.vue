<script setup>
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

defineProps({
    isAuthenticated: { type: Boolean, default: false },
    dashboardUrl: { type: String, default: '/dashboard' },
    loginUrl: { type: String, default: '/auth/battlenet/redirect' },
});
</script>

<template>
    <section class="relative min-h-screen flex flex-col items-center justify-center px-6 overflow-hidden">
        <!-- Background effects -->
        <div class="absolute inset-0 pointer-events-none z-0">
            <div class="absolute top-[-20%] left-[-10%] w-[50%] h-[50%] bg-primary/8 rounded-full blur-[160px]"></div>
            <div class="absolute bottom-[-20%] right-[-10%] w-[50%] h-[50%] bg-cyan-500/6 rounded-full blur-[160px]"></div>
            <div class="absolute top-[30%] right-[10%] w-[20%] h-[20%] bg-secondary/5 rounded-full blur-[100px]"></div>
        </div>

        <div class="relative z-10 text-center max-w-5xl w-full space-y-8">
            <!-- Status badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-white/5 border border-white/10 backdrop-blur-md">
                <span class="w-2 h-2 bg-success-neon rounded-full animate-pulse"></span>
                <span class="text-3xs font-semibold uppercase tracking-[0.3em] text-on-surface-variant">{{ __('landing.status') }}</span>
            </div>

            <!-- Logo -->
            <div class="flex items-center justify-center gap-6 drop-shadow-[0_0_40px_rgba(58,223,250,0.3)] transition-transform hover:scale-105 duration-500">
                <img src="/images/logo.svg" alt="BlastR Logo" class="w-28 h-28 md:w-36 md:h-36 object-contain" />
                <div class="text-6xl md:text-8xl font-black uppercase tracking-tighter italic leading-none pr-4">
                    <span class="text-white">Blast</span><span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-400 pr-4">R</span>
                </div>
            </div>

            <!-- Slogan -->
            <p class="font-slogan text-base md:text-lg font-bold tracking-[0.4em] uppercase text-on-surface drop-shadow-[0_0_8px_rgba(58,223,250,0.4)]">
                {{ __('landing.slogan') }}
            </p>

            <!-- Headline -->
            <div class="max-w-3xl mx-auto space-y-4 pt-4">
                <h1 class="font-headline text-3xl md:text-5xl font-bold text-white leading-tight">
                    {{ __('landing.headline_1') }} <span class="text-primary">{{ __('landing.headline_tabs') }}</span>.<br>
                    {{ __('landing.headline_2') }} <span class="text-primary">{{ __('landing.headline_window') }}</span>.
                </h1>
                <p class="text-on-surface-variant text-base md:text-lg leading-relaxed max-w-2xl mx-auto">
                    {{ __('landing.subheadline') }}
                </p>
            </div>

            <!-- CTA -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-6">
                <a
                    v-if="isAuthenticated"
                    :href="dashboardUrl"
                    class="group relative px-10 py-4 bg-primary text-on-primary font-headline font-bold text-sm uppercase tracking-[0.15em] rounded-sm transition-all hover:scale-105 active:scale-95 shadow-[0_0_40px_rgba(79,211,247,0.2)]"
                >
                    <span class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-xl">dashboard</span>
                        {{ __('landing.cta_dashboard') }}
                        <span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
                    </span>
                </a>
                <a
                    v-else
                    :href="loginUrl"
                    class="group relative px-10 py-4 bg-[#00aeff] text-white font-headline font-bold text-sm uppercase tracking-[0.15em] rounded-sm transition-all hover:scale-105 active:scale-95 shadow-[0_0_40px_rgba(0,174,255,0.3)]"
                >
                    <span class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-xl">login</span>
                        {{ __('landing.cta_login') }}
                    </span>
                </a>
                <a
                    href="#features"
                    class="group flex items-center gap-2 px-6 py-4 text-on-surface-variant hover:text-white font-headline text-sm uppercase tracking-[0.15em] transition-colors"
                >
                    {{ __('landing.cta_learn_more') }}
                    <span class="material-symbols-outlined text-lg transition-transform group-hover:translate-y-0.5">expand_more</span>
                </a>
            </div>
        </div>

        <!-- Scroll indicator -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-on-surface-variant/40 animate-bounce">
            <span class="material-symbols-outlined text-2xl">keyboard_arrow_down</span>
        </div>
    </section>
</template>
