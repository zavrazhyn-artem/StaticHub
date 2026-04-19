<script setup>
import GlassModal from '../UI/GlassModal.vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    show: { type: Boolean, required: true },
    characterName: { type: String, required: true },
    comment: { type: String, required: true },
});
const emit = defineEmits(['close']);
</script>

<template>
    <GlassModal :show="show" backdrop="bg-black/60" z-index="z-[110]" @close="emit('close')">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="flex flex-col">
                    <span class="text-3xs font-bold uppercase tracking-[0.2em] text-on-surface-variant">{{ __('Comment from') }}</span>
                    <h2 class="text-lg font-black text-white uppercase tracking-tight font-headline">{{ characterName }}</h2>
                </div>
                <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="bg-white/5 border border-white/10 rounded-xl p-4">
                <p class="text-sm text-white italic leading-relaxed whitespace-pre-wrap">{{ comment }}</p>
            </div>

            <div class="mt-6 flex justify-end">
                <button
                    @click="emit('close')"
                    class="px-6 py-2 bg-white/5 hover:bg-white/10 text-white rounded-lg text-3xs font-bold uppercase tracking-wider transition-all"
                >
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </GlassModal>
</template>
