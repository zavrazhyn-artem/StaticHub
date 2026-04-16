<script setup>
import GlassModal from './GlassModal.vue';

defineProps({
    show: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'close']);
</script>

<template>
    <GlassModal :show="show" max-width="max-w-sm" z-index="z-[110]" backdrop="bg-black/90" @close="emit('close')">
        <div class="p-6 space-y-6">
            <div class="flex items-center gap-4" :class="variant === 'danger' ? 'text-error-dim' : 'text-amber-500'">
                <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" :class="variant === 'danger' ? 'bg-error-dim/20' : 'bg-amber-500/20'">
                    <span class="material-symbols-outlined text-2xl">warning</span>
                </div>
                <div>
                    <h3 class="font-headline text-lg font-black uppercase tracking-tighter text-white">{{ title }}</h3>
                    <p class="text-on-surface-variant text-xs font-medium">{{ message }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button @click="emit('close')" class="px-6 py-2.5 rounded-lg text-3xs font-black uppercase tracking-widest text-on-surface-variant hover:text-white transition-colors">
                    {{ cancelLabel }}
                </button>
                <button @click="emit('confirm')" :disabled="loading" class="px-6 py-2.5 rounded-lg text-3xs font-black uppercase tracking-widest hover:brightness-110 transition-all text-white disabled:opacity-50" :class="variant === 'danger' ? 'bg-error-dim' : 'bg-amber-500'">
                    <span v-if="loading" class="material-symbols-outlined text-sm animate-spin mr-1">sync</span>
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </GlassModal>
</template>
