<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
import GearGrid from './GearGrid.vue';
import SlotItemPicker from './SlotItemPicker.vue';

const { __ } = useTranslation();

const props = defineProps({
    characterId: { type: [Number, String, null], default: null },
    specId: { type: [Number, String, null], default: null },
    context: { type: Array, required: true },
    enchantableSlots: { type: Array, default: () => [] },
    csrfToken: { type: String, required: true },
    listSummariesUrl: { type: String, required: true },
    activeListUrlTemplate: { type: String, required: true },
    gearListStoreUrl: { type: String, required: true },
    gearListDestroyUrlTemplate: { type: String, required: true },
    gearListSetSlotUrlTemplate: { type: String, required: true },
    gearListPickerUrlTemplate: { type: String, required: true },
    gearListExportSimcUrlTemplate: { type: String, required: true },
    gearListImportSimcUrlTemplate: { type: String, required: true },
    gearBisImportUrl: { type: String, required: true },
});

const emit = defineEmits(['update:characterId', 'update:specId']);

// ---------------------------------------------------------------------------
// Active context — character + spec both owned by the parent so they stay in
// sync between Gear and Wishlist tabs. Persistence handled at parent level.
// ---------------------------------------------------------------------------

const allChars = computed(() => props.context);
const currentCharacter = computed(() => allChars.value.find(c => c.id === props.characterId) ?? null);
const availableSpecs = computed(() => currentCharacter.value?.specs ?? []);

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

const listSummaries = ref([]);
const loadingSummaries = ref(false);
const activeListId = ref(null);
const activeList = ref(null);
const loadingActive = ref(false);

const fetchSummaries = async () => {
    if (!props.characterId || !props.specId) {
        listSummaries.value = [];
        activeListId.value = null;
        activeList.value = null;
        return;
    }
    loadingSummaries.value = true;
    try {
        const url = `${props.listSummariesUrl}?character_id=${props.characterId}&spec_id=${props.specId}`;
        const resp = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await resp.json();
        listSummaries.value = data.lists ?? [];
        // Pick first list automatically if none active or active not in new context
        if (!activeListId.value || !listSummaries.value.find(l => l.id === activeListId.value)) {
            activeListId.value = listSummaries.value[0]?.id ?? null;
        }
    } finally {
        loadingSummaries.value = false;
    }
};

const fetchActiveList = async () => {
    if (!activeListId.value) {
        activeList.value = null;
        return;
    }
    loadingActive.value = true;
    try {
        const url = props.activeListUrlTemplate.replace('__ID__', activeListId.value);
        const resp = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await resp.json();
        activeList.value = data.list ?? null;
    } finally {
        loadingActive.value = false;
    }
};

watch(() => [props.characterId, props.specId], () => fetchSummaries(), { immediate: true });
watch(activeListId, () => fetchActiveList());

// ---------------------------------------------------------------------------
// New list / Import modals
// ---------------------------------------------------------------------------

const showNewListModal = ref(false);
const showImportBisModal = ref(false);
const showImportSimcModal = ref(false);
const showExportSimcModal = ref(false);
const newListName = ref('');
const bisUrl = ref('');
const simcText = ref('');
const exportText = ref('');
const exportLoading = ref(false);
const exportCopied = ref(false);

const customCount = computed(() => listSummaries.value.filter(l => l.type === 'custom').length);

const submitFormPost = (action, data) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    const all = { _token: props.csrfToken, ...data };
    Object.entries(all).forEach(([k, v]) => {
        if (v === null || v === undefined) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        input.value = v;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
};

const submitFormDelete = (action) => {
    submitFormPost(action, { _method: 'DELETE' });
};

const createList = () => {
    if (!newListName.value.trim()) return;
    submitFormPost(props.gearListStoreUrl, {
        character_id: props.characterId,
        spec_id: props.specId,
        name: newListName.value.trim(),
    });
};

const importBis = () => {
    if (!bisUrl.value.trim()) return;
    submitFormPost(props.gearBisImportUrl, {
        character_id: props.characterId,
        spec_id: props.specId,
        url: bisUrl.value.trim(),
    });
};

const importSimc = () => {
    if (!simcText.value.trim() || !activeListId.value) return;
    const url = props.gearListImportSimcUrlTemplate.replace('__ID__', activeListId.value);
    submitFormPost(url, { simc: simcText.value });
};

const openExportSimc = async () => {
    if (!activeListId.value) return;
    exportText.value = '';
    exportCopied.value = false;
    exportLoading.value = true;
    showExportSimcModal.value = true;
    try {
        const url = props.gearListExportSimcUrlTemplate.replace('__ID__', activeListId.value);
        const resp = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await resp.json();
        exportText.value = data.simc ?? '';
    } catch (e) {
        exportText.value = '# ' + __('Failed to load:') + ' ' + (e?.message ?? e);
    } finally {
        exportLoading.value = false;
    }
};

const copyExportSimc = async () => {
    if (!exportText.value) return;
    try {
        await navigator.clipboard.writeText(exportText.value);
        exportCopied.value = true;
        setTimeout(() => { exportCopied.value = false; }, 2000);
    } catch {
        // Clipboard API may be blocked (insecure context); manual select fallback.
        const ta = document.querySelector('#simc-export-textarea');
        ta?.select();
        document.execCommand?.('copy');
        exportCopied.value = true;
        setTimeout(() => { exportCopied.value = false; }, 2000);
    }
};

const deleteList = (listId) => {
    if (!confirm(__('Delete this list?'))) return;
    submitFormDelete(props.gearListDestroyUrlTemplate.replace('__ID__', listId));
};

const clearSlot = async (slot) => {
    if (!activeListId.value) return;
    if (!confirm(__('Clear this slot?'))) return;
    const url = props.gearListSetSlotUrlTemplate.replace('__ID__', activeListId.value);
    try {
        const resp = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ slot, item_id: null }),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data.success === false) {
            alert(data.error || __('Failed to clear slot'));
            return;
        }
        if (data.list) activeList.value = data.list;
    } catch (e) {
        alert(String(e.message || e));
    }
};

const pickerSlot = ref(null);
const showPickerModal = computed(() => pickerSlot.value !== null);

const editSlot = (slot) => {
    if (!activeListId.value) return;
    pickerSlot.value = slot;
};
const closePicker = () => { pickerSlot.value = null; };

// Picker emits the refreshed list payload so we update in-place — no page reload.
const onPicked = (updatedList) => {
    if (updatedList) {
        activeList.value = updatedList;
        // The list-summaries sidebar also shows item_count; if it changed,
        // refresh that too. Cheap GET.
        fetchSummaries();
    } else {
        fetchActiveList();
    }
};

const isOwnContext = computed(() => currentCharacter.value?.is_own ?? false);

// Initial fetch happens via the immediate watch on [characterId, specId].

// Helpers for sidebar
const sourceBadge = (source) => ({
    bnet: 'auto',
    icy_veins: 'icy-veins',
    simc: '/simc',
    manual: 'manual',
}[source] ?? source);

const typeIcon = (type) => ({
    current: 'sync',
    bis: 'star',
    custom: 'list_alt',
}[type] ?? 'list_alt');

const typeColor = (type) => ({
    current: 'text-cyan-300',
    bis: 'text-yellow-300',
    custom: 'text-on-surface-variant',
}[type] ?? 'text-on-surface-variant');
</script>

<template>
    <div v-if="!allChars.length" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
        <EmptyState
            icon="group"
            :title="__('No characters in this static')"
            :description="__('Invite members and assign characters to start tracking gear.')"
        />
    </div>

    <div v-else class="space-y-5">
        <!-- Action bar (character + spec are picked at the top level) -->
        <div v-if="isOwnContext" class="bg-surface-container-low border border-white/5 rounded-xl p-4 flex flex-wrap items-center justify-end gap-2">
                <button
                    type="button"
                    @click="bisUrl = ''; showImportBisModal = true"
                    class="px-3 py-1.5 rounded-lg bg-yellow-500/10 border border-yellow-400/40 text-yellow-100 font-headline text-[11px] font-bold uppercase tracking-widest flex items-center gap-1.5 hover:bg-yellow-500/20 transition"
                >
                    <span class="material-symbols-outlined text-base">star</span>
                    {{ __('Import BiS') }}
                </button>
                <button
                    type="button"
                    @click="newListName = ''; showNewListModal = true"
                    :disabled="customCount >= 10"
                    class="px-3 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-100 font-headline text-[11px] font-bold uppercase tracking-widest flex items-center gap-1.5 hover:bg-cyan-500/20 transition disabled:opacity-40 disabled:cursor-not-allowed"
                    :title="customCount >= 10 ? __('Max 10 custom lists per spec') : ''"
                >
                    <span class="material-symbols-outlined text-base">add</span>
                    {{ __('New List') }} ({{ customCount }}/10)
                </button>
        </div>

        <!-- Sidebar + active list -->
        <div class="grid grid-cols-1 lg:grid-cols-[260px,1fr] gap-5">
            <!-- Sidebar: lists -->
            <aside class="bg-surface-container-low border border-white/5 rounded-xl p-3 space-y-1">
                <div v-if="loadingSummaries" class="px-3 py-4 text-xs text-on-surface-variant">{{ __('Loading…') }}</div>
                <div v-else-if="!listSummaries.length" class="px-3 py-4 text-xs text-on-surface-variant italic">
                    {{ __('No lists yet.') }}
                </div>
                <button
                    v-for="l in listSummaries"
                    :key="l.id"
                    type="button"
                    @click="activeListId = l.id"
                    :class="[
                        'w-full text-left px-3 py-2.5 rounded-lg flex items-center gap-2 transition',
                        activeListId === l.id
                            ? 'bg-cyan-500/10 border border-cyan-400/40'
                            : 'border border-transparent hover:bg-white/5'
                    ]"
                >
                    <span :class="['material-symbols-outlined text-base', typeColor(l.type)]">{{ typeIcon(l.type) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-white truncate">{{ l.name }}</div>
                        <div class="text-[10px] text-on-surface-variant uppercase tracking-widest flex items-center gap-1.5">
                            <span>{{ sourceBadge(l.source) }}</span>
                            <span>·</span>
                            <span>{{ l.item_count }} {{ __('items') }}</span>
                        </div>
                    </div>
                    <button
                        v-if="isOwnContext && l.type === 'custom'"
                        type="button"
                        @click.stop="deleteList(l.id)"
                        class="text-on-surface-variant/50 hover:text-error transition shrink-0"
                        :title="__('Delete')"
                    >
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </button>
            </aside>

            <!-- Active list -->
            <div class="space-y-3">
                <div v-if="loadingActive" class="bg-surface-container-low border border-white/5 rounded-xl p-12 text-center text-on-surface-variant">
                    {{ __('Loading…') }}
                </div>
                <div v-else-if="!activeList" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
                    <EmptyState
                        icon="checkroom"
                        :title="__('Pick a list to view')"
                        :description="__('Or create a custom one — or import BiS from icy-veins.')"
                    />
                </div>
                <div v-else>
                    <header class="flex items-center justify-between mb-3 px-1">
                        <div>
                            <h3 class="font-headline text-base font-black text-white uppercase tracking-widest flex items-center gap-2">
                                <span :class="['material-symbols-outlined text-lg', typeColor(activeList.type)]">{{ typeIcon(activeList.type) }}</span>
                                {{ activeList.name }}
                            </h3>
                            <div v-if="activeList.imported_at" class="text-[10px] text-on-surface-variant uppercase tracking-widest mt-0.5">
                                {{ __('Synced') }}: {{ new Date(activeList.imported_at).toLocaleString() }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="isOwnContext"
                                type="button"
                                @click="openExportSimc"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-400/40 text-emerald-100 font-headline text-[11px] font-bold uppercase tracking-widest flex items-center gap-1.5 hover:bg-emerald-500/20 transition"
                                :title="__('Copy a SimC string for this list — paste into Raidbots')"
                            >
                                <span class="material-symbols-outlined text-base">ios_share</span>
                                {{ __('Export /simc') }}
                            </button>
                            <button
                                v-if="isOwnContext && activeList.editable"
                                type="button"
                                @click="simcText = ''; showImportSimcModal = true"
                                class="px-3 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-100 font-headline text-[11px] font-bold uppercase tracking-widest flex items-center gap-1.5 hover:bg-cyan-500/20 transition"
                            >
                                <span class="material-symbols-outlined text-base">content_paste</span>
                                {{ __('Fill from /simc') }}
                            </button>
                        </div>
                    </header>

                    <GearGrid
                        :slots="activeList.slots"
                        :stats="activeList.stats"
                        :stats-placeholder="activeList.type === 'current' ? __('Sync your character to populate stats.') : __('Pick items via the slot picker to see set totals.')"
                        :enchantable-slots="enchantableSlots"
                        :audit="activeList.type === 'current'"
                        :editable="isOwnContext && activeList.editable"
                        :class-id="activeList.class_id"
                        :spec-id="activeList.spec_id"
                        @edit="editSlot"
                        @clear="clearSlot"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- New custom list modal -->
    <GlassModal :show="showNewListModal" @close="showNewListModal = false" max-width="max-w-md">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest">{{ __('New Gear List') }}</h3>
            <button type="button" @click="showNewListModal = false" class="text-on-surface-variant hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>
        <form @submit.prevent="createList" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">{{ __('Name') }}</label>
                <input
                    v-model="newListName"
                    type="text"
                    maxlength="80"
                    required
                    :placeholder="__('My M+ Setup')"
                    class="w-full px-4 py-2.5 bg-surface-container border border-white/10 rounded-lg text-white text-sm focus:outline-none focus:border-cyan-400"
                />
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="showNewListModal = false" class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant text-xs font-bold uppercase tracking-widest hover:bg-white/5">{{ __('Cancel') }}</button>
                <button type="submit" :disabled="!newListName.trim()" class="px-5 py-2 rounded-lg bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold uppercase tracking-widest hover:bg-cyan-500/30 disabled:opacity-50">{{ __('Create') }}</button>
            </div>
        </form>
    </GlassModal>

    <!-- Import BiS modal -->
    <GlassModal :show="showImportBisModal" @close="showImportBisModal = false" max-width="max-w-lg">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-yellow-300 text-base">star</span>
                {{ __('Import BiS from icy-veins') }}
            </h3>
            <button type="button" @click="showImportBisModal = false" class="text-on-surface-variant hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>
        <form @submit.prevent="importBis" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">{{ __('icy-veins URL') }}</label>
                <input
                    v-model="bisUrl"
                    type="url"
                    required
                    placeholder="https://www.icy-veins.com/wow/.../gear-best-in-slot"
                    class="w-full px-4 py-2.5 bg-surface-container border border-white/10 rounded-lg text-white text-sm font-mono focus:outline-none focus:border-cyan-400"
                />
                <p class="text-[11px] text-on-surface-variant/70 mt-2">
                    {{ __('Re-importing replaces the existing BiS list for this character + spec.') }}
                </p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="showImportBisModal = false" class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant text-xs font-bold uppercase tracking-widest hover:bg-white/5">{{ __('Cancel') }}</button>
                <button type="submit" :disabled="!bisUrl.trim()" class="px-5 py-2 rounded-lg bg-yellow-500/20 border border-yellow-400/40 text-yellow-100 text-xs font-bold uppercase tracking-widest hover:bg-yellow-500/30 disabled:opacity-50">{{ __('Import') }}</button>
            </div>
        </form>
    </GlassModal>

    <!-- Import simc into custom list modal -->
    <GlassModal :show="showImportSimcModal" @close="showImportSimcModal = false" max-width="max-w-2xl">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-cyan-300 text-base">content_paste</span>
                {{ __('Fill list from /simc') }}
            </h3>
            <button type="button" @click="showImportSimcModal = false" class="text-on-surface-variant hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>
        <form @submit.prevent="importSimc" class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">{{ __('Paste output of /simc') }}</label>
                <textarea
                    v-model="simcText"
                    rows="10"
                    required
                    placeholder="head=,id=250024,enchant_id=7961,bonus_id=6652/13335..."
                    class="w-full px-4 py-2.5 bg-surface-container border border-white/10 rounded-lg text-white text-xs font-mono focus:outline-none focus:border-cyan-400"
                ></textarea>
                <p class="text-[11px] text-on-surface-variant/70 mt-2">
                    {{ __('In-game type /simc, copy the output, paste here. Replaces this list contents.') }}
                </p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" @click="showImportSimcModal = false" class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant text-xs font-bold uppercase tracking-widest hover:bg-white/5">{{ __('Cancel') }}</button>
                <button type="submit" :disabled="!simcText.trim()" class="px-5 py-2 rounded-lg bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold uppercase tracking-widest hover:bg-cyan-500/30 disabled:opacity-50">{{ __('Apply') }}</button>
            </div>
        </form>
    </GlassModal>

    <!-- Export SimC modal — read-only paste-and-copy textarea for Raidbots -->
    <GlassModal :show="showExportSimcModal" @close="showExportSimcModal = false" max-width="max-w-2xl">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-300 text-base">ios_share</span>
                {{ __('Export /simc') }}
            </h3>
            <button type="button" @click="showExportSimcModal = false" class="text-on-surface-variant hover:text-white">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>
        <div class="p-6 space-y-4">
            <p class="text-[11px] text-on-surface-variant/80">
                {{ __('Copy this and paste it into:') }}
                <a href="https://www.raidbots.com/simbot/topgear" target="_blank" rel="noopener" class="text-cyan-300 hover:text-cyan-200 underline">raidbots.com</a>
                {{ __('(DPS / Tank — Top Gear / Droptimizer)') }}
                {{ __('or') }}
                <a href="https://questionablyepic.com/live" target="_blank" rel="noopener" class="text-cyan-300 hover:text-cyan-200 underline">questionablyepic.com/live</a>
                {{ __('(Healers — Top Gear / Upgrade Finder).') }}
            </p>
            <div v-if="exportLoading" class="text-center py-8 text-on-surface-variant">
                <span class="material-symbols-outlined text-2xl animate-spin">progress_activity</span>
            </div>
            <textarea
                v-else
                id="simc-export-textarea"
                :value="exportText"
                readonly
                rows="14"
                class="w-full px-4 py-2.5 bg-surface-container border border-white/10 rounded-lg text-white text-xs font-mono focus:outline-none focus:border-cyan-400 resize-y"
                @click="$event.target.select()"
            ></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" @click="showExportSimcModal = false" class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant text-xs font-bold uppercase tracking-widest hover:bg-white/5">
                    {{ __('Close') }}
                </button>
                <button
                    type="button"
                    @click="copyExportSimc"
                    :disabled="!exportText || exportLoading"
                    class="px-5 py-2 rounded-lg bg-emerald-500/20 border border-emerald-400/40 text-emerald-100 text-xs font-bold uppercase tracking-widest hover:bg-emerald-500/30 disabled:opacity-50 flex items-center gap-1.5 transition"
                >
                    <span class="material-symbols-outlined text-base">{{ exportCopied ? 'check' : 'content_copy' }}</span>
                    {{ exportCopied ? __('Copied!') : __('Copy') }}
                </button>
            </div>
        </div>
    </GlassModal>

    <!-- Slot picker — opens when the user clicks an editable slot in GearGrid -->
    <SlotItemPicker
        :show="showPickerModal"
        :list-id="activeListId"
        :slot="pickerSlot ?? ''"
        :picker-url-template="gearListPickerUrlTemplate"
        :set-slot-url-template="gearListSetSlotUrlTemplate"
        :csrf-token="csrfToken"
        @close="closePicker"
        @picked="onPicked"
    />
</template>
