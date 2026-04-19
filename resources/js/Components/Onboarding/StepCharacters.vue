<template>
    <div>
        <div class="text-center mb-8">
            <span class="text-primary text-3xs font-semibold uppercase tracking-[0.3em] mb-2 block">
                {{ __('— Step 3') }}
            </span>
            <h2 class="font-headline text-3xl font-black text-white uppercase tracking-tight italic">
                {{ __('Select Your Characters') }}
            </h2>
            <p class="text-on-surface-variant text-sm mt-3 max-w-lg mx-auto">
                {{ __('Choose your main character and any alts you\'d like to raid with in') }}
                <strong class="text-white">{{ staticName }}</strong>.
            </p>
        </div>

        <!-- Sync banner -->
        <div v-if="syncing" class="mb-6 bg-primary/10 border border-primary/20 rounded-lg px-5 py-4 flex items-center gap-3">
            <span class="material-symbols-outlined text-primary animate-spin">progress_activity</span>
            <span class="text-primary text-sm font-bold">{{ __('Syncing characters from Battle.net...') }}</span>
        </div>

        <div v-if="syncError" class="mb-6 bg-amber-500/10 border border-amber-500/20 rounded-lg px-5 py-4 flex items-center gap-3">
            <span class="material-symbols-outlined text-amber-500">warning</span>
            <span class="text-amber-500 text-sm">{{ syncError }}</span>
            <button @click="resync" class="ml-auto text-amber-500 hover:text-amber-300 text-3xs font-semibold uppercase tracking-wider">
                {{ __('Retry') }}
            </button>
        </div>

        <!-- No characters state -->
        <div v-if="!syncing && characterList.length === 0" class="text-center py-16 border border-dashed border-white/5 rounded-xl">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant/40 mb-3 block">person_off</span>
            <p class="text-on-surface-variant text-sm font-bold uppercase tracking-widest mb-2">{{ __('No Characters Found') }}</p>
            <p class="text-on-surface-variant/60 text-xs mb-6">{{ __('We couldn\'t find any max-level characters on your account.') }}</p>
            <button
                @click="resync"
                :disabled="syncing"
                class="inline-flex items-center gap-2 px-6 py-3 bg-primary/10 border border-primary/30 text-primary rounded-lg hover:bg-primary/20 transition-all text-3xs font-semibold uppercase tracking-[0.2em]"
            >
                <span class="material-symbols-outlined text-sm">sync</span>
                {{ __('Sync from Battle.net') }}
            </button>
        </div>

        <!-- Character list -->
        <div v-else-if="characterList.length > 0" class="space-y-6">
            <!-- Main Character Section -->
            <div>
                <h3 class="text-3xs font-semibold uppercase tracking-[0.2em] text-on-surface-variant mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-primary">star</span>
                    {{ __('Main Character') }}
                    <span class="text-error">*</span>
                </h3>

                <div class="bg-surface-container-high rounded-xl border border-white/5 overflow-hidden divide-y divide-white/5">
                    <label
                        v-for="char in characterList"
                        :key="'main-' + char.id"
                        class="flex items-center gap-4 px-5 py-4 cursor-pointer transition-all hover:bg-white/[0.02]"
                        :class="mainCharId === char.id ? 'bg-primary/5' : ''"
                    >
                        <!-- Radio -->
                        <div class="relative shrink-0">
                            <input
                                type="radio"
                                :value="char.id"
                                v-model="mainCharId"
                                class="peer sr-only"
                            />
                            <div class="w-5 h-5 rounded-full border-2 border-white/20 peer-checked:border-primary flex items-center justify-center transition-colors">
                                <div class="w-2.5 h-2.5 rounded-full bg-primary scale-0 peer-checked:scale-100 transition-transform" :class="mainCharId === char.id ? 'scale-100' : 'scale-0'"></div>
                            </div>
                        </div>

                        <!-- Avatar -->
                        <img
                            :src="char.avatar_url"
                            class="w-10 h-10 rounded-full border border-white/10 shrink-0"
                            :alt="char.name"
                        />

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm truncate" :class="classColor(char.playable_class)">
                                    {{ char.name }}
                                </span>
                                <span v-if="char.active_spec" class="text-3xs text-on-surface-variant font-medium">
                                    {{ char.active_spec }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-3xs text-on-surface-variant/60">{{ char.realm_name }}</span>
                            </div>
                        </div>

                        <!-- Item level -->
                        <div v-if="char.equipped_item_level" class="text-right shrink-0">
                            <div class="text-sm font-bold text-white">{{ char.equipped_item_level }}</div>
                            <div class="text-4xs text-on-surface-variant/50 uppercase tracking-wider">ilvl</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Alt Characters Section -->
            <div v-if="altCandidates.length > 0">
                <h3 class="text-3xs font-semibold uppercase tracking-[0.2em] text-on-surface-variant mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-cyan-400">group</span>
                    {{ __('Alts for Raid') }}
                    <span class="text-on-surface-variant/40 font-normal normal-case tracking-normal">({{ __('optional') }})</span>
                </h3>

                <div class="bg-surface-container-high rounded-xl border border-white/5 overflow-hidden divide-y divide-white/5">
                    <label
                        v-for="char in altCandidates"
                        :key="'alt-' + char.id"
                        class="flex items-center gap-4 px-5 py-4 cursor-pointer transition-all hover:bg-white/[0.02]"
                        :class="raidingCharIds.includes(char.id) ? 'bg-success-neon/5' : ''"
                    >
                        <!-- Checkbox -->
                        <div class="relative shrink-0">
                            <input
                                type="checkbox"
                                :value="char.id"
                                v-model="raidingCharIds"
                                class="peer sr-only"
                            />
                            <div
                                class="w-5 h-5 rounded border-2 border-white/20 peer-checked:border-success-neon peer-checked:bg-success-neon/20 flex items-center justify-center transition-all"
                            >
                                <span
                                    class="material-symbols-outlined text-xs text-success-neon transition-opacity"
                                    :class="raidingCharIds.includes(char.id) ? 'opacity-100' : 'opacity-0'"
                                >check</span>
                            </div>
                        </div>

                        <!-- Avatar -->
                        <img
                            :src="char.avatar_url"
                            class="w-10 h-10 rounded-full border border-white/10 shrink-0"
                            :alt="char.name"
                        />

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm truncate" :class="classColor(char.playable_class)">
                                    {{ char.name }}
                                </span>
                                <span v-if="char.active_spec" class="text-3xs text-on-surface-variant font-medium">
                                    {{ char.active_spec }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-3xs text-on-surface-variant/60">{{ char.realm_name }}</span>
                            </div>
                        </div>

                        <!-- Item level -->
                        <div v-if="char.equipped_item_level" class="text-right shrink-0">
                            <div class="text-sm font-bold text-white">{{ char.equipped_item_level }}</div>
                            <div class="text-4xs text-on-surface-variant/50 uppercase tracking-wider">ilvl</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Sync button -->
            <div class="flex justify-center">
                <button
                    @click="resync"
                    :disabled="syncing"
                    class="inline-flex items-center gap-2 text-on-surface-variant/60 hover:text-primary transition-colors text-3xs font-semibold uppercase tracking-[0.15em]"
                >
                    <span class="material-symbols-outlined text-sm" :class="syncing ? 'animate-spin' : ''">sync</span>
                    {{ __('Re-sync from Battle.net') }}
                </button>
            </div>

            <!-- Complete button -->
            <div v-if="error" class="bg-error/10 border border-error/20 text-error text-xs font-bold rounded px-4 py-3">
                {{ error }}
            </div>

            <button
                @click="complete"
                :disabled="!mainCharId || saving"
                class="w-full bg-primary text-on-primary font-headline font-bold text-xs uppercase tracking-[0.2em] py-4 rounded hover:brightness-110 active:scale-[0.98] transition-all shadow-lg shadow-primary/20 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-3"
            >
                <span v-if="saving" class="material-symbols-outlined text-sm animate-spin">progress_activity</span>
                {{ saving ? __('Saving...') : __('Complete Setup') }}
                <span v-if="!saving" class="material-symbols-outlined text-sm">check_circle</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useTranslation } from '../../composables/useTranslation';

const { __ } = useTranslation();

const CLASS_COLORS = {
    'Death Knight': 'text-[#C41F3B]', 'Demon Hunter': 'text-[#A330C9]',
    'Druid':        'text-[#FF7C0A]', 'Evoker':       'text-[#33937F]',
    'Hunter':       'text-[#ABD473]', 'Mage':         'text-[#3FC7EB]',
    'Monk':         'text-[#00FF98]', 'Paladin':      'text-[#F48CBA]',
    'Priest':       'text-[#FFFFFF]', 'Rogue':        'text-[#FFF468]',
    'Shaman':       'text-[#0070DD]', 'Warlock':      'text-[#8788EE]',
    'Warrior':      'text-[#C69B6D]',
};

const props = defineProps({
    csrfToken: { type: String, required: true },
    characters: { type: Array, default: () => [] },
    specializations: { type: Array, default: () => [] },
    staticId: { type: Number, default: null },
    staticName: { type: String, default: '' },
});

const emit = defineEmits(['complete']);

const characterList = ref([...props.characters]);
const mainCharId = ref(null);
const raidingCharIds = ref([]);
const syncing = ref(false);
const syncError = ref('');
const saving = ref(false);
const error = ref('');

const altCandidates = computed(() => {
    return characterList.value.filter(c => c.id !== mainCharId.value);
});

function classColor(playableClass) {
    return CLASS_COLORS[playableClass] ?? 'text-white';
}

async function resync() {
    syncing.value = true;
    syncError.value = '';

    try {
        const response = await fetch('/onboarding/sync-characters', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
        });

        if (!response.ok) throw new Error('Sync failed');

        const data = await response.json();
        characterList.value = data.characters || [];
    } catch (e) {
        syncError.value = __('Failed to sync characters. Please try again.');
    } finally {
        syncing.value = false;
    }
}

async function complete() {
    if (!mainCharId.value || saving.value) return;

    saving.value = true;
    error.value = '';

    try {
        const response = await fetch('/onboarding/save-participation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                static_id: props.staticId,
                main_character_id: mainCharId.value,
                raiding_character_ids: raidingCharIds.value,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            error.value = data.message || __('Failed to save. Please try again.');
            return;
        }

        emit('complete');
    } catch (e) {
        error.value = __('Network error. Please try again.');
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    // If no characters loaded yet, try to sync
    if (characterList.value.length === 0) {
        resync();
    }
});
</script>
