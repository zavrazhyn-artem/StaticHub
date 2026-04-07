<template>
    <div>
        <div class="mb-8">
            <button
                @click="$emit('back')"
                class="flex items-center gap-2 text-on-surface-variant hover:text-primary transition-colors font-headline text-[10px] font-bold uppercase tracking-[0.15em] mb-6"
            >
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                {{ __('Back') }}
            </button>

            <div class="text-center">
                <span class="text-primary font-headline text-[10px] font-bold uppercase tracking-[0.3em] mb-2 block">
                    {{ __('— Step 2') }}
                </span>
                <h2 class="font-headline text-3xl font-black text-white uppercase tracking-tighter italic">
                    {{ __('Create Your Team') }}
                </h2>
                <p class="text-on-surface-variant text-sm mt-3 max-w-lg mx-auto">
                    {{ __('Give your raid group a name and select your region.') }}
                </p>
            </div>
        </div>

        <div class="max-w-md mx-auto">
            <div class="bg-surface-container-high p-8 rounded-xl border border-white/5">
                <div class="space-y-6">
                    <div>
                        <label class="block text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-2">
                            {{ __('Team Name') }}
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            :placeholder="__('Enter team name...')"
                            class="w-full bg-black/40 border border-white/10 focus:border-primary focus:ring-1 focus:ring-primary text-white py-3 px-4 rounded transition-all outline-none text-sm"
                            @keyup.enter="submit"
                        />
                    </div>

                    <div>
                        <label class="block text-on-surface-variant text-[10px] font-bold uppercase tracking-widest mb-2">
                            {{ __('Region') }}
                        </label>
                        <SearchableSelect
                            v-model="form.region"
                            :options="regionOptions"
                            :placeholder="__('Select region...')"
                            icon="public"
                            accent-color="#4fd3f7"
                            :use-search="false"
                        />
                    </div>

                    <div v-if="error" class="bg-error/10 border border-error/20 text-error text-xs font-bold rounded px-4 py-3">
                        {{ error }}
                    </div>

                    <button
                        @click="submit"
                        :disabled="!canSubmit || loading"
                        class="w-full bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-[0.2em] py-4 rounded hover:brightness-110 active:scale-[0.98] transition-all shadow-lg shadow-primary/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                    >
                        <span v-if="loading" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                        {{ loading ? __('Creating...') : __('Create & Continue') }}
                        <span v-if="!loading" class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { useTranslation } from '../../composables/useTranslation';
import SearchableSelect from '../UI/SearchableSelect.vue';

const { __ } = useTranslation();

const props = defineProps({
    csrfToken: { type: String, required: true },
    isGuildMaster: { type: Boolean, default: false },
    guildName: { type: String, default: '' },
});

const emit = defineEmits(['back', 'created']);

const regionOptions = [
    { id: 'eu', name: 'EU — Europe' },
    { id: 'us', name: 'US — Americas' },
];

const form = reactive({
    name: props.isGuildMaster ? props.guildName : '',
    region: 'eu',
});

const loading = ref(false);
const error = ref('');

const canSubmit = computed(() => form.name.trim().length > 0 && form.region);

async function submit() {
    if (!canSubmit.value || loading.value) return;

    loading.value = true;
    error.value = '';

    try {
        const response = await fetch('/onboarding/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(form),
        });

        if (!response.ok) {
            const data = await response.json();
            error.value = data.message || __('Failed to create team. Please try again.');
            return;
        }

        const data = await response.json();
        emit('created', data);
    } catch (e) {
        error.value = __('Network error. Please try again.');
    } finally {
        loading.value = false;
    }
}
</script>
