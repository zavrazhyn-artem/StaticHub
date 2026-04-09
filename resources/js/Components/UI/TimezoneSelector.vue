<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true },
    inputName: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const TIMEZONE_ALIASES = {
    'Europe/Kiev': 'Europe/Kyiv',
    'Asia/Calcutta': 'Asia/Kolkata',
    'Asia/Saigon': 'Asia/Ho_Chi_Minh',
    'Asia/Rangoon': 'Asia/Yangon',
    'Pacific/Ponape': 'Pacific/Pohnpei',
    'Pacific/Truk': 'Pacific/Chuuk',
    'America/Buenos_Aires': 'America/Argentina/Buenos_Aires',
};

const rawTimezones = Intl.supportedValuesOf ? Intl.supportedValuesOf('timeZone') : [
    'UTC', 'Europe/Kyiv', 'Europe/Warsaw', 'Europe/London', 'Europe/Berlin', 'Europe/Paris',
    'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo', 'Asia/Dubai',
];

const allTimezones = [...new Set(rawTimezones.map(tz => TIMEZONE_ALIASES[tz] || tz))].sort();

onMounted(() => {
    const canonical = TIMEZONE_ALIASES[props.modelValue];
    if (canonical) emit('update:modelValue', canonical);
});

const timezoneSearch = ref('');
const showDropdown = ref(false);
const searchInputRef = ref(null);

const filteredTimezones = computed(() => {
    if (!timezoneSearch.value) return allTimezones;
    const lower = timezoneSearch.value.toLowerCase();
    return allTimezones.filter(tz => tz.toLowerCase().includes(lower));
});

const toggle = () => {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) {
        timezoneSearch.value = '';
        nextTick(() => {
            if (searchInputRef.value) searchInputRef.value.focus();
        });
    }
};

const select = (tz) => {
    emit('update:modelValue', tz);
    showDropdown.value = false;
    timezoneSearch.value = '';
};

const close = () => { showDropdown.value = false; };

defineExpose({ close });
</script>

<template>
    <div class="relative">
        <!-- Backdrop -->
        <div v-if="showDropdown" @click="close" class="fixed inset-0 z-40"></div>

        <!-- Trigger -->
        <div
            @click="toggle"
            class="relative z-50 w-full bg-surface-container-highest border border-white/5 rounded-lg pl-9 pr-3 py-2.5 text-sm text-white hover:border-primary/50 transition-all cursor-pointer flex items-center justify-between"
            :class="{ 'ring-1 ring-primary border-primary': showDropdown }"
        >
            <div class="flex items-center gap-2 overflow-hidden">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant pointer-events-none">public</span>
                <span class="truncate">{{ modelValue }}</span>
            </div>
            <span
                class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform"
                :class="{ 'rotate-180': showDropdown }"
            >expand_more</span>
        </div>

        <!-- Dropdown panel -->
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="showDropdown"
                class="absolute z-[60] left-0 right-0 mt-2 bg-surface-container-high border border-white/10 rounded-xl shadow-2xl overflow-hidden backdrop-blur-xl"
            >
                <!-- Search -->
                <div class="p-2 border-b border-white/5 sticky top-0 bg-surface-container-high z-10">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-[14px] text-on-surface-variant">search</span>
                        <input
                            ref="searchInputRef"
                            type="text"
                            v-model="timezoneSearch"
                            :placeholder="__('Search timezone...')"
                            class="w-full bg-surface-container/50 border border-white/5 rounded-lg pl-8 pr-3 py-1.5 text-xs text-white focus:ring-1 focus:ring-primary outline-none transition-all"
                            @click.stop
                        />
                    </div>
                </div>

                <!-- Options -->
                <div class="max-h-60 overflow-y-auto custom-scrollbar p-1">
                    <div v-if="filteredTimezones.length === 0" class="py-4 text-center text-xs text-on-surface-variant">
                        No timezones found
                    </div>
                    <button
                        v-for="tz in filteredTimezones"
                        :key="tz"
                        type="button"
                        class="w-full text-left px-3 py-2 rounded-md text-xs transition-colors flex items-center justify-between"
                        :class="modelValue === tz ? 'bg-primary/20 text-primary font-bold' : 'text-on-surface-variant hover:bg-white/5 hover:text-white'"
                        @click="select(tz)"
                    >
                        <span class="truncate">{{ tz }}</span>
                        <span v-if="modelValue === tz" class="material-symbols-outlined text-[14px]">check</span>
                    </button>
                </div>
            </div>
        </Transition>

        <input v-if="inputName" type="hidden" :name="inputName" :value="modelValue">
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 2px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.2); }
</style>
