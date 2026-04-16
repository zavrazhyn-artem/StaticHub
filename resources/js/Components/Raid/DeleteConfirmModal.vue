<script setup>
import GlassModal from '../UI/GlassModal.vue';

defineProps({
    show: { type: Boolean, required: true },
    csrfToken: { type: String, required: true },
    destroyRoute: { type: String, required: true },
});
const emit = defineEmits(['close']);
</script>

<template>
    <GlassModal :show="show" max-width="max-w-sm" z-index="z-[110]" backdrop="bg-black/90" @close="emit('close')">
        <div class="p-6 space-y-6">
            <div class="flex items-center gap-4 text-error-dim">
                <div class="w-12 h-12 rounded-full bg-error-dim/20 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-2xl">warning</span>
                </div>
                <div>
                    <h3 class="font-headline text-lg font-black uppercase tracking-tighter text-white">{{ __('Delete Event?') }}</h3>
                    <p class="text-on-surface-variant text-xs font-medium">{{ __('This action cannot be undone.') }}</p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    @click="emit('close')"
                    class="px-6 py-2.5 rounded-lg text-3xs font-black uppercase tracking-widest text-on-surface-variant hover:text-white transition-colors"
                >{{ __('Cancel') }}</button>
                <form :action="destroyRoute" method="POST">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="DELETE">
                    <button
                        type="submit"
                        class="px-6 py-2.5 bg-error-dim text-white rounded-lg text-3xs font-black uppercase tracking-widest hover:brightness-110 transition-all"
                    >{{ __('Delete Forever') }}</button>
                </form>
            </div>
        </div>
    </GlassModal>
</template>
