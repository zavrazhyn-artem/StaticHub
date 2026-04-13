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
    <section class="relative py-24 md:py-32 px-6 overflow-hidden">
        <!-- Background glow -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary/6 rounded-full blur-[200px]"></div>
        </div>

        <div class="relative z-10 max-w-3xl mx-auto text-center space-y-8">
            <h2 class="font-headline text-3xl md:text-5xl font-bold text-white leading-tight">
                {{ __('landing.cta_final_title_1') }} <span class="text-primary">{{ __('landing.cta_final_title_2') }}</span> {{ __('landing.cta_final_title_3') }}
            </h2>
            <p class="text-on-surface-variant text-base md:text-lg leading-relaxed max-w-xl mx-auto">
                {{ __('landing.cta_final_desc') }}
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
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
            </div>
        </div>
    </section>
</template>
