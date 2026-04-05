<script setup>
import { ref, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import SearchableSelect from '@/Components/UI/SearchableSelect.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
const { __ } = useTranslation();

const props = defineProps({
    staticName:       { type: String,  required: true },
    botGuilds:        { type: Array,   default: () => [] },
    discordChannels:  { type: Array,   default: () => [] },
    discordRoles:     { type: Array,   default: () => [] },
    discordGuildId:   { type: String,  default: '' },
    discordChannelId: { type: String,  default: '' },
    discordRoleId:    { type: String,  default: '' },
    webhookUrl:       { type: String,  default: '' },
    webhookChannel:   { type: Object,  default: null },
    webhookMuted:     { type: Boolean, default: false },
    updateUrl:        { type: String,  required: true },
    testUrl:          { type: String,  required: true },
    deleteMessageUrl: { type: String,  required: true },
    inviteUrl:        { type: String,  required: true },
    scheduleTabUrl:   { type: String,  required: true },
    discordTabUrl:    { type: String,  required: true },
    logsTabUrl:       { type: String,  required: true },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// --- State ---
const selectedGuildId   = ref(props.discordGuildId);
const selectedChannelId = ref(props.discordChannelId);
const selectedRoleId    = ref(props.discordRoleId);
const webhookUrlInput   = ref(props.webhookUrl);
const resolvedChannel   = ref(props.webhookChannel);
const notifMuted        = ref(props.webhookMuted);
const showHelpModal     = ref(false);

const channels        = ref([...props.discordChannels]);
const roles           = ref([...props.discordRoles]);
const channelsLoading = ref(false);
const rolesLoading    = ref(false);
const fetchedGuildId  = ref(props.discordGuildId);

// Per-field save state
const saving = ref({});
const saved  = ref({});

// Test webhook
const testState     = ref('idle'); // idle | loading | success | error
const testMessageId = ref(null);
const deletingMsg   = ref(false);

// Toast
const toastShow    = ref(false);
const toastMessage = ref('');
const toastIsError = ref(false);
let toastTimer = null;

const showToast = (msg, error = false) => {
    clearTimeout(toastTimer);
    toastMessage.value = msg;
    toastIsError.value = error;
    toastShow.value    = true;
    toastTimer = setTimeout(() => { toastShow.value = false; }, 4000);
};

// --- Auto-save ---
async function saveField(fields) {
    const key = Object.keys(fields)[0];
    saving.value = { ...saving.value, [key]: true };

    try {
        const res  = await fetch(props.updateUrl, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(fields),
        });
        const data = await res.json();

        if (!res.ok || !data.success) {
            showToast(__('Failed to save. Please try again.'), true);
        } else {
            saved.value = { ...saved.value, [key]: true };
            setTimeout(() => { saved.value = { ...saved.value, [key]: false }; }, 2000);
            if (data.webhook_channel) resolvedChannel.value = data.webhook_channel;
        }
    } catch {
        showToast(__('Failed to save. Please try again.'), true);
    } finally {
        saving.value = { ...saving.value, [key]: false };
    }
}

function fieldIcon(key) {
    if (saving.value[key]) return { icon: 'sync',         cls: 'text-on-surface-variant animate-spin' };
    if (saved.value[key])  return { icon: 'check_circle', cls: 'text-success-neon' };
    return null;
}

// --- Guild watcher ---
watch(selectedGuildId, async (newGuildId) => {
    selectedChannelId.value = '';
    selectedRoleId.value    = '';
    channels.value          = [];
    roles.value             = [];
    fetchedGuildId.value    = '';

    await saveField({ discord_guild_id: newGuildId || null });

    if (!newGuildId) return;

    channelsLoading.value = true;
    rolesLoading.value    = true;

    const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };
    const [chRes, roRes] = await Promise.allSettled([
        fetch(`/api/discord/guilds/${newGuildId}/channels`, { headers }),
        fetch(`/api/discord/guilds/${newGuildId}/roles`,    { headers }),
    ]);

    channels.value = (chRes.status === 'fulfilled' && chRes.value.ok) ? await chRes.value.json() : [];
    roles.value    = (roRes.status === 'fulfilled' && roRes.value.ok) ? await roRes.value.json() : [];

    channelsLoading.value = false;
    rolesLoading.value    = false;
    fetchedGuildId.value  = newGuildId;
});

watch(selectedChannelId, (val) => {
    if (fetchedGuildId.value) saveField({ discord_channel_id: val || null });
});

watch(selectedRoleId, (val) => {
    if (selectedGuildId.value) saveField({ 'automation_settings[ping_role_id]': val || null });
});

watch(notifMuted, (val) => {
    saveField({ 'automation_settings[webhook_muted]': val });
});

// --- Webhook URL debounce ---
let webhookDebounce = null;
watch(webhookUrlInput, (val) => {
    resolvedChannel.value = null;
    clearTimeout(webhookDebounce);
    webhookDebounce = setTimeout(() => saveField({ discord_webhook_url: val || null }), 800);
});

// --- Test webhook (always works, ignores mute) ---
async function testWebhook() {
    testState.value     = 'loading';
    testMessageId.value = null;

    try {
        const res  = await fetch(props.testUrl, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            testState.value     = 'success';
            testMessageId.value = data.message_id || null;
        } else {
            testState.value = 'error';
            showToast(__('Webhook test failed. Check the URL and try again.'), true);
            setTimeout(() => { testState.value = 'idle'; }, 4000);
        }
    } catch {
        testState.value = 'error';
        showToast(__('Webhook test failed. Check the URL and try again.'), true);
        setTimeout(() => { testState.value = 'idle'; }, 4000);
    }
}

// --- Delete test message ---
async function deleteTestMessage() {
    if (!testMessageId.value) return;
    deletingMsg.value = true;

    const url = props.deleteMessageUrl.replace(':messageId', testMessageId.value);

    try {
        const res  = await fetch(url, {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            testMessageId.value = null;
            testState.value     = 'idle';
            showToast(__('Test message deleted.'));
        } else {
            showToast(__('Could not delete the message.'), true);
        }
    } catch {
        showToast(__('Could not delete the message.'), true);
    } finally {
        deletingMsg.value = false;
    }
}

const noChannelsError = () =>
    selectedGuildId.value &&
    !channelsLoading.value &&
    channels.value.length === 0 &&
    fetchedGuildId.value === selectedGuildId.value;
</script>

<template>
    <ToastNotification
        :show="toastShow"
        :message="toastMessage"
        :icon="toastIsError ? 'error' : 'check_circle'"
        :icon-class="toastIsError ? 'text-error-neon' : 'text-success-neon'"
    />

    <!-- ── Webhook How-To Modal ─────────────────────────────────────── -->
    <GlassModal :show="showHelpModal" max-width="max-w-lg" @close="showHelpModal = false">
        <div class="p-6 space-y-5">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-[#5865F2] text-xl">help</span>
                    <h3 class="font-headline text-sm font-bold text-white uppercase tracking-[0.15em]">{{ __('How to create a Webhook') }}</h3>
                </div>
                <button @click="showHelpModal = false"
                        class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Steps -->
            <ol class="space-y-4">
                <li v-for="(step, i) in [
                    __('Open Discord and go to your server.'),
                    __('Click the server name at the top left → Server Settings.'),
                    __('Go to Integrations → Webhooks → New Webhook.'),
                    __('Choose the channel where service notifications should appear.'),
                    __('Click Copy Webhook URL and paste it into the field below.'),
                ]" :key="i" class="flex items-start gap-4">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-[#5865F2]/20 border border-[#5865F2]/40 flex items-center justify-center font-headline text-[10px] font-bold text-[#5865F2]">{{ i + 1 }}</span>
                    <p class="text-sm text-on-surface-variant font-medium leading-relaxed pt-0.5">{{ step }}</p>
                </li>
            </ol>

            <div class="pt-2 border-t border-white/5">
                <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">
                    {{ __('Service notifications include: AI raid analysis results, roster alerts, and similar automated messages.') }}
                </p>
            </div>
        </div>
    </GlassModal>

    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ staticName }}</p>
        </div>

        <SettingsTabs :schedule-url="scheduleTabUrl" :discord-url="discordTabUrl" :logs-url="logsTabUrl" active-tab="discord" />

        <div class="space-y-4">

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 1 — SERVER
            ════════════════════════════════════════════════════════ -->
            <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-[#5865F2] text-xl">dns</span>
                    <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Server') }}</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    <!-- Guild select -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Discord Server (Guild)') }}</label>
                            <template v-if="fieldIcon('discord_guild_id')">
                                <span :class="['material-symbols-outlined text-sm', fieldIcon('discord_guild_id').cls]">{{ fieldIcon('discord_guild_id').icon }}</span>
                            </template>
                        </div>
                        <SearchableSelect
                            v-if="botGuilds.length"
                            v-model="selectedGuildId"
                            :options="botGuilds"
                            input-name="discord_guild_id"
                            icon="dns"
                            :placeholder="__('Select a server...')"
                            :search-placeholder="__('Search server...')"
                            :empty-text="__('No servers found.')"
                            accent-color="#5865F2"
                        />
                        <div v-else>
                            <input type="text" :value="selectedGuildId" placeholder="e.g. 123456789012345678"
                                class="block w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none">
                            <p class="mt-1 text-[9px] text-error-neon font-medium uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[10px]">warning</span>
                                {{ __('Bot is not in any servers or token is missing.') }}
                            </p>
                        </div>
                        <p v-if="botGuilds.length" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Select the server where your bot is present.') }}</p>
                    </div>

                    <!-- Invite bot -->
                    <div class="space-y-2">
                        <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest invisible select-none">{{ __('Bot') }}</label>
                        <a :href="inviteUrl" target="_blank" rel="noopener noreferrer"
                           class="flex w-full justify-center items-center gap-2 py-3 bg-surface-container-highest border border-white/5 hover:border-[#5865F2]/50 hover:bg-[#5865F2]/10 text-on-surface-variant hover:text-[#5865F2] rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest transition-all">
                            <span class="material-symbols-outlined text-sm">smart_toy</span>
                            {{ __('Invite Bot to Server') }}
                        </a>
                        <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('The bot must be in your server to manage channels and roles.') }}</p>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 2 — SCHEDULE SETTINGS
            ════════════════════════════════════════════════════════ -->
            <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
                <div class="flex items-center gap-3 mb-6">
                    <span class="material-symbols-outlined text-[#5865F2] text-xl">calendar_month</span>
                    <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Schedule Settings') }}</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Announcement Channel -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Announcement Channel') }}</label>
                            <template v-if="fieldIcon('discord_channel_id')">
                                <span :class="['material-symbols-outlined text-sm', fieldIcon('discord_channel_id').cls]">{{ fieldIcon('discord_channel_id').icon }}</span>
                            </template>
                        </div>
                        <SearchableSelect
                            v-if="selectedGuildId"
                            v-model="selectedChannelId"
                            :options="channels"
                            input-name="discord_channel_id"
                            icon="chat"
                            prefix="# "
                            :placeholder="channelsLoading ? __('Loading channels...') : __('Select a channel...')"
                            :search-placeholder="__('Search channel...')"
                            :empty-text="__('No channels found.')"
                            :loading="channelsLoading"
                            :disabled="noChannelsError()"
                            accent-color="#5865F2"
                        />
                        <div v-else class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                            {{ __('Select Server first...') }}
                        </div>
                        <p v-if="noChannelsError()" class="text-[9px] text-error-neon font-medium uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[11px]">error</span>
                            {{ __('Bot has no access or no text channels found in this server.') }}
                        </p>
                        <p v-else class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Raid announcements and RSVP posts will appear here.') }}</p>
                    </div>

                    <!-- Role to Mention -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Role to Mention') }}</label>
                            <template v-if="fieldIcon('automation_settings[ping_role_id]')">
                                <span :class="['material-symbols-outlined text-sm', fieldIcon('automation_settings[ping_role_id]').cls]">{{ fieldIcon('automation_settings[ping_role_id]').icon }}</span>
                            </template>
                        </div>
                        <SearchableSelect
                            v-if="selectedGuildId"
                            v-model="selectedRoleId"
                            :options="roles"
                            input-name="automation_settings[ping_role_id]"
                            icon="groups"
                            prefix="@ "
                            :placeholder="rolesLoading ? __('Loading roles...') : __('No mention')"
                            :search-placeholder="__('Search role...')"
                            :empty-text="__('No roles found.')"
                            :loading="rolesLoading"
                            :disabled="!roles.length && !rolesLoading"
                            accent-color="#a78bfa"
                        />
                        <div v-else class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                            {{ __('Select Server first...') }}
                        </div>
                        <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('This role will be pinged when a raid announcement is posted.') }}</p>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════
                 SECTION 3 — NOTIFICATIONS
            ════════════════════════════════════════════════════════ -->
            <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">

                <!-- Section header -->
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <span :class="['material-symbols-outlined text-xl transition-colors', notifMuted ? 'text-on-surface-variant' : 'text-[#5865F2]']">
                            {{ notifMuted ? 'notifications_off' : 'notifications_active' }}
                        </span>
                        <h2 class="font-headline text-sm font-bold text-white uppercase tracking-[0.2em]">{{ __('Notifications') }}</h2>
                        <!-- Help button -->
                        <button @click="showHelpModal = true" type="button"
                                class="w-5 h-5 rounded-full border border-white/20 flex items-center justify-center text-on-surface-variant hover:text-white hover:border-white/40 transition-colors">
                            <span class="material-symbols-outlined text-[12px]">question_mark</span>
                        </button>
                    </div>

                    <!-- Active / Muted toggle -->
                    <label class="flex items-center gap-3 cursor-pointer">
                        <span class="font-headline text-[10px] font-bold uppercase tracking-widest transition-colors"
                              :class="notifMuted ? 'text-on-surface-variant' : 'text-[#5865F2]'">
                            {{ notifMuted ? __('Muted') : __('Active') }}
                        </span>
                        <div @click="notifMuted = !notifMuted"
                             :class="['relative w-11 h-6 rounded-full transition-colors cursor-pointer border',
                                      notifMuted
                                        ? 'bg-surface-container-highest border-white/10'
                                        : 'bg-[#5865F2]/20 border-[#5865F2]/40']">
                            <div :class="['absolute top-0.5 w-5 h-5 rounded-full transition-all shadow-sm',
                                         notifMuted ? 'left-0.5 bg-on-surface-variant/50' : 'left-5 bg-[#5865F2]']"></div>
                        </div>
                        <template v-if="fieldIcon('automation_settings[webhook_muted]')">
                            <span :class="['material-symbols-outlined text-sm', fieldIcon('automation_settings[webhook_muted]').cls]">
                                {{ fieldIcon('automation_settings[webhook_muted]').icon }}
                            </span>
                        </template>
                    </label>
                </div>

                <!-- Body — dimmed when muted -->
                <div :class="['space-y-3 transition-opacity duration-200', notifMuted ? 'opacity-40 pointer-events-none select-none' : '']">

                    <!-- Two columns, equal height -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:items-stretch">

                        <!-- Left: webhook URL input with integrated test button -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Webhook URL') }}</label>
                                <template v-if="fieldIcon('discord_webhook_url')">
                                    <span :class="['material-symbols-outlined text-sm', fieldIcon('discord_webhook_url').cls]">{{ fieldIcon('discord_webhook_url').icon }}</span>
                                </template>
                            </div>

                            <!-- Input with button inside — flex-1 so it stretches to match right column -->
                            <div class="relative group flex-1 flex items-center">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#5865F2] transition-colors text-lg">link</span>
                                </span>
                                <input
                                    v-model="webhookUrlInput"
                                    type="url"
                                    placeholder="https://discord.com/api/webhooks/..."
                                    class="block w-full h-full min-h-[48px] pl-12 pr-[88px] py-2 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-xs font-bold text-white tracking-wider focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none placeholder:text-on-surface-variant/30"
                                />
                                <!-- Integrated send button -->
                                <button
                                    type="button"
                                    :disabled="!webhookUrlInput || testState === 'loading'"
                                    @click="testWebhook"
                                    :class="['absolute inset-y-0 right-0 px-4 flex items-center gap-1.5 rounded-r-lg font-headline text-[10px] font-bold uppercase tracking-widest border-l transition-all disabled:opacity-40 disabled:cursor-not-allowed',
                                             testState === 'success' ? 'text-success-neon border-success-neon/20 bg-success-neon/10' :
                                             testState === 'error'   ? 'text-error-neon border-error-neon/20 bg-error-neon/10' :
                                                                       'text-[#5865F2] border-white/5 hover:bg-[#5865F2]/15']">
                                    <span :class="['material-symbols-outlined text-base leading-none', testState === 'loading' ? 'animate-spin' : '']">
                                        {{ testState === 'loading' ? 'sync' : testState === 'success' ? 'check_circle' : testState === 'error' ? 'error' : 'send' }}
                                    </span>
                                    <span>{{ __('Test') }}</span>
                                </button>
                            </div>

                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">
                                {{ __('Paste the webhook URL copied from Discord.') }}
                            </p>
                        </div>

                        <!-- Right: resolved channel display -->
                        <div class="flex flex-col gap-2">
                            <div>
                                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Posts To') }}</label>
                            </div>

                            <!-- Channel block — flex-1 to match input height -->
                            <div class="flex-1">
                                <!-- Resolved -->
                                <div v-if="resolvedChannel"
                                     class="flex items-center gap-3 h-full min-h-[48px] px-4 py-2 bg-surface-container-highest border border-[#5865F2]/20 rounded-lg">
                                    <span class="material-symbols-outlined text-[#5865F2] text-lg flex-shrink-0">tag</span>
                                    <p class="font-headline text-sm font-bold text-white truncate"># {{ resolvedChannel.channel_name }}</p>
                                </div>
                                <!-- Not configured -->
                                <div v-else
                                     class="flex items-center gap-3 h-full min-h-[48px] px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg">
                                    <span class="material-symbols-outlined text-on-surface-variant/40 text-lg flex-shrink-0">tag</span>
                                    <p class="font-headline text-xs font-bold text-on-surface-variant/40 italic tracking-widest">
                                        {{ webhookUrlInput ? __('Verifying...') : __('No channel configured') }}
                                    </p>
                                </div>
                            </div>

                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">
                                {{ __('Resolved from the webhook URL after saving.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Delete test message banner -->
                    <div v-if="testMessageId"
                         class="flex items-center gap-3 p-4 rounded-lg bg-success-neon/5 border border-success-neon/20">
                        <span class="material-symbols-outlined text-success-neon text-lg flex-shrink-0">check_circle</span>
                        <p class="text-[10px] text-on-surface-variant font-medium flex-1">
                            {{ __('Test message sent! You can delete it from Discord now.') }}
                        </p>
                        <button
                            type="button"
                            :disabled="deletingMsg"
                            @click="deleteTestMessage"
                            class="flex items-center gap-2 px-4 py-2 rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest border text-error-neon border-error-neon/30 bg-error-neon/5 hover:bg-error-neon/15 transition-all disabled:opacity-50 flex-shrink-0">
                            <span :class="['material-symbols-outlined text-sm', deletingMsg ? 'animate-spin' : '']">
                                {{ deletingMsg ? 'sync' : 'delete' }}
                            </span>
                            {{ __('Delete message') }}
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>
</template>
