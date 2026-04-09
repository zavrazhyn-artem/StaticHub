<script setup>
import { ref, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import TimezoneSelector from '@/Components/UI/TimezoneSelector.vue';
const { __ } = useTranslation();

const props = defineProps({
    scheduleData:   { type: Object, required: true },
    updateUrl:      { type: String, required: true },
    profileTabUrl:  { type: String, required: true },
    scheduleTabUrl: { type: String, required: true },
    discordTabUrl:  { type: String, required: true },
    logsTabUrl:     { type: String, required: true },
    canManage:      { type: Boolean, default: false },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// --- Reactive state ---
const raidDays = ref([...(props.scheduleData.raid_days || [])]);
const raidStartTime = ref(props.scheduleData.raid_start_time || '');
const raidEndTime = ref(props.scheduleData.raid_end_time || '');
const selectedTimezone = ref(props.scheduleData.timezone || 'Europe/Paris');
const postNextAfterRaid = ref(!!props.scheduleData.automation_settings?.post_next_after_raid);
const reminderHoursBefore = ref(props.scheduleData.automation_settings?.reminder_hours_before ?? '');

// --- Toast ---
const toastShow    = ref(false);
const toastMessage = ref('');
const toastIsError = ref(false);
let toastTimer = null;

const showToast = (message, error = false, duration = 3000) => {
    clearTimeout(toastTimer);
    toastMessage.value = message;
    toastIsError.value  = error;
    toastShow.value     = true;
    toastTimer = setTimeout(() => { toastShow.value = false; }, duration);
};

// --- Save logic ---
let saveTimeout = null;
const saving = ref(false);

const save = (payload) => {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(async () => {
        saving.value = true;
        try {
            const res = await fetch(props.updateUrl, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) throw new Error();
            showToast(__('Settings saved'));
        } catch {
            showToast(__('Failed to save settings'), true);
        } finally {
            saving.value = false;
        }
    }, 500);
};

const buildFullPayload = () => ({
    raid_days: raidDays.value,
    raid_start_time: raidStartTime.value || null,
    raid_end_time: raidEndTime.value || null,
    timezone: selectedTimezone.value,
    automation_settings: {
        post_next_after_raid: postNextAfterRaid.value,
        reminder_hours_before: reminderHoursBefore.value || null,
    },
});

// --- Day toggle ---
const toggleDay = (key) => {
    const idx = raidDays.value.indexOf(key);
    if (idx >= 0) {
        raidDays.value.splice(idx, 1);
    } else {
        raidDays.value.push(key);
    }
    save(buildFullPayload());
};

// --- Watchers for auto-save ---
watch(raidStartTime, () => save(buildFullPayload()));
watch(raidEndTime, () => save(buildFullPayload()));
watch(selectedTimezone, () => save(buildFullPayload()));

watch(postNextAfterRaid, (val) => {
    if (val) reminderHoursBefore.value = '';
    save(buildFullPayload());
});

watch(reminderHoursBefore, (val) => {
    if (val) postNextAfterRaid.value = false;
    else save(buildFullPayload());
});

const days = {
    mon: 'Monday',
    tue: 'Tuesday',
    wed: 'Wednesday',
    thu: 'Thursday',
    fri: 'Friday',
    sat: 'Saturday',
    sun: 'Sunday',
};
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

        <SettingsTabs :profile-url="profileTabUrl" :schedule-url="scheduleTabUrl" :discord-url="discordTabUrl" :logs-url="logsTabUrl" active-tab="schedule" :can-manage="canManage" />

        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
            <div class="space-y-8">
                <!-- Raid Days -->
                <div class="space-y-4">
                    <label class="block font-headline text-xs font-bold text-primary uppercase tracking-[0.2em]">{{ __('Raid Days') }}</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label v-for="(label, key) in days" :key="key"
                               class="flex items-center gap-3 p-4 rounded-lg bg-surface-container-highest border border-white/5 cursor-pointer hover:bg-white/5 transition-colors group">
                            <input type="checkbox" :value="key"
                                :checked="raidDays.includes(key)"
                                @change="toggleDay(key)"
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
                                <input type="time" id="raid_start_time"
                                    v-model="raidStartTime"
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
                                <input type="time" id="raid_end_time"
                                    v-model="raidEndTime"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Timezone') }}</label>
                            <TimezoneSelector v-model="selectedTimezone" />
                        </div>
                    </div>
                    <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Times should be set in the selected timezone.') }}</p>
                </div>

                <!-- Automation Rules -->
                <div class="space-y-4 mt-8 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-secondary-neon uppercase tracking-[0.2em]">{{ __('Automation Rules') }}</label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Auto-Post Next Raid') }}</label>
                            <label class="flex items-center gap-4 w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg cursor-pointer hover:bg-white/5 transition-colors group">
                                <input type="checkbox"
                                       v-model="postNextAfterRaid"
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
                                <input type="number" id="reminder_hours_before"
                                       v-model="reminderHoursBefore"
                                       placeholder="e.g. 24"
                                       class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-secondary-neon focus:border-transparent transition-all outline-none appearance-none [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Automatically post announcement X hours before start if missing.') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Saving indicator -->
                <div class="flex items-center gap-2 h-5">
                    <template v-if="saving">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant animate-spin">progress_activity</span>
                        <span class="font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">{{ __('Saving...') }}</span>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
