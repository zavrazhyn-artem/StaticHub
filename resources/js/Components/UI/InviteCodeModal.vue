<script setup>
import { ref } from 'vue';
import { useTranslation } from '../../composables/useTranslation';
import GlassModal from './GlassModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    show: { type: Boolean, required: true },
    csrfToken: { type: String, required: true },
});

const emit = defineEmits(['confirmed', 'close']);

const code = ref('');
const error = ref('');
const loading = ref(false);

async function validate() {
    if (!code.value.trim()) {
        error.value = __('Please enter an invite code.');
        return;
    }

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/onboarding/validate-invite-code', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ invite_code: code.value.trim() }),
        });

        if (!response.ok) {
            const data = await response.json();
            error.value = data.message || __('Invalid or already used invite code.');
            return;
        }

        emit('confirmed', code.value.trim());
    } catch (e) {
        error.value = __('Network error. Please try again.');
    } finally {
        loading.value = false;
    }
}

function close() {
    code.value = '';
    error.value = '';
    emit('close');
}
</script>

<template>
    <GlassModal :show="show" max-width="max-w-sm" @close="close">
        <div class="p-6">
            <div class="text-center mb-6">
                <span class="material-symbols-outlined text-primary text-4xl mb-3 block">lock</span>
                <h3 class="font-headline text-lg font-bold text-white uppercase tracking-tight">
                    {{ __('Early Access') }}
                </h3>
                <p class="text-on-surface-variant text-sm mt-2">
                    {{ __('The app is currently in testing. Enter your invite code to create a team.') }}
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <input
                        v-model="code"
                        type="text"
                        :placeholder="__('BLASTR-XXXX-XXXX')"
                        class="w-full bg-black/40 border border-white/10 focus:border-primary focus:ring-1 focus:ring-primary text-white py-3 px-4 rounded text-center font-mono text-sm uppercase tracking-widest transition-all outline-none"
                        @keyup.enter="validate"
                        autofocus
                    />
                </div>

                <div v-if="error" class="bg-error/10 border border-error/20 text-error text-xs font-bold rounded px-4 py-2.5 text-center">
                    {{ error }}
                </div>

                <div class="flex gap-3">
                    <button
                        @click="close"
                        class="flex-1 bg-white/5 border border-white/10 text-on-surface-variant font-headline font-bold text-xs uppercase tracking-[0.15em] py-3 rounded hover:bg-white/10 transition-all"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        @click="validate"
                        :disabled="!code.trim() || loading"
                        class="flex-1 bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-[0.15em] py-3 rounded hover:brightness-110 transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <span v-if="loading" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                        {{ loading ? __('Checking...') : __('Confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </GlassModal>
</template>
