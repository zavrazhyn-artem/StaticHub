<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: String, required: true }, // "HH:mm"
    inputName: { type: String, required: true },
    icon: { type: String, default: 'schedule' },
});
const emit = defineEmits(['update:modelValue', 'open']);

const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const minutes = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

const showDropdown = ref(false);
const hoursRef = ref(null);
const minutesRef = ref(null);
const tempHour = ref('20');
const tempMinute = ref('00');
let scrollTimeout = null;

const toggle = async () => {
    showDropdown.value = !showDropdown.value;
    if (showDropdown.value) {
        emit('open');
        const [h, m] = (props.modelValue || '20:00').split(':');
        tempHour.value = h || '20';
        tempMinute.value = m || '00';
        await nextTick();
        if (hoursRef.value) hoursRef.value.scrollTop = hours.indexOf(tempHour.value) * 40;
        if (minutesRef.value) minutesRef.value.scrollTop = minutes.indexOf(tempMinute.value) * 40;
    }
};

const close = () => { showDropdown.value = false; };

const handleScroll = (e, type) => {
    const index = Math.round(e.target.scrollTop / 40);
    const arr = type === 'hours' ? hours : minutes;
    const val = arr[Math.max(0, Math.min(index, arr.length - 1))];
    if (type === 'hours') tempHour.value = val;
    else tempMinute.value = val;
    emit('update:modelValue', `${tempHour.value}:${tempMinute.value}`);
    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        e.target.scrollTo({ top: index * 40, behavior: 'smooth' });
    }, 100);
};

const stepScroll = (refEl, direction) => {
    if (!refEl) return;
    const step = 40;
    const newScroll = direction === 'up'
        ? Math.max(0, refEl.scrollTop - step)
        : refEl.scrollTop + step;
    refEl.scrollTo({ top: newScroll, behavior: 'smooth' });
};

defineExpose({ close });
</script>

<template>
    <div class="relative">
        <!-- Backdrop to close dropdown -->
        <div v-if="showDropdown" @click="close" class="fixed inset-0 z-40"></div>

        <!-- Trigger -->
        <div
            @click="toggle"
            class="relative z-50 w-full bg-surface-container-highest border border-white/5 rounded-lg pl-9 pr-3 py-2.5 text-sm text-white hover:border-primary/50 transition-all cursor-pointer flex items-center justify-between"
            :class="{ 'ring-1 ring-primary border-primary': showDropdown }"
        >
            <span>{{ modelValue }}</span>
            <span
                class="material-symbols-outlined text-[16px] text-on-surface-variant transition-transform"
                :class="{ 'rotate-180': showDropdown }"
            >expand_more</span>
        </div>
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[16px] text-on-surface-variant pointer-events-none z-50">{{ icon }}</span>

        <!-- Carousel dropdown -->
        <div
            v-if="showDropdown"
            class="absolute z-50 w-full mt-2 bg-surface-container-highest border border-white/10 rounded-xl shadow-2xl overflow-hidden glassmorphism flex flex-col"
        >
            <div class="flex justify-center items-center h-[120px] relative w-full px-2">
                <!-- Selection highlight -->
                <div class="absolute top-1/2 -translate-y-1/2 left-2 right-2 h-[40px] bg-primary/10 border-y border-primary/20 rounded-md pointer-events-none"></div>

                <!-- Hours column -->
                <div class="h-full w-1/2 relative group/hours">
                    <button
                        type="button"
                        @click.stop="stepScroll(hoursRef, 'up')"
                        class="absolute top-0 w-full z-[60] opacity-0 group-hover/hours:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity"
                    >
                        <span class="material-symbols-outlined text-primary text-base">expand_less</span>
                    </button>
                    <div
                        ref="hoursRef"
                        @scroll="(e) => handleScroll(e, 'hours')"
                        class="h-full overflow-y-auto snap-y snap-mandatory hide-scrollbar"
                    >
                        <div class="h-[40px]"></div>
                        <div
                            v-for="h in hours"
                            :key="h"
                            class="h-[40px] flex items-center justify-center snap-center text-lg font-bold"
                            :class="tempHour === h ? 'text-primary' : 'text-on-surface-variant/40'"
                        >{{ h }}</div>
                        <div class="h-[40px]"></div>
                    </div>
                    <button
                        type="button"
                        @click.stop="stepScroll(hoursRef, 'down')"
                        class="absolute bottom-0 w-full z-[60] opacity-0 group-hover/hours:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity"
                    >
                        <span class="material-symbols-outlined text-primary text-base">expand_more</span>
                    </button>
                </div>

                <div class="text-xl font-bold text-on-surface-variant/50 pb-1">:</div>

                <!-- Minutes column -->
                <div class="h-full w-1/2 relative group/mins">
                    <button
                        type="button"
                        @click.stop="stepScroll(minutesRef, 'up')"
                        class="absolute top-0 w-full z-[60] opacity-0 group-hover/mins:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity"
                    >
                        <span class="material-symbols-outlined text-primary text-base">expand_less</span>
                    </button>
                    <div
                        ref="minutesRef"
                        @scroll="(e) => handleScroll(e, 'minutes')"
                        class="h-full overflow-y-auto snap-y snap-mandatory hide-scrollbar"
                    >
                        <div class="h-[40px]"></div>
                        <div
                            v-for="m in minutes"
                            :key="m"
                            class="h-[40px] flex items-center justify-center snap-center text-lg font-bold"
                            :class="tempMinute === m ? 'text-primary' : 'text-on-surface-variant/40'"
                        >{{ m }}</div>
                        <div class="h-[40px]"></div>
                    </div>
                    <button
                        type="button"
                        @click.stop="stepScroll(minutesRef, 'down')"
                        class="absolute bottom-0 w-full z-[60] opacity-0 group-hover/mins:opacity-100 flex justify-center py-1 bg-surface-container-highest/80 backdrop-blur-sm transition-opacity"
                    >
                        <span class="material-symbols-outlined text-primary text-base">expand_more</span>
                    </button>
                </div>
            </div>

            <!-- Done button -->
            <div
                @click="close"
                class="bg-[#0a0a0a] border-t border-white/10 text-primary text-center py-3 text-xs font-black uppercase tracking-widest cursor-pointer hover:brightness-125 transition-colors"
            >{{ __('Done') }}</div>
        </div>

        <input type="hidden" :name="inputName" :value="modelValue">
    </div>
</template>

<style scoped>
.glassmorphism {
    background: rgba(23, 23, 23, 0.8);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.hide-scrollbar {
    scroll-behavior: smooth;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.hide-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
