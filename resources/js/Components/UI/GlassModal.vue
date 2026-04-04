<script setup>
defineProps({
    show: { type: Boolean, required: true },
    maxWidth: { type: String, default: 'max-w-md' },
    zIndex: { type: String, default: 'z-[100]' },
    backdrop: { type: String, default: 'bg-black/80' },
    closeable: { type: Boolean, default: true },
});
const emit = defineEmits(['close']);
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div
            v-if="show"
            class="fixed inset-0 flex items-center justify-center p-4 backdrop-blur-sm"
            :class="[zIndex, backdrop]"
            @click="closeable && emit('close')"
        >
            <div
                @click.stop
                class="w-full bg-surface-container border border-white/10 rounded-2xl shadow-2xl overflow-hidden glassmorphism flex flex-col"
                :class="maxWidth"
            >
                <slot />
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.glassmorphism {
    background: rgba(23, 23, 23, 0.8);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
</style>
