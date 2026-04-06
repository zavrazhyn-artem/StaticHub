<script setup>
import { ref, nextTick, onMounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import AiChatBlocks from './AiChatBlocks.vue';
const { __ } = useTranslation();

const props = defineProps({
    open: { type: Boolean, required: true },
    reportId: { type: [Number, String], required: true },
    reportTitle: { type: String, default: '' },
    wclReportId: { type: String, default: '' },
    canViewGlobalReport: { type: Boolean, default: false },
    chatHistory: { type: Array, default: () => [] },
    analyzeApiUrl: { type: String, required: true },
});
const emit = defineEmits(['close']);

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const messages = ref([]);
const newMessage = ref('');
const isLoading = ref(false);
const chatHistoryRef = ref(null);

function parseHistoryRow(row) {
    if (row.role === 'user') {
        return { role: 'user', text: row.content, blocks: null, time: formatDbTime(row.created_at) };
    }
    let blocks = null;
    try { blocks = JSON.parse(row.content)?.blocks ?? null; } catch {}
    return { role: 'ai', text: null, blocks, time: formatDbTime(row.created_at) };
}

function formatDbTime(ts) {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
}

onMounted(() => {
    if (props.chatHistory?.length) {
        messages.value = props.chatHistory.map(parseHistoryRow);
    }
});

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
            body: JSON.stringify({ message: userMsg, report_id: props.reportId }),
        });

        const data = await response.json();
        let blocks = null;
        try { blocks = JSON.parse(data.reply)?.blocks ?? null; } catch {}
        messages.value.push({
            role: 'ai',
            text: blocks ? null : (data.reply || __('Error: Connection lost. Tactical link severed.')),
            blocks,
            time: currentTime(),
        });
    } catch {
        messages.value.push({
            role: 'ai',
            text: null,
            blocks: [{ type: 'alert', level: 'danger', content: __('Error: Connection lost. Tactical link severed.') }],
            time: currentTime(),
        });
    } finally {
        isLoading.value = false;
        scrollChatToBottom();
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition ease-in duration-300"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div v-if="open"
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
                <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Member notice -->
            <div v-if="!canViewGlobalReport"
                 class="mx-4 mt-4 px-4 py-3 bg-amber-500/5 border border-amber-500/20 rounded-xl flex items-start gap-3">
                <span class="material-symbols-outlined text-amber-500 text-sm mt-0.5 flex-shrink-0">lock</span>
                <p class="text-[10px] text-amber-400/80 font-bold uppercase tracking-wider leading-relaxed">
                    {{ __('Personal mode — you can only ask about your own tactical report.') }}
                </p>
            </div>

            <!-- Chat History -->
            <div ref="chatHistoryRef" class="flex-1 overflow-y-auto p-6 space-y-6 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                <div v-for="(msg, index) in messages" :key="index">
                    <div v-if="msg.role === 'user'" class="flex flex-col items-end gap-2">
                        <div class="bg-primary/10 border border-primary/20 rounded-2xl rounded-tr-none p-4 max-w-[85%]">
                            <p class="text-xs text-on-surface font-medium leading-relaxed">{{ msg.text }}</p>
                        </div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant opacity-40">User &bull; {{ msg.time }}</span>
                    </div>

                    <div v-if="msg.role === 'ai'" class="flex flex-col items-start gap-2">
                        <div class="flex items-start gap-3 w-full">
                            <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-surface-container-highest border border-primary/20 flex items-center justify-center shadow-[0_0_10px_rgba(79,211,247,0.1)]">
                                <img src="/images/logo.svg" alt="B" class="h-4 w-4">
                            </div>
                            <div class="bg-surface-container-high border border-white/5 rounded-2xl rounded-tl-none p-4 max-w-[85%]">
                                <AiChatBlocks v-if="msg.blocks" :blocks="msg.blocks" />
                                <p v-else class="text-xs text-on-surface font-medium leading-relaxed">{{ msg.text }}</p>
                            </div>
                        </div>
                        <span class="ml-11 text-[9px] font-black uppercase tracking-widest text-primary opacity-60">AI Analyst &bull; {{ msg.time }}</span>
                    </div>
                </div>

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
                        {{ __('Context:') }} {{ reportTitle }} ({{ wclReportId }}) {{ __('is loaded.') }}
                    </p>
                </div>
            </div>
        </div>
    </Transition>
</template>
