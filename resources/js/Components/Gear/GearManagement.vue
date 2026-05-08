<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
import SelectUserWithMain from '@/Components/UI/SelectUserWithMain.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import GearTab from './Tab/GearTab.vue';
import WishlistPanel from './Wishlist/WishlistPanel.vue';
import LootHistoryPanel from './LootHistory/LootHistoryPanel.vue';

const { __ } = useTranslation();

const props = defineProps({
    staticName: { type: String, required: true },
    wishlistPayload: { type: Array, default: () => [] },
    gearContext: { type: Array, default: () => [] },
    enchantableSlots: { type: Array, default: () => [] },
    storeUrl: { type: String, required: true },
    destroyUrlTemplate: { type: String, required: true },
    gearListStoreUrl: { type: String, required: true },
    gearListDestroyUrlTemplate: { type: String, required: true },
    gearListRenameUrlTemplate: { type: String, required: true },
    gearListSetSlotUrlTemplate: { type: String, required: true },
    gearListPickerUrlTemplate: { type: String, required: true },
    gearListExportSimcUrlTemplate: { type: String, required: true },
    gearListImportSimcUrlTemplate: { type: String, required: true },
    gearBisImportUrl: { type: String, required: true },
    listSummariesUrl: { type: String, required: true },
    activeListUrlTemplate: { type: String, required: true },
    lootHistoryPayloadUrl: { type: String, required: true },
    initialTab: { type: String, default: 'gear' },
    tabUrls: { type: Object, default: () => ({}) },
    csrfToken: { type: String, required: true },
    flashSuccess: { type: String, default: '' },
    flashError: { type: String, default: '' },
});

const showImportModal = ref(false);
const submitting = ref(false);
const importForm = ref(null);
const urlInput = ref('');
const activeTab = ref(props.initialTab || 'gear');

// Sync tab → URL so refresh / back-button preserves the active tab and
// the URL is shareable. replaceState (not pushState) — switching tabs
// shouldn't clutter browser history with N entries per session.
watch(activeTab, (id) => {
    const url = props.tabUrls?.[id];
    if (url && window.location.pathname !== new URL(url, window.location.origin).pathname) {
        window.history.replaceState({}, '', url);
    }
});

// Top-level character + spec selection — drives BOTH the Gear and Wishlist
// tabs so changes in one are reflected in the other. Both are persisted in
// localStorage; spec is keyed per-character so switching characters restores
// each one's last spec.
const CHAR_STORAGE_KEY = 'blastr.gear.character.v1';
const SPEC_STORAGE_KEY = 'blastr.gear.spec-by-char.v1';

const selectedCharacterId = ref(null);
const selectedSpecId = ref(null);

const characterOptions = computed(() => props.gearContext.map(c => ({
    id: c.id,
    name: c.name,
    character: {
        name: c.name,
        playable_class: c.playable_class,
        avatar_url: c.avatar_url,
    },
})));

const currentCharacter = computed(() => props.gearContext.find(c => c.id === selectedCharacterId.value) ?? null);
const availableSpecs = computed(() => currentCharacter.value?.specs ?? []);

// Resolve the role of the active (character, spec) pair so the wishlist
// import modal can swap its wording between Raidbots (DPS/Tank) and QE Live
// (Healer) — the right tool depends on what the spec is being sim'd for.
const currentRole = computed(() => {
    const spec = availableSpecs.value.find(s => s.id === selectedSpecId.value)
        ?? availableSpecs.value.find(s => s.is_main)
        ?? availableSpecs.value[0];
    return spec?.role?.toLowerCase() ?? null;
});
const isCurrentSpecHealer = computed(() => currentRole.value === 'heal' || currentRole.value === 'healer');

const loadSpecMap = () => {
    try { return JSON.parse(localStorage.getItem(SPEC_STORAGE_KEY) || '{}') ?? {}; }
    catch { return {}; }
};
const persistSpecMap = (map) => {
    localStorage.setItem(SPEC_STORAGE_KEY, JSON.stringify(map));
};

watch(selectedCharacterId, (id) => {
    if (id !== null && id !== undefined) {
        localStorage.setItem(CHAR_STORAGE_KEY, String(id));
    }
    // When character changes, restore the spec we last used for THAT character;
    // fall back to its main spec, then to the first available.
    const char = props.gearContext.find(c => c.id === id);
    if (!char) { selectedSpecId.value = null; return; }
    const stored = loadSpecMap()[String(id)];
    const candidate = stored && char.specs.find(s => s.id === stored)
        ? stored
        : (char.specs.find(s => s.is_main) || char.specs[0])?.id ?? null;
    selectedSpecId.value = candidate;
});

watch(selectedSpecId, (specId) => {
    if (!selectedCharacterId.value || !specId) return;
    const map = loadSpecMap();
    map[String(selectedCharacterId.value)] = specId;
    persistSpecMap(map);
});

const toast = ref({ show: false, message: '', icon: 'check_circle', iconClass: 'text-success-neon' });

const tabs = [
    { id: 'gear', icon: 'checkroom', label: 'Gear' },
    { id: 'wishlist', icon: 'list_alt', label: 'Wishlist' },
    { id: 'loot-history', icon: 'history', label: 'Loot History' },
    { id: 'vault', icon: 'inventory_2', label: 'Vault Optimizer', disabled: true },
];

const submitImport = () => {
    if (!urlInput.value.trim()) return;
    submitting.value = true;
    importForm.value?.submit();
};

const showToast = (message, isError = false) => {
    toast.value = {
        show: true,
        message,
        icon: isError ? 'error' : 'check_circle',
        iconClass: isError ? 'text-error' : 'text-success-neon',
    };
    setTimeout(() => { toast.value.show = false; }, 3500);
};

onMounted(() => {
    // Restore last picked character if it's still in the static; otherwise
    // fall back to the first character we have.
    const stored = Number(localStorage.getItem(CHAR_STORAGE_KEY));
    const hasStored = stored && props.gearContext.some(c => c.id === stored);
    if (hasStored) {
        selectedCharacterId.value = stored;
    } else if (props.gearContext.length) {
        selectedCharacterId.value = props.gearContext[0].id;
    }

    if (props.flashSuccess) showToast(props.flashSuccess);
    else if (props.flashError) showToast(props.flashError, true);
});
</script>

<template>
    <section class="mb-6">
        <header class="flex items-end justify-between border-b border-white/5 pb-4">
            <div>
                <span class="text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest">{{ staticName }}</span>
                <h1 class="font-headline text-4xl font-black text-white uppercase tracking-tight leading-tight flex items-center gap-3">
                    <span class="material-symbols-outlined text-cyan-400">shield</span>
                    {{ __('Gear Management') }}
                </h1>
            </div>
            <button
                v-if="activeTab === 'wishlist'"
                type="button"
                @click="showImportModal = true"
                class="px-5 py-2.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-100 font-headline text-xs font-bold uppercase tracking-widest flex items-center gap-2 hover:bg-cyan-500/20 transition"
            >
                <span class="material-symbols-outlined text-base">add_link</span>
                {{ __('Import Wishlist') }}
            </button>
        </header>
    </section>

    <!-- Tabs bar with shared character picker — switching characters here
         affects both the Gear and Wishlist tabs simultaneously. -->
    <div class="flex items-end justify-between gap-4 border-b border-white/5 mb-6">
        <nav class="flex gap-1">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                :disabled="tab.disabled"
                @click="!tab.disabled && (activeTab = tab.id)"
                :class="[
                    'px-4 py-3 font-headline text-xs font-bold uppercase tracking-widest flex items-center gap-2 border-b-2 transition',
                    activeTab === tab.id
                        ? 'border-cyan-400 text-cyan-100'
                        : tab.disabled
                            ? 'border-transparent text-on-surface-variant/40 cursor-not-allowed'
                            : 'border-transparent text-on-surface-variant hover:text-white'
                ]"
            >
                <span class="material-symbols-outlined text-base">{{ tab.icon }}</span>
                {{ __(tab.label) }}
                <span v-if="tab.disabled" class="text-[10px] text-on-surface-variant/60 normal-case font-normal">(soon)</span>
            </button>
        </nav>

        <div v-if="gearContext.length" class="flex items-center gap-3 pb-2 flex-wrap">
            <div class="flex items-center gap-2 min-w-[260px]">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest shrink-0">{{ __('Character') }}</span>
                <div class="flex-1 min-w-[220px]">
                    <SelectUserWithMain
                        v-model="selectedCharacterId"
                        :members="characterOptions"
                        :placeholder="__('Select a character...')"
                        :search-placeholder="__('Search character...')"
                        :empty-text="__('No characters found.')"
                        accent-color="#22d3ee"
                    />
                </div>
            </div>

            <div v-if="availableSpecs.length" class="flex items-center gap-1.5">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-1">{{ __('Spec') }}</span>
                <button
                    v-for="s in availableSpecs"
                    :key="s.id"
                    type="button"
                    @click="selectedSpecId = s.id"
                    :title="s.name + (s.is_main ? ' · main' : '')"
                    :class="[
                        'relative w-9 h-9 rounded-md border-2 transition shrink-0',
                        selectedSpecId === s.id
                            ? 'border-cyan-400 ring-2 ring-cyan-400/30'
                            : 'border-white/10 hover:border-white/30 opacity-60 hover:opacity-100'
                    ]"
                >
                    <img
                        v-if="s.icon_url"
                        :src="s.icon_url"
                        :alt="s.name"
                        class="w-full h-full rounded-sm object-cover"
                    />
                    <div
                        v-else
                        class="w-full h-full rounded-sm bg-surface-container flex items-center justify-center text-[9px] font-bold text-on-surface-variant"
                    >{{ s.name.slice(0, 3).toUpperCase() }}</div>
                    <span
                        v-if="s.is_main"
                        class="absolute -top-1 -left-1 w-3.5 h-3.5 bg-yellow-400 rounded-full flex items-center justify-center shadow-sm"
                        :title="__('Main spec')"
                    >
                        <span class="material-symbols-outlined text-black leading-none" style="font-size: 9px;">star</span>
                    </span>
                </button>
            </div>
        </div>
    </div>

    <GearTab
        v-if="activeTab === 'gear'"
        v-model:character-id="selectedCharacterId"
        v-model:spec-id="selectedSpecId"
        :context="gearContext"
        :enchantable-slots="enchantableSlots"
        :csrf-token="csrfToken"
        :list-summaries-url="listSummariesUrl"
        :active-list-url-template="activeListUrlTemplate"
        :gear-list-store-url="gearListStoreUrl"
        :gear-list-destroy-url-template="gearListDestroyUrlTemplate"
        :gear-list-set-slot-url-template="gearListSetSlotUrlTemplate"
        :gear-list-picker-url-template="gearListPickerUrlTemplate"
        :gear-list-export-simc-url-template="gearListExportSimcUrlTemplate"
        :gear-list-import-simc-url-template="gearListImportSimcUrlTemplate"
        :gear-bis-import-url="gearBisImportUrl"
    />

    <WishlistPanel
        v-else-if="activeTab === 'wishlist'"
        v-model:character-id="selectedCharacterId"
        v-model:spec-id="selectedSpecId"
        :payload="wishlistPayload"
        :characters="gearContext"
        :csrf-token="csrfToken"
        :destroy-url-template="destroyUrlTemplate"
        @open-import="urlInput = ''; showImportModal = true"
    />

    <LootHistoryPanel
        v-else-if="activeTab === 'loot-history'"
        :payload-url="lootHistoryPayloadUrl"
    />

    <div v-else class="bg-surface-container-low border border-white/5 rounded-xl p-12">
        <EmptyState
            icon="construction"
            :title="__('Coming Soon')"
            :description="__('This module is currently in development.')"
        />
    </div>

    <!-- Import wishlist modal (only shown when activeTab=wishlist) -->
    <GlassModal :show="showImportModal" @close="showImportModal = false" max-width="max-w-lg">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-400 text-base">add_link</span>
                {{ __('Import Wishlist') }}
            </h3>
            <button
                type="button"
                @click="showImportModal = false"
                class="text-on-surface-variant hover:text-white transition"
            >
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>
        <form
            ref="importForm"
            method="POST"
            :action="storeUrl"
            @submit="submitImport"
            class="space-y-4 p-6"
        >
            <input type="hidden" name="_token" :value="csrfToken" />

            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">
                    {{ isCurrentSpecHealer ? __('QE Live Upgrade Report URL') : __('Raidbots Droptimizer URL') }}
                </label>
                <input
                    v-model="urlInput"
                    name="url"
                    type="url"
                    required
                    :placeholder="isCurrentSpecHealer ? 'https://questionablyepic.com/live/upgradereport/...' : 'https://www.raidbots.com/simbot/report/...'"
                    class="w-full px-4 py-3 bg-surface-container border border-white/10 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-cyan-400 transition"
                />
                <p class="text-[11px] text-on-surface-variant/70 mt-2">
                    {{ isCurrentSpecHealer
                        ? __('Run an Upgrade Report on questionablyepic.com/live and paste the URL here. The character must be linked to your account first.')
                        : __('Run a Droptimizer on raidbots.com, paste the URL here. The character must be linked to your account first.') }}
                </p>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    @click="showImportModal = false"
                    class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant font-headline text-xs font-bold uppercase tracking-widest hover:bg-white/5 transition"
                >
                    {{ __('Cancel') }}
                </button>
                <button
                    type="submit"
                    :disabled="submitting || !urlInput.trim()"
                    class="px-5 py-2 rounded-lg bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 font-headline text-xs font-bold uppercase tracking-widest hover:bg-cyan-500/30 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    <span v-if="submitting" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                    {{ submitting ? __('Importing…') : __('Import') }}
                </button>
            </div>
        </form>
    </GlassModal>

    <ToastNotification
        :show="toast.show"
        :message="toast.message"
        :icon="toast.icon"
        :icon-class="toast.iconClass"
    />
</template>
