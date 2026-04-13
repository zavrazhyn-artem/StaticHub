<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
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
    discordRoleIds:   { type: Array,   default: () => [] },
    notificationMethod:   { type: String,  default: 'webhook' },
    notificationChannelId:{ type: String,  default: '' },
    webhookUrl:       { type: String,  default: '' },
    webhookChannel:   { type: Object,  default: null },
    webhookMuted:     { type: Boolean, default: false },
    updateUrl:        { type: String,  required: true },
    testUrl:                { type: String,  required: true },
    testChannelUrl:         { type: String,  required: true },
    testNotificationChannelUrl:    { type: String,  required: true },
    deleteMessageUrl:       { type: String,  required: true },
    deleteChannelMessageUrl:{ type: String,  required: true },
    deleteNotificationChannelMessageUrl: { type: String, required: true },
    inviteUrl:        { type: String,  required: true },
    profileTabUrl:    { type: String,  required: true },
    scheduleTabUrl:   { type: String,  required: true },
    discordTabUrl:    { type: String,  required: true },
    logsTabUrl:       { type: String,  required: true },
    canManage:        { type: Boolean, default: false },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// --- State ---
const selectedGuildId   = ref(props.discordGuildId);
const selectedChannelId = ref(props.discordChannelId);
const selectedRoleIds   = ref([...props.discordRoleIds]);
const notifMethod              = ref(props.notificationMethod);
const selectedNotifChannelId   = ref(props.notificationChannelId);
const webhookUrlInput   = ref(props.webhookUrl);
const resolvedChannel   = ref(props.webhookChannel);
const notifMuted        = ref(props.webhookMuted);
const showHelpModal     = ref(false);
const showRolesModal    = ref(false);
const maxVisibleRoles   = 2;
let inviteCheckInterval = null;

function openInvitePopup() {
    const popup = window.open(props.inviteUrl, 'discord_invite', 'width=500,height=800');
    if (!popup) return;

    clearInterval(inviteCheckInterval);
    inviteCheckInterval = setInterval(() => {
        if (popup.closed) {
            clearInterval(inviteCheckInterval);
            window.location.reload();
            return;
        }
        try {
            if (popup.location.origin === window.location.origin) {
                popup.close();
                clearInterval(inviteCheckInterval);
                window.location.reload();
            }
        } catch {
            // cross-origin — still on Discord, keep waiting
        }
    }, 500);
}

onBeforeUnmount(() => clearInterval(inviteCheckInterval));

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

// Test channel (bot API)
const channelTestState     = ref('idle'); // idle | loading | success | error
const channelTestMessageId = ref(null);
const channelTestError     = ref('');
const deletingChannelMsg   = ref(false);

// Test notification channel (bot API)
const notifChannelTestState     = ref('idle');
const notifChannelTestMessageId = ref(null);
const notifChannelTestError     = ref('');
const deletingNotifChannelMsg   = ref(false);

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
async function saveField(fields, trackKey = null) {
    const key = trackKey || Object.keys(fields)[0];
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
    if (saving.value[key]) return { icon: 'sync',         cls: 'text-on-surface-variant animate-spin', visible: true };
    if (saved.value[key])  return { icon: 'check_circle', cls: 'text-success-neon', visible: true };
    return { icon: 'check_circle', cls: '', visible: false };
}

// --- Guild watcher ---
watch(selectedGuildId, async (newGuildId) => {
    selectedChannelId.value = '';
    selectedRoleIds.value   = [];
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

watch(selectedRoleIds, (val) => {
    if (selectedGuildId.value) saveField({ automation_settings: { ping_role_ids: val } }, 'ping_role_ids');
}, { deep: true });

watch(notifMuted, (val) => {
    saveField({ automation_settings: { webhook_muted: val } }, 'webhook_muted');
});

watch(notifMethod, (val) => {
    if (val === 'webhook') {
        if (!webhookUrlInput.value) return;
        saveField({ discord_webhook_url: webhookUrlInput.value, automation_settings: { notification_method: 'webhook' } }, 'notification_method');
    } else {
        if (!selectedNotifChannelId.value) return;
        saveField({ notification_channel_id: selectedNotifChannelId.value, automation_settings: { notification_method: 'channel' } }, 'notification_method');
    }
});

watch(selectedNotifChannelId, (val) => {
    if (!selectedGuildId.value) return;
    saveField({ notification_channel_id: val || null, automation_settings: { notification_method: 'channel' } }, 'notification_method');
});

// --- Webhook URL debounce ---
let webhookDebounce = null;
watch(webhookUrlInput, (val) => {
    resolvedChannel.value = null;
    clearTimeout(webhookDebounce);
    webhookDebounce = setTimeout(() => {
        saveField({ discord_webhook_url: val || null, automation_settings: { notification_method: 'webhook' } }, 'notification_method');
    }, 800);
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

// --- Test channel (bot API) ---
async function testChannel() {
    channelTestState.value     = 'loading';
    channelTestMessageId.value = null;
    channelTestError.value     = '';

    try {
        const res  = await fetch(props.testChannelUrl, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            channelTestState.value     = 'success';
            channelTestMessageId.value = data.message_id || null;
        } else {
            channelTestState.value = 'error';
            channelTestError.value = data.error || __('Unknown error');
            setTimeout(() => { channelTestState.value = 'idle'; channelTestError.value = ''; }, 8000);
        }
    } catch {
        channelTestState.value = 'error';
        channelTestError.value = __('Network error. Please try again.');
        setTimeout(() => { channelTestState.value = 'idle'; channelTestError.value = ''; }, 8000);
    }
}

async function deleteChannelTestMessage() {
    if (!channelTestMessageId.value) return;
    deletingChannelMsg.value = true;

    const url = props.deleteChannelMessageUrl.replace(':messageId', channelTestMessageId.value);

    try {
        const res  = await fetch(url, {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            channelTestMessageId.value = null;
            channelTestState.value     = 'idle';
            showToast(__('Test message deleted.'));
        } else {
            showToast(__('Could not delete the message.'), true);
        }
    } catch {
        showToast(__('Could not delete the message.'), true);
    } finally {
        deletingChannelMsg.value = false;
    }
}

// --- Test notification channel (bot API) ---
async function testNotificationChannel() {
    notifChannelTestState.value     = 'loading';
    notifChannelTestMessageId.value = null;
    notifChannelTestError.value     = '';

    try {
        const res  = await fetch(props.testNotificationChannelUrl, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            notifChannelTestState.value     = 'success';
            notifChannelTestMessageId.value = data.message_id || null;
        } else {
            notifChannelTestState.value = 'error';
            notifChannelTestError.value = data.error || __('Unknown error');
            setTimeout(() => { notifChannelTestState.value = 'idle'; notifChannelTestError.value = ''; }, 8000);
        }
    } catch {
        notifChannelTestState.value = 'error';
        notifChannelTestError.value = __('Network error. Please try again.');
        setTimeout(() => { notifChannelTestState.value = 'idle'; notifChannelTestError.value = ''; }, 8000);
    }
}

async function deleteNotifChannelTestMessage() {
    if (!notifChannelTestMessageId.value) return;
    deletingNotifChannelMsg.value = true;

    const url = props.deleteNotificationChannelMessageUrl.replace(':messageId', notifChannelTestMessageId.value);

    try {
        const res  = await fetch(url, {
            method:  'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();

        if (data.success) {
            notifChannelTestMessageId.value = null;
            notifChannelTestState.value     = 'idle';
            showToast(__('Test message deleted.'));
        } else {
            showToast(__('Could not delete the message.'), true);
        }
    } catch {
        showToast(__('Could not delete the message.'), true);
    } finally {
        deletingNotifChannelMsg.value = false;
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

    <!-- ── Selected Roles Modal ─────────────────────────────────────── -->
    <GlassModal :show="showRolesModal" max-width="max-w-sm" @close="showRolesModal = false">
        <div class="px-6 py-4 border-b border-white/5 bg-gradient-to-r from-surface-container-high to-surface-container flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#a78bfa]/20 flex items-center justify-center text-[#a78bfa]">
                    <span class="material-symbols-outlined text-[18px]">groups</span>
                </div>
                <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest">{{ __('Mention Roles') }}</h3>
            </div>
            <button @click="showRolesModal = false" class="text-on-surface-variant hover:text-white transition-colors p-1 hover:bg-white/5 rounded-md">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-4 space-y-2">
            <div
                v-for="roleId in selectedRoleIds"
                :key="roleId"
                class="flex items-center justify-between px-3 py-2.5 rounded-lg bg-surface-container-highest/50 border border-white/5"
            >
                <span class="text-xs font-bold text-[#a78bfa] uppercase tracking-widest">@ {{ roles.find(r => r.id === roleId)?.name || roleId }}</span>
                <button
                    type="button"
                    @click="selectedRoleIds = selectedRoleIds.filter(id => id !== roleId)"
                    class="text-on-surface-variant hover:text-error transition-colors p-0.5 hover:bg-white/5 rounded"
                >
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                </button>
            </div>
            <p v-if="!selectedRoleIds.length" class="text-center text-xs text-on-surface-variant/50 py-4 italic">{{ __('No roles selected.') }}</p>
        </div>
    </GlassModal>

    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ staticName }}</p>
        </div>

        <SettingsTabs :profile-url="profileTabUrl" :schedule-url="scheduleTabUrl" :discord-url="discordTabUrl" :logs-url="logsTabUrl" active-tab="discord" :can-manage="canManage" />

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
                            <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('discord_guild_id').cls, fieldIcon('discord_guild_id').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('discord_guild_id').icon }}</span>
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
                        <div v-else class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic min-h-[48px] flex items-center">
                            {{ __('Invite the bot to a server first') }}
                        </div>
                        <p v-if="botGuilds.length" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Select the server where your bot is present.') }}</p>
                    </div>

                    <!-- Invite bot -->
                    <div class="space-y-2">
                        <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest invisible select-none">{{ __('Bot') }}</label>
                        <button type="button"
                           @click="openInvitePopup"
                           class="flex w-full justify-center items-center gap-2 py-3 bg-surface-container-highest border border-white/5 hover:border-[#5865F2]/50 hover:bg-[#5865F2]/10 text-on-surface-variant hover:text-[#5865F2] rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest transition-all">
                            <span class="material-symbols-outlined text-sm">smart_toy</span>
                            {{ __('Invite Bot to Server') }}
                        </button>
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
                            <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('discord_channel_id').cls, fieldIcon('discord_channel_id').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('discord_channel_id').icon }}</span>
                        </div>
                        <div :class="['relative', selectedChannelId ? 'channel-test-group' : '']">
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
                            <!-- Integrated test button (same pattern as webhook) -->
                            <button
                                v-if="selectedChannelId"
                                type="button"
                                :disabled="channelTestState === 'loading'"
                                @click="testChannel"
                                :class="['absolute inset-y-0 right-0 px-4 flex items-center gap-1.5 rounded-r-lg font-headline text-[10px] font-bold uppercase tracking-widest border-l transition-all disabled:opacity-40 disabled:cursor-not-allowed z-10',
                                         channelTestState === 'success' ? 'text-success-neon border-success-neon/20 bg-success-neon/10' :
                                         channelTestState === 'error'   ? 'text-error-neon border-error-neon/20 bg-error-neon/10' :
                                                                           'text-[#5865F2] border-white/5 hover:bg-[#5865F2]/15']">
                                <span :class="['material-symbols-outlined text-base leading-none', channelTestState === 'loading' ? 'animate-spin' : '']">
                                    {{ channelTestState === 'loading' ? 'sync' : channelTestState === 'success' ? 'check_circle' : channelTestState === 'error' ? 'error' : 'send' }}
                                </span>
                                <span>{{ __('Test') }}</span>
                            </button>
                        </div>
                        <p v-if="noChannelsError()" class="text-[9px] text-error-neon font-medium uppercase tracking-wider flex items-center gap-1">
                            <span class="material-symbols-outlined text-[11px]">error</span>
                            {{ __('Bot has no access or no text channels found in this server.') }}
                        </p>
                        <p v-else class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Raid announcements and RSVP posts will appear here.') }}</p>
                    </div>

                    <!-- Roles to Mention -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Roles to Mention') }}</label>
                            <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('ping_role_ids').cls, fieldIcon('ping_role_ids').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('ping_role_ids').icon }}</span>
                        </div>
                        <template v-if="selectedGuildId">
                            <!-- Add role dropdown -->
                            <SearchableSelect
                                :model-value="''"
                                @update:model-value="(val) => { if (val && !selectedRoleIds.includes(val)) selectedRoleIds = [...selectedRoleIds, val]; }"
                                :options="roles.filter(r => !selectedRoleIds.includes(r.id))"
                                icon="groups"
                                prefix="@ "
                                :placeholder="rolesLoading ? __('Loading roles...') : (selectedRoleIds.length ? __('Add another role...') : __('No mention'))"
                                :search-placeholder="__('Search role...')"
                                :empty-text="__('No more roles.')"
                                :loading="rolesLoading"
                                :disabled="!roles.length && !rolesLoading"
                                accent-color="#a78bfa"
                            />
                            <!-- Selected roles chips below -->
                            <div v-if="selectedRoleIds.length" class="flex items-center gap-1.5 mt-2">
                                <span
                                    v-for="roleId in selectedRoleIds.slice(0, maxVisibleRoles)"
                                    :key="roleId"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#a78bfa]/15 border border-[#a78bfa]/30 text-[9px] font-bold text-[#a78bfa] uppercase tracking-widest shrink-0"
                                >
                                    @ {{ roles.find(r => r.id === roleId)?.name || roleId }}
                                    <button
                                        type="button"
                                        @click="selectedRoleIds = selectedRoleIds.filter(id => id !== roleId)"
                                        class="ml-0.5 hover:text-white transition-colors"
                                    >
                                        <span class="material-symbols-outlined text-[10px]">close</span>
                                    </button>
                                </span>
                                <button
                                    v-if="selectedRoleIds.length > maxVisibleRoles"
                                    type="button"
                                    @click="showRolesModal = true"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-white/5 border border-white/10 text-[9px] font-bold text-on-surface-variant uppercase tracking-widest hover:bg-white/10 hover:text-white transition-colors shrink-0"
                                >
                                    +{{ selectedRoleIds.length - maxVisibleRoles }}
                                </button>
                            </div>
                        </template>
                        <div v-else class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                            {{ __('Select Server first...') }}
                        </div>
                        <p v-if="!selectedRoleIds.length" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('These roles will be pinged when a raid announcement is posted.') }}</p>
                    </div>
                </div>

                <!-- Channel test success banner -->
                <div v-if="channelTestMessageId"
                     class="flex items-center gap-3 p-4 mt-4 rounded-lg bg-success-neon/5 border border-success-neon/20">
                    <span class="material-symbols-outlined text-success-neon text-lg flex-shrink-0">check_circle</span>
                    <p class="text-[10px] text-on-surface-variant font-medium flex-1">
                        {{ __('Test message sent! Check your Discord channel.') }}
                    </p>
                    <button
                        type="button"
                        :disabled="deletingChannelMsg"
                        @click="deleteChannelTestMessage"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest border text-error-neon border-error-neon/30 bg-error-neon/5 hover:bg-error-neon/15 transition-all disabled:opacity-50 flex-shrink-0">
                        <span :class="['material-symbols-outlined text-sm', deletingChannelMsg ? 'animate-spin' : '']">
                            {{ deletingChannelMsg ? 'sync' : 'delete' }}
                        </span>
                        {{ __('Delete message') }}
                    </button>
                </div>

                <!-- Channel test error banner -->
                <div v-if="channelTestState === 'error' && channelTestError"
                     class="flex items-center gap-3 p-4 mt-4 rounded-lg bg-error-neon/5 border border-error-neon/20">
                    <span class="material-symbols-outlined text-error-neon text-lg flex-shrink-0">error</span>
                    <div class="flex-1">
                        <p class="text-[10px] font-headline font-bold text-error-neon uppercase tracking-widest">{{ __('Bot cannot post to this channel') }}</p>
                        <p class="text-[10px] text-on-surface-variant font-medium mt-1">{{ channelTestError }}</p>
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
                        <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('webhook_muted').cls, fieldIcon('webhook_muted').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('webhook_muted').icon }}</span>
                    </label>
                </div>

                <!-- Body — dimmed when muted -->
                <div :class="['space-y-3 transition-opacity duration-200', notifMuted ? 'opacity-40 pointer-events-none select-none' : '']">

                    <!-- ── Delivery method switch ── -->
                    <div class="flex items-center gap-1 p-1 bg-surface-container-highest rounded-lg w-full md:w-[calc(50%-0.5rem)]">
                        <button type="button"
                                @click="notifMethod = 'channel'"
                                :class="['flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-md font-headline text-[10px] font-bold uppercase tracking-widest transition-all',
                                         notifMethod === 'channel'
                                           ? 'bg-[#5865F2]/20 text-[#5865F2] border border-[#5865F2]/30 shadow-sm'
                                           : 'text-on-surface-variant hover:text-white border border-transparent']">
                            <span class="material-symbols-outlined text-sm">smart_toy</span>
                            {{ __('Bot Channel') }}
                        </button>
                        <button type="button"
                                @click="notifMethod = 'webhook'"
                                :class="['flex-1 flex items-center justify-center gap-2 px-4 py-2 rounded-md font-headline text-[10px] font-bold uppercase tracking-widest transition-all',
                                         notifMethod === 'webhook'
                                           ? 'bg-[#5865F2]/20 text-[#5865F2] border border-[#5865F2]/30 shadow-sm'
                                           : 'text-on-surface-variant hover:text-white border border-transparent']">
                            <span class="material-symbols-outlined text-sm">link</span>
                            {{ __('Webhook') }}
                        </button>
                        <span :class="['material-symbols-outlined text-sm w-5 text-center ml-1 transition-opacity', fieldIcon('notification_method').cls, fieldIcon('notification_method').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('notification_method').icon }}</span>
                    </div>

                    <!-- ── Channel mode ── -->
                    <template v-if="notifMethod === 'channel'">
                        <div>
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Notification Channel') }}</label>
                                    <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('notification_channel_id').cls, fieldIcon('notification_channel_id').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('notification_channel_id').icon }}</span>
                                </div>
                                <div :class="['relative', selectedNotifChannelId ? 'notif-channel-test-group' : '']">
                                    <SearchableSelect
                                        v-if="selectedGuildId"
                                        v-model="selectedNotifChannelId"
                                        :options="channels"
                                        input-name="notification_channel_id"
                                        icon="chat"
                                        prefix="# "
                                        :placeholder="channelsLoading ? __('Loading channels...') : __('Select a channel...')"
                                        :search-placeholder="__('Search channel...')"
                                        :empty-text="__('No channels found.')"
                                        :loading="channelsLoading"
                                        :disabled="noChannelsError()"
                                        accent-color="#5865F2"
                                        drop-up
                                    />
                                    <div v-else class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                                        {{ __('Select Server first...') }}
                                    </div>
                                    <button
                                        v-if="selectedNotifChannelId"
                                        type="button"
                                        :disabled="notifChannelTestState === 'loading'"
                                        @click="testNotificationChannel"
                                        :class="['absolute inset-y-0 right-0 px-4 flex items-center gap-1.5 rounded-r-lg font-headline text-[10px] font-bold uppercase tracking-widest border-l transition-all disabled:opacity-40 disabled:cursor-not-allowed z-10',
                                                 notifChannelTestState === 'success' ? 'text-success-neon border-success-neon/20 bg-success-neon/10' :
                                                 notifChannelTestState === 'error'   ? 'text-error-neon border-error-neon/20 bg-error-neon/10' :
                                                                                       'text-[#5865F2] border-white/5 hover:bg-[#5865F2]/15']">
                                        <span :class="['material-symbols-outlined text-base leading-none', notifChannelTestState === 'loading' ? 'animate-spin' : '']">
                                            {{ notifChannelTestState === 'loading' ? 'sync' : notifChannelTestState === 'success' ? 'check_circle' : notifChannelTestState === 'error' ? 'error' : 'send' }}
                                        </span>
                                        <span>{{ __('Test') }}</span>
                                    </button>
                                </div>
                                <p v-if="!selectedGuildId" class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">
                                    {{ __('Select a server above to see available channels.') }}
                                </p>
                                <p v-else-if="noChannelsError()" class="text-[9px] text-error-neon font-medium uppercase tracking-wider flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[11px]">error</span>
                                    {{ __('Bot has no access or no text channels found in this server.') }}
                                </p>
                                <p v-else class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">
                                    {{ __('Service notifications will be posted to this channel via bot.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Notification channel test success banner -->
                        <div v-if="notifChannelTestMessageId"
                             class="flex items-center gap-3 p-4 rounded-lg bg-success-neon/5 border border-success-neon/20">
                            <span class="material-symbols-outlined text-success-neon text-lg flex-shrink-0">check_circle</span>
                            <p class="text-[10px] text-on-surface-variant font-medium flex-1">
                                {{ __('Test message sent! Check your Discord channel.') }}
                            </p>
                            <button
                                type="button"
                                :disabled="deletingNotifChannelMsg"
                                @click="deleteNotifChannelTestMessage"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest border text-error-neon border-error-neon/30 bg-error-neon/5 hover:bg-error-neon/15 transition-all disabled:opacity-50 flex-shrink-0">
                                <span :class="['material-symbols-outlined text-sm', deletingNotifChannelMsg ? 'animate-spin' : '']">
                                    {{ deletingNotifChannelMsg ? 'sync' : 'delete' }}
                                </span>
                                {{ __('Delete message') }}
                            </button>
                        </div>

                        <!-- Notification channel test error banner -->
                        <div v-if="notifChannelTestState === 'error' && notifChannelTestError"
                             class="flex items-center gap-3 p-4 rounded-lg bg-error-neon/5 border border-error-neon/20">
                            <span class="material-symbols-outlined text-error-neon text-lg flex-shrink-0">error</span>
                            <div class="flex-1">
                                <p class="text-[10px] font-headline font-bold text-error-neon uppercase tracking-widest">{{ __('Bot cannot post to this channel') }}</p>
                                <p class="text-[10px] text-on-surface-variant font-medium mt-1">{{ notifChannelTestError }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- ── Webhook mode ── -->
                    <template v-else>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:items-stretch">
                            <!-- Left: webhook URL input with integrated test button -->
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Webhook URL') }}</label>
                                    <span :class="['material-symbols-outlined text-sm w-5 text-center transition-opacity', fieldIcon('discord_webhook_url').cls, fieldIcon('discord_webhook_url').visible ? 'opacity-100' : 'opacity-0']">{{ fieldIcon('discord_webhook_url').icon }}</span>
                                </div>

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

                                <div class="flex-1">
                                    <div v-if="resolvedChannel"
                                         class="flex items-center gap-3 h-full min-h-[48px] px-4 py-2 bg-surface-container-highest border border-[#5865F2]/20 rounded-lg">
                                        <span class="material-symbols-outlined text-[#5865F2] text-lg flex-shrink-0">tag</span>
                                        <p class="font-headline text-sm font-bold text-white truncate"># {{ resolvedChannel.channel_name }}</p>
                                    </div>
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
                    </template>

                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* When test button is overlaid, adjust the SearchableSelect trigger */
.channel-test-group :deep(> div > div:first-child),
.notif-channel-test-group :deep(> div > div:first-child) {
    padding-right: 88px;
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
/* Shift the clear/chevron icons left so they don't overlap the test button */
.channel-test-group :deep(> div > div:first-child > span:last-of-type),
.notif-channel-test-group :deep(> div > div:first-child > span:last-of-type) {
    right: 96px;
}
</style>
