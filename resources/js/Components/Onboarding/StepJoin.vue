<template>
    <div>
        <div class="mb-8">
            <button
                @click="$emit('back')"
                class="flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors text-3xs font-semibold uppercase tracking-[0.15em] mb-6"
            >
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                {{ __('Back') }}
            </button>

            <div class="text-center">
                <span class="text-primary text-3xs font-semibold uppercase tracking-[0.3em] mb-2 block">
                    {{ __('— Step 2') }}
                </span>
                <h2 class="font-headline text-3xl font-black text-white uppercase tracking-tight italic">
                    {{ __('Join a Team') }}
                </h2>
                <p class="text-on-surface-variant text-sm mt-3 max-w-lg mx-auto">
                    {{ __('Paste the invite link your Raid Leader sent you.') }}
                </p>
            </div>
        </div>

        <div class="max-w-md mx-auto">
            <div class="bg-surface-container-high p-8 rounded-xl border border-white/5">
                <div class="space-y-6">
                    <!-- Token input -->
                    <div>
                        <label class="block text-on-surface-variant text-3xs font-semibold uppercase tracking-wider mb-2">
                            {{ __('Invite Link') }}
                        </label>
                        <div class="relative">
                            <input
                                v-model="tokenInput"
                                type="text"
                                :placeholder="__('Paste invite link or token...')"
                                class="w-full bg-black/40 border text-white py-3 px-4 pr-12 rounded transition-all outline-none text-sm"
                                :class="inputBorderClass"
                                @input="debouncedValidate"
                                @paste="onPaste"
                            />
                            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                <span v-if="validating" class="material-symbols-outlined text-on-surface-variant text-lg animate-spin">progress_activity</span>
                                <span v-else-if="staticInfo" class="material-symbols-outlined text-success-neon text-lg">check_circle</span>
                                <span v-else-if="tokenInput && !validating && validationError" class="material-symbols-outlined text-error text-lg">error</span>
                            </div>
                        </div>
                        <p v-if="validationError" class="text-error text-3xs font-semibold mt-2">{{ validationError }}</p>
                    </div>

                    <!-- Static preview -->
                    <Transition name="fade">
                        <div v-if="staticInfo" class="bg-black/30 border border-primary/20 rounded-lg p-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-2xl text-primary">shield</span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-headline text-white text-sm font-bold uppercase tracking-wider truncate">
                                        {{ staticInfo.name }}
                                    </h4>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-3xs font-semibold uppercase tracking-wider text-on-surface-variant">
                                            {{ staticInfo.region?.toUpperCase() }}
                                        </span>
                                        <span class="text-white/10">|</span>
                                        <span class="text-3xs font-semibold text-on-surface-variant">
                                            {{ staticInfo.memberCount }} {{ __('members') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <div v-if="error" class="bg-error/10 border border-error/20 text-error text-xs font-bold rounded px-4 py-3">
                        {{ error }}
                    </div>

                    <!-- Join button -->
                    <button
                        @click="join"
                        :disabled="!staticInfo || joining"
                        class="w-full bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-[0.2em] py-4 rounded hover:brightness-110 active:scale-[0.98] transition-all shadow-lg shadow-primary/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                    >
                        <span v-if="joining" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                        {{ joining ? __('Joining...') : __('Join Team & Continue') }}
                        <span v-if="!joining" class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useTranslation } from '../../composables/useTranslation';

const { __ } = useTranslation();

const props = defineProps({
    csrfToken: { type: String, required: true },
    initialToken: { type: String, default: '' },
    initialStatic: { type: Object, default: null },
});

const emit = defineEmits(['back', 'joined']);

const tokenInput = ref(props.initialToken || '');
const staticInfo = ref(props.initialStatic || null);
const validating = ref(false);
const validationError = ref('');
const joining = ref(false);
const error = ref('');

let debounceTimer = null;

const inputBorderClass = computed(() => {
    if (staticInfo.value) return 'border-success-neon/30 focus:border-success-neon focus:ring-1 focus:ring-success-neon';
    if (tokenInput.value && validationError.value) return 'border-error/30 focus:border-error focus:ring-1 focus:ring-error';
    return 'border-white/10 focus:border-primary focus:ring-1 focus:ring-primary';
});

function debouncedValidate() {
    staticInfo.value = null;
    validationError.value = '';

    if (debounceTimer) clearTimeout(debounceTimer);

    if (!tokenInput.value.trim()) return;

    debounceTimer = setTimeout(() => {
        validateToken();
    }, 500);
}

function onPaste() {
    // Validate immediately after paste
    if (debounceTimer) clearTimeout(debounceTimer);
    setTimeout(() => validateToken(), 50);
}

async function validateToken() {
    const input = tokenInput.value.trim();
    if (!input) return;

    validating.value = true;
    validationError.value = '';

    try {
        const response = await fetch('/onboarding/validate-token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token: input }),
        });

        if (!response.ok) {
            validationError.value = __('Invalid or expired invite link.');
            staticInfo.value = null;
            return;
        }

        const data = await response.json();
        if (data.valid) {
            staticInfo.value = data.static;
        } else {
            validationError.value = __('Invalid or expired invite link.');
        }
    } catch (e) {
        validationError.value = __('Could not verify the link. Please try again.');
    } finally {
        validating.value = false;
    }
}

async function join() {
    if (!staticInfo.value || joining.value) return;

    joining.value = true;
    error.value = '';

    try {
        const response = await fetch('/onboarding/join', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ token: tokenInput.value.trim() }),
        });

        if (!response.ok) {
            const data = await response.json();
            error.value = data.message || __('Failed to join team. Please try again.');
            return;
        }

        const data = await response.json();
        emit('joined', data);
    } catch (e) {
        error.value = __('Network error. Please try again.');
    } finally {
        joining.value = false;
    }
}

onMounted(() => {
    // If we have a pre-filled token from session (invite link flow), validate it
    if (props.initialToken && !props.initialStatic) {
        validateToken();
    }
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
