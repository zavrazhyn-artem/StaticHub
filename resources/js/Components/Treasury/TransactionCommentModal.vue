<script setup>
import GlassModal from '../UI/GlassModal.vue';

defineProps({
    show: { type: Boolean, required: true },
    transaction: { type: Object, default: () => ({}) },
    staticId: { type: Number, required: true },
    csrfToken: { type: String, required: true },
});
const emit = defineEmits(['close']);
</script>

<template>
    <GlassModal :show="show" max-width="max-w-md" @close="emit('close')">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
            <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">{{ __('Transaction Comment') }}</h3>
            <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form :action="`/statics/${staticId}/treasury/${transaction.id}`" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="_method" value="PATCH">

            <div class="p-3 rounded-lg bg-surface-container-highest border border-white/5 space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ transaction.date }}</span>
                    <span class="text-sm font-black font-headline tracking-tight" :class="transaction.type === 'deposit' ? 'text-[#FFD700]' : 'text-error'">
                        {{ (transaction.type === 'deposit' ? '+' : '-') + transaction.amount }}
                    </span>
                </div>
                <div class="text-xs font-bold text-white">{{ transaction.member }}</div>
            </div>

            <div class="space-y-1">
                <label for="edit_description" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Comment') }}</label>
                <textarea name="description" id="edit_description" rows="4" v-model="transaction.description" :placeholder="__('Optional notes...')"
                          class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-yellow-500 focus:border-transparent outline-none"></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full bg-yellow-500 text-black py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all">
                    {{ __('Save Comment') }}
                </button>
            </div>
        </form>
    </GlassModal>
</template>
