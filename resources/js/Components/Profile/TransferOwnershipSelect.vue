<script setup>
import { ref, getCurrentInstance } from 'vue';
import SelectUserWithMain from '../UI/SelectUserWithMain.vue';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

const props = defineProps({
    staticId:    { type: Number, required: true },
    staticName:  { type: String, required: true },
    transferUrl: { type: String, required: true },
    members:     { type: Array,  default: () => [] },
    csrfToken:   { type: String, required: true },
});

const selectedId = ref('');
const formRef    = ref(null);

const submit = () => {
    if (!selectedId.value) return;
    const msg = __('Transfer ownership of :name? You will become an officer.', { name: props.staticName });
    if (!confirm(msg)) return;
    formRef.value?.submit();
};
</script>

<template>
    <form ref="formRef" method="POST" :action="transferUrl" class="hidden">
        <input type="hidden" name="_token" :value="csrfToken">
        <input type="hidden" name="new_owner_id" :value="selectedId">
    </form>

    <div class="p-4 bg-surface-container-lowest border border-amber-500/20 rounded-lg">
        <div class="mb-3">
            <div class="text-3xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                {{ __('Transfer Ownership') }}
            </div>
            <p class="text-4xs text-gray-500 font-semibold uppercase tracking-wider mt-1">
                {{ __('You will become an officer after transfer.') }}
            </p>
        </div>

        <div class="flex gap-3">
            <div class="flex-1">
                <SelectUserWithMain
                    v-model="selectedId"
                    :members="members"
                    :placeholder="__('Select new owner...')"
                    :search-placeholder="__('Search...')"
                    accent-color="#f59e0b"
                />
            </div>

            <button
                type="button"
                :disabled="!selectedId"
                @click="submit"
                class="bg-amber-500/10 hover:bg-amber-500 text-amber-400 hover:text-white text-3xs font-semibold uppercase tracking-wider py-2 px-4 rounded-lg transition-all active:scale-95 whitespace-nowrap disabled:opacity-40 disabled:cursor-not-allowed"
            >
                {{ __('Transfer') }}
            </button>
        </div>
    </div>
</template>
