<script setup>
import { ref, computed } from 'vue';
import GlassModal from '../UI/GlassModal.vue';
import TimePickerCarousel from '../UI/TimePickerCarousel.vue';
import TimezoneSelector from '../UI/TimezoneSelector.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    event: { type: Object, required: true },
    csrfToken: { type: String, required: true },
    routes: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const selectedTimezone = ref(props.event.timezone || props.event.static?.timezone || 'UTC');
const startTime = ref(props.event.start_time_formatted || '20:00');
const endTime = ref(props.event.end_time_formatted || '23:00');
const eventDescription = ref(props.event.description || '');
const difficulty = ref(props.event.difficulty || 'mythic');

const difficulties = [
    { value: 'mythic', label: 'Mythic', color: 'text-orange-400', bg: 'bg-orange-400/10', border: 'border-orange-400/30' },
    { value: 'heroic', label: 'Heroic', color: 'text-purple-400', bg: 'bg-purple-400/10', border: 'border-purple-400/30' },
    { value: 'normal', label: 'Normal', color: 'text-green-400', bg: 'bg-green-400/10', border: 'border-green-400/30' },
];

const startPickerRef = ref(null);
const endPickerRef = ref(null);

const isOvernight = computed(() => {
    if (!startTime.value || !endTime.value) return false;
    const [sh, sm] = startTime.value.split(':').map(Number);
    const [eh, em] = endTime.value.split(':').map(Number);
    return eh < sh || (eh === sh && em < sm);
});
</script>

<template>
    <GlassModal :show="show" @close="emit('close')">
        <!-- Modal header -->
        <div class="px-6 py-4 border-b border-white/5 bg-gradient-to-r from-surface-container-high to-surface-container flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-fuchsia-400/20 flex items-center justify-center text-fuchsia-400">
                    <span class="material-symbols-outlined text-[18px]">edit_calendar</span>
                </div>
                <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest">{{ __('Edit Raid Event') }}</h3>
            </div>
            <button
                @click="emit('close')"
                class="text-on-surface-variant hover:text-white transition-colors p-1 hover:bg-white/5 rounded-md"
            >
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <!-- Form body -->
        <form :action="routes.update" method="POST" class="p-6 space-y-5">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PATCH">
            <input type="hidden" name="static_id" :value="event.static_id">
            <input type="hidden" name="date" :value="event.start_time_date">

            <!-- Difficulty -->
            <div class="space-y-1.5">
                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Difficulty') }}</label>
                <input type="hidden" name="difficulty" :value="difficulty">
                <div class="flex gap-2">
                    <button
                        v-for="d in difficulties" :key="d.value"
                        type="button"
                        @click="difficulty = d.value"
                        class="flex-1 px-3 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest border transition-all text-center"
                        :class="difficulty === d.value
                            ? `${d.bg} ${d.border} ${d.color}`
                            : 'bg-white/5 border-white/10 text-on-surface-variant hover:text-white'"
                    >{{ d.label }}</button>
                </div>
            </div>

            <!-- Timezone -->
            <div class="space-y-1.5">
                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Timezone') }}</label>
                <TimezoneSelector v-model="selectedTimezone" input-name="timezone" />
            </div>

            <!-- Start / End Time -->
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Start Time') }}</label>
                    <TimePickerCarousel
                        ref="startPickerRef"
                        v-model="startTime"
                        input-name="start_time"
                        icon="schedule"
                        @open="endPickerRef?.close()"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('End Time') }}</label>
                    <TimePickerCarousel
                        ref="endPickerRef"
                        v-model="endTime"
                        input-name="end_time"
                        icon="update"
                        @open="startPickerRef?.close()"
                    />
                    <div
                        class="mt-2 flex items-center gap-1.5 text-amber-400 transition-opacity duration-200"
                        :class="isOvernight ? 'opacity-100' : 'opacity-0 select-none pointer-events-none'"
                    >
                        <span class="material-symbols-outlined text-sm">event_repeat</span>
                        <span class="text-[9px] font-black uppercase tracking-widest">{{ __('Ends on the next day') }}</span>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="space-y-1.5">
                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Description') }}</label>
                <textarea
                    name="description"
                    rows="3"
                    v-model="eventDescription"
                    class="w-full bg-surface-container-highest border border-white/5 rounded-lg px-3 py-2.5 text-sm text-white focus:ring-1 focus:ring-fuchsia-400 outline-none resize-none"
                ></textarea>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-2">
                <button
                    type="button"
                    @click="emit('close')"
                    class="px-6 py-2.5 rounded-lg font-headline text-[10px] font-black uppercase tracking-widest text-on-surface-variant hover:text-white transition-colors"
                >{{ __('Cancel') }}</button>
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-fuchsia-400 text-black rounded-lg font-headline text-[10px] font-black uppercase tracking-widest hover:brightness-110 transition-all"
                >{{ __('Save Changes') }}</button>
            </div>
        </form>
    </GlassModal>
</template>
