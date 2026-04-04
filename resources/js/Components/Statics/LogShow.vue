<script setup>
import { ref, nextTick } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    report:         { type: Object, required: true },
    personalReport: { type: Object, default: null },  // { html, char_name, char_class, char_class_css }
    isRaidLeader:   { type: Boolean, default: false },
    staticName:     { type: String, required: true },
    logsIndexUrl:   { type: String, required: true },
    analyzeApiUrl:  { type: String, required: true },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Tabs
const activeTab = ref('global');

// AI Chat sidebar
const chatOpen  = ref(false);
const messages  = ref([]);
const newMessage = ref('');
const isLoading = ref(false);
const chatHistoryRef = ref(null);

function scrollChatToBottom() {
    nextTick(() => {
        if (chatHistoryRef.value) {
            chatHistoryRef.value.scrollTop = chatHistoryRef.value.scrollHeight;
        }
    });
}

function currentTime() {
    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
}

async function sendMessage() {
    if (!newMessage.value.trim() || isLoading.value) return;

    const userMsg = newMessage.value;
    messages.value.push({ role: 'user', text: userMsg, time: currentTime() });
    newMessage.value = '';
    isLoading.value = true;
    scrollChatToBottom();

    try {
        const response = await fetch(props.analyzeApiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ message: userMsg, report_id: props.report.id }),
        });

        const data = await response.json();
        messages.value.push({
            role: 'ai',
            text: data.reply || __('Error: Connection lost. Tactical link severed.'),
            time: currentTime(),
        });
    } catch {
        messages.value.push({
            role: 'ai',
            text: __('Error: Connection lost. Tactical link severed.'),
            time: currentTime(),
        });
    } finally {
        isLoading.value = false;
        scrollChatToBottom();
    }
}
</script>

<template>
    <div class="relative">
        <!-- AI Analyst Sidebar -->
        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition ease-in duration-300"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="chatOpen"
                 class="fixed right-0 top-0 h-full w-96 bg-surface-container border-l border-primary/20 shadow-[0_0_50px_rgba(0,0,0,0.5)] z-50 flex flex-col">
                <!-- Header -->
                <div class="p-6 border-b border-white/5 flex items-center justify-between bg-surface-container-high">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img src="/images/logo.svg" alt="B" class="h-6 w-6 drop-shadow-[0_0_8px_rgba(79,211,247,0.5)]">
                            <div class="absolute -top-1 -right-1 w-2 h-2 bg-success-neon rounded-full shadow-[0_0_5px_rgba(57,255,20,0.8)] animate-pulse"></div>
                        </div>
                        <h2 class="text-white font-headline text-xs font-black uppercase tracking-[0.2em]">BlastR AI Analyst</h2>
                    </div>
                    <button @click="chatOpen = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Chat History -->
                <div ref="chatHistoryRef" class="flex-1 overflow-y-auto p-6 space-y-6 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                    <div v-for="(msg, index) in messages" :key="index">
                        <!-- User Message -->
                        <div v-if="msg.role === 'user'" class="flex flex-col items-end gap-2">
                            <div class="bg-primary/10 border border-primary/20 rounded-2xl rounded-tr-none p-4 max-w-[85%]">
                                <p class="text-xs text-on-surface font-medium leading-relaxed">{{ msg.text }}</p>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant opacity-40">User • {{ msg.time }}</span>
                        </div>

                        <!-- AI Message -->
                        <div v-if="msg.role === 'ai'" class="flex flex-col items-start gap-2">
                            <div class="flex items-start gap-3 w-full">
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-surface-container-highest border border-primary/20 flex items-center justify-center shadow-[0_0_10px_rgba(79,211,247,0.1)]">
                                    <img src="/images/logo.svg" alt="B" class="h-4 w-4">
                                </div>
                                <div class="bg-surface-container-high border border-white/5 rounded-2xl rounded-tl-none p-4 max-w-[85%]">
                                    <p class="text-xs text-on-surface font-medium leading-relaxed" v-html="msg.text"></p>
                                </div>
                            </div>
                            <span class="ml-11 text-[9px] font-black uppercase tracking-widest text-primary opacity-60">AI Analyst • {{ msg.time }}</span>
                        </div>
                    </div>

                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="flex flex-col items-start gap-2 animate-pulse">
                        <div class="flex items-start gap-3 w-full">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-surface-container-highest border border-primary/20 flex items-center justify-center">
                                <img src="/images/logo.svg" alt="B" class="h-4 w-4 opacity-50">
                            </div>
                            <div class="bg-surface-container-high border border-white/5 rounded-2xl rounded-tl-none p-4 w-32">
                                <div class="flex gap-1">
                                    <div class="w-1 h-1 bg-primary rounded-full animate-bounce"></div>
                                    <div class="w-1 h-1 bg-primary rounded-full animate-bounce [animation-delay:0.2s]"></div>
                                    <div class="w-1 h-1 bg-primary rounded-full animate-bounce [animation-delay:0.4s]"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="p-6 bg-surface-container-high border-t border-white/5">
                    <form @submit.prevent="sendMessage" class="relative group">
                        <div class="absolute -inset-0.5 bg-gradient-to-r from-primary/50 to-primary/0 rounded-xl opacity-20 group-focus-within:opacity-100 transition duration-300"></div>
                        <div class="relative flex items-center gap-2 bg-surface-container-lowest border border-white/10 rounded-xl p-2 pl-4">
                            <input type="text" v-model="newMessage"
                                :placeholder="__('Analyze tactical data...')"
                                :disabled="isLoading"
                                class="bg-transparent border-none focus:ring-0 text-xs text-white placeholder-on-surface-variant/40 flex-1 py-2 outline-none">
                            <button type="submit"
                                :disabled="isLoading || !newMessage.trim()"
                                class="bg-primary hover:bg-primary-dim text-on-primary-fixed p-2 rounded-lg transition-all shadow-[0_0_15px_rgba(79,211,247,0.3)] flex items-center justify-center disabled:opacity-50 disabled:shadow-none">
                                <span class="material-symbols-outlined text-sm">send</span>
                            </button>
                        </div>
                    </form>
                    <div class="mt-4 flex items-center gap-2 px-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
                        <p class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant opacity-60">
                            {{ __('Context:') }} {{ report.title }} ({{ report.wcl_report_id }}) {{ __('is loaded.') }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Floating Chat Toggle -->
        <button v-if="!chatOpen" @click="chatOpen = true"
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

            <!-- Tabs (AI analysis present) -->
            <div v-if="report.has_ai_analysis" class="space-y-8">
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
