<script setup>
import { computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import HeroSection from './HeroSection.vue';
import FeatureBlock from './FeatureBlock.vue';
import CtaSection from './CtaSection.vue';

const { __ } = useTranslation();

const props = defineProps({
    isAuthenticated: { type: Boolean, default: false },
    dashboardUrl: { type: String, default: '/dashboard' },
    loginUrl: { type: String, default: '/auth/battlenet/redirect' },
});

const features = computed(() => [
    {
        id: 'roster',
        icon: 'groups',
        iconColor: 'text-primary',
        badge: __('landing.roster_badge'),
        titleHtml: `${__('landing.roster_title_1')} <span class="text-primary">${__('landing.roster_title_2')}</span>`,
        description: __('landing.roster_desc'),
        features: [
            __('landing.roster_f1'),
            __('landing.roster_f2'),
            __('landing.roster_f3'),
            __('landing.roster_f4'),
        ],
        glowColor: 'rgba(79,211,247,0.08)',
    },
    {
        id: 'calendar',
        icon: 'calendar_month',
        iconColor: 'text-secondary',
        badge: __('landing.calendar_badge'),
        titleHtml: `${__('landing.calendar_title_1')} <span class="text-secondary">${__('landing.calendar_title_2')}</span>`,
        description: __('landing.calendar_desc'),
        features: [
            __('landing.calendar_f1'),
            __('landing.calendar_f2'),
            __('landing.calendar_f3'),
            __('landing.calendar_f4'),
        ],
        glowColor: 'rgba(250,121,2,0.08)',
    },
    {
        id: 'ai',
        icon: 'psychology',
        iconColor: 'text-success-neon',
        badge: __('landing.ai_badge'),
        titleHtml: `${__('landing.ai_title_1')} <span class="text-success-neon">${__('landing.ai_title_2')}</span>`,
        description: __('landing.ai_desc'),
        features: [
            __('landing.ai_f1'),
            __('landing.ai_f2'),
            __('landing.ai_f3'),
            __('landing.ai_f4'),
        ],
        glowColor: 'rgba(57,255,20,0.06)',
    },
    {
        id: 'planner',
        icon: 'map',
        iconColor: 'text-tertiary',
        badge: __('landing.planner_badge'),
        titleHtml: `${__('landing.planner_title_1')} <span class="text-tertiary">${__('landing.planner_title_2')}</span>`,
        description: __('landing.planner_desc'),
        features: [
            __('landing.planner_f1'),
            __('landing.planner_f2'),
            __('landing.planner_f3'),
            __('landing.planner_f4'),
        ],
        glowColor: 'rgba(255,250,223,0.06)',
    },
    {
        id: 'treasury',
        icon: 'account_balance',
        iconColor: 'text-secondary',
        badge: __('landing.treasury_badge'),
        titleHtml: `${__('landing.treasury_title_1')} <span class="text-secondary">${__('landing.treasury_title_2')}</span>`,
        description: __('landing.treasury_desc'),
        features: [
            __('landing.treasury_f1'),
            __('landing.treasury_f2'),
            __('landing.treasury_f3'),
            __('landing.treasury_f4'),
        ],
        glowColor: 'rgba(250,121,2,0.06)',
    },
]);
</script>

<template>
    <div class="min-h-screen bg-background text-on-background arcane-bg">
        <!-- Hero -->
        <HeroSection
            :is-authenticated="isAuthenticated"
            :dashboard-url="dashboardUrl"
            :login-url="loginUrl"
        />

        <!-- Features -->
        <div id="features" class="relative">
            <!-- Divider line -->
            <div class="max-w-6xl mx-auto px-6">
                <div class="h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
            </div>

            <FeatureBlock
                v-for="(feature, index) in features"
                :key="feature.id"
                :icon="feature.icon"
                :icon-color="feature.iconColor"
                :badge="feature.badge"
                :title="feature.titleHtml"
                :description="feature.description"
                :features="feature.features"
                :reversed="index % 2 !== 0"
                :glow-color="feature.glowColor"
            />
        </div>

        <!-- Divider -->
        <div class="max-w-6xl mx-auto px-6">
            <div class="h-px bg-gradient-to-r from-transparent via-white/10 to-transparent"></div>
        </div>

        <!-- Final CTA -->
        <CtaSection
            :is-authenticated="isAuthenticated"
            :dashboard-url="dashboardUrl"
            :login-url="loginUrl"
        />

        <!-- Footer -->
        <footer class="py-8 text-center">
            <p class="text-[9px] font-bold text-gray-600 uppercase tracking-[0.5em]">&copy; {{ new Date().getFullYear() }} {{ __('landing.footer') }}</p>
        </footer>
    </div>
</template>
