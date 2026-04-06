<script setup>
import { ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SettingsTabs from './SettingsTabs.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import TimezoneSelector from '@/Components/UI/TimezoneSelector.vue';
const { __ } = useTranslation();

const props = defineProps({
    scheduleData:   { type: Object, required: true },
    updateUrl:      { type: String, required: true },
    scheduleTabUrl: { type: String, required: true },
    discordTabUrl:  { type: String, required: true },
    logsTabUrl:     { type: String, required: true },
    successMessage: { type: String, default: '' },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const selectedTimezone = ref(props.scheduleData.timezone || 'Europe/Paris');

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

const postNextAfterRaid = ref(!!props.scheduleData.automation_settings?.post_next_after_raid);
const reminderHoursBefore = ref(props.scheduleData.automation_settings?.reminder_hours_before ?? '');

const onPostNextChanged = () => {
    if (postNextAfterRaid.value) {
        reminderHoursBefore.value = '';
    }
};

const onReminderHoursChanged = () => {
    if (reminderHoursBefore.value) {
        postNextAfterRaid.value = false;
    }
};

const validateAndSubmit = () => {};

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

        <SettingsTabs :schedule-url="scheduleTabUrl" :discord-url="discordTabUrl" :logs-url="logsTabUrl" active-tab="schedule" />

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

                <!-- Automation Rules -->
                <div class="space-y-4 mt-8 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-secondary-neon uppercase tracking-[0.2em]">{{ __('Automation Rules') }}</label>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Auto-Post Next Raid') }}</label>
                            <label class="flex items-center gap-4 w-full px-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg cursor-pointer hover:bg-white/5 transition-colors group">
                                <input type="checkbox" name="automation_settings[post_next_after_raid]" value="1"
                                       v-model="postNextAfterRaid"
                                       @change="onPostNextChanged"
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
                                       v-model="reminderHoursBefore"
                                       @input="onReminderHoursChanged"
                                       placeholder="e.g. 24"
                                       class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-secondary-neon focus:border-transparent transition-all outline-none appearance-none [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">{{ __('Automatically post announcement X hours before start if missing.') }}</p>
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
