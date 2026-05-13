<script setup>
import { ref, computed, watch, onMounted, onUpdated, onBeforeUnmount } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import EmptyState from '@/Components/UI/EmptyState.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';
import StyledSelect from '@/Components/UI/StyledSelect.vue';
import WishlistItemCard from './WishlistItemCard.vue';
import WishlistClaimantsModal from './WishlistClaimantsModal.vue';

const { __ } = useTranslation();

const props = defineProps({
    payload: { type: Array, required: true },
    // Full list of the viewer's own characters in this static. Used so that
    // the empty-state lookup includes characters that don't have a wishlist
    // yet — those then get an "Import for this character" CTA.
    characters: { type: Array, default: () => [] },
    // Allowed Droptimizer configs from the static settings — drives the
    // "Run on Raidbots" deep-link buttons in empty state and the matched
    // badge on imported wishlists.
    configs: { type: Array, default: () => [] },
    // Character + spec are owned by the parent so they stay in sync with the
    // Gear tab. specId may not match any imported wishlist (when none exist
    // yet for the chosen spec) — we fall back to availableSpecs[0] in that
    // case while leaving the parent's selection untouched.
    characterId: { type: [Number, String, null], default: null },
    specId: { type: [Number, String, null], default: null },
    csrfToken: { type: String, required: true },
    destroyUrlTemplate: { type: String, required: true },
    // Officers/leaders only — gates the per-player chip strip. Members
    // are already server-side scoped to their own wishlists, so a picker
    // would be a degenerate "one chip" UI.
    allowPlayerPicker: { type: Boolean, default: false },
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

// "Mains only" — default ON. Hides alts and off-spec wishlists so the
// raid lead's normal view is "what each main wants for their main spec".
// Persisted so a user who flips it off doesn't have to repeat themselves.
const MAINS_ONLY_KEY = 'blastr.wishlist.mainsOnly.v1';
const mainsOnly = ref(localStorage.getItem(MAINS_ONLY_KEY) !== '0');
watch(mainsOnly, (v) => localStorage.setItem(MAINS_ONLY_KEY, v ? '1' : '0'));

// Boss/slot filter — value is a bucket key (encounter_id for boss mode,
// slot string for slot mode), or null for "all". Resets whenever the user
// flips groupBy because boss-keys don't apply to slot mode and vice versa.
const filterValue = ref(null);
watch(groupBy, () => { filterValue.value = null; });

// Wishlists are spec-bound: a Holy wishlist and a Discipline wishlist for
// the same character are independent. Filter strictly by the parent's spec —
// if no wishlist exists for it, the empty-state CTA prompts the user to
// import one rather than silently showing some other spec's data.
const effectiveSpecId = computed(() => props.specId ?? null);

// Merge the wishlist payload with the viewer's own roster.
// Payload is authoritative for visibility — for officers/leaders it spans
// every player in the static; for members it's already server-scoped to
// their own characters. props.characters (gearContext) is the viewer's
// OWN roster only, used to add empty-state stubs for the viewer's chars
// that haven't imported wishlists yet. The old implementation overwrote
// payload with gearContext, which silently dropped every other player's
// character from the grid (and from the player chip strip).
const characters = computed(() => {
    const byId = new Map();
    props.payload.forEach(entry => byId.set(entry.character.id, entry));

    props.characters.forEach(ch => {
        if (byId.has(ch.id)) return;
        byId.set(ch.id, {
            character: {
                id: ch.id,
                name: ch.name,
                realm: ch.realm,
                playable_class: ch.playable_class,
                avatar_url: ch.avatar_url,
                is_own: ch.is_own,
            },
            wishlists: [],
        });
    });
    return Array.from(byId.values());
});

const currentCharacter = computed(() =>
    characters.value.find(c => c.character.id === props.characterId) ?? null);

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

// Build a Raidbots Droptimizer URL — only character is URL-bindable on
// raidbots.com (region/realm/name). Form fields like fightStyle /
// numEnemies / fightLength / maxIlvl are React state and silently
// ignored when passed via query string. Config-specific values are
// shown next to the link inside the chooser modal so the user knows
// what to set manually on the Raidbots form once the page loads.
const raidbotsLink = () => {
    const ctxChar = props.characters.find(c => c.id === props.characterId);
    const params = new URLSearchParams();
    if (ctxChar?.region) params.set('region', String(ctxChar.region).toLowerCase());
    if (ctxChar?.realm)  params.set('realm',  String(ctxChar.realm));
    if (ctxChar?.name)   params.set('name',   String(ctxChar.name));
    const qs = params.toString();
    return 'https://www.raidbots.com/simbot/droptimizer' + (qs ? '?' + qs : '');
};

// Modal that lists every allowed config so the user can pick one before
// jumping to Raidbots. The link itself only prefills the character —
// per-config values are shown as a checklist the user copies into the
// Raidbots form (URL params for the form fields are silently ignored).
const showRaidbotsModal = ref(false);

const formatLength = (min) => `${min} ${__('min')}`;
const formatOp = (op) => ({
    is: 'is', at_least: '≥', at_most: '≤', less_than: '<', more_than: '>',
})[op] ?? op;

// Always show every field the matcher checks (style, bosses, length,
// all 4 difficulty upgrade levels) so the user sees the complete
// picture; "—" means no constraint for that field. Boolean toggles
// (PI, Voidforged, Upgrade All, Vault Socket, Custom APL) only render
// when they actively impose a constraint, since "not required" gives
// the player nothing to set on Raidbots.
const configChecklist = (c) => {
    const items = [
        { label: __('Fight Style'),   value: c.fight_style ?? '—' },
        { label: __('Bosses'),        value: c.num_bosses_op ? `${formatOp(c.num_bosses_op)} ${c.num_bosses}` : '—' },
        { label: __('Fight Length'),  value: c.fight_length_op ? `${formatOp(c.fight_length_op)} ${formatLength(c.fight_length_minutes)}` : '—' },
        { label: __('Mythic ilvl'),   value: c.upgrade_level_mythic || '—' },
        { label: __('Heroic ilvl'),   value: c.upgrade_level_heroic || '—' },
        { label: __('Normal ilvl'),   value: c.upgrade_level_normal || '—' },
        { label: __('LFR ilvl'),      value: c.upgrade_level_lfr    || '—' },
    ];
    if (c.require_pi)               items.push({ label: __('Power Infusion'),       value: __('required') });
    if (c.voidforged)               items.push({ label: __('Voidforged'),           value: __('required') });
    if (!c.allow_expert)            items.push({ label: __('Custom APL / Expert'),  value: __('not allowed') });
    if (c.require_upgrade_all_same) items.push({ label: __('Upgrade All Equipped'), value: __('required') });
    if (c.require_vault_socket)     items.push({ label: __('Add Vault Socket'),     value: __('required') });
    return items;
};

// Per-player chip strip — one chip per user_id, even when that user has
// several characters (main + alts) in this static. Face = the user's main
// character in the static; if none, fall back to whichever character we
// have. Alt count drives the "+N" badge. Selection is character-level
// (selectedCharIds) so the kebab popover can drill into specific alts
// independently of the user-level toggle. Ephemeral across page loads —
// lock-in across statics rarely matches RL intent.
const selectedCharIds = ref(new Set());
const openKebabUserId = ref(null);

const players = computed(() => {
    const byUser = new Map();
    characters.value.forEach(entry => {
        const owner = entry.character.owner;
        if (!owner?.user_id) return;
        const bucket = byUser.get(owner.user_id) ?? {
            user_id: owner.user_id,
            user_name: owner.user_name,
            characters: [],
            mainCharacter: null,
        };
        bucket.characters.push(entry.character);
        if (entry.character.static_role === 'main' && !bucket.mainCharacter) {
            bucket.mainCharacter = entry.character;
        }
        byUser.set(owner.user_id, bucket);
    });
    // Normalise the chip "face": prefer the static's main character,
    // otherwise the first character we have for that user.
    return Array.from(byUser.values())
        .map(b => {
            const face = b.mainCharacter ?? b.characters[0];
            return {
                ...b,
                face,
                alts_count: Math.max(0, b.characters.length - 1),
            };
        })
        .sort((a, b) => (a.face?.name ?? '').localeCompare(b.face?.name ?? ''));
});

// Tri-state per chip: 'none' (no chars selected), 'full' (all chars
// selected), 'partial' (subset). Drives the chip's visual state — only
// 'full' and 'partial' get the bright outline. 'partial' adds a tiny
// indicator so the RL knows the user has narrowed selection further.
const userSelectionState = (player) => {
    if (!player.characters.length) return 'none';
    const sel = player.characters.filter(c => selectedCharIds.value.has(c.id)).length;
    if (sel === 0) return 'none';
    if (sel === player.characters.length) return 'full';
    return 'partial';
};

// Main click on chip body — toggles just the player's MAIN character
// (the chip face). Alts stay out of selection unless the RL explicitly
// picks them via the kebab menu — that's the intended "main is the
// default story; alts are the side quest" semantic.
const toggleUser = (player) => {
    if (!player.face) return;
    toggleChar(player.face.id);
};

const toggleChar = (charId) => {
    const next = new Set(selectedCharIds.value);
    if (next.has(charId)) next.delete(charId); else next.add(charId);
    selectedCharIds.value = next;
};

const clearPlayers = () => { selectedCharIds.value = new Set(); };

// Auto-disable "Mains only" the moment an alt is explicitly selected —
// otherwise the alt char would be filtered out anyway and the user's
// click would silently do nothing. Only fires when an alt becomes
// selected (not on deselect) so re-enabling Mains-only manually still
// works as expected.
watch(selectedCharIds, (set) => {
    if (!mainsOnly.value) return;
    for (const charId of set) {
        for (const p of players.value) {
            const c = p.characters.find(c => c.id === charId);
            if (c && c.static_role === 'alt') {
                mainsOnly.value = false;
                return;
            }
        }
    }
});

// Close any open kebab popover when clicking outside chip strip. Single
// global listener — there's only ever one popover open at a time.
const onDocumentClick = (e) => {
    if (openKebabUserId.value === null) return;
    const popover = document.querySelector('[data-kebab-popover]');
    const trigger = document.querySelector('[data-kebab-trigger="' + openKebabUserId.value + '"]');
    if (popover?.contains(e.target) || trigger?.contains(e.target)) return;
    openKebabUserId.value = null;
};
onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));

// Wowhead tooltip rescan — tooltips.js binds hover listeners on initial
// load; new cards rendered by Vue (filter changes, group toggles, player
// picks) need an explicit refresh so the popup appears. Same pattern as
// the Roster gear tab uses.
const refreshTooltips = () => {
    if (window.whTooltips?.refreshLinks) window.whTooltips.refreshLinks();
};
onMounted(refreshTooltips);
onUpdated(refreshTooltips);

// Static-wide aggregation: walk every member's wishlists and apply the
// active filters (difficulty + mains-only + selected characters). The
// previous per-character scope was wrong — the loot-council needs to
// see the whole pool to decide who gets what drop, not just the
// currently-focused player.
const allWishlists = computed(() => {
    const out = [];
    const charFilter = selectedCharIds.value;
    characters.value.forEach(entry => {
        const ch = entry.character;
        if (charFilter.size > 0 && !charFilter.has(ch.id)) return;
        if (mainsOnly.value) {
            // Two-step gate: the character must be a main in this static
            // (character_static.role='main') AND the wishlist's spec_id
            // must match the character's main_spec_id. This drops alts
            // entirely and off-spec wishlists for mains.
            if (ch.static_role !== 'main') return;
            if (ch.main_spec_id == null) return;
        }
        entry.wishlists.forEach(w => {
            if (mainsOnly.value && w.spec_id !== ch.main_spec_id) return;
            out.push({ ...w, _character: ch });
        });
    });
    return out;
});

const availableDifficulties = computed(() =>
    new Set(allWishlists.value.map(w => w.difficulty)));

const availableSpecs = computed(() => {
    if (!currentCharacter.value) return [];
    const seen = new Map();
    currentCharacter.value.wishlists.forEach(w => {
        if (!seen.has(w.spec_id)) seen.set(w.spec_id, { id: w.spec_id, name: w.spec_name });
    });
    return Array.from(seen.values());
});

const hasAnyWishlist = computed(() => allWishlists.value.length > 0);

watch(availableDifficulties, (difficulties) => {
    if (!difficulties.has(selectedDifficulty.value)) {
        const first = DIFFICULTIES.find(d => difficulties.has(d.id));
        if (first) selectedDifficulty.value = first.id;
    }
}, { immediate: true });

const activeWishlists = computed(() =>
    allWishlists.value.filter(w => w.difficulty === selectedDifficulty.value));

// Items dedup'd by item_id across the whole static. When the same item
// appears in N wishlists with different matched_configs, we compute a
// weighted average of value/percent (sum(value × weight) / sum(weight))
// so the loot caller sees one number that reflects all valid setups
// instead of arbitrarily picking the first or highest.
//
// When mainsOnly is on we re-derive claimants + counts from the original
// claimants array (filtering out alts/off-specs). The backend can't pre-
// compute this because the toggle lives in the client.
const dedupedItems = computed(() => {
    const groups = new Map();
    activeWishlists.value.forEach(w => {
        const weight = Number(w.matched_config?.weight ?? 1);
        w.items.forEach(item => {
            if (!groups.has(item.item_id)) {
                groups.set(item.item_id, {
                    base: item,
                    sumValue: 0,
                    sumPercent: 0,
                    sumWeight: 0,
                    bestValue: -Infinity,
                    bestItem: item,
                    configNames: new Set(),
                });
            }
            const g = groups.get(item.item_id);
            const v = Number(item.value) || 0;
            const p = Number(item.percent) || 0;
            g.sumValue += v * weight;
            g.sumPercent += p * weight;
            g.sumWeight += weight;
            if (v > g.bestValue) { g.bestValue = v; g.bestItem = item; }
            if (w.matched_config?.name) g.configNames.add(w.matched_config.name);
        });
    });

    const list = Array.from(groups.values()).map(g => ({
        ...g.bestItem,
        // Weighted overrides — fall back to bestItem's raw value if no
        // configs matched (sumWeight = N × default 1, behaves like avg).
        value:   g.sumWeight > 0 ? Math.round(g.sumValue / g.sumWeight)  : g.bestItem.value,
        percent: g.sumWeight > 0 ? +(g.sumPercent / g.sumWeight).toFixed(2) : g.bestItem.percent,
        matched_configs: Array.from(g.configNames),
    }));

    if (!mainsOnly.value) return list;
    return list.map(item => {
        const filteredClaimants = (item.claimants || []).filter(
            c => c.role === 'main' && c.is_main_spec
        );
        return {
            ...item,
            claimants: filteredClaimants,
            claimant_count: filteredClaimants.length,
            bis_count: filteredClaimants.filter(c => c.is_bis).length,
        };
    });
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
                label: item.boss_name || __('Other'),
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
                label: __(SLOT_LABELS[slot] || slot.replace(/_/g, ' ')),
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

// Filter dropdown options — derived from whatever buckets the active
// grouping produces. "All" sentinel resets to the full set.
//
// Boss mode skips synthetic "__no_boss_*" buckets (M+/Catalyst/crafted/
// PvP items lack a boss_name) so the dropdown is the actual raid roster
// instead of a wall of "Other" entries. Slot mode keeps all buckets.
const filterOptions = computed(() => {
    const opts = activeGrouping.value
        .filter(b => groupBy.value !== 'boss' || !String(b.key).startsWith('__no_boss_'))
        .map(b => ({ value: String(b.key), label: b.label }));
    return [{ value: '__all__', label: __('All') }, ...opts];
});

const filterValueDisplay = computed(() => filterValue.value ?? '__all__');

const onFilterChange = (v) => {
    filterValue.value = v === '__all__' ? null : v;
};

// Apply the boss/slot filter on top of the active grouping. When set,
// only the matching bucket survives — keeps section-header semantics
// intact so the filtered view looks identical to a one-bucket grouping.
const visibleGrouping = computed(() => {
    if (filterValue.value === null) return activeGrouping.value;
    return activeGrouping.value.filter(b => String(b.key) === String(filterValue.value));
});

const difficultyShort = computed(() => {
    const d = DIFFICULTIES.find(d => d.id === selectedDifficulty.value);
    return d?.short ?? '';
});

// Last import date scoped to the (currentCharacter, currentSpec) the
// gear page is focused on — switching spec there must update the date
// even though the items grid below stays static-wide. Falls back to
// null when the focused spec has no wishlist yet.
const lastImportedAt = computed(() => {
    if (!currentCharacter.value || !effectiveSpecId.value) return null;
    const dates = currentCharacter.value.wishlists
        .filter(w => w.spec_id === effectiveSpecId.value)
        .map(w => w.imported_at)
        .filter(Boolean);
    return dates.length ? dates.sort().at(-1) : null;
});

const formatDate = (iso) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
};

// Every wishlist whose character belongs to the viewer — used by the
// management section to enumerate delete targets across the user's full
// roster. Independent of the active filters; you can still delete an
// off-spec wishlist while viewing mains-only.
const ownWishlists = computed(() => {
    const out = [];
    characters.value.forEach(entry => {
        if (!entry.character.is_own) return;
        entry.wishlists.forEach(w => out.push({ ...w, _character: entry.character }));
    });
    return out;
});

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
        <!-- Per-player chip strip (officers/leaders only). One chip per
             user — main click toggles every character of that user.
             Users with alts get a ⋮ kebab next to the +N badge that
             opens a popover for picking specific characters (e.g. just
             the user's alt warrior). Members never see this row: their
             payload is already server-scoped to themselves. -->
        <div
            v-if="allowPlayerPicker && hasAnyWishlist && players.length > 0"
            class="bg-surface-container-low border border-white/5 rounded-xl p-4"
        >
            <div class="flex items-center gap-2 mb-3">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest">{{ __('Players') }}</span>
                <span class="text-[10px] text-on-surface-variant/60">({{ selectedCharIds.size > 0 ? selectedCharIds.size : characters.length }}/{{ characters.length }})</span>
                <button
                    v-if="selectedCharIds.size > 0"
                    type="button"
                    @click="clearPlayers"
                    class="ml-auto text-[10px] text-on-surface-variant hover:text-white font-headline font-bold uppercase tracking-widest transition"
                >
                    {{ __('All players') }}
                </button>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <div
                    v-for="p in players"
                    :key="p.user_id"
                    class="relative"
                >
                    <div
                        :class="[
                            'inline-flex items-center gap-2 pl-1 py-1 rounded-full border transition',
                            p.alts_count > 0 ? 'pr-1' : 'pr-2.5',
                            userSelectionState(p) === 'full'
                                ? 'border-cyan-400/60 bg-cyan-500/15 text-white'
                                : userSelectionState(p) === 'partial'
                                    ? 'border-cyan-400/40 bg-cyan-500/5 text-white'
                                    : selectedCharIds.size === 0
                                        ? 'border-cyan-400/50 bg-cyan-500/10 text-white'
                                        : 'border-white/10 bg-surface-container text-on-surface-variant/70 hover:text-white hover:border-white/30',
                        ]"
                    >
                        <button
                            type="button"
                            @click="toggleUser(p)"
                            class="inline-flex items-center gap-2 pr-1"
                            :title="p.user_name ? `${p.face?.name ?? ''} — ${p.user_name}` : (p.face?.name ?? '')"
                        >
                            <img
                                v-if="p.face?.avatar_url"
                                :src="p.face.avatar_url"
                                :alt="p.face.name"
                                class="w-6 h-6 rounded-full ring-1 ring-white/10 object-cover"
                            />
                            <span
                                v-else
                                class="w-6 h-6 rounded-full bg-surface-container-highest flex items-center justify-center text-[10px] font-bold text-on-surface-variant"
                            >
                                {{ (p.face?.name ?? '?').charAt(0) }}
                            </span>
                            <span class="text-xs font-headline font-bold tracking-wider">{{ p.face?.name ?? p.user_name ?? '—' }}</span>
                            <span
                                v-if="p.alts_count > 0"
                                class="px-1.5 py-px rounded-full bg-white/10 text-[9px] font-bold text-on-surface-variant/80"
                            >
                                +{{ p.alts_count }}
                            </span>
                            <!-- Partial-selection dot: tiny visual cue
                                 that the user has a narrowed sub-pick
                                 inside this chip (e.g. only the alt
                                 selected, not the main). -->
                            <span
                                v-if="userSelectionState(p) === 'partial'"
                                class="w-1.5 h-1.5 rounded-full bg-cyan-300"
                            ></span>
                        </button>
                        <button
                            v-if="p.alts_count > 0"
                            type="button"
                            :data-kebab-trigger="p.user_id"
                            @click.stop="openKebabUserId = openKebabUserId === p.user_id ? null : p.user_id"
                            :class="[
                                'w-6 h-6 rounded-full flex items-center justify-center transition',
                                openKebabUserId === p.user_id
                                    ? 'bg-white/15 text-white'
                                    : 'text-on-surface-variant/60 hover:bg-white/10 hover:text-white',
                            ]"
                            :title="__('Pick specific characters')"
                        >
                            <span class="material-symbols-outlined text-base">more_vert</span>
                        </button>
                    </div>

                    <!-- Kebab popover — alt-only picker. The main is already
                         toggled via the chip body click, so listing it
                         here would just be a duplicate control. Width
                         auto-fits the longest character name. -->
                    <div
                        v-if="openKebabUserId === p.user_id"
                        data-kebab-popover
                        class="absolute z-50 top-full mt-1 left-0 w-max bg-surface-container-high border border-white/10 rounded-xl shadow-2xl p-2 space-y-0.5"
                    >
                        <label
                            v-for="c in p.characters.filter(c => c.static_role !== 'main')"
                            :key="c.id"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-white/5 cursor-pointer transition whitespace-nowrap"
                        >
                            <input
                                type="checkbox"
                                :checked="selectedCharIds.has(c.id)"
                                @change="toggleChar(c.id)"
                                class="w-3.5 h-3.5 rounded border-white/20 bg-surface-container-highest text-cyan-400 focus:ring-cyan-400/40 cursor-pointer"
                            />
                            <img
                                v-if="c.avatar_url"
                                :src="c.avatar_url"
                                :alt="c.name"
                                class="w-5 h-5 rounded-full ring-1 ring-white/10 object-cover"
                            />
                            <span class="text-xs font-headline font-bold tracking-wider text-white">{{ c.name }}</span>
                            <span class="ml-3 px-1.5 py-px rounded text-[9px] font-bold uppercase tracking-widest border border-amber-400/30 bg-amber-500/10 text-amber-200">
                                {{ __('alt') }}
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

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
                    {{ __(d.label) }}
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

            <!-- Boss/slot filter — collapses the active grouping to a
                 single bucket when chosen. Uses our teleported StyledSelect
                 so the dropdown panel can escape the filter-bar overflow. -->
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest">
                    {{ groupBy === 'slot' ? __('Slot') : __('Boss') }}
                </span>
                <StyledSelect
                    :model-value="filterValueDisplay"
                    @update:model-value="onFilterChange"
                    :options="filterOptions"
                    min-width="160px"
                />
            </div>

            <!-- Mains-only toggle: default ON. Filters wishlists down to
                 each character's main spec in this static; alts/off-specs
                 stay hidden until the raid lead unticks. -->
            <label class="flex items-center gap-2 cursor-pointer text-[11px] text-on-surface-variant hover:text-white transition select-none">
                <input
                    type="checkbox"
                    v-model="mainsOnly"
                    class="w-4 h-4 rounded border-white/20 bg-surface-container-highest text-cyan-400 focus:ring-cyan-400/40 cursor-pointer"
                />
                <span class="font-headline font-bold uppercase tracking-widest">{{ __('Mains only') }}</span>
            </label>

            <!-- Last update -->
            <div v-if="lastImportedAt" class="ml-auto flex items-center gap-2 text-on-surface-variant text-[11px]">
                <span class="material-symbols-outlined text-base">schedule</span>
                <span>{{ formatDate(lastImportedAt) }}</span>
            </div>

            <!-- Raidbots opener — opens a modal listing every allowed
                 config so the user picks one before being sent to
                 Raidbots. Hidden for healers. -->
            <button
                v-if="!isHealer && currentCharacter?.character.is_own"
                type="button"
                @click="showRaidbotsModal = true"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 border border-orange-400/40 text-orange-200 font-headline text-[11px] font-bold uppercase tracking-widest hover:bg-orange-500/20 transition',
                    lastImportedAt ? '' : 'ml-auto',
                ]"
            >
                <span class="material-symbols-outlined text-base">tune</span>
                {{ __('Run on Raidbots') }}
            </button>
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

            <!-- Raidbots opener — opens a modal listing every allowed
                 config so the user picks one before being sent to
                 Raidbots. Hidden for healers since Raidbots doesn't
                 sim healing throughput. -->
            <div v-if="!isHealer && currentCharacter?.character.is_own" class="mt-4 flex flex-wrap items-center justify-center gap-2">
                <button
                    type="button"
                    @click="showRaidbotsModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 border border-orange-400/40 text-orange-200 font-headline text-[11px] font-bold uppercase tracking-widest hover:bg-orange-500/20 transition"
                >
                    <span class="material-symbols-outlined text-base">tune</span>
                    {{ __('Run on Raidbots') }}
                </button>
            </div>
        </div>

        <!-- Empty state when wishlists exist but none match the chosen filter -->
        <div v-else-if="!activeWishlists.length || !visibleGrouping.length" class="bg-surface-container-low border border-white/5 rounded-xl p-12">
            <EmptyState
                icon="filter_alt_off"
                :title="__('No data for this filter')"
                :description="mainsOnly
                    ? __('Try a different difficulty or untick Mains only to see alt specs.')
                    : __('Try a different difficulty or boss/slot.')"
            />
        </div>

        <!-- Grouped sections — header label/icon depends on the active group-by mode -->
        <div v-else class="space-y-4">
            <section
                v-for="bucket in visibleGrouping"
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

        <!-- Wishlist management — list ALL of the viewer's own wishlists
             with a delete button. Static-wide aggregate doesn't bind the
             viewer to a single character, so management has to enumerate. -->
        <div v-if="ownWishlists.length" class="bg-surface-container-low/40 border border-white/5 rounded-xl px-5 py-3">
            <header class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-base">folder_managed</span>
                {{ __('Your wishlists') }}
            </header>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="wl in ownWishlists"
                    :key="wl.id"
                    type="button"
                    @click="deleteWishlist(wl.id)"
                    class="group inline-flex items-center gap-1.5 px-2.5 py-1 rounded border border-white/5 text-[11px] text-on-surface-variant hover:border-error/40 hover:text-error transition"
                    :title="__('Delete this wishlist')"
                >
                    <span>{{ wl._character.name }}</span>
                    <span class="opacity-50">·</span>
                    <span class="font-bold">{{ wl.spec_name }}</span>
                    <span class="opacity-50">·</span>
                    <span class="uppercase font-headline">{{ wl.raid_slug }}</span>
                    <!-- Matched-config chip: green when matched, amber
                         when nothing fit (raid lead may need to add a
                         config row or the player to re-run). -->
                    <span
                        v-if="wl.matched_config"
                        class="px-1.5 py-px rounded text-[9px] font-headline font-bold uppercase tracking-widest border border-emerald-400/40 bg-emerald-500/10 text-emerald-200"
                        :title="__('Matched config: :name (weight :w)', { name: wl.matched_config.name, w: wl.matched_config.weight })"
                    >
                        {{ wl.matched_config.name }}
                    </span>
                    <span
                        v-else
                        class="px-1.5 py-px rounded text-[9px] font-headline font-bold uppercase tracking-widest border border-amber-400/40 bg-amber-500/10 text-amber-200"
                        :title="__('No matching Droptimizer config — value not weighted in overview')"
                    >
                        {{ __('unmatched') }}
                    </span>
                    <span class="material-symbols-outlined text-sm opacity-40 group-hover:opacity-100">delete</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Claimants modal — opens from any item card; shows everyone in the
         static who has this item in their wishlist for the current bucket. -->
    <WishlistClaimantsModal
        :show="showClaimantsModal"
        :item="claimantsItem"
        :mains-only="mainsOnly"
        @close="closeClaimants"
    />

    <!-- Raidbots config chooser — Raidbots' Droptimizer page reads only
         region/realm/name from the URL, so we list every allowed config
         here as a checklist the user copies into the Raidbots form. One
         "Open" button per config opens a new tab with the character
         prefilled; the rest is set manually. -->
    <GlassModal :show="showRaidbotsModal" @close="showRaidbotsModal = false" max-width="max-w-2xl">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-orange-300 text-base">tune</span>
                {{ __('Run on Raidbots') }}
                <span class="text-on-surface-variant text-[11px] font-bold ml-2">({{ configs.length }})</span>
            </h3>
            <button type="button" @click="showRaidbotsModal = false" class="text-on-surface-variant hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
            <p class="text-xs text-on-surface-variant leading-relaxed">
                {{ __('Pick a configuration below. Raidbots only auto-fills the character — set the listed Fight Style, Number of Bosses, Fight Length, Difficulty and Item Upgrade Level on the Raidbots form to match. Otherwise the import will be rejected.') }}
            </p>

            <div v-if="!configs.length" class="text-xs text-on-surface-variant text-center py-6">
                {{ __('No allowed configurations defined yet. Ask your raid lead.') }}
            </div>

            <div
                v-for="cfg in configs"
                :key="cfg.id"
                class="bg-surface-container-highest border border-white/5 rounded-lg p-4 space-y-3"
                :class="{ 'border-cyan-400/40': cfg.is_default }"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 min-w-0">
                        <span v-if="cfg.is_default" class="material-symbols-outlined text-cyan-300 text-base shrink-0">star</span>
                        <span class="font-headline text-sm font-bold text-white uppercase tracking-widest truncate">{{ cfg.display_name }}</span>
                        <span v-if="cfg.weight" class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest shrink-0">
                            {{ __('weight') }} {{ cfg.weight }}
                        </span>
                    </div>
                    <a
                        :href="raidbotsLink()"
                        target="_blank"
                        rel="noopener"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-500/10 border border-orange-400/40 text-orange-200 font-headline text-[11px] font-bold uppercase tracking-widest hover:bg-orange-500/20 transition"
                    >
                        <span class="material-symbols-outlined text-base">open_in_new</span>
                        {{ __('Open Raidbots') }}
                    </a>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    <div v-for="(item, i) in configChecklist(cfg)" :key="i" class="flex items-baseline justify-between gap-2 border-b border-white/5 py-1">
                        <dt class="text-on-surface-variant uppercase tracking-wider text-[10px] font-headline font-bold">{{ item.label }}</dt>
                        <dd class="text-white font-mono text-[11px] text-right">{{ item.value }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </GlassModal>
</template>
