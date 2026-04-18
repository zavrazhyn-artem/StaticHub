<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/themes/dark.css'; // Темна тема з коробки
import { Ukrainian } from 'flatpickr/dist/l10n/uk.js';
import { useTranslation } from '@/composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    staticName:         { type: String, required: true },
    logs:               { type: Array, default: () => [] },
    filterUrl:          { type: String, required: true },
    currentDifficulties:{ type: String, default: '' },
    currentFromDate:    { type: String, default: '' },
    currentToDate:      { type: String, default: '' },
    manualLogUrl:       { type: String, required: true },
    cooldownState:      { type: Object, default: () => ({ on_cooldown: false, remaining_seconds: 0, cooldown_minutes: 60 }) },
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const showModal = ref(false);
const wclUrl = ref('');
const isSubmitting = ref(false);

const filterFormRef = ref(null);

// === COOLDOWN TIMER ===
const remainingSeconds = ref(props.cooldownState.remaining_seconds);
let cooldownInterval = null;

const isOnCooldown = computed(() => remainingSeconds.value > 0);

const cooldownDisplay = computed(() => {
    const mins = Math.floor(remainingSeconds.value / 60);
    const secs = remainingSeconds.value % 60;
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
});

function startCooldownTimer() {
    if (remainingSeconds.value <= 0) return;
    cooldownInterval = setInterval(() => {
        remainingSeconds.value--;
        if (remainingSeconds.value <= 0) {
            clearInterval(cooldownInterval);
            cooldownInterval = null;
        }
    }, 1000);
}

onMounted(() => {
    if (remainingSeconds.value > 0) {
        startCooldownTimer();
    }
});

onBeforeUnmount(() => {
    if (cooldownInterval) clearInterval(cooldownInterval);
});

async function submitManualLog() {
    if (isOnCooldown.value) return;
    isSubmitting.value = true;
    const form = document.getElementById('manual-log-form');
    form.submit();
}

// === 1. ЛОГІКА КАЛЕНДАРЯ ===
const dateInputRef = ref(null);
const selectedDateRange = ref('');
const fromDate = ref(props.currentFromDate);
const toDate = ref(props.currentToDate);

function clearDateFilter() {
    dateInputRef.value._flatpickr.clear();
    fromDate.value = '';
    toDate.value = '';
    nextTick(() => filterFormRef.value.submit());
}

onMounted(() => {
    const defaultDates = [];
    if (props.currentFromDate) defaultDates.push(props.currentFromDate);
    if (props.currentToDate) defaultDates.push(props.currentToDate);

    flatpickr(dateInputRef.value, {
        mode: 'range',
        dateFormat: 'Y-m-d',
        defaultDate: defaultDates,
        locale: Ukrainian,
        onChange: (selectedDates, dateStr) => {
            selectedDateRange.value = dateStr;

            if (selectedDates.length === 2) {
                const adjustDate = (date) => {
                    const offset = date.getTimezoneOffset() * 60000;
                    return new Date(date.getTime() - offset).toISOString().split('T')[0];
                };

                fromDate.value = adjustDate(selectedDates[0]);
                toDate.value = adjustDate(selectedDates[1]);

                nextTick(() => { if (filterFormRef.value) filterFormRef.value.submit(); });
            } else if (selectedDates.length === 0) {
                fromDate.value = '';
                toDate.value = '';
                nextTick(() => { if (filterFormRef.value) filterFormRef.value.submit(); });
            }
        }
    });
});

// === 2. ЛОГІКА МУЛЬТИСЕЛЕКТУ ===
const isDifficultyOpen = ref(false);
const dropdownRef = ref(null);

const initialDifficulties = props.currentDifficulties ? props.currentDifficulties.split(',') : [];
const selectedDifficulties = ref(initialDifficulties);

const difficulties = [
    { value: 'Normal', label: 'Normal', color: 'text-green-400' },
    { value: 'Heroic', label: 'Heroic', color: 'text-purple-400' },
    { value: 'Mythic', label: 'Mythic', color: 'text-orange-500' }
];

const toggleDropdown = () => {
    isDifficultyOpen.value = !isDifficultyOpen.value;
};

const closeDropdown = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        if(isDifficultyOpen.value) {
            isDifficultyOpen.value = false;
            if(filterFormRef.value) {
                setTimeout(() => { filterFormRef.value.submit(); }, 50);
            }
        }
    }
};

onMounted(() => document.addEventListener('click', closeDropdown));
onBeforeUnmount(() => document.removeEventListener('click', closeDropdown));

const displayDifficultyText = computed(() => {
    if (selectedDifficulties.value.length === 0) return __('All Difficulties');
    return selectedDifficulties.value.map(v =>
        difficulties.find(d => d.value === v)?.label
    ).join(', ');
});
</script>

<template>
    <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2 text-indigo-400 text-3xs font-bold uppercase tracking-[0.3em]">
                    <span class="material-symbols-outlined text-lg">terminal</span>
                    {{ __('Mission Intelligence Hub') }}
                </div>
                <h1 class="text-4xl font-black text-white uppercase tracking-tight font-headline leading-tight">{{ __('Tactical Logs') }}</h1>
                <p class="text-on-surface-variant font-medium mt-2 uppercase tracking-widest text-xs flex items-center gap-2">
                    {{ staticName }}
                    <span class="opacity-20">•</span>
                    {{ __('Performance Archives') }}
                </p>
            </div>

            <div class="flex flex-col md:flex-row items-stretch md:items-center gap-4 w-full md:w-auto">

                <form :action="filterUrl" method="GET" class="flex flex-col md:flex-row items-stretch md:items-center gap-4 w-full md:w-auto" ref="filterFormRef">

                    <input type="hidden" name="from_date" :value="fromDate">
                    <input type="hidden" name="to_date" :value="toDate">
                    <input type="hidden" name="difficulties" :value="selectedDifficulties.join(',')">

                    <div class="relative w-full md:w-56 group">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <span class="material-symbols-outlined text-on-surface-variant text-lg group-focus-within:text-indigo-400 transition-colors">calendar_month</span>
                        </span>
                        <input
                            ref="dateInputRef"
                            type="text"
                            :placeholder="__('Dates Filter...')"
                            class="block w-full pl-12 pr-4 py-3 h-[42px] bg-surface-container-highest border border-white/5 rounded-lg text-3xs font-semibold text-white uppercase tracking-wider focus:ring-1 focus:ring-indigo-400/50 focus:border-indigo-400/50 transition-all outline-none cursor-pointer placeholder:text-on-surface-variant/50 placeholder:normal-case"
                        />
                        <button v-if="fromDate && toDate" type="button"
                                @click="clearDateFilter"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-on-surface-variant hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>

                    <div class="relative w-full md:w-48" ref="dropdownRef">
                        <button
                            type="button"
                            @click="toggleDropdown"
                            class="flex items-center justify-between w-full pl-4 pr-3 py-3 h-[42px] bg-surface-container-highest border border-white/5 rounded-lg text-3xs font-semibold uppercase tracking-wider hover:border-white/10 transition-all outline-none"
                            :class="{'border-indigo-400/50 ring-1 ring-indigo-400/50': isDifficultyOpen}"
                        >
                            <span class="truncate pr-2"
                                  :class="{
                                      'text-white': selectedDifficulties.length !== 1,
                                      'text-green-400': selectedDifficulties.length === 1 && selectedDifficulties[0] === 'Normal',
                                      'text-purple-400': selectedDifficulties.length === 1 && selectedDifficulties[0] === 'Heroic',
                                      'text-orange-500': selectedDifficulties.length === 1 && selectedDifficulties[0] === 'Mythic'
                                  }">
                                {{ displayDifficultyText }}
                            </span>
                            <span class="material-symbols-outlined text-on-surface-variant text-lg transition-transform duration-200"
                                  :class="{'rotate-180': isDifficultyOpen}">
                                expand_more
                            </span>
                        </button>

                        <transition
                            enter-active-class="transition duration-100 ease-out"
                            enter-from-class="transform scale-95 opacity-0"
                            enter-to-class="transform scale-100 opacity-100"
                            leave-active-class="transition duration-75 ease-in"
                            leave-from-class="transform scale-100 opacity-100"
                            leave-to-class="transform scale-95 opacity-0"
                        >
                            <div v-if="isDifficultyOpen" class="absolute z-50 w-full mt-2 bg-surface-container-highest border border-white/5 rounded-lg shadow-2xl overflow-hidden backdrop-blur-md">
                                <label v-for="diff in difficulties" :key="diff.value"
                                       class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-white/5 transition-colors">
                                    <input type="checkbox" :value="diff.value" v-model="selectedDifficulties"
                                           class="w-4 h-4 rounded border-white/10 bg-black/40 text-indigo-400 focus:ring-indigo-400 focus:ring-offset-0 transition-all cursor-pointer">
                                    <span class="text-3xs font-semibold uppercase tracking-wider" :class="diff.color">
                                        {{ __(diff.label) }}
                                    </span>
                                </label>
                            </div>
                        </transition>
                    </div>
                </form>

                <button type="button" @click="showModal = true"
                        class="flex items-center justify-center gap-2 bg-indigo-400/10 border border-indigo-400/30 hover:bg-indigo-400 hover:text-black px-6 py-2.5 rounded-lg text-3xs font-bold uppercase tracking-[0.2em] transition-all text-indigo-400 h-[42px] whitespace-nowrap w-full md:w-auto">
                    <span class="material-symbols-outlined text-sm">upload_file</span>
                    {{ __('Process Manual Log') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <template v-if="logs.length">
                <div v-for="log in logs" :key="log.id"
                     class="group relative bg-surface-container-low border border-white/5 rounded-2xl overflow-hidden hover:border-indigo-400/30 transition-all duration-500 hover:shadow-[0_0_40px_-15px_rgba(245,158,11,0.2)]">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-indigo-400/20 to-transparent"></div>

                    <div class="p-6 space-y-6">
                        <div>
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <div class="text-3xs font-bold text-on-surface-variant uppercase tracking-wider opacity-60">
                                        {{ log.date }}
                                    </div>
                                    <span v-if="log.has_ai"
                                          class="flex items-center gap-1.5 px-2.5 py-1 bg-success-neon/10 border border-success-neon/20 rounded-full text-4xs font-bold text-success-neon uppercase tracking-wider">
                                    <span class="w-1 h-1 bg-success-neon rounded-full animate-pulse"></span>
                                    {{ __('Analyzed') }}
                                    </span>
                                    <span v-else
                                          class="flex items-center gap-1.5 px-2.5 py-1 bg-indigo-400/10 border border-indigo-400/20 rounded-full text-4xs font-bold text-indigo-400 uppercase tracking-wider">
                                    <span class="w-1 h-1 bg-indigo-400 rounded-full animate-pulse"></span>
                                    {{ __('Pending AI') }}
                                    </span>
                                </div>
                                <h3 class="text-l font-black text-white uppercase tracking-tight group-hover:text-indigo-400 transition-colors">
                                    {{ log.title ?? __('Manual Log Analysis') }}
                                </h3>
                                <div v-if="log.difficulties && log.difficulties.length" class="flex gap-2 mt-2">
                                     <span v-for="diff in log.difficulties" :key="diff"
                                           class="text-5xs font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded border border-white/10"
                                           :class="{'text-orange-500 border-orange-500/30': diff === 'mythic', 'text-purple-400 border-purple-400/30': diff === 'heroic', 'text-green-400 border-green-400/30': diff === 'normal'}">
                                        {{ diff }}
                                     </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <div class="flex gap-4">
                                <div class="text-center">
                                    <div class="text-3xs font-bold text-white">WCL</div>
                                    <div class="text-5xs font-semibold text-on-surface-variant uppercase tracking-tighter opacity-60">{{ __('Report') }}</div>
                                </div>
                            </div>

                            <a :href="log.url" class="flex items-center gap-2 bg-white/5 hover:bg-indigo-400 hover:text-black px-4 py-2 rounded-lg text-3xs font-bold uppercase tracking-[0.15em] transition-all group/btn">
                                {{ __('Open Files') }}
                                <span class="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        </div>
                    </div>

                    <div class="absolute bottom-0 right-0 w-8 h-8 opacity-5">
                        <div class="absolute bottom-0 right-0 w-full h-[1px] bg-indigo-400"></div>
                        <div class="absolute bottom-0 right-0 h-full w-[1px] bg-indigo-400"></div>
                    </div>
                </div>
            </template>

            <div v-else class="col-span-full py-24 text-center border-2 border-dashed border-white/5 rounded-3xl">
                <span class="material-symbols-outlined text-6xl text-white/10 mb-4">folder_off</span>
                <h3 class="text-xl font-black text-white uppercase tracking-widest">{{ __('No Intelligence Data Found') }}</h3>
                <p class="text-on-surface-variant mt-2 uppercase tracking-wider text-xs">{{ __('Matching reports have not been processed for this static yet.') }}</p>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="showModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
                 @click.self="showModal = false">
                <div class="w-full max-w-lg mx-4">
                    <div class="p-8 bg-surface-container-highest border border-white/5 rounded-2xl shadow-2xl">

                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-indigo-400/10 rounded-xl">
                                <span class="material-symbols-outlined text-indigo-400">upload_file</span>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black text-white uppercase tracking-tight leading-none">{{ __('Manual Log Submission') }}</h2>
                                <p class="text-on-surface-variant font-semibold text-3xs uppercase tracking-wider mt-1">{{ __('Tactical Analysis Pipeline') }}</p>
                            </div>
                        </div>

                        <!-- Cooldown Timer -->
                        <div v-if="isOnCooldown"
                             class="mb-6 p-4 bg-error/10 border border-error/20 rounded-xl">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-error text-xl">timer</span>
                                <div>
                                    <p class="text-3xs font-bold text-error uppercase tracking-wider">{{ __('Cooldown Active') }}</p>
                                    <p class="text-on-surface-variant text-4xs font-semibold uppercase tracking-wider mt-0.5">
                                        {{ __('Next upload available in') }}
                                    </p>
                                </div>
                                <div class="ml-auto font-mono text-2xl font-black text-error tracking-wider">
                                    {{ cooldownDisplay }}
                                </div>
                            </div>
                        </div>

                        <form id="manual-log-form" :action="manualLogUrl" method="POST">
                            <input type="hidden" name="_token" :value="csrfToken">

                            <div class="space-y-6">
                                <div>
                                    <label for="wcl_url" class="block text-indigo-400/60 uppercase tracking-wider text-3xs font-bold mb-2">{{ __('WCL Report URL') }}</label>
                                    <input id="wcl_url" name="wcl_url" type="text" v-model="wclUrl"
                                           :disabled="isOnCooldown"
                                           class="w-full bg-black/20 border border-white/10 p-4 rounded-lg text-white text-sm outline-none focus:border-indigo-400/50 disabled:opacity-40 disabled:cursor-not-allowed"
                                           placeholder="https://www.warcraftlogs.com/reports/..."
                                           required>
                                    <p class="mt-2 text-4xs text-on-surface-variant font-semibold uppercase tracking-wider opacity-40">
                                        Example: https://www.warcraftlogs.com/reports/aBcDeFg123456789
                                    </p>
                                </div>
                            </div>

                            <!-- Info Note -->
                            <div class="mt-4 p-3 bg-white/5 border border-white/5 rounded-lg flex items-start gap-2.5">
                                <span class="material-symbols-outlined text-on-surface-variant text-base mt-0.5 shrink-0">info</span>
                                <p class="text-4xs text-on-surface-variant font-semibold uppercase tracking-wider leading-relaxed">
                                    {{ __('Mythic+ logs and characters not in your roster will be filtered out and will not be processed.') }}
                                </p>
                            </div>

                            <div class="mt-8 flex justify-end gap-3">
                                <button type="button" @click="showModal = false"
                                        class="px-6 py-3 rounded-xl text-3xs font-bold uppercase tracking-wider text-on-surface-variant hover:bg-white/5 transition-all">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit"
                                        :disabled="isOnCooldown"
                                        class="bg-indigo-400 hover:bg-indigo-300 text-black px-8 py-3 rounded-xl text-3xs font-bold uppercase tracking-[0.2em] shadow-lg shadow-indigo-400/20 transition-all active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-indigo-400"
                                        @click.prevent="submitManualLog">
                                    {{ __('Submit for Analysis') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
