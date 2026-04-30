<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
import GearGrid from './GearGrid.vue';

const { __ } = useTranslation();

const props = defineProps({
    context: { type: Array, required: true },
    enchantableSlots: { type: Array, default: () => [] },
    csrfToken: { type: String, required: true },
    listSummariesUrl: { type: String, required: true },
    activeListUrlTemplate: { type: String, required: true },
    gearListStoreUrl: { type: String, required: true },
    gearListDestroyUrlTemplate: { type: String, required: true },
    gearListSetSlotUrlTemplate: { type: String, required: true },
    gearListImportSimcUrlTemplate: { type: String, required: true },
    gearBisImportUrl: { type: String, required: true },
});

// ---------------------------------------------------------------------------
// Active context (character + spec) — persisted to localStorage
// ---------------------------------------------------------------------------

const STORAGE_KEY = 'blastr.gear.context.v1';

const selectedCharacterId = ref(null);
const selectedSpecId = ref(null);

const restoreFromStorage = () => {
    try {
        const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
        if (stored?.charId && props.context.find(c => c.id === stored.charId)) {
            selectedCharacterId.value = stored.charId;
            const char = props.context.find(c => c.id === stored.charId);
            if (stored.specId && char.specs.find(s => s.id === stored.specId)) {
                selectedSpecId.value = stored.specId;
            }
        }
    } catch (e) { /* ignore */ }
};

const persist = () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
        charId: selectedCharacterId.value,
        specId: selectedSpecId.value,
    }));
};

const ownChars = computed(() => props.context.filter(c => c.is_own));
const allChars = computed(() => props.context);

const currentCharacter = computed(() => allChars.value.find(c => c.id === selectedCharacterId.value) ?? null);

const availableSpecs = computed(() => currentCharacter.value?.specs ?? []);

watch(selectedCharacterId, (id) => {
    persist();
    const char = allChars.value.find(c => c.id === id);
    if (!char) { selectedSpecId.value = null; return; }
    if (!char.specs.find(s => s.id === selectedSpecId.value)) {
        const main = char.specs.find(s => s.is_main) || char.specs[0];
        selectedSpecId.value = main?.id ?? null;
    }
});

watch(selectedSpecId, () => persist());

// ---------------------------------------------------------------------------
// Lists
// ---------------------------------------------------------------------------

const listSummaries = ref([]);
const loadingSummaries = ref(false);
const activeListId = ref(null);
const activeList = ref(null);
const loadingActive = ref(false);

const fetchSummaries = async () => {
    if (!selectedCharacterId.value || !selectedSpecId.value) {
        listSummaries.value = [];
        activeListId.value = null;
        activeList.value = null;
        return;
    }
    loadingSummaries.value = true;
    try {
        const url = `${props.listSummariesUrl}?character_id=${selectedCharacterId.value}&spec_id=${selectedSpecId.value}`;
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

watch([selectedCharacterId, selectedSpecId], () => fetchSummaries());
watch(activeListId, () => fetchActiveList());

// ---------------------------------------------------------------------------
// New list / Import modals
// ---------------------------------------------------------------------------

const showNewListModal = ref(false);
const showImportBisModal = ref(false);
const showImportSimcModal = ref(false);
const newListName = ref('');
const bisUrl = ref('');
const simcText = ref('');

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
        character_id: selectedCharacterId.value,
        spec_id: selectedSpecId.value,
        name: newListName.value.trim(),
    });
};

const importBis = () => {
    if (!bisUrl.value.trim()) return;
    submitFormPost(props.gearBisImportUrl, {
        character_id: selectedCharacterId.value,
        spec_id: selectedSpecId.value,
        url: bisUrl.value.trim(),
    });
};

const importSimc = () => {
    if (!simcText.value.trim() || !activeListId.value) return;
    const url = props.gearListImportSimcUrlTemplate.replace('__ID__', activeListId.value);
    submitFormPost(url, { simc: simcText.value });
};

const deleteList = (listId) => {
    if (!confirm(__('Delete this list?'))) return;
    submitFormDelete(props.gearListDestroyUrlTemplate.replace('__ID__', listId));
};

const clearSlot = (slot) => {
    if (!activeListId.value) return;
    if (!confirm(__('Clear this slot?'))) return;
    const url = props.gearListSetSlotUrlTemplate.replace('__ID__', activeListId.value);
    submitFormPost(url, { _method: 'PATCH', slot, item_id: '' });
};

const editSlot = () => {
    alert(__('Slot editing UI coming with the season seed pipeline. For now, fill custom lists via the Import simc paste.'));
};

const isOwnContext = computed(() => currentCharacter.value?.is_own ?? false);

// Boot
onMounted(() => {
    restoreFromStorage();
    if (!selectedCharacterId.value) {
        const first = ownChars.value[0] || allChars.value[0];
        if (first) {
            selectedCharacterId.value = first.id;
            const main = first.specs.find(s => s.is_main) || first.specs[0];
            selectedSpecId.value = main?.id ?? null;
        }
    }
    fetchSummaries();
});

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
        <!-- Context selector -->
        <div class="bg-surface-container-low border border-white/5 rounded-xl p-4 flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest">{{ __('Character') }}</span>
                <select
                    v-model="selectedCharacterId"
                    class="bg-surface-container border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-cyan-400 max-w-[220px]"
                >
                    <option v-for="c in allChars" :key="c.id" :value="c.id">
                        {{ c.name }} {{ c.is_own ? '★' : '' }}
                    </option>
                </select>
            </div>

            <div v-if="availableSpecs.length" class="flex items-center gap-1">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-1">{{ __('Spec') }}</span>
                <button
                    v-for="s in availableSpecs"
                    :key="s.id"
                    type="button"
                    @click="selectedSpecId = s.id"
                    :class="[
                        'px-3 py-1.5 rounded-full text-[11px] font-bold border transition',
                        selectedSpecId === s.id
                            ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100'
                            : 'border-white/5 text-on-surface-variant/60 hover:text-white'
                    ]"
                >
                    {{ s.name }}
                    <span v-if="s.is_main" class="ml-1 text-[9px] opacity-60">main</span>
                </button>
            </div>

            <div v-if="isOwnContext" class="ml-auto flex items-center gap-2">
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
                        <button
                            v-if="isOwnContext && activeList.editable"
                            type="button"
                            @click="simcText = ''; showImportSimcModal = true"
                            class="px-3 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-100 font-headline text-[11px] font-bold uppercase tracking-widest flex items-center gap-1.5 hover:bg-cyan-500/20 transition"
                        >
                            <span class="material-symbols-outlined text-base">content_paste</span>
                            {{ __('Fill from /simc') }}
                        </button>
                    </header>

                    <GearGrid
                        :slots="activeList.slots"
                        :stats="activeList.stats"
                        :stats-placeholder="activeList.type === 'current' ? __('Sync your character to populate stats.') : __('Stats are only available for the Current Equipment list.')"
                        :enchantable-slots="enchantableSlots"
                        :audit="activeList.type === 'current'"
                        :editable="isOwnContext && activeList.editable"
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
                    placeholder="My M+ Setup"
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
</template>
