<script setup>
import { ref, computed, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import WishlistItemCard from './WishlistItemCard.vue';

const { __ } = useTranslation();

const props = defineProps({
    payload: { type: Array, required: true },
    csrfToken: { type: String, required: true },
    destroyUrlTemplate: { type: String, required: true },
});

const SLOT_ORDER = [
    'head', 'neck', 'shoulder', 'back', 'chest', 'wrist',
    'hands', 'waist', 'legs', 'feet',
    'finger', 'trinket',
    'main_hand', 'off_hand', 'two_hand', 'ranged',
];

const SLOT_LABELS = {
    head: 'Head', neck: 'Neck', shoulder: 'Shoulder', back: 'Back', chest: 'Chest',
    wrist: 'Wrist', hands: 'Hands', waist: 'Waist', legs: 'Legs', feet: 'Feet',
    finger: 'Finger', trinket: 'Trinket',
    main_hand: 'Main Hand', off_hand: 'Off Hand', two_hand: 'Two Hand', ranged: 'Ranged',
};

const DIFFICULTIES = [
    { id: 'mythic', label: 'Mythic', short: 'M', accent: 'border-orange-300/60 text-orange-200 bg-orange-500/10' },
    { id: 'heroic', label: 'Heroic', short: 'H', accent: 'border-purple-300/60 text-purple-200 bg-purple-500/10' },
    { id: 'normal', label: 'Normal', short: 'N', accent: 'border-blue-300/60 text-blue-200 bg-blue-500/10' },
    { id: 'raid_finder', label: 'LFR', short: 'R', accent: 'border-emerald-300/60 text-emerald-200 bg-emerald-500/10' },
];

const selectedCharacterId = ref(null);
const selectedDifficulty = ref('mythic');
const selectedSpecId = ref(null);

const characters = computed(() => props.payload);

watch(characters, (list) => {
    if (!list.length) { selectedCharacterId.value = null; return; }
    if (!list.find(c => c.character.id === selectedCharacterId.value)) {
        selectedCharacterId.value = list[0].character.id;
    }
}, { immediate: true });

const currentCharacter = computed(() =>
    characters.value.find(c => c.character.id === selectedCharacterId.value) ?? null);

const availableDifficulties = computed(() => {
    if (!currentCharacter.value) return new Set();
    return new Set(currentCharacter.value.wishlists.map(w => w.difficulty));
});

const availableSpecs = computed(() => {
    if (!currentCharacter.value) return [];
    const seen = new Map();
    currentCharacter.value.wishlists.forEach(w => {
        if (!seen.has(w.spec_id)) seen.set(w.spec_id, { id: w.spec_id, name: w.spec_name });
    });
    return Array.from(seen.values());
});

watch(availableSpecs, (specs) => {
    if (!specs.length) { selectedSpecId.value = null; return; }
    if (!specs.find(s => s.id === selectedSpecId.value)) {
        selectedSpecId.value = specs[0].id;
    }
}, { immediate: true });

watch(availableDifficulties, (difficulties) => {
    if (!difficulties.has(selectedDifficulty.value)) {
        const first = DIFFICULTIES.find(d => difficulties.has(d.id));
        if (first) selectedDifficulty.value = first.id;
    }
}, { immediate: true });

const activeWishlists = computed(() => {
    if (!currentCharacter.value) return [];
    return currentCharacter.value.wishlists.filter(w =>
        w.difficulty === selectedDifficulty.value
        && (!selectedSpecId.value || w.spec_id === selectedSpecId.value)
    );
});

const itemsBySlot = computed(() => {
    // Items can be sim'd in multiple wishlists (e.g. catalyst items appear
    // in two raid instances). Keep one entry per item_id — the highest-
    // value one — so the UI doesn't show the same item twice in the same slot.
    const bestById = new Map();
    activeWishlists.value.forEach(w => {
        w.items.forEach(item => {
            const existing = bestById.get(item.item_id);
            if (!existing || Number(item.value) > Number(existing.value)) {
                bestById.set(item.item_id, item);
            }
        });
    });

    const buckets = {};
    bestById.forEach(item => {
        const slot = (item.item_slot || 'unknown').toLowerCase();
        buckets[slot] ??= [];
        buckets[slot].push(item);
    });
    Object.values(buckets).forEach(list => list.sort((a, b) => Number(b.value) - Number(a.value)));
    return buckets;
});

const renderedSlots = computed(() => {
    const present = new Set(Object.keys(itemsBySlot.value));
    return SLOT_ORDER.filter(s => present.has(s)).concat(
        Array.from(present).filter(s => !SLOT_ORDER.includes(s))
    );
});

const slotLabel = (s) => SLOT_LABELS[s] || s.replace(/_/g, ' ');

const difficultyShort = computed(() => {
    const d = DIFFICULTIES.find(d => d.id === selectedDifficulty.value);
    return d?.short ?? '';
});

const lastImportedAt = computed(() => {
    if (!activeWishlists.value.length) return null;
    return activeWishlists.value
        .map(w => w.imported_at)
        .filter(Boolean)
        .sort()
        .at(-1);
});

const formatDate = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
};

const deleteWishlist = (wishlistId) => {
    if (!confirm(__('Delete this wishlist?'))) return;
    const url = props.destroyUrlTemplate.replace('__ID__', wishlistId);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.innerHTML = `
        <input type="hidden" name="_token" value="${props.csrfToken}">
        <input type="hidden" name="_method" value="DELETE">
    `;
    document.body.appendChild(form);
    form.submit();
};
</script>

<template>
    <div v-if="!characters.length" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
        <EmptyState
            icon="list_alt"
            :title="__('No wishlists yet')"
            :description="__('Import a Raidbots Droptimizer URL to start tracking gear upgrades.')"
        />
    </div>

    <div v-else class="space-y-5">
        <!-- Filter bar -->
        <div class="bg-surface-container-low border border-white/5 rounded-xl p-4 flex flex-wrap items-center gap-4">
            <!-- Character selector -->
            <div v-if="characters.length > 1" class="flex items-center gap-2">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest">{{ __('Character') }}</span>
                <select
                    v-model="selectedCharacterId"
                    class="bg-surface-container border border-white/10 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-cyan-400"
                >
                    <option v-for="c in characters" :key="c.character.id" :value="c.character.id">
                        {{ c.character.name }}
                    </option>
                </select>
            </div>

            <div v-else-if="currentCharacter" class="flex items-center gap-2">
                <img
                    v-if="currentCharacter.character.avatar_url"
                    :src="currentCharacter.character.avatar_url"
                    alt=""
                    class="w-8 h-8 rounded-full ring-1 ring-white/10"
                />
                <div>
                    <div class="text-sm font-bold text-white">{{ currentCharacter.character.name }}</div>
                    <div class="text-[10px] text-on-surface-variant uppercase tracking-widest">
                        {{ currentCharacter.character.realm }} · {{ currentCharacter.character.playable_class }}
                    </div>
                </div>
            </div>

            <!-- Difficulty pills -->
            <div class="flex items-center gap-1 ml-auto">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-2">{{ __('Difficulty') }}</span>
                <button
                    v-for="d in DIFFICULTIES"
                    :key="d.id"
                    type="button"
                    :disabled="!availableDifficulties.has(d.id)"
                    @click="availableDifficulties.has(d.id) && (selectedDifficulty = d.id)"
                    :class="[
                        'px-3 py-1.5 rounded-full font-headline text-[11px] font-bold uppercase tracking-widest border transition',
                        selectedDifficulty === d.id
                            ? d.accent
                            : 'border-white/5 text-on-surface-variant/50',
                        availableDifficulties.has(d.id) ? 'cursor-pointer hover:text-white' : 'opacity-30 cursor-not-allowed',
                    ]"
                >
                    {{ d.label }}
                </button>
            </div>

            <!-- Spec switcher -->
            <div v-if="availableSpecs.length > 1" class="flex items-center gap-1">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-2">{{ __('Spec') }}</span>
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
                </button>
            </div>

            <!-- Last update -->
            <div v-if="lastImportedAt" class="flex items-center gap-2 text-on-surface-variant text-[11px]">
                <span class="material-symbols-outlined text-base">schedule</span>
                <span>{{ formatDate(lastImportedAt) }}</span>
            </div>
        </div>

        <!-- Empty state if no wishlist for filter -->
        <div v-if="!activeWishlists.length" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
            <EmptyState
                icon="filter_alt_off"
                :title="__('No data for this filter')"
                :description="__('Try a different difficulty or spec.')"
            />
        </div>

        <!-- Slot-grouped grid -->
        <div v-else class="space-y-4">
            <section
                v-for="slot in renderedSlots"
                :key="slot"
                class="bg-surface-container-low/60 border border-white/5 rounded-xl px-5 py-4"
            >
                <header class="flex items-baseline justify-between mb-3 pb-2 border-b border-white/5">
                    <h3 class="font-headline text-xs font-black text-white uppercase tracking-widest flex items-center gap-2">
                        {{ slotLabel(slot) }}
                        <span class="text-[10px] text-on-surface-variant font-normal normal-case tracking-normal">
                            ({{ itemsBySlot[slot].length }})
                        </span>
                    </h3>
                </header>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                    <WishlistItemCard
                        v-for="item in itemsBySlot[slot]"
                        :key="`${item.item_id}-${item.value}`"
                        :item="item"
                        :difficulty-letter="difficultyShort"
                    />
                </div>
            </section>
        </div>

        <!-- Wishlist management (own characters) -->
        <div v-if="currentCharacter?.character.is_own && activeWishlists.length" class="flex justify-end">
            <button
                v-for="wl in activeWishlists"
                :key="wl.id"
                type="button"
                @click="deleteWishlist(wl.id)"
                class="text-[11px] text-on-surface-variant hover:text-error transition flex items-center gap-1.5 px-2 py-1"
                :title="__('Delete this wishlist')"
            >
                <span class="material-symbols-outlined text-sm">delete</span>
                <span>{{ __('Delete') }} {{ wl.raid_slug }}</span>
            </button>
        </div>
    </div>
</template>
