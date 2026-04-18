<script setup>
import { ref, watch, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    staticName:             { type: String, required: true },
    guildInfo:              { type: Object, default: null },
    autoFetchLogs:          { type: Boolean, default: false },
    autoFetchDelayMinutes:  { type: Number, default: 30 },
    updateUrl:              { type: String, required: true },
    connectGuildUrl:        { type: String, required: true },
    disconnectGuildUrl:     { type: String, required: true },
    profileTabUrl:          { type: String, required: true },
    scheduleTabUrl:         { type: String, required: true },
    discordTabUrl:          { type: String, required: true },
    logsTabUrl:             { type: String, required: true },
    canManage:              { type: Boolean, default: false },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// === Guild connection state ===
const guild = ref(props.guildInfo);
const showGuildModal = ref(false);
const guildUrl = ref('');
const isConnecting = ref(false);
const connectError = ref('');

// === Auto-fetch state ===
const autoFetch = ref(props.autoFetchLogs);
const delayMinutes = ref(props.autoFetchDelayMinutes);

// === Info modal ===
const showInfoModal = ref(false);

// === AI Status ===
const isFullyActive = computed(() => autoFetch.value && !!guild.value);

// === Auto-save debounce ===
let saveTimeout = null;

function autoSave() {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(async () => {
        await fetch(props.updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                auto_fetch_logs: autoFetch.value,
                auto_fetch_delay_minutes: delayMinutes.value,
            }),
        });
    }, 500);
}

watch(autoFetch, autoSave);
watch(delayMinutes, autoSave);

// === Guild connect ===
async function connectGuild() {
    if (!guildUrl.value.trim()) return;

    isConnecting.value = true;
    connectError.value = '';

    try {
        const res = await fetch(props.connectGuildUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ wcl_url: guildUrl.value }),
        });

        const data = await res.json();

        if (data.success) {
            guild.value = data.guild;
            showGuildModal.value = false;
            guildUrl.value = '';
        } else {
            connectError.value = data.error === 'not_found'
                ? __('Guild not found on Warcraft Logs. Check the URL and try again.')
                : data.error === 'invalid_url'
                    ? __('Invalid WCL guild URL. Use a link like: warcraftlogs.com/guild/id/123456')
                    : __('Failed to connect. WCL API may be unavailable. Try again later.');
        }
    } catch {
        connectError.value = __('Network error. Please try again.');
    } finally {
        isConnecting.value = false;
    }
}

async function disconnectGuild() {
    await fetch(props.disconnectGuildUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
        },
    });

    guild.value = null;
    autoFetch.value = false;
}
</script>

<template>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ staticName }}</p>
        </div>

        <SettingsTabs :profile-url="profileTabUrl" :schedule-url="scheduleTabUrl" :discord-url="discordTabUrl" :logs-url="logsTabUrl" active-tab="logs" :can-manage="canManage" />

        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm space-y-8">

            <!-- ═══ WCL Guild Connection ═══ -->
            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-[#ff7d0a]/10 flex items-center justify-center border border-[#ff7d0a]/20">
                        <span class="material-symbols-outlined text-[#ff7d0a]">analytics</span>
                    </div>
                    <div>
                        <h3 class="text-white font-headline text-sm font-black uppercase tracking-widest">{{ __('Warcraft Logs Integration') }}</h3>
                        <p class="text-3xs text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Connect your guild to enable log fetching and AI analysis.') }}</p>
                    </div>
                </div>

                <!-- Connected State -->
                <div v-if="guild" class="bg-black/20 rounded-xl p-6 border border-white/5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <span class="material-symbols-outlined text-success-neon text-2xl">check_circle</span>
                            <div>
                                <p class="text-sm text-white font-black uppercase tracking-tighter">
                                    {{ guild.name || 'Guild #' + guild.id }}
                                </p>
                                <p class="text-3xs text-on-surface-variant font-semibold uppercase tracking-wider mt-0.5">
                                    {{ guild.server_name || guild.server_slug }}
                                    <span class="opacity-30 mx-1">•</span>
                                    {{ guild.region_name || guild.region_slug?.toUpperCase() }}
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="showGuildModal = true"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg text-3xs font-black uppercase tracking-[0.15em] text-on-surface-variant hover:text-white hover:bg-white/5 transition-all border border-white/5">
                            <span class="material-symbols-outlined text-sm">swap_horiz</span>
                            {{ __('Change Guild') }}
                        </button>
                    </div>
                </div>

                <!-- Disconnected State -->
                <div v-else class="bg-black/20 rounded-xl p-6 border border-dashed border-white/10 text-center">
                    <span class="material-symbols-outlined text-4xl text-white/10 mb-3">link_off</span>
                    <p class="text-sm text-on-surface-variant font-bold uppercase tracking-wider mb-4">{{ __('No guild connected') }}</p>
                    <button type="button" @click="showGuildModal = true"
                            class="inline-flex items-center gap-2 bg-[#ff7d0a]/10 border border-[#ff7d0a]/30 hover:bg-[#ff7d0a] hover:text-black px-6 py-2.5 rounded-lg text-3xs font-black uppercase tracking-[0.2em] transition-all text-[#ff7d0a]">
                        <span class="material-symbols-outlined text-sm">link</span>
                        {{ __('Connect Guild') }}
                    </button>
                </div>
            </div>

            <!-- ═══ Auto-Fetch Logs ═══ -->
            <div class="space-y-6 pt-8 border-t border-white/5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center border border-amber-500/20">
                        <span class="material-symbols-outlined text-amber-500">schedule_send</span>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white font-headline text-sm font-black uppercase tracking-widest">{{ __('Automatic Log Fetching') }}</h3>
                        <p class="text-3xs text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Automatically fetch and analyze logs from WCL after each raid ends.') }}</p>
                    </div>
                    <!-- Info button -->
                    <button @click="showInfoModal = true" type="button"
                            class="w-5 h-5 rounded-full border border-white/20 flex items-center justify-center text-on-surface-variant hover:text-white hover:border-white/40 transition-colors shrink-0">
                        <span class="material-symbols-outlined text-xs">question_mark</span>
                    </button>
                </div>

                <div class="bg-black/20 rounded-xl p-6 border border-white/5 space-y-6">
                    <label class="flex items-center gap-4 cursor-pointer group">
                        <input type="checkbox" v-model="autoFetch"
                               :disabled="!guild"
                               class="w-5 h-5 rounded border-white/10 bg-black/40 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 transition-all cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed">
                        <div>
                            <p class="text-sm text-white font-bold uppercase tracking-tighter group-hover:text-amber-500 transition-colors"
                               :class="{ 'opacity-30': !guild }">
                                {{ __('Enable Auto-Fetch Logs') }}
                            </p>
                            <p class="text-xs text-on-surface-variant mt-0.5">
                                <template v-if="guild">{{ __('After a raid ends, the system will automatically search WCL for logs and trigger AI analysis.') }}</template>
                                <template v-else>{{ __('Connect a guild first to enable automatic log fetching.') }}</template>
                            </p>
                        </div>
                    </label>

                    <div v-if="autoFetch && guild" class="pl-9 space-y-2">
                        <label for="auto_fetch_delay_minutes" class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">
                            {{ __('Fetch Delay (minutes)') }}
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="number" id="auto_fetch_delay_minutes"
                                   v-model.number="delayMinutes"
                                   min="5" max="120"
                                   class="w-24 bg-surface-container-highest border border-white/5 rounded-lg px-4 py-2.5 font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all outline-none">
                            <span class="text-3xs text-on-surface-variant font-medium uppercase tracking-wider">{{ __('minutes after raid ends') }}</span>
                        </div>
                        <p class="text-4xs text-on-surface-variant/60 font-medium uppercase tracking-wider">{{ __('Allow enough time for the log uploader to finish uploading to WCL.') }}</p>
                    </div>
                </div>
            </div>

            <!-- ═══ AI Tactical Analyst ═══ -->
            <div class="space-y-6 pt-8 border-t border-white/5">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-slate-400/10 flex items-center justify-center border border-slate-400/20">
                        <span class="material-symbols-outlined text-slate-400">psychology</span>
                    </div>
                    <div>
                        <h3 class="text-white font-headline text-sm font-black uppercase tracking-widest">{{ __('AI Tactical Analyst') }}</h3>
                        <p class="text-3xs text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Automated performance reviews powered by Gemini Flash.') }}</p>
                    </div>
                </div>

                <!-- Active: Gemini configured + auto-fetch on + guild connected -->
                <div v-if="isFullyActive" class="bg-black/20 rounded-xl p-6 border border-white/5 space-y-4">
                    <div class="flex items-start gap-4">
                        <span class="material-symbols-outlined text-success-neon text-xl mt-1">check_circle</span>
                        <div>
                            <p class="text-sm text-white font-bold uppercase tracking-tighter">{{ __('Status: Active') }}</p>
                            <p class="text-xs text-on-surface-variant mt-1">{{ __('AI analysis will be automatically triggered after each raid once logs are detected. Results will be posted to Discord and available in the raid details.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Partially configured or inactive -->
                <div v-else class="bg-black/20 rounded-xl p-6 border border-white/5 space-y-4">
                    <div class="flex items-start gap-4">
                        <span class="material-symbols-outlined text-amber-500 text-xl mt-1">warning</span>
                        <div>
                            <p class="text-sm text-white font-bold uppercase tracking-tighter">{{ __('Status: Inactive') }}</p>
                            <p class="text-xs text-on-surface-variant mt-1">{{ __('Complete the following steps to activate automatic AI analysis:') }}</p>
                        </div>
                    </div>
                    <div class="pl-10 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm" :class="guild ? 'text-success-neon' : 'text-on-surface-variant/30'">
                                {{ guild ? 'check_circle' : 'radio_button_unchecked' }}
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider" :class="guild ? 'text-success-neon' : 'text-on-surface-variant'">
                                {{ __('WCL guild connected') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm" :class="autoFetch ? 'text-success-neon' : 'text-on-surface-variant/30'">
                                {{ autoFetch ? 'check_circle' : 'radio_button_unchecked' }}
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider" :class="autoFetch ? 'text-success-neon' : 'text-on-surface-variant'">
                                {{ __('Auto-fetch logs enabled') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Guild Connect Modal ═══ -->
    <GlassModal :show="showGuildModal" max-width="max-w-lg" @close="showGuildModal = false">
        <div class="p-6 space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#ff7d0a] text-xl">link</span>
                    <h3 class="font-headline text-sm font-bold text-white uppercase tracking-[0.15em]">{{ __('Connect WCL Guild') }}</h3>
                </div>
                <button @click="showGuildModal = false"
                        class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="space-y-3">
                <label for="guild_url" class="block text-3xs font-black text-on-surface-variant uppercase tracking-wider">{{ __('WCL Guild URL') }}</label>
                <input id="guild_url" type="text" v-model="guildUrl"
                       class="w-full bg-black/20 border border-white/10 p-4 rounded-lg text-white text-sm outline-none focus:border-[#ff7d0a]/50"
                       placeholder="https://www.warcraftlogs.com/guild/id/123456"
                       @keyup.enter="connectGuild">
                <p class="text-4xs text-on-surface-variant font-semibold uppercase tracking-wider opacity-40">
                    {{ __('Supports formats:') }} /guild/id/123456 {{ __('or') }} /guild/eu/server/name
                </p>
            </div>

            <div v-if="connectError" class="p-3 bg-error/10 border border-error/20 rounded-lg">
                <p class="text-xs text-error font-bold">{{ connectError }}</p>
            </div>

            <!-- Disconnect option -->
            <div v-if="guild" class="pt-2 border-t border-white/5">
                <button type="button" @click="disconnectGuild(); showGuildModal = false"
                        class="flex items-center gap-2 text-3xs font-black uppercase tracking-wider text-error/60 hover:text-error transition-colors">
                    <span class="material-symbols-outlined text-sm">link_off</span>
                    {{ __('Disconnect Guild') }}
                </button>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="showGuildModal = false"
                        class="px-6 py-3 rounded-xl text-3xs font-black uppercase tracking-wider text-on-surface-variant hover:bg-white/5 transition-all">
                    {{ __('Cancel') }}
                </button>
                <button type="button" @click="connectGuild"
                        :disabled="isConnecting || !guildUrl.trim()"
                        class="bg-[#ff7d0a] hover:bg-[#ff7d0a]/80 text-black px-8 py-3 rounded-xl text-3xs font-black uppercase tracking-[0.2em] shadow-lg transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <span v-if="isConnecting" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-sm">link</span>
                    {{ isConnecting ? __('Connecting...') : __('Connect') }}
                </button>
            </div>
        </div>
    </GlassModal>

    <!-- ═══ Info Modal — How Auto-Fetch Works ═══ -->
    <GlassModal :show="showInfoModal" max-width="max-w-lg" @close="showInfoModal = false">
        <div class="p-6 space-y-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-500 text-xl">help</span>
                    <h3 class="font-headline text-sm font-bold text-white uppercase tracking-[0.15em]">{{ __('How Auto-Fetch Works') }}</h3>
                </div>
                <button @click="showInfoModal = false"
                        class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="space-y-4">
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-3xs font-black text-amber-500">1</span>
                    </div>
                    <p class="text-sm text-on-surface-variant">{{ __('After your scheduled raid ends, the system waits the configured number of minutes for logs to be uploaded.') }}</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-3xs font-black text-amber-500">2</span>
                    </div>
                    <p class="text-sm text-on-surface-variant">{{ __('It then automatically searches your guild on Warcraft Logs for matching reports.') }}</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-3xs font-black text-amber-500">3</span>
                    </div>
                    <p class="text-sm text-on-surface-variant">{{ __('Found logs are linked to the raid event and sent for AI analysis.') }}</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-3xs font-black text-amber-500">4</span>
                    </div>
                    <p class="text-sm text-on-surface-variant">{{ __('Once analysis is complete, a notification is sent to your Discord notifications channel with a link to the report.') }}</p>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-6 h-6 rounded-full bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-3xs font-black text-amber-500">5</span>
                    </div>
                    <p class="text-sm text-on-surface-variant">{{ __('If no logs are found, a notification is sent with a link to upload logs manually.') }}</p>
                </div>
            </div>

            <div class="p-3 bg-white/5 border border-white/5 rounded-lg flex items-start gap-2.5">
                <span class="material-symbols-outlined text-on-surface-variant text-base mt-0.5 shrink-0">info</span>
                <p class="text-3xs text-on-surface-variant font-semibold uppercase tracking-wider leading-relaxed">
                    {{ __('Make sure your Discord notifications webhook is configured in the Discord tab for notifications to work.') }}
                </p>
            </div>
        </div>
    </GlassModal>
</template>
