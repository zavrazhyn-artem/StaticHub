<script setup>
import { ref, computed, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import WishlistItemCard from './WishlistItemCard.vue';
import WishlistClaimantsModal from './WishlistClaimantsModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    payload: { type: Array, required: true },
    // Full list of the viewer's own characters in this static. Used so that
    // the empty-state lookup includes characters that don't have a wishlist
    // yet — those then get an "Import for this character" CTA.
    characters: { type: Array, default: () => [] },
    // Character + spec are owned by the parent so they stay in sync with the
    // Gear tab. specId may not match any imported wishlist (when none exist
    // yet for the chosen spec) — we fall back to availableSpecs[0] in that
    // case while leaving the parent's selection untouched.
    characterId: { type: [Number, String, null], default: null },
    specId: { type: [Number, String, null], default: null },
    csrfToken: { type: String, required: true },
    destroyUrlTemplate: { type: String, required: true },
});

const emit = defineEmits(['open-import', 'update:characterId', 'update:specId']);

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

const selectedDifficulty = ref('mythic');

// Claimants modal — opens when a card is clicked. Holds the item that was
// clicked so the modal can render its claimants list without a refetch.
const claimantsItem = ref(null);
const showClaimantsModal = computed(() => claimantsItem.value !== null);
const openClaimants = (item) => { claimantsItem.value = item; };
const closeClaimants = () => { claimantsItem.value = null; };

// Group items either by paper-doll slot or by boss they drop from. Choice
// is persisted across sessions so a user who prefers boss grouping doesn't
// have to switch every time they open the wishlist.
const GROUP_STORAGE_KEY = 'blastr.wishlist.groupBy.v1';
const groupBy = ref(localStorage.getItem(GROUP_STORAGE_KEY) || 'boss');
watch(groupBy, (v) => localStorage.setItem(GROUP_STORAGE_KEY, v));

const GROUP_TABS = [
    { id: 'boss', label: 'By boss', icon: 'skull' },
    { id: 'slot', label: 'By slot', icon: 'view_module' },
];

// Wishlists are spec-bound: a Holy wishlist and a Discipline wishlist for
// the same character are independent. Filter strictly by the parent's spec —
// if no wishlist exists for it, the empty-state CTA prompts the user to
// import one rather than silently showing some other spec's data.
const effectiveSpecId = computed(() => props.specId ?? null);

// Merge the wishlist payload with the full character roster so the empty
// state for a character without wishlists still resolves correctly.
const characters = computed(() => {
    const byId = new Map();
    props.payload.forEach(entry => byId.set(entry.character.id, entry));

    if (!props.characters.length) return Array.from(byId.values());

    return props.characters.map(ch => {
        const existing = byId.get(ch.id);
        if (existing) return existing;
        return {
            character: {
                id: ch.id,
                name: ch.name,
                realm: ch.realm,
                playable_class: ch.playable_class,
                avatar_url: ch.avatar_url,
                is_own: ch.is_own,
            },
            wishlists: [],
        };
    });
});

const currentCharacter = computed(() =>
    characters.value.find(c => c.character.id === props.characterId) ?? null);

// "Has data" is per (character, spec) pair — flipping spec must surface the
// import CTA when that specific spec has no wishlist yet.
const wishlistsForSpec = computed(() => {
    if (!currentCharacter.value || !effectiveSpecId.value) return [];
    return currentCharacter.value.wishlists.filter(w => w.spec_id === effectiveSpecId.value);
});

const hasAnyWishlist = computed(() => wishlistsForSpec.value.length > 0);

// Resolve the spec.role of the parent's selected spec so the empty-state CTA
// can switch from "Run a Droptimizer on raidbots" → "Run an Upgrade Report on
// QE Live" for healers (Raidbots doesn't sim healer throughput; QE does).
const currentRole = computed(() => {
    const ctxChar = props.characters.find(c => c.id === props.characterId);
    if (!ctxChar) return null;
    const spec = ctxChar.specs?.find(s => s.id === props.specId)
        ?? ctxChar.specs?.find(s => s.is_main)
        ?? ctxChar.specs?.[0];
    return spec?.role?.toLowerCase() ?? null;
});

const isHealer = computed(() => currentRole.value === 'heal' || currentRole.value === 'healer');

const currentSpecName = computed(() => {
    const ctxChar = props.characters.find(c => c.id === props.characterId);
    return ctxChar?.specs?.find(s => s.id === props.specId)?.name ?? '';
});

const importTargetLabel = computed(() => {
    const charName = currentCharacter.value?.character.name ?? '';
    return currentSpecName.value
        ? `${charName} (${currentSpecName.value})`
        : charName;
});

const availableDifficulties = computed(() =>
    new Set(wishlistsForSpec.value.map(w => w.difficulty)));

const availableSpecs = computed(() => {
    if (!currentCharacter.value) return [];
    const seen = new Map();
    currentCharacter.value.wishlists.forEach(w => {
        if (!seen.has(w.spec_id)) seen.set(w.spec_id, { id: w.spec_id, name: w.spec_name });
    });
    return Array.from(seen.values());
});

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
        && (!effectiveSpecId.value || w.spec_id === effectiveSpecId.value)
    );
});

// Items dedup'd by item_id (best value across difficulties/raids) — both
// groupings work off the same source list so switching the toggle doesn't
// re-derive item identity.
const dedupedItems = computed(() => {
    const bestById = new Map();
    activeWishlists.value.forEach(w => {
        w.items.forEach(item => {
            const existing = bestById.get(item.item_id);
            if (!existing || Number(item.value) > Number(existing.value)) {
                bestById.set(item.item_id, item);
            }
        });
    });
    return Array.from(bestById.values());
});

// Group by encounter — boss section header, items sorted by value desc,
// bosses sorted by their top-value item so the most useful drop surfaces.
const itemsByBoss = computed(() => {
    const buckets = new Map();
    dedupedItems.value.forEach(item => {
        const key = item.encounter_id ?? `__no_boss_${item.item_slot ?? 'unknown'}`;
        if (!buckets.has(key)) {
            buckets.set(key, {
                key,
                label: item.boss_name || 'Other',
                icon: 'skull',
                accent: 'text-orange-300/80',
                items: [],
                topValue: 0,
            });
        }
        const bucket = buckets.get(key);
        bucket.items.push(item);
        bucket.topValue = Math.max(bucket.topValue, Number(item.value) || 0);
    });
    buckets.forEach(b => b.items.sort((a, b2) => Number(b2.value) - Number(a.value)));
    return Array.from(buckets.values()).sort((a, b) => b.topValue - a.topValue);
});

// Group by paper-doll slot — same shape as boss buckets so the template
// renders both via a single v-for over the active grouping.
const itemsBySlot = computed(() => {
    const buckets = new Map();
    dedupedItems.value.forEach(item => {
        const slot = (item.item_slot || 'unknown').toLowerCase();
        if (!buckets.has(slot)) {
            buckets.set(slot, {
                key: slot,
                label: SLOT_LABELS[slot] || slot.replace(/_/g, ' '),
                icon: 'checkroom',
                accent: 'text-cyan-300/80',
                items: [],
                topValue: 0,
                slotOrder: SLOT_ORDER.indexOf(slot),
            });
        }
        const bucket = buckets.get(slot);
        bucket.items.push(item);
        bucket.topValue = Math.max(bucket.topValue, Number(item.value) || 0);
    });
    buckets.forEach(b => b.items.sort((a, b2) => Number(b2.value) - Number(a.value)));
    // Stable slot order (head → ... → ranged); unknown slots fall to the end.
    return Array.from(buckets.values()).sort((a, b) => {
        if (a.slotOrder === -1) return 1;
        if (b.slotOrder === -1) return -1;
        return a.slotOrder - b.slotOrder;
    });
});

const activeGrouping = computed(() => groupBy.value === 'slot' ? itemsBySlot.value : itemsByBoss.value);

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
            :title="__('No characters in this static')"
            :description="__('Invite members and assign characters to start tracking gear upgrades.')"
        />
    </div>

    <div v-else class="space-y-5">
        <!-- Filter bar (character is picked at the top level) -->
        <div v-if="hasAnyWishlist" class="bg-surface-container-low border border-white/5 rounded-xl p-4 flex flex-wrap items-center gap-4">
            <!-- Difficulty pills -->
            <div class="flex items-center gap-1">
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

            <!-- Group-by toggle -->
            <div class="flex items-center gap-1">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-2">{{ __('Group') }}</span>
                <button
                    v-for="g in GROUP_TABS"
                    :key="g.id"
                    type="button"
                    @click="groupBy = g.id"
                    :class="[
                        'px-3 py-1.5 rounded-full font-headline text-[11px] font-bold uppercase tracking-widest border flex items-center gap-1.5 transition',
                        groupBy === g.id
                            ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100'
                            : 'border-white/5 text-on-surface-variant/60 hover:text-white'
                    ]"
                >
                    <span class="material-symbols-outlined text-base">{{ g.icon }}</span>
                    {{ __(g.label) }}
                </button>
            </div>

            <!-- Last update -->
            <div v-if="lastImportedAt" class="ml-auto flex items-center gap-2 text-on-surface-variant text-[11px]">
                <span class="material-symbols-outlined text-base">schedule</span>
                <span>{{ formatDate(lastImportedAt) }}</span>
            </div>
        </div>

        <!-- Empty state when current character has no wishlists at all yet.
             Wording flips for healer specs: Raidbots doesn't sim healer
             throughput, but QE Live does — so we point them there instead. -->
        <div v-if="!hasAnyWishlist" class="bg-surface-container-low border border-white/5 rounded-xl p-12 text-center">
            <EmptyState
                icon="list_alt"
                :title="__('No wishlist for :name', { name: importTargetLabel })"
                :description="isHealer
                    ? __('Run an Upgrade Report on questionablyepic.com/live for this character, then paste the report URL to import.')
                    : __('Run a Droptimizer on raidbots.com for this character, then paste the report URL to import.')"
            />
            <button
                v-if="currentCharacter?.character.is_own"
                type="button"
                @click="emit('open-import')"
                class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-100 font-headline text-xs font-bold uppercase tracking-widest hover:bg-cyan-500/20 transition"
            >
                <span class="material-symbols-outlined text-base">add_link</span>
                {{ __('Import for :name', { name: importTargetLabel }) }}
            </button>
        </div>

        <!-- Empty state when wishlists exist but none match the chosen filter -->
        <div v-else-if="!activeWishlists.length" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
            <EmptyState
                icon="filter_alt_off"
                :title="__('No data for this filter')"
                :description="__('Try a different difficulty or spec.')"
            />
        </div>

        <!-- Grouped sections — header label/icon depends on the active group-by mode -->
        <div v-else class="space-y-4">
            <section
                v-for="bucket in activeGrouping"
                :key="bucket.key"
                class="bg-surface-container-low/60 border border-white/5 rounded-xl px-5 py-4"
            >
                <header class="flex items-baseline justify-between mb-3 pb-2 border-b border-white/5">
                    <h3 class="font-headline text-xs font-black text-white uppercase tracking-widest flex items-center gap-2">
                        <span :class="['material-symbols-outlined text-base', bucket.accent]">{{ bucket.icon }}</span>
                        {{ bucket.label }}
                        <span class="text-[10px] text-on-surface-variant font-normal normal-case tracking-normal">
                            ({{ bucket.items.length }})
                        </span>
                    </h3>
                </header>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                    <WishlistItemCard
                        v-for="item in bucket.items"
                        :key="`${item.item_id}-${item.value}`"
                        :item="item"
                        :difficulty-letter="difficultyShort"
                        @open-claimants="openClaimants"
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

    <!-- Claimants modal — opens from any item card; shows everyone in the
         static who has this item in their wishlist for the current bucket. -->
    <WishlistClaimantsModal
        :show="showClaimantsModal"
        :item="claimantsItem"
        @close="closeClaimants"
    />
</template>
