<script setup>
import { ref } from 'vue';

defineProps({
    text: { type: String, required: true },
    position: { type: String, default: 'bottom' }, // top, bottom, left, right
});

const visible = ref(false);
</script>

<template>
    <span class="relative inline-flex items-center"
          @mouseenter="visible = true"
          @mouseleave="visible = false">
        <span class="material-symbols-outlined text-sm text-on-surface-variant/50 cursor-help hover:text-on-surface-variant transition-colors leading-none">
            info
        </span>

        <Transition name="tooltip">
            <div v-if="visible"
                 class="absolute z-50 pointer-events-none"
                 :class="{
                     'top-full left-1/2 -translate-x-1/2 mt-2': position === 'bottom',
                     'bottom-full left-1/2 -translate-x-1/2 mb-2': position === 'top',
                     'right-full top-1/2 -translate-y-1/2 mr-2': position === 'left',
                     'left-full top-1/2 -translate-y-1/2 ml-2': position === 'right',
                 }">
                <div class="tooltip-glass border border-white/10 px-3 py-2 rounded-lg shadow-2xl whitespace-nowrap">
                    <span class="text-3xs font-semibold text-gray-200 tracking-wide">{{ text }}</span>
                </div>
            </div>
        </Transition>
    </span>
</template>

<style scoped>
.tooltip-glass {
    background: rgba(23, 23, 23, 0.92);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
}

.tooltip-enter-active {
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.tooltip-leave-active {
    transition: all 0.15s ease-in;
}
.tooltip-enter-from,
.tooltip-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
