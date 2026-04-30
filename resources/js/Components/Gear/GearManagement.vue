<script setup>
import { ref, onMounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
import ToastNotification from '@/Components/UI/ToastNotification.vue';
import GearTab from './Tab/GearTab.vue';
import WishlistPanel from './Wishlist/WishlistPanel.vue';

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
    gearListImportSimcUrlTemplate: { type: String, required: true },
    gearBisImportUrl: { type: String, required: true },
    listSummariesUrl: { type: String, required: true },
    activeListUrlTemplate: { type: String, required: true },
    csrfToken: { type: String, required: true },
    flashSuccess: { type: String, default: '' },
    flashError: { type: String, default: '' },
});

const showImportModal = ref(false);
const submitting = ref(false);
const importForm = ref(null);
const urlInput = ref('');
const activeTab = ref('gear');

const toast = ref({ show: false, message: '', icon: 'check_circle', iconClass: 'text-success-neon' });

const tabs = [
    { id: 'gear', icon: 'checkroom', label: 'Gear' },
    { id: 'wishlist', icon: 'list_alt', label: 'Wishlist' },
    { id: 'loot', icon: 'history', label: 'Loot History', disabled: true },
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

    <!-- Tabs bar -->
    <nav class="flex gap-1 border-b border-white/5 mb-6">
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

    <GearTab
        v-if="activeTab === 'gear'"
        :context="gearContext"
        :enchantable-slots="enchantableSlots"
        :csrf-token="csrfToken"
        :list-summaries-url="listSummariesUrl"
        :active-list-url-template="activeListUrlTemplate"
        :gear-list-store-url="gearListStoreUrl"
        :gear-list-destroy-url-template="gearListDestroyUrlTemplate"
        :gear-list-set-slot-url-template="gearListSetSlotUrlTemplate"
        :gear-list-import-simc-url-template="gearListImportSimcUrlTemplate"
        :gear-bis-import-url="gearBisImportUrl"
    />

    <WishlistPanel
        v-else-if="activeTab === 'wishlist'"
        :payload="wishlistPayload"
        :csrf-token="csrfToken"
        :destroy-url-template="destroyUrlTemplate"
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
                    {{ __('Raidbots Droptimizer URL') }}
                </label>
                <input
                    v-model="urlInput"
                    name="url"
                    type="url"
                    required
                    placeholder="https://www.raidbots.com/simbot/report/..."
                    class="w-full px-4 py-3 bg-surface-container border border-white/10 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-cyan-400 transition"
                />
                <p class="text-[11px] text-on-surface-variant/70 mt-2">
                    {{ __('Run a Droptimizer on raidbots.com, paste the URL here. The character must be linked to your account first.') }}
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
