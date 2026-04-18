<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import AiChatSidebar from './AiChatSidebar.vue';
import SelectUserWithMain from '../UI/SelectUserWithMain.vue';
const { __ } = useTranslation();

const props = defineProps({
    report:              { type: Object, required: true },
    personalReport:      { type: Object, default: null },
    rosterReports:       { type: Object, default: () => ({}) },
    rosterMembers:       { type: Array, default: () => [] },
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

// Chat availability timer
const chatTimeRemaining = ref('');
const chatExpired = ref(!props.report.chat_available);

let chatTimer = null;

function updateChatTimer() {
    if (!props.report.chat_expires_at) {
        chatExpired.value = true;
        chatTimeRemaining.value = '';
        return;
    }
    const now = new Date();
    const expires = new Date(props.report.chat_expires_at);
    const diff = expires - now;

    if (diff <= 0) {
        chatExpired.value = true;
        chatTimeRemaining.value = '';
        if (chatTimer) clearInterval(chatTimer);
        return;
    }

    chatExpired.value = false;
    const hours = Math.floor(diff / 3600000);
    const minutes = Math.floor((diff % 3600000) / 60000);
    const seconds = Math.floor((diff % 60000) / 1000);
    chatTimeRemaining.value = `${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
}

onMounted(() => {
    updateChatTimer();
    chatTimer = setInterval(updateChatTimer, 1000);
});

onUnmounted(() => {
    if (chatTimer) clearInterval(chatTimer);
});

const canChat = computed(() => props.canUseAiChat && !chatExpired.value);

// Character selector for leaders/officers on Personal Report tab
const selectedReportId = ref(props.personalReport?.id ? String(props.personalReport.id) : '');

const activePersonalReport = computed(() => {
    if (!props.canViewGlobalReport || !props.rosterMembers.length) {
        return props.personalReport;
    }
    const id = selectedReportId.value;
    const member = props.rosterMembers.find(m => String(m.id) === id);
    if (!member) return props.personalReport;
    const html = props.rosterReports[id];
    return {
        html,
        char_name:      member.character.name,
        char_class:     member.character.playable_class,
        char_class_css: member.character.playable_class.toLowerCase().replace(/ /g, '-'),
    };
});
</script>

<template>
    <div class="relative">
        <AiChatSidebar
            v-if="canChat"
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
        <button v-if="canChat && !chatOpen" @click="chatOpen = true"
            class="fixed bottom-8 right-8 z-40 bg-primary hover:bg-primary-dim text-on-primary-fixed p-4 rounded-full shadow-[0_0_20px_rgba(79,211,247,0.4)] transition-all hover:scale-110 flex items-center gap-3 group">
            <span v-if="chatTimeRemaining" class="text-4xs font-bold uppercase tracking-wider overflow-hidden max-w-0 group-hover:max-w-xs transition-all duration-500 whitespace-nowrap">{{ chatTimeRemaining }}</span>
            <span class="material-symbols-outlined">psychology</span>
        </button>

        <!-- Chat Expired Badge -->
        <div v-if="canUseAiChat && chatExpired && !chatOpen"
            class="fixed bottom-8 right-8 z-40 bg-surface-container-high text-on-surface-variant p-4 rounded-full opacity-50 cursor-not-allowed flex items-center gap-3">
            <span class="text-4xs font-bold uppercase tracking-wider">{{ __('Chat expired') }}</span>
            <span class="material-symbols-outlined">psychology</span>
        </div>

        <!-- Main Content -->
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Breadcrumbs & Back -->
            <div class="mb-8 flex items-center justify-between">
                <a :href="logsIndexUrl" class="flex items-center gap-2 text-on-surface-variant hover:text-indigo-400 transition-colors text-3xs font-bold uppercase tracking-wider group">
                    <span class="material-symbols-outlined text-sm group-hover:-translate-x-1 transition-transform">arrow_back</span>
                    {{ __('Return to Archives') }}
                </a>

                <a :href="report.wcl_url" target="_blank"
                   class="flex items-center gap-2 bg-[#ff7d0a]/10 hover:bg-[#ff7d0a] hover:text-black text-[#ff7d0a] px-4 py-2 rounded-lg text-3xs font-bold uppercase tracking-wider transition-all border border-[#ff7d0a]/20">
                    <span class="material-symbols-outlined text-sm">open_in_new</span>
                    {{ __('Raw WCL Report') }}
                </a>
            </div>

            <!-- Mission Header -->
            <div class="mb-12">
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-indigo-400/10 border border-indigo-400/20 rounded text-3xs font-bold text-indigo-400 uppercase tracking-wider">
                        {{ __('Report ID:') }} {{ report.wcl_report_id }}
                    </span>
                    <span class="text-on-surface-variant opacity-20">•</span>
                    <span class="text-on-surface-variant text-3xs font-bold uppercase tracking-wider">
                        {{ __('Executed:') }} {{ report.created_at }}
                    </span>
                    <template v-if="report.model">
                        <span class="text-on-surface-variant opacity-20">•</span>
                        <span class="px-2 py-0.5 bg-emerald-400/10 border border-emerald-400/20 rounded text-3xs font-bold text-emerald-400 uppercase tracking-wider">
                            {{ report.model }}
                        </span>
                    </template>
                </div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tight font-headline leading-tight mb-4">
                    {{ report.title }}
                </h1>
                <div class="flex items-center gap-6">
                    <div v-if="report.duration_hours" class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-indigo-400 text-sm">schedule</span>
                        <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">{{ report.duration_hours }} {{ __('Hours Duration') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-indigo-400 text-sm">terminal</span>
                        <span class="text-xs text-on-surface-variant font-bold uppercase tracking-wider">{{ __('Tactical Intelligence') }}</span>
                    </div>
                </div>
            </div>

            <!-- Member view: Personal Report only (no tabs, no global data) -->
            <template v-if="!canViewGlobalReport">
                <div v-if="personalReport" class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                    <div class="bg-indigo-400/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div :class="`w-10 h-10 rounded-xl bg-${personalReport.char_class_css}/20 flex items-center justify-center border border-${personalReport.char_class_css}/30`">
                                <span :class="`material-symbols-outlined text-${personalReport.char_class_css}`">person</span>
                            </div>
                            <div>
                                <h2 class="text-white font-headline text-xs font-black uppercase tracking-[0.2em] leading-none mb-1">
                                    {{ personalReport.char_name }}
                                </h2>
                                <p :class="`text-4xs font-bold text-${personalReport.char_class_css} uppercase tracking-wider`">
                                    {{ personalReport.char_class }}
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-indigo-400/10 border border-indigo-400/20 rounded text-4xs font-bold text-indigo-400 uppercase tracking-wider">
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
                <div class="flex items-center border-b border-white/5 pb-px">
                    <div class="flex gap-4">
                        <button @click="activeTab = 'global'"
                                :class="activeTab === 'global' ? 'text-indigo-400 border-indigo-400' : 'text-on-surface-variant border-transparent hover:text-white'"
                                class="px-6 py-4 text-3xs font-bold uppercase tracking-[0.2em] border-b-2 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">public</span>
                            {{ __('Global Report') }}
                        </button>
                        <button @click="activeTab = 'roster'"
                                :class="activeTab === 'roster' ? 'text-indigo-400 border-indigo-400' : 'text-on-surface-variant border-transparent hover:text-white'"
                                class="px-6 py-4 text-3xs font-bold uppercase tracking-[0.2em] border-b-2 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">person</span>
                            {{ __('Personal Report') }}
                        </button>
                    </div>

                    <div v-if="rosterMembers.length > 0 && activeTab === 'roster'" class="ml-auto w-64">
                        <SelectUserWithMain
                            v-model="selectedReportId"
                            :members="rosterMembers"
                            :placeholder="__('Select Character...')"
                            :search-placeholder="__('Search character...')"
                            :empty-text="__('No characters found.')"
                            accent-color="#f59e0b"
                        />
                    </div>
                </div>

                <!-- Global Tab -->
                <div v-show="activeTab === 'global'" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- AI Summary -->
                    <div class="lg:col-span-2 space-y-8">
                        <section class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                            <div class="bg-indigo-400/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                                <h2 class="text-indigo-400 font-headline text-xs font-black uppercase tracking-[0.2em] flex items-center gap-3">
                                    <span class="material-symbols-outlined text-lg">psychology</span>
                                    {{ __('AI Tactical Review') }}
                                </h2>
                                <div class="flex gap-1">
                                    <div class="w-2 h-2 rounded-full bg-indigo-400/20"></div>
                                    <div class="w-2 h-2 rounded-full bg-indigo-400/40"></div>
                                    <div class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></div>
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
                            <h3 class="text-white text-3xs font-bold uppercase tracking-wider opacity-40">{{ __('Mission Summary') }}</h3>
                            <div class="space-y-4">
                                <div class="flex justify-between p-3 bg-black/20 rounded-xl border border-white/5">
                                    <span class="text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Status') }}</span>
                                    <span class="text-3xs font-bold text-success-neon uppercase tracking-wider">{{ __('COMPLETED') }}</span>
                                </div>
                                <div class="flex justify-between p-3 bg-black/20 rounded-xl border border-white/5">
                                    <span class="text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Analyzed By') }}</span>
                                    <span class="text-3xs font-bold text-white uppercase tracking-wider">{{ report.model || 'Gemini 2.5 Flash' }}</span>
                                </div>
                                <div class="flex justify-between p-3 bg-black/20 rounded-xl border border-white/5">
                                    <span class="text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('AI Chat') }}</span>
                                    <span v-if="!chatExpired" class="text-3xs font-bold text-success-neon uppercase tracking-wider">{{ chatTimeRemaining }}</span>
                                    <span v-else class="text-3xs font-bold text-error uppercase tracking-wider">{{ __('Expired') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Roster Tab -->
                <div v-show="activeTab === 'roster'" class="space-y-6">
                    <!-- Personal report found -->
                    <div v-if="activePersonalReport" class="bg-surface-container-low border border-white/5 rounded-3xl overflow-hidden shadow-2xl">
                        <div class="bg-indigo-400/5 border-b border-white/5 px-8 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div :class="`w-10 h-10 rounded-xl bg-${activePersonalReport.char_class_css}/20 flex items-center justify-center border border-${activePersonalReport.char_class_css}/30`">
                                    <span :class="`material-symbols-outlined text-${activePersonalReport.char_class_css}`">person</span>
                                </div>
                                <div>
                                    <h2 class="text-white font-headline text-xs font-black uppercase tracking-[0.2em] leading-none mb-1">
                                        {{ activePersonalReport.char_name }}
                                    </h2>
                                    <p :class="`text-4xs font-bold text-${activePersonalReport.char_class_css} uppercase tracking-wider`">
                                        {{ activePersonalReport.char_class }}
                                    </p>
                                </div>
                            </div>
                            <span class="px-3 py-1 bg-indigo-400/10 border border-indigo-400/20 rounded text-4xs font-bold text-indigo-400 uppercase tracking-wider">
                                {{ personalReport && activePersonalReport.id === personalReport.id ? __('Your personal Report') : __('Personal Report') }}
                            </span>
                        </div>
                        <div class="p-8">
                            <div class="prose prose-invert prose-tactical max-w-none text-gray-300" v-html="activePersonalReport.html"></div>
                        </div>
                    </div>

                    <!-- Not participated (only when no roster reports and no personal report) -->
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
                    <span class="material-symbols-outlined text-8xl text-indigo-400/10">psychology</span>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="material-symbols-outlined text-4xl text-indigo-400 animate-pulse">hourglass_empty</span>
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
