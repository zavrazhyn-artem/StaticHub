<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import AiChatSidebar from './AiChatSidebar.vue';
const { __ } = useTranslation();

const props = defineProps({
    report:              { type: Object, required: true },
    personalReport:      { type: Object, default: null },
    isRaidLeader:        { type: Boolean, default: false },
    canViewGlobalReport: { type: Boolean, default: false },
    canUseAiChat:        { type: Boolean, default: false },
    chatHistory:         { type: Array, default: () => [] },
    staticName:          { type: String, required: true },
    logsIndexUrl:        { type: String, required: true },
    analyzeApiUrl:       { type: String, required: true },
});

const activeTab = ref('global');
const chatOpen = ref(false);
</script>

<template>
    <div class="relative">
        <AiChatSidebar
            v-if="canUseAiChat"
            :open="chatOpen"
            :report-id="report.id"
            :report-title="report.title"
            :wcl-report-id="report.wcl_report_id"
            :can-view-global-report="canViewGlobalReport"
            :chat-history="chatHistory"
            :analyze-api-url="analyzeApiUrl"
            @close="chatOpen = false"
        />

        <!-- Floating Chat Toggle -->
        <button v-if="canUseAiChat && !chatOpen" @click="chatOpen = true"
            class="fixed bottom-8 right-8 z-40 bg-primary hover:bg-primary-dim text-on-primary-fixed p-4 rounded-full shadow-[0_0_20px_rgba(79,211,247,0.4)] transition-all hover:scale-110 flex items-center gap-3 group">
            <span class="text-[10px] font-black uppercase tracking-widest overflow-hidden max-w-0 group-hover:max-w-xs transition-all duration-500 whitespace-nowrap">{{ __('Tactical Analysis') }}</span>
            <span class="material-symbols-outlined">psychology</span>
        </button>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Breadcrumbs & Back -->
            <div class="mb-8 flex items-center justify-between">
                <a :href="logsIndexUrl" class="flex items-center gap-2 text-on-surface-variant hover:text-amber-500 transition-colors text-[10px] font-black uppercase tracking-widest group">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    {{ __('Return to Archives') }}
                </a>

                <a :href="report.wcl_url" target="_blank"
                   class="flex items-center gap-2 bg-[#ff7d0a]/10 hover:bg-[#ff7d0a] hover:text-black text-[#ff7d0a] px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all border border-[#ff7d0a]/20">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    {{ __('Raw WCL Report') }}
                </a>
            </div>

            <!-- Mission Header -->
            <div class="mb-12">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded text-[10px] font-black text-amber-500 uppercase tracking-widest">
                        {{ __('Report ID:') }} {{ report.wcl_report_id }}
                    </span>
                    <span class="text-on-surface-variant opacity-20">•</span>
                    <span class="text-on-surface-variant font-headline text-[10px] font-black uppercase tracking-widest">
                        {{ __('Executed:') }} {{ report.created_at }}
                    </span>
                </div>
                <h1 class="text-6xl font-black text-white uppercase tracking-tighter font-headline leading-none mb-4">
                    {{ report.title }}
                </h1>
                <div class="flex items-center gap-6">
                    <div v-if="report.duration_hours" class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500 text-sm">schedule</span>
                        <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">{{ report.duration_hours }} {{ __('Hours Duration') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-500 text-sm">terminal</span>
                        <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">{{ __('Tactical Intelligence') }}</span>
                    </div>
                </div>
            </div>

            <!-- Member view: Personal Report only (no tabs, no global data) -->
            <template v-if="!canViewGlobalReport">
                <div v-if="personalReport" class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                    <div class="bg-amber-500/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div :class="`w-10 h-10 rounded-xl bg-${personalReport.char_class_css}/20 flex items-center justify-center border border-${personalReport.char_class_css}/30`">
                                <span :class="`material-symbols-outlined text-${personalReport.char_class_css}`">person</span>
                            </div>
                            <div>
                                <h2 class="text-white font-headline text-xs font-black uppercase tracking-[0.2em] leading-none mb-1">
                                    {{ personalReport.char_name }}
                                </h2>
                                <p :class="`text-[9px] font-black text-${personalReport.char_class_css} uppercase tracking-widest`">
                                    {{ personalReport.char_class }}
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded text-[9px] font-black text-amber-500 uppercase tracking-widest">
                            {{ __('Your personal Report') }}
                        </span>
                    </div>
                    <div class="p-8">
                        <div class="prose prose-invert prose-tactical max-w-none text-gray-300" v-html="personalReport.html"></div>
                    </div>
                </div>
                <div v-else class="bg-surface-container-low border border-white/5 rounded-3xl p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-20 mb-6">person_off</span>
                    <h3 class="text-2xl font-black text-white uppercase tracking-tighter mb-3">{{ __('You did not participate in this raid') }}</h3>
                    <p class="text-on-surface-variant text-sm font-bold uppercase tracking-widest opacity-60 max-w-md">{{ __('A report for your character was not found in this log.') }}</p>
                </div>
            </template>

            <!-- Leader/Officer view: full tabs with Global Report + Personal Report -->
            <div v-else-if="report.has_ai_analysis" class="space-y-8">
                <div class="flex gap-4 border-b border-white/5 pb-px">
                    <button @click="activeTab = 'global'"
                            :class="activeTab === 'global' ? 'text-amber-500 border-amber-500' : 'text-on-surface-variant border-transparent hover:text-white'"
                            class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] border-b-2 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">public</span>
                        {{ __('Global Report') }}
                    </button>
                    <button @click="activeTab = 'roster'"
                            :class="activeTab === 'roster' ? 'text-amber-500 border-amber-500' : 'text-on-surface-variant border-transparent hover:text-white'"
                            class="px-6 py-4 text-[10px] font-black uppercase tracking-[0.2em] border-b-2 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">person</span>
                        {{ __('Personal Report') }}
                    </button>
                </div>

                <!-- Global Tab -->
                <div v-show="activeTab === 'global'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- AI Summary -->
                    <div class="lg:col-span-2 space-y-8">
                        <section class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                            <div class="bg-amber-500/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                                <h2 class="text-amber-500 font-headline text-xs font-black uppercase tracking-[0.2em] flex items-center gap-3">
                                    <span class="material-symbols-outlined text-lg">psychology</span>
                                    {{ __('AI Tactical Review') }}
                                </h2>
                                <div class="flex gap-1">
                                    <div class="w-2 h-2 rounded-full bg-amber-500/20"></div>
                                    <div class="w-2 h-2 rounded-full bg-amber-500/40"></div>
                                    <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                                </div>
                            </div>
                            <div class="p-8">
                                <div class="prose prose-invert prose-tactical max-w-none text-gray-300" v-html="report.ai_html"></div>
                            </div>
                        </section>
                    </div>

                    <!-- Sidebar: Execution Metrics + Mission Summary -->
                    <div class="space-y-8">
                        <div class="bg-surface-container-low border border-white/5 rounded-3xl p-8 space-y-6">
                            <h3 class="text-white font-headline text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Execution Metrics') }}</h3>
                            <div class="space-y-6">
                                <div v-for="metric in report.execution_metrics" :key="metric.label" class="space-y-2">
                                    <div class="flex justify-between items-end">
                                        <span class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest">{{ __(metric.label) }}</span>
                                        <span class="text-lg font-black" :class="metric.color">{{ metric.value }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full" :class="metric.bar_class"
                                             :style="{ width: metric.value + '%' }"></div>
                                    </div>
                                    <p v-if="metric.note" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider opacity-60">{{ __(metric.note) }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-surface-container-low border border-white/5 rounded-3xl p-8 space-y-6">
                            <h3 class="text-white font-headline text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Mission Summary') }}</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between p-3 bg-black/20 rounded-xl border border-white/5">
                                    <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">{{ __('Status') }}</span>
                                    <span class="text-[10px] font-black text-success-neon uppercase tracking-wider">{{ __('COMPLETED') }}</span>
                                </div>
                                <div class="flex justify-between p-3 bg-black/20 rounded-xl border border-white/5">
                                    <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">{{ __('Analyzed By') }}</span>
                                    <span class="text-[10px] font-black text-white uppercase tracking-wider">Gemini 2.5 Flash</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roster Tab -->
                <div v-show="activeTab === 'roster'" class="space-y-6">
                    <!-- Personal report found -->
                    <div v-if="personalReport" class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                        <div class="bg-amber-500/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div :class="`w-10 h-10 rounded-xl bg-${personalReport.char_class_css}/20 flex items-center justify-center border border-${personalReport.char_class_css}/30`">
                                    <span :class="`material-symbols-outlined text-${personalReport.char_class_css}`">person</span>
                                </div>
                                <div>
                                    <h2 class="text-white font-headline text-xs font-black uppercase tracking-[0.2em] leading-none mb-1">
                                        {{ personalReport.char_name }}
                                    </h2>
                                    <p :class="`text-[9px] font-black text-${personalReport.char_class_css} uppercase tracking-widest`">
                                        {{ personalReport.char_class }}
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-amber-500/10 border border-amber-500/20 rounded text-[9px] font-black text-amber-500 uppercase tracking-widest">
                                {{ __('Your personal Report') }}
                            </span>
                        </div>
                        <div class="p-8">
                            <div class="prose prose-invert prose-tactical max-w-none text-gray-300" v-html="personalReport.html"></div>
                        </div>
                    </div>

                    <!-- Not participated -->
                    <div v-else class="bg-surface-container-low border border-white/5 rounded-3xl p-12 text-center flex flex-col items-center justify-center">
                        <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-20 mb-6">person_off</span>
                        <h3 class="text-2xl font-black text-white uppercase tracking-tighter mb-3">{{ __('You did not participate in this raid') }}</h3>
                        <p class="text-on-surface-variant text-sm font-bold uppercase tracking-widest opacity-60 max-w-md">{{ __('A report for your character was not found in this log.') }}</p>
                    </div>
                </div>
            </div>

            <!-- No AI analysis yet -->
            <div v-else class="py-24 text-center border-2 border-dashed border-white/5 rounded-3xl">
                <div class="relative inline-block mb-6">
                    <span class="material-symbols-outlined text-8xl text-amber-500/10">psychology</span>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="material-symbols-outlined text-4xl text-amber-500 animate-pulse">hourglass_empty</span>
                    </div>
                </div>
                <h3 class="text-2xl font-black text-white uppercase tracking-widest">{{ __('Analysis in Progress') }}</h3>
                <p class="text-on-surface-variant mt-4 max-w-md mx-auto uppercase tracking-wider text-xs leading-relaxed">
                    {{ __('Our tactical analyst is currently processing the combat logs. Deep neural patterns take time to stabilize. Check back shortly.') }}
                </p>
            </div>
        </div>
    </div>
</template>
