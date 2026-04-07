<script setup>
import { ref } from 'vue';
import GlassModal from '../UI/GlassModal.vue';
import SelectUserWithMain from '../UI/SelectUserWithMain.vue';

defineProps({
    show: { type: Boolean, required: true },
    transactionType: { type: String, required: true },
    members: { type: Array, required: true },
    staticId: { type: Number, required: true },
    csrfToken: { type: String, required: true },
});
const emit = defineEmits(['close']);

const selectedUserId = ref('');
</script>

<template>
    <GlassModal :show="show" max-width="max-w-md" @close="emit('close')">
        <div class="px-6 py-4 border-b border-white/5 flex justify-between items-center">
            <h3 class="font-headline text-xs font-bold text-white uppercase tracking-widest">
                {{ transactionType === 'deposit' ? __('Record Deposit') : __('Record Withdrawal') }}
            </h3>
            <button @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form :action="`/statics/${staticId}/treasury`" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="_token" :value="csrfToken">
            <input type="hidden" name="type" :value="transactionType">

            <div class="space-y-1">
                <label class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Select Member') }}</label>
                <SelectUserWithMain
                    v-model="selectedUserId"
                    :members="members"
                    input-name="user_id"
                    :placeholder="__('Select a member...')"
                    :search-placeholder="__('Search...')"
                />
            </div>

            <div class="space-y-1">
                <label for="amount" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Amount (Gold)') }}</label>
                <input type="number" name="amount" id="amount" required :placeholder="__('e.g., 50000')"
                       class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none">
            </div>

            <div class="space-y-1">
                <label for="description" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">{{ __('Description') }}</label>
                <textarea name="description" id="description" rows="2" :placeholder="__('Optional notes...')"
                          class="w-full bg-surface-container-highest border border-white/5 rounded-sm px-3 py-2 text-sm text-white focus:ring-1 focus:ring-primary focus:border-transparent outline-none"></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" :disabled="!selectedUserId"
                        class="w-full bg-primary text-on-primary py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ __('Save Transaction') }}
                </button>
            </div>
        </form>
    </GlassModal>
</template>
