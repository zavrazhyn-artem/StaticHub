<script setup>
import { computed } from 'vue';
import GlassModal from './GlassModal.vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    title: { type: String, required: true },
    message: { type: String, required: true },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    tertiaryLabel: { type: String, default: '' },
    variant: { type: String, default: 'danger' },
    loading: { type: Boolean, default: false },
    zIndex: { type: String, default: 'z-[110]' },
});

const emit = defineEmits(['confirm', 'close', 'tertiary']);

const variantClasses = computed(() => {
    if (props.variant === 'boss-planner') {
        return {
            text: 'text-orange-400',
            iconBg: 'bg-orange-500/15',
            confirmBg: 'bg-orange-500/80',
            icon: 'edit_note',
        };
    }
    if (props.variant === 'warning') {
        return {
            text: 'text-amber-500',
            iconBg: 'bg-amber-500/20',
            confirmBg: 'bg-amber-500',
            icon: 'warning',
        };
    }
    return {
        text: 'text-error-dim',
        iconBg: 'bg-error-dim/20',
        confirmBg: 'bg-error-dim',
        icon: 'warning',
    };
});
</script>

<template>
    <GlassModal :show="show" max-width="max-w-sm" :z-index="zIndex" backdrop="bg-black/90" @close="emit('close')">
        <div class="p-6 space-y-6">
            <div class="flex items-center gap-4" :class="variantClasses.text">
                <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" :class="variantClasses.iconBg">
                    <span class="material-symbols-outlined text-2xl">{{ variantClasses.icon }}</span>
                </div>
                <div>
                    <h3 class="font-headline text-lg font-black uppercase tracking-tight text-white">{{ title }}</h3>
                    <p class="text-on-surface-variant text-xs font-medium">{{ message }}</p>
                </div>
            </div>
            <div class="flex items-stretch gap-2">
                <button @click="emit('close')" class="flex-1 px-3 py-2.5 rounded-lg text-3xs font-bold uppercase tracking-wider text-on-surface-variant hover:text-white hover:bg-white/5 transition-colors whitespace-nowrap">
                    {{ cancelLabel }}
                </button>
                <button v-if="tertiaryLabel" @click="emit('tertiary')" :disabled="loading"
                    class="flex-1 px-3 py-2.5 rounded-lg text-3xs font-bold uppercase tracking-wider bg-white/5 hover:bg-white/10 text-white transition-all disabled:opacity-50 whitespace-nowrap">
                    {{ tertiaryLabel }}
                </button>
                <button @click="emit('confirm')" :disabled="loading" class="flex-1 flex items-center justify-center px-3 py-2.5 rounded-lg text-3xs font-bold uppercase tracking-wider hover:brightness-110 transition-all text-white disabled:opacity-50 whitespace-nowrap" :class="variantClasses.confirmBg">
                    <span v-if="loading" class="material-symbols-outlined text-sm animate-spin mr-1">sync</span>
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </GlassModal>
</template>
