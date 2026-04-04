<script setup>
import { ref, computed, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import SearchableSelect from '@/Components/UI/SearchableSelect.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import TimezoneSelector from '@/Components/UI/TimezoneSelector.vue';
const { __ } = useTranslation();

const props = defineProps({
    scheduleData:    { type: Object, required: true },
    botGuilds:       { type: Array, default: () => [] },
    discordChannels: { type: Array, default: () => [] },
    discordRoles:    { type: Array, default: () => [] },
    discordGuildId:  { type: String, default: '' },
    updateUrl:       { type: String, required: true },
    discordTestUrl:  { type: String, required: true },
    discordInviteUrl:{ type: String, required: true },
    scheduleTabUrl:  { type: String, required: true },
    logsTabUrl:      { type: String, required: true },
    successMessage:  { type: String, default: '' },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const selectedTimezone = ref(props.scheduleData.timezone || 'Europe/Paris');

// --- Reactive Discord state ---
const selectedGuildId   = ref(props.discordGuildId);
const selectedChannelId = ref(props.scheduleData.discord_channel_id ?? '');
const selectedRoleId    = ref(props.scheduleData.automation_settings?.ping_role_id ?? '');
const channels          = ref([...props.discordChannels]);
const roles             = ref([...props.discordRoles]);
const channelsLoading   = ref(false);
const rolesLoading      = ref(false);
// tracks which guild was last fetched — lets us show errors only after an actual attempt
const fetchedGuildId    = ref(props.discordGuildId);

const channelError = computed(
    () => selectedGuildId.value
       && !channelsLoading.value
       && channels.value.length === 0
       && fetchedGuildId.value === selectedGuildId.value,
);

watch(selectedGuildId, async (newGuildId) => {
    selectedChannelId.value = '';
    selectedRoleId.value    = '';
    channels.value          = [];
    roles.value             = [];
    fetchedGuildId.value    = '';

    if (!newGuildId) return;

    channelsLoading.value = true;
    rolesLoading.value    = true;

    const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };

    const [chRes, roRes] = await Promise.allSettled([
        fetch(`/api/discord/guilds/${newGuildId}/channels`, { headers }),
        fetch(`/api/discord/guilds/${newGuildId}/roles`,    { headers }),
    ]);

    channels.value = (chRes.status === 'fulfilled' && chRes.value.ok)
        ? await chRes.value.json() : [];
    roles.value    = (roRes.status === 'fulfilled' && roRes.value.ok)
        ? await roRes.value.json() : [];

    channelsLoading.value = false;
    rolesLoading.value    = false;
    fetchedGuildId.value  = newGuildId;
});

// --- Toast ---
const toastShow    = ref(!!props.successMessage);
const toastMessage = ref(props.successMessage);
const toastIsError = ref(false);
let toastTimer = null;

if (props.successMessage) {
    setTimeout(() => { toastShow.value = false; }, 5000);
}

const showToast = (message, error = false, duration = 5000) => {
    clearTimeout(toastTimer);
    toastMessage.value = message;
    toastIsError.value  = error;
    toastShow.value     = true;
    toastTimer = setTimeout(() => { toastShow.value = false; }, duration);
};

// --- Form validation ---
const validateAndSubmit = (e) => {
    if (selectedGuildId.value && !selectedChannelId.value && !channelsLoading.value) {
        e.preventDefault();
        showToast(__('Please select an announcement channel for the chosen server.'), true);
    }
};

const days = {
    mon: 'Monday',
    tue: 'Tuesday',
    wed: 'Wednesday',
    thu: 'Thursday',
    fri: 'Friday',
    sat: 'Saturday',
    sun: 'Sunday',
};

// Discord webhook test state
const webhookState = ref('idle'); // idle | loading | success | error

async function testDiscordWebhook() {
    webhookState.value = 'loading';
    try {
        const response = await fetch(props.discordTestUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await response.json();
        webhookState.value = data.success ? 'success' : 'error';
    } catch {
        webhookState.value = 'error';
    }
    setTimeout(() => { webhookState.value = 'idle'; }, 3000);
}

const webhookButtonClass = computed(() => {
    const base = 'px-6 py-3 rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap border';
    if (webhookState.value === 'success') return base + ' text-success-neon border-success-neon/30 bg-success-neon/10';
    if (webhookState.value === 'error')   return base + ' text-error-neon border-error-neon/30 bg-error-neon/10';
    return base + ' text-[#5865F2] border-[#5865F2]/30 bg-[#5865F2]/10 hover:bg-[#5865F2]/20';
});
</script>

<template>
    <ToastNotification
        :show="toastShow"
        :message="toastMessage"
        :icon="toastIsError ? 'error' : 'check_circle'"
        :icon-class="toastIsError ? 'text-error-neon' : 'text-success-neon'"
    />

    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">{{ __('Static Settings') }}</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ scheduleData.static_name }}</p>
        </div>

        <SettingsTabs :schedule-url="scheduleTabUrl" :logs-url="logsTabUrl" active-tab="schedule" />

        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
            <form :action="updateUrl" method="POST" class="space-y-8" @submit="validateAndSubmit">
                <input type="hidden" name="_token" :value="csrfToken">

                <!-- Raid Days -->
                <div class="space-y-4">
                    <label class="block font-headline text-xs font-bold text-primary uppercase tracking-[0.2em]">{{ __('Raid Days') }}</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label v-for="(label, key) in days" :key="key"
                               class="flex items-center gap-3 p-4 rounded-lg bg-surface-container-highest border border-white/5 cursor-pointer hover:bg-white/5 transition-colors group">
                            <input type="checkbox" name="raid_days[]" :value="key"
                                :checked="scheduleData.raid_days?.includes(key)"
                                class="w-4 h-4 rounded border-outline-variant bg-black/40 text-primary focus:ring-primary focus:ring-offset-0 focus:ring-offset-transparent">
                            <span class="font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant group-hover:text-white transition-colors">{{ __(label) }}</span>
                        </label>
                    </div>
                </div>

                <!-- Schedule Parameters -->
                <div class="space-y-4">
                    <label class="block font-headline text-xs font-bold text-primary uppercase tracking-[0.2em]">{{ __('Schedule Parameters') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Start Time -->
                        <div class="space-y-2">
                            <label for="raid_start_time" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Start Time') }}</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors text-lg">schedule</span>
                                </span>
                                <input type="time" name="raid_start_time" id="raid_start_time"
                                    :value="scheduleData.raid_start_time"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                            </div>
                        </div>

                        <!-- End Time -->
                        <div class="space-y-2">
                            <label for="raid_end_time" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('End Time') }}</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors text-lg">timer_off</span>
                                </span>
                                <input type="time" name="raid_end_time" id="raid_end_time"
                                    :value="scheduleData.raid_end_time"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Timezone') }}</label>
                            <TimezoneSelector v-model="selectedTimezone" input-name="timezone" />
                        </div>
                    </div>
                    <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Times should be set in the selected timezone.') }}</p>
                </div>

                <!-- Discord Integration -->
                <div class="space-y-4 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-[#5865F2] uppercase tracking-[0.2em]">{{ __('Discord Integration') }}</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Discord Server (Guild) -->
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Discord Server (Guild)') }}</label>
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
                            <input v-else type="text" name="discord_guild_id"
                                :value="scheduleData.discord_guild_id"
                                placeholder="e.g. 123456789012345678"
                                class="block w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none">
                            <p v-if="!botGuilds.length" class="text-[9px] text-error-neon font-medium uppercase tracking-wider mt-1">
                                <span class="material-symbols-outlined text-[10px] align-middle">warning</span>
                                {{ __('Bot is not in any servers or token is missing.') }}
                            </p>
                            <p v-else class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Select the server where your bot is present.') }}</p>

                            <!-- Invite Bot Button -->
                            <div class="pt-2">
                                <a :href="discordInviteUrl"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="flex w-full justify-center items-center gap-2 py-3 bg-surface-container-highest border border-white/5 hover:border-[#5865F2]/50 hover:bg-[#5865F2]/10 text-on-surface-variant hover:text-[#5865F2] rounded-lg font-headline text-[10px] font-bold uppercase tracking-widest transition-all">
                                    <span class="material-symbols-outlined text-sm">smart_toy</span>
                                    {{ __('Invite Bot to Server') }}
                                </a>
                            </div>
                        </div>

                        <!-- Announcement Channel -->
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Announcement Channel') }}</label>
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
                                :disabled="channelError"
                                accent-color="#5865F2"
                            />
                            <template v-else>
                                <div class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                                    {{ __('Select Server first...') }}
                                </div>
                                <input type="hidden" name="discord_channel_id" value="">
                            </template>
                            <!-- Error: server was fetched but returned no text channels -->
                            <p v-if="channelError" class="text-[9px] text-error-neon font-medium uppercase tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[11px]">error</span>
                                {{ __('Bot has no access or no text channels found in this server.') }}
                            </p>
                            <p v-else class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('The Discord channel where raid announcements will be posted.') }}</p>
                        </div>

                        <!-- Webhook URL (Global, readonly) -->
                        <div class="absolute invisible w-0 h-0 overflow-hidden pointer-events-none -z-50">
                            <label for="discord_webhook_url" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Discord Webhook URL (Global)') }}</label>
                            <div class="flex gap-4">
                                <div class="relative group flex-1">
                                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#5865F2] transition-colors text-lg">link</span>
                                    </span>
                                    <input type="text" id="discord_webhook_url"
                                           :value="scheduleData.discord_webhook_url"
                                           :placeholder="__('Configure in .env')"
                                           readonly
                                           class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/60 tracking-widest outline-none cursor-not-allowed">
                                </div>
                                <button type="button" @click="testDiscordWebhook"
                                        :disabled="webhookState === 'loading'"
                                        :class="webhookButtonClass">
                                    <span v-if="webhookState === 'loading'" class="material-symbols-outlined text-lg animate-spin">sync</span>
                                    <span v-else-if="webhookState === 'success'" class="material-symbols-outlined text-lg">check_circle</span>
                                    <span v-else-if="webhookState === 'error'" class="material-symbols-outlined text-lg">error</span>
                                    <span v-else class="material-symbols-outlined text-lg">experimental_ship_it</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Automation Rules -->
                <div class="space-y-4 mt-8 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-secondary-neon uppercase tracking-[0.2em]">{{ __('Automation Rules') }}</label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Auto-Post Next Raid') }}</label>
                            <label class="flex items-center gap-4 w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg cursor-pointer hover:bg-white/5 transition-colors group">
                                <input type="checkbox" name="automation_settings[post_next_after_raid]" value="1"
                                       :checked="scheduleData.automation_settings?.post_next_after_raid"
                                       class="w-5 h-5 rounded border-white/10 bg-black/40 text-secondary-neon focus:ring-secondary-neon focus:ring-offset-0 focus:ring-offset-transparent transition-all cursor-pointer">
                                <span class="font-headline text-sm font-bold text-white tracking-widest uppercase group-hover:text-secondary-neon transition-colors">{{ __('Enable Auto-Post') }}</span>
                            </label>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Post the next scheduled raid immediately after current one ends.') }}</p>
                        </div>

                        <div class="space-y-2">
                            <label for="reminder_hours_before" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Pre-Raid Announcement (Hours)') }}</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-secondary-neon transition-colors text-lg">notifications_active</span>
                                </span>
                                <input type="number" name="automation_settings[reminder_hours_before]" id="reminder_hours_before"
                                       :value="scheduleData.automation_settings?.reminder_hours_before ?? ''"
                                       placeholder="e.g. 24"
                                       class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-secondary-neon focus:border-transparent transition-all outline-none appearance-none [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Automatically post announcement X hours before start if missing.') }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Role to Mention') }}</label>
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
                            <template v-else>
                                <div class="w-full px-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                                    {{ __('Select Server first...') }}
                                </div>
                                <input type="hidden" name="automation_settings[ping_role_id]" value="">
                            </template>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('The Discord role to mention in the announcement (e.g. @Raiders).') }}</p>
                        </div>

                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-4 border-t border-white/5">
                    <button type="submit" class="bg-primary text-on-primary px-8 py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">save</span>
                        {{ __('Save Configuration') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
