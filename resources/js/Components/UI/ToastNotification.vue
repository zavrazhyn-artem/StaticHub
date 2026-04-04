<script setup>
defineProps({
    show: { type: Boolean, required: true },
    message: { type: String, required: true },
    icon: { type: String, default: 'check_circle' },
    iconClass: { type: String, default: 'text-success-neon' },
});
</script>

<template>
    <Transition name="toast">
        <div v-if="show" class="fixed top-6 left-1/2 -translate-x-1/2 z-[200] pointer-events-none">
            <div class="glassmorphism border border-white/10 px-6 py-3 rounded-xl shadow-2xl backdrop-blur-xl flex items-center gap-3">
                <span class="material-symbols-outlined" :class="iconClass">{{ icon }}</span>
                <span class="text-xs font-black uppercase tracking-widest text-white">{{ message }}</span>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.glassmorphism {
    background: rgba(23, 23, 23, 0.9);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}
.toast-enter-active,
.toast-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-enter-from {
    opacity: 0;
    transform: translate(-50%, -20px);
}
.toast-leave-to {
    opacity: 0;
    transform: translate(-50%, -10px) scale(0.95);
}
/* Override translate-x from utility since we handle it in keyframes */
.toast-enter-from,
.toast-leave-to {
    left: 50%;
}
</style>
