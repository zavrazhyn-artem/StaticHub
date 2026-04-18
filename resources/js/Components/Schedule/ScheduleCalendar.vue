<script setup>
import { ref, computed } from 'vue';
import { useTimeFormatter } from '../../composables/useTimeFormatter.js';
import { useTranslation } from '../../composables/useTranslation.js';
import GlassModal from '../UI/GlassModal.vue';
import TimePickerCarousel from '../UI/TimePickerCarousel.vue';
import TimezoneSelector from '../UI/TimezoneSelector.vue';

const { __ } = useTranslation();

const props = defineProps({
    staticId: { type: Number, required: true },
    staticName: { type: String, required: true },
    canManageSchedule: { type: Boolean, default: false },
    defaultRaidTime: { type: String, default: '20:00' },
    defaultRaidEndTime: { type: String, default: '23:00' },
    staticTimezone: { type: String, default: '' },
    currentMonthName: { type: String, required: true },
    prevMonthUrl: { type: String, required: true },
    nextMonthUrl: { type: String, required: true },
    todayUrl: { type: String, required: true },
    grid: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
    csrfToken: { type: String, required: true },
    createEventRoute: { type: String, required: true },
    settingsRoute: { type: String, default: '' },
});

const { formatRange } = useTimeFormatter();

const daysOfWeek = computed(() => [
    __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun'),
]);

// Modal state
const showModal = ref(Object.keys(props.errors).length > 0);
const isEditing = ref(false);
const editingEventId = ref(null);
const updateEventRoute = ref('');

// Form field state
const selectedDate = ref('');
const selectedDateTime = ref(props.defaultRaidTime);
const selectedEndTime = ref(props.defaultRaidEndTime);
const defaultTimezone = props.staticTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const selectedTimezone = ref(defaultTimezone);
const selectedDescription = ref('');
const selectedDifficulty = ref('mythic');

const difficulties = [
    { value: 'mythic', label: 'Mythic', color: 'text-orange-400', bg: 'bg-orange-400/10', border: 'border-orange-400/30' },
    { value: 'heroic', label: 'Heroic', color: 'text-purple-400', bg: 'bg-purple-400/10', border: 'border-purple-400/30' },
    { value: 'normal', label: 'Normal', color: 'text-green-400', bg: 'bg-green-400/10', border: 'border-green-400/30' },
];

// Picker coordination refs
const startPickerRef = ref(null);
const endPickerRef = ref(null);
const timezoneSelectorRef = ref(null);

const isOvernight = computed(() => {
    if (!selectedDateTime.value || !selectedEndTime.value) return false;
    const [sh, sm] = selectedDateTime.value.split(':').map(Number);
    const [eh, em] = selectedEndTime.value.split(':').map(Number);
    return eh < sh || (eh === sh && em < sm);
});

const formatInTimezone = (isoString, timezone) => {
    const pad = (n) => String(n).padStart(2, '0');
    const date = new Date(isoString);
    const parts = new Intl.DateTimeFormat('en-GB', {
        hour: '2-digit', minute: '2-digit', hour12: false, timeZone: timezone,
    }).formatToParts(date);
    const h = parts.find(p => p.type === 'hour')?.value || '00';
    const m = parts.find(p => p.type === 'minute')?.value || '00';
    return `${h}:${m}`;
};

const openCreateModal = (date, defaultTime) => {
    isEditing.value = false;
    editingEventId.value = null;
    updateEventRoute.value = '';
    selectedDate.value = date;
    selectedDateTime.value = defaultTime;
    selectedEndTime.value = props.defaultRaidEndTime;
    selectedDescription.value = '';
    selectedTimezone.value = defaultTimezone;

    // Close any open sub-dropdowns
    startPickerRef.value?.close();
    endPickerRef.value?.close();
    timezoneSelectorRef.value?.close();

    showModal.value = true;
};

const openEditModal = (event, date) => {
    isEditing.value = true;
    editingEventId.value = event.id;
    updateEventRoute.value = event.update_url;
    selectedDate.value = date;
    selectedDescription.value = event.description || '';

    const tz = event.timezone || defaultTimezone;
    selectedTimezone.value = tz;

    selectedDateTime.value = formatInTimezone(event.start_time, tz);
    selectedEndTime.value = event.end_time ? formatInTimezone(event.end_time, tz) : '23:00';

    // Close any open sub-dropdowns
    startPickerRef.value?.close();
    endPickerRef.value?.close();
    timezoneSelectorRef.value?.close();

    showModal.value = true;
};

const closeModal = () => { showModal.value = false; };
</script>

<template>
    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tight font-headline">{{ __('Raid Schedule') }}</h1>
                <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">
                    {{ staticName }} &bull; {{ currentMonthName }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a :href="prevMonthUrl" class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm text-3xs font-semibold uppercase tracking-wider transition-colors flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">chevron_left</span>
                    {{ __('Previous') }}
                </a>
                <a :href="todayUrl" class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm text-3xs font-semibold uppercase tracking-wider transition-colors">
                    {{ __('Today') }}
                </a>
                <a :href="nextMonthUrl" class="bg-surface-container-high text-on-surface-variant hover:text-white px-4 py-2 rounded-sm text-3xs font-semibold uppercase tracking-wider transition-colors flex items-center gap-2">
                    {{ __('Next') }}
                    <span class="material-symbols-outlined text-sm">chevron_right</span>
                </a>
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="bg-white/5 border border-white/5 rounded-xl overflow-hidden shadow-2xl backdrop-blur-sm">
            <!-- Day-of-week header -->
            <div class="grid grid-cols-7 border-b border-white/5 bg-surface-container">
                <div v-for="dayName in daysOfWeek" :key="dayName" class="py-3 text-center">
                    <span class="text-3xs uppercase tracking-wider text-on-surface-variant font-semibold">{{ dayName }}</span>
                </div>
            </div>

            <!-- Cells -->
            <div class="grid grid-cols-7 gap-px bg-white/5">
                <div
                    v-for="(day, index) in grid"
                    :key="index"
                    @click="canManageSchedule && day.events.length === 0 ? openCreateModal(day.formatted_date, defaultRaidTime) : null"
                    class="min-h-[100px] bg-surface-container-lowest p-2 transition-colors relative group flex flex-col"
                    :class="[
                        canManageSchedule && day.events.length === 0 ? 'cursor-pointer hover:bg-white/[0.02]' : '',
                        day.is_today ? 'ring-1 ring-inset ring-fuchsia-400/30' : '',
                    ]"
                >
                    <div class="flex justify-between items-start shrink-0">
                        <span
                            class="font-headline font-bold text-sm"
                            :class="!day.is_current_month ? 'text-white/20' : (day.is_today ? 'text-fuchsia-400' : 'text-on-surface-variant')"
                        >{{ day.day_number }}</span>
                        <div v-if="canManageSchedule && day.events.length === 0" class="opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="material-symbols-outlined text-on-surface-variant hover:text-fuchsia-400 text-lg">add_circle</span>
                        </div>
                    </div>

                    <div class="mt-auto w-full pt-2" @click.stop>
                        <div class="space-y-1.5 max-h-[60px] overflow-y-auto custom-scrollbar">
                            <div v-for="event in day.events" :key="event.id" class="relative group/event">
                                <a
                                    :href="event.show_url"
                                    class="block w-full bg-surface-container-high border border-white/5 rounded-md px-2 py-5 hover:border-fuchsia-400/40 transition-all shadow-md"
                                >
                                    <div class="flex items-center justify-between text-3xs font-semibold tracking-wider uppercase">
                                        <div class="text-fuchsia-400">{{ formatRange(event.start_time, event.end_time) }}</div>
                                        <div class="text-on-surface-variant flex items-center gap-0.5 shrink-0">
                                            <span class="material-symbols-outlined text-xs">group</span>
                                            {{ event.characters_count ?? 0 }}/20
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit event modal -->
        <GlassModal :show="showModal" @close="closeModal">
            <!-- Modal header -->
            <div class="px-6 py-4 border-b border-white/5 bg-gradient-to-r from-surface-container-high to-surface-container flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-fuchsia-400/20 flex items-center justify-center text-fuchsia-400">
                        <span class="material-symbols-outlined text-lg">{{ isEditing ? 'edit_calendar' : 'event_available' }}</span>
                    </div>
                    <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest">
                        {{ isEditing ? __('Edit Raid Event') : __('Plan Raid Event') }}
                    </h3>
                </div>
                <button @click="closeModal" class="text-on-surface-variant hover:text-white transition-colors p-1 hover:bg-white/5 rounded-md">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Form -->
            <form :action="isEditing ? updateEventRoute : createEventRoute" method="POST" class="p-6 space-y-5">
                <input type="hidden" name="_token" :value="csrfToken">
                <input v-if="isEditing" type="hidden" name="_method" value="PATCH">
                <input type="hidden" name="static_id" :value="staticId">
                <input type="hidden" name="date" :value="selectedDate">

                <!-- Validation errors -->
                <div v-if="Object.keys(errors).length > 0" class="p-4 bg-error-dim/20 border border-error-dim/50 rounded-xl">
                    <div class="flex items-center gap-2 mb-2 text-error-dim">
                        <span class="material-symbols-outlined text-sm">error</span>
                        <span class="text-3xs font-bold uppercase tracking-wider">{{ __('Validation Errors') }}</span>
                    </div>
                    <ul class="space-y-1">
                        <li v-for="(messages, field) in errors" :key="field" class="text-3xs text-white/70 font-medium">
                            &bull; {{ messages[0] }}
                        </li>
                    </ul>
                </div>

                <!-- Date display -->
                <div class="space-y-1.5">
                    <label class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Selected Date') }}</label>
                    <div class="flex items-center gap-2 px-3 py-2.5 bg-surface-container-highest border border-white/5 rounded-lg text-white font-headline text-sm font-bold tracking-tight">
                        <span class="material-symbols-outlined text-base text-fuchsia-400">calendar_today</span>
                        {{ selectedDate }}
                    </div>
                </div>

                <!-- Difficulty -->
                <div class="space-y-1.5">
                    <label class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Difficulty') }}</label>
                    <input type="hidden" name="difficulty" :value="selectedDifficulty">
                    <div class="flex gap-2">
                        <button
                            v-for="d in difficulties" :key="d.value"
                            type="button"
                            @click="selectedDifficulty = d.value"
                            class="flex-1 px-3 py-2 rounded-lg text-3xs font-bold uppercase tracking-wider border transition-all text-center"
                            :class="selectedDifficulty === d.value
                                ? `${d.bg} ${d.border} ${d.color}`
                                : 'bg-white/5 border-white/10 text-on-surface-variant hover:text-white'"
                        >{{ d.label }}</button>
                    </div>
                </div>

                <!-- Timezone -->
                <div class="space-y-1.5">
                    <label class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Timezone') }}</label>
                    <TimezoneSelector
                        ref="timezoneSelectorRef"
                        v-model="selectedTimezone"
                        input-name="timezone"
                    />
                </div>

                <!-- Start / End Time -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">
                            {{ __('Start Time') }} <span class="text-error">*</span>
                        </label>
                        <TimePickerCarousel
                            ref="startPickerRef"
                            v-model="selectedDateTime"
                            input-name="start_time"
                            icon="schedule"
                            @open="endPickerRef?.close(); timezoneSelectorRef?.close()"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">
                            {{ __('End Time') }} <span class="text-error">*</span>
                        </label>
                        <TimePickerCarousel
                            ref="endPickerRef"
                            v-model="selectedEndTime"
                            input-name="end_time"
                            icon="update"
                            @open="startPickerRef?.close(); timezoneSelectorRef?.close()"
                        />
                        <div
                            class="mt-2 flex items-center gap-1.5 text-amber-400 transition-opacity duration-200"
                            :class="isOvernight ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
                        >
                            <span class="material-symbols-outlined text-sm">event_repeat</span>
                            <span class="text-4xs font-bold uppercase tracking-wider">{{ __('Ends on the next day') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <label for="description" class="block text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Notes (Optional)') }}</label>
                    <div class="relative">
                        <textarea
                            name="description"
                            id="description"
                            rows="2"
                            :placeholder="__('Any specific requirements?')"
                            v-model="selectedDescription"
                            class="w-full bg-surface-container-highest border border-white/5 rounded-lg pl-9 pr-3 py-2.5 text-sm text-white focus:ring-1 focus:ring-fuchsia-400 focus:border-fuchsia-400 outline-none transition-all placeholder:text-white/20 resize-none"
                        ></textarea>
                        <span class="material-symbols-outlined absolute left-3 top-3 text-base text-on-surface-variant pointer-events-none">notes</span>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-2">
                    <button
                        type="submit"
                        class="w-full bg-fuchsia-400 text-black py-3.5 rounded-lg font-headline text-xs font-black uppercase tracking-[0.2em] hover:brightness-110 hover:shadow-[0_0_15px_rgba(0,255,153,0.3)] active:scale-[0.98] transition-all flex items-center justify-center gap-2"
                    >
                        <span class="material-symbols-outlined text-lg">{{ isEditing ? 'save' : 'add_task' }}</span>
                        {{ isEditing ? __('Update Event') : __('Publish Event') }}
                    </button>
                </div>
            </form>
        </GlassModal>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 2px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
</style>
