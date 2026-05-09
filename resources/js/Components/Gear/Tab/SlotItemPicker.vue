<script setup>
import { ref, computed, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import GlassModal from '@/Components/UI/GlassModal.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import StyledSelect from '@/Components/UI/StyledSelect.vue';

const { __ } = useTranslation();

const props = defineProps({
    show: { type: Boolean, default: false },
    listId: { type: [Number, String, null], default: null },
    slot: { type: String, default: '' },
    pickerUrlTemplate: { type: String, required: true }, // /gear/lists/__ID__/picker
    setSlotUrlTemplate: { type: String, required: true }, // /gear/lists/__ID__/slot
    csrfToken: { type: String, required: true },
});

const emit = defineEmits(['close', 'picked']);

const SLOT_LABELS = {
    head: 'Head', neck: 'Neck', shoulder: 'Shoulder', back: 'Back', chest: 'Chest',
    wrist: 'Wrist', hands: 'Hands', waist: 'Waist', legs: 'Legs', feet: 'Feet',
    finger_1: 'Finger', finger_2: 'Finger', trinket_1: 'Trinket', trinket_2: 'Trinket',
    main_hand: 'Main Hand', off_hand: 'Off Hand', ranged: 'Ranged',
};

const QUALITY_BORDER = {
    raid:     'border-orange-400/60',     // gold/legendary feel
    catalyst: 'border-orange-400/60',
    dungeon:  'border-purple-400/40',
    crafted:  'border-emerald-400/40',    // forge-green
};

// Track display colors — matches Roster/UnifiedRoster.vue tier palette so the
// modal feels consistent with the rest of the app. Crafted bands reuse the
// raid-tier hues (Mythic→orange, Heroic→purple) since they map to roughly
// the same ilvl bands.
const TRACK_COLORS = {
    'Myth':       'text-orange-500',
    'Hero':       'text-purple-500',
    'Champion':   'text-blue-500',
    'Veteran':    'text-green-500',
    'Adventurer': 'text-teal-500',
    'Explorer':   'text-gray-400',
    'Mythic':     'text-orange-500',
    'Heroic':     'text-purple-500',
    'Base':       'text-blue-500',
};

const SOURCE_TABS = [
    { id: 'all',      label: 'All',      icon: 'list' },
    { id: 'raid',     label: 'Raid',     icon: 'castle' },
    { id: 'dungeon',  label: 'Mythic+',  icon: 'door_front' },
    { id: 'catalyst', label: 'Catalyst', icon: 'auto_awesome' },
    { id: 'crafted',  label: 'Crafted',  icon: 'construction' },
];

const SECONDARY_STATS = [
    { id: 'crit',        label: 'Crit' },
    { id: 'haste',       label: 'Haste' },
    { id: 'mastery',     label: 'Mastery' },
    { id: 'versatility', label: 'Versatility' },
];

// ----------------------------------------------------------------------------
// State
// ----------------------------------------------------------------------------

const loading = ref(false);
const items = ref([]);
const tracks = ref([]);
const craftedTracks = ref([]);
// Mirror of wow_season.crafted_missive_bonus_ids — used to extend the
// Wowhead tooltip URL with the matching missive bonus_id as the user
// toggles secondary stats in step 2 (so "+81 Random Stat 1/2" becomes
// "+81 Crit, +81 Haste" while still in the picker).
const craftedMissiveBonusIds = ref({});
const craftedSeasonBonusId = ref(0);
const pickerClassId = ref(null);
const pickerSpecId = ref(null);

// Two-step flow: 'list' shows the item grid, 'configure' shows track/
// level (and, later, crafted-stat pickers) for the item the user just
// clicked. We stay inside one modal — a nested modal-over-modal would
// feel jarring for what's effectively step 2 of the same task.
const step = ref('list');
const selectedItem = ref(null);

const selectedTrackName = ref('Myth');
const selectedLevel = ref(6);
// Crafted-only — exactly two of the secondary_pool. Reset on every
// step-1 → step-2 transition to avoid carrying picks across items.
const chosenStats = ref([]);
const activeTab = ref('all');
const submitting = ref(false);

const slotLabel = computed(() => __(SLOT_LABELS[props.slot] || props.slot));

// Has to come before activeTracks so the computed closure captures an
// already-initialized binding. Vue's computed lazily evaluates but the
// closure resolves the binding at the script-evaluation point that
// triggers it, which during HMR/initial setup hits TDZ if isCrafted
// hasn't been declared yet.
const isCrafted = computed(() => !!selectedItem.value?.is_craftable);

// Crafted items live on a separate quality scale (1-5 stars → ilvl
// curve set by reagents/skill, no bonus_id). For everything else the
// raid/m+ tracks apply. activeTracks computes the right set so the
// dropdowns stay correct when the user picks a crafted item in step 2.
const activeTracks = computed(() => isCrafted.value ? craftedTracks.value : tracks.value);

const selectedTrack = computed(() => activeTracks.value.find(t => t.name === selectedTrackName.value) ?? null);

const selectedIlvl = computed(() => selectedTrack.value?.ilvls?.[selectedLevel.value] ?? null);

const filteredItems = computed(() => {
    if (activeTab.value === 'all') return items.value;
    return items.value.filter(i => i.source_type === activeTab.value);
});

const trackOptions = computed(() => activeTracks.value.map(t => ({
    value: t.name,
    label: t.name,
    color: TRACK_COLORS[t.name],
})));

const levelOptions = computed(() => {
    const max = selectedTrack.value?.max ?? 6;
    return Array.from({ length: max }, (_, i) => {
        const lvl = i + 1;
        return { value: lvl, label: `${lvl}/${max}` };
    });
});

const itemsBySource = computed(() => {
    const groups = new Map();
    filteredItems.value.forEach(item => {
        const key = sourceKey(item);
        if (!groups.has(key)) groups.set(key, { label: sourceLabel(item), items: [] });
        groups.get(key).items.push(item);
    });
    return Array.from(groups.entries()).sort(([a], [b]) => a.localeCompare(b)).map(([_, v]) => v);
});

// ----------------------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------------------

const sourceKey = (item) => `${item.source_type}|${item.source_slug}`;
const sourceLabel = (item) => {
    // For raids prefer boss name (Vorasius, Crown of the Cosmos…) since one
    // raid has many bosses; for everything else use the seeded display name
    // (Maisara Caverns, Catalyst, …).
    if (item.source_type === 'raid' && item.boss_name) return item.boss_name;
    return item.source_label || item.source_slug;
};

const iconUrl = (item) => item.icon || '/images/icons/inv_misc_questionmark.jpg';

// Forward class+spec to wowhead so hybrid stat lines ("+93 Agility or
// Intellect") resolve to the spec's actual mainstat in the tooltip and
// tier-set bonuses render under the correct class.
//
// Both href AND data-wowhead need the params explicitly: wowhead's tooltip
// JS rewrites the href from data-wowhead but only keeps params it recognises
// natively (ilvl, bonus, gems, ench). Setting them upfront in the href makes
// the visible link follow them too.
const tooltipQuery = (item) => {
    const parts = [];
    if (selectedIlvl.value) parts.push(`ilvl=${selectedIlvl.value}`);
    const bonusIds = [];
    const trackBonus = selectedTrack.value?.level_to_bonus_id?.[selectedLevel.value];
    if (trackBonus) bonusIds.push(trackBonus);
    // Crafted-only: append season's "Radiance Crafted" bonus (always)
    // and the missive bonus matching the chosen pair (when 2 are picked).
    // Mirrors GearListService::setSlot so the picker tooltip previews
    // exactly what the saved item will look like on the slot card.
    if (item?.is_craftable) {
        if (craftedSeasonBonusId.value) bonusIds.push(craftedSeasonBonusId.value);
        if (chosenStats.value.length === 2) {
            const key = [...chosenStats.value].sort().join('_');
            const missive = craftedMissiveBonusIds.value[key];
            if (missive) bonusIds.push(missive);
        }
    }
    if (bonusIds.length) parts.push(`bonus=${bonusIds.join(':')}`);
    if (pickerClassId.value) parts.push(`cl=${pickerClassId.value}`);
    if (pickerSpecId.value) parts.push(`spec=${pickerSpecId.value}`);
    return parts.join('&');
};

const wowheadHref = (item) => {
    const q = tooltipQuery(item);
    return `https://www.wowhead.com/item=${item.id}${q ? '?' + q : ''}`;
};

const wowheadAttr = (item) => {
    const q = tooltipQuery(item);
    return `item=${item.id}${q ? '&' + q : ''}`;
};

const itemClass = (item) => {
    return [
        'rounded-lg border p-2.5 transition cursor-pointer hover:bg-white/5 flex gap-3',
        QUALITY_BORDER[item.source_type] ?? 'border-white/10',
        item.is_tier ? 'ring-1 ring-orange-400/40 bg-orange-500/[0.04]' : 'bg-surface-container',
    ].join(' ');
};

// Stat profile (no numbers) — wowhead tooltip on hover gives accurate
// per-ilvl values; the inline label only conveys WHICH stats this item
// rolls so the user can scan a list at a glance. Primary stat first,
// then secondaries in the order Blizzard sorts them.
const STAT_ABBR = {
    intellect: 'Int', agility: 'Agi', strength: 'Str', stamina: 'Sta',
    crit: 'Crit', haste: 'Haste', mastery: 'Mastery', versatility: 'Vers',
};

const statProfile = (stats) => {
    if (!stats) return '';
    const primary = ['intellect', 'agility', 'strength']
        .filter(k => stats[k])
        .map(k => STAT_ABBR[k]);
    const secondary = ['crit', 'haste', 'mastery', 'versatility']
        .filter(k => stats[k])
        .map(k => STAT_ABBR[k]);
    if (!primary.length && !secondary.length) return '';
    return [primary.join(' / '), secondary.join(' / ')].filter(Boolean).join(' · ');
};
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

const sourceBadgeClass = (sourceType) => ({
    raid:     'bg-orange-500/10 border-orange-400/40 text-orange-200',
    catalyst: 'bg-amber-500/10 border-amber-400/40 text-amber-200',
    dungeon:  'bg-purple-500/10 border-purple-400/40 text-purple-200',
    crafted:  'bg-emerald-500/10 border-emerald-400/40 text-emerald-200',
}[sourceType] ?? 'bg-white/5 border-white/10 text-on-surface-variant');

// ----------------------------------------------------------------------------
// Load
// ----------------------------------------------------------------------------

const fetchPicker = async () => {
    if (!props.listId || !props.slot) return;
    loading.value = true;
    try {
        const url = props.pickerUrlTemplate.replace('__ID__', props.listId) + `?slot=${props.slot}`;
        const resp = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await resp.json();
        items.value = data.items ?? [];
        tracks.value = data.tracks ?? [];
        craftedTracks.value = data.crafted_tracks ?? [];
        craftedMissiveBonusIds.value = data.crafted_missive_bonus_ids ?? {};
        craftedSeasonBonusId.value = data.crafted_season_bonus_id ?? 0;
        pickerClassId.value = data.class_id ?? null;
        pickerSpecId.value = data.spec_id ?? null;
        // Default selection: top of Myth (or first track / its max level if Myth missing).
        const defaultTrack = tracks.value.find(t => t.name === 'Myth') ?? tracks.value[0];
        if (defaultTrack) {
            selectedTrackName.value = defaultTrack.name;
            selectedLevel.value = defaultTrack.max;
        }
    } finally {
        loading.value = false;
    }
};

watch(() => [props.show, props.listId, props.slot], ([show]) => {
    if (show) {
        step.value = 'list';
        selectedItem.value = null;
        activeTab.value = 'all';
        submitting.value = false;
        pickError.value = '';
        fetchPicker();
    }
}, { immediate: false });

// When the user changes track in step 2, snap level to that track's
// max so an Adventurer-6 selection that doesn't exist on Crafted (max
// 5) stays valid. Also covers the implicit re-snap when the track
// list itself changes (raid → crafted on item swap).
watch([selectedTrackName, activeTracks], () => {
    if (step.value === 'configure' && selectedTrack.value) {
        if (selectedLevel.value > selectedTrack.value.max) {
            selectedLevel.value = selectedTrack.value.max;
        }
    }
});

// ----------------------------------------------------------------------------
// Submit
// ----------------------------------------------------------------------------

const pickError = ref('');

// Step 1 → step 2: stash the item and reset track/level to the default
// (top of Myth for raid drops, top Quality for crafted). For crafted
// items also reset the secondary pick to empty so the user must
// consciously choose. The user can still back out.
const pickItem = (item) => {
    if (submitting.value) return;
    selectedItem.value = item;
    pickError.value = '';
    const list = item.is_craftable ? craftedTracks.value : tracks.value;
    // Crafted top tier matches Myth-equivalent ilvl band — the user
    // most often plans against the highest possible roll, so default
    // there. Falls back to first track if that name isn't present.
    const preferred = item.is_craftable ? 'Mythic' : 'Myth';
    const defaultTrack = list.find(t => t.name === preferred) ?? list[0];
    if (defaultTrack) {
        selectedTrackName.value = defaultTrack.name;
        selectedLevel.value = defaultTrack.max;
    }
    chosenStats.value = [];
    step.value = 'configure';
};

const secondaryOptions = computed(() => {
    const pool = selectedItem.value?.secondary_pool;
    if (!Array.isArray(pool) || pool.length === 0) return [];
    return SECONDARY_STATS.filter(s => pool.includes(s.id));
});

// Crafted items without a Missive slot have fixed secondaries — picker
// stays hidden, no chosen_stats are sent.
const showStatPicker = computed(() => isCrafted.value && secondaryOptions.value.length > 0);

const toggleStat = (statId) => {
    const idx = chosenStats.value.indexOf(statId);
    if (idx >= 0) {
        chosenStats.value.splice(idx, 1);
        return;
    }
    if (chosenStats.value.length >= 2) {
        // Replace the oldest pick — keeps the UI behaviour predictable
        // when the user clicks a third checkbox without first unticking.
        chosenStats.value.shift();
    }
    chosenStats.value.push(statId);
};

const canConfirm = computed(() => {
    if (!selectedItem.value) return false;
    if (showStatPicker.value && chosenStats.value.length !== 2) return false;
    return true;
});

const backToList = () => {
    if (submitting.value) return;
    step.value = 'list';
    selectedItem.value = null;
    pickError.value = '';
};

const confirmPick = async () => {
    if (submitting.value || !canConfirm.value) return;
    submitting.value = true;
    pickError.value = '';
    try {
        const url = props.setSlotUrlTemplate.replace('__ID__', props.listId);
        const body = {
            slot: props.slot,
            item_id: selectedItem.value.id,
            track: selectedTrackName.value,
            level: selectedLevel.value,
        };
        if (showStatPicker.value) body.chosen_stats = [...chosenStats.value];
        const resp = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data.success === false) {
            pickError.value = data.error || __('Failed to update slot');
            submitting.value = false;
            return;
        }
        emit('picked', data.list ?? null);
        emit('close');
    } catch (e) {
        pickError.value = String(e.message || e);
        submitting.value = false;
    }
};

const close = () => {
    if (submitting.value) return;
    emit('close');
};
</script>

<template>
    <GlassModal :show="show" @close="close" max-width="max-w-3xl">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <button
                    v-if="step === 'configure'"
                    type="button"
                    @click="backToList"
                    class="text-on-surface-variant hover:text-white transition"
                    :title="__('Back to items')"
                >
                    <span class="material-symbols-outlined text-base">arrow_back</span>
                </button>
                <span class="material-symbols-outlined text-cyan-400 text-base">checkroom</span>
                <template v-if="step === 'list'">
                    {{ __('Pick item for') }}
                    <span class="text-cyan-300">{{ slotLabel }}</span>
                </template>
                <template v-else>
                    {{ __('Configure') }}
                    <span class="text-cyan-300">{{ slotLabel }}</span>
                    <span
                        v-if="isCrafted"
                        class="material-symbols-outlined text-base text-on-surface-variant/50 hover:text-emerald-300 cursor-help transition"
                        :title="__('Crafted secondaries are projected — totals may be off by ~1 per stat versus in-game.')"
                    >info</span>
                </template>
            </h3>
            <button type="button" @click="close" class="text-on-surface-variant hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <!-- Step 1: source tabs only — track/level moved to step 2 so the
             user picks the item first, then configures its variant. -->
        <div v-if="step === 'list'" class="px-6 py-3 border-b border-white/5">
            <nav class="flex gap-1">
                <button
                    v-for="tab in SOURCE_TABS"
                    :key="tab.id"
                    type="button"
                    @click="activeTab = tab.id"
                    :class="[
                        'px-3 py-1.5 rounded-full font-headline text-[11px] font-bold uppercase tracking-widest border flex items-center gap-1.5 transition',
                        activeTab === tab.id
                            ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100'
                            : 'border-white/5 text-on-surface-variant/60 hover:text-white'
                    ]"
                >
                    <span class="material-symbols-outlined text-base">{{ tab.icon }}</span>
                    {{ __(tab.label) }}
                </button>
            </nav>
        </div>

        <div v-if="pickError" class="mx-6 mt-3 px-3 py-2 rounded-lg bg-error/10 border border-error/40 text-error text-xs">
            {{ pickError }}
        </div>

        <!-- Step 1 — item grid (fixed height so source-tab switch doesn't reflow) -->
        <div v-if="step === 'list'" class="h-[60vh] overflow-y-auto custom-scrollbar p-4">
            <div v-if="loading" class="text-center py-12 text-on-surface-variant">
                <span class="material-symbols-outlined text-3xl animate-spin">progress_activity</span>
                <div class="mt-2 text-xs">{{ __('Loading…') }}</div>
            </div>

            <EmptyState
                v-else-if="!filteredItems.length"
                icon="inventory_2"
                :title="__('No items match this filter')"
                :description="__('Try a different source tab.')"
            />

            <div v-else class="space-y-5">
                <section v-for="group in itemsBySource" :key="group.label">
                    <header class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mb-2 pl-1">
                        {{ group.label }}
                        <span class="text-on-surface-variant/50 font-normal normal-case tracking-normal ml-1">({{ group.items.length }})</span>
                    </header>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <button
                            v-for="item in group.items"
                            :key="item.id"
                            type="button"
                            :class="itemClass(item)"
                            @click="pickItem(item)"
                            :disabled="submitting"
                        >
                            <img :src="iconUrl(item)" :alt="item.name" class="w-12 h-12 rounded shrink-0" />
                            <div class="min-w-0 flex-1 text-left">
                                <div class="flex items-center gap-1.5 mb-0.5">
                                    <a
                                        :href="wowheadHref(item)"
                                        :data-wowhead="wowheadAttr(item)"
                                        @click.prevent
                                        class="font-headline text-xs font-bold text-white truncate hover:text-cyan-300"
                                    >
                                        {{ item.name }}
                                    </a>
                                    <span v-if="item.is_tier" class="px-1.5 py-0.5 rounded bg-orange-500/20 border border-orange-400/40 text-orange-200 text-[9px] font-bold uppercase tracking-widest shrink-0">
                                        {{ __('Tier') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1 mb-1">
                                    <span
                                        v-for="r in (item.role || [])"
                                        :key="r"
                                        class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest border"
                                        :class="sourceBadgeClass(item.source_type)"
                                    >
                                        {{ __(r) }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-on-surface-variant truncate">
                                    {{ statProfile(item.stats) }}
                                </div>
                            </div>
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <!-- Step 2 — configure track + level for the chosen item.
             Crafted-stat picker (2 of 4 secondaries) hooks in here once
             craftable items exist in the catalog. -->
        <div v-else class="p-6 space-y-5">
            <div v-if="selectedItem" class="flex gap-4 items-center bg-surface-container rounded-lg border border-white/10 p-3">
                <img :src="iconUrl(selectedItem)" :alt="selectedItem.name" class="w-14 h-14 rounded shrink-0 ring-1 ring-white/10" />
                <div class="min-w-0 flex-1">
                    <a
                        :href="wowheadHref(selectedItem)"
                        :data-wowhead="wowheadAttr(selectedItem)"
                        @click.prevent
                        class="font-headline text-sm font-bold text-white hover:text-cyan-300 block truncate"
                    >
                        {{ selectedItem.name }}
                    </a>
                    <div class="text-[11px] text-on-surface-variant mt-0.5">
                        {{ statProfile(selectedItem.stats) }}
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mb-1.5">{{ __('Track') }}</label>
                    <StyledSelect v-model="selectedTrackName" :options="trackOptions" min-width="140px" />
                </div>
                <div>
                    <label class="block text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mb-1.5">{{ __('Level') }}</label>
                    <StyledSelect v-model="selectedLevel" :options="levelOptions" min-width="90px" />
                </div>
                <div v-if="selectedIlvl" class="px-3 py-2 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-200 font-mono text-sm font-bold">
                    {{ __('iLvl') }} {{ selectedIlvl }}
                </div>
            </div>

            <!-- Crafted-only AND Missive-slot present: pick exactly two
                 secondary stats. Chosen stats drive the secondary
                 contribution in the stat panel aggregator (split 50/50
                 of the secondary budget). Items with fixed secondaries
                 (e.g. Arcanoweave Cloak) skip this. -->
            <div v-if="showStatPicker">
                <label class="block text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mb-2">
                    {{ __('Pick 2 secondary stats') }}
                    <span class="ml-1 text-on-surface-variant/50 normal-case tracking-normal font-normal">({{ chosenStats.length }} / 2)</span>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="opt in secondaryOptions"
                        :key="opt.id"
                        type="button"
                        @click="toggleStat(opt.id)"
                        :class="[
                            'px-3 py-2 rounded-lg border text-xs font-bold flex items-center gap-2 transition',
                            chosenStats.includes(opt.id)
                                ? 'border-cyan-400/60 bg-cyan-500/10 text-cyan-100'
                                : 'border-white/10 bg-surface-container text-on-surface-variant hover:text-white'
                        ]"
                    >
                        <span class="material-symbols-outlined text-base">
                            {{ chosenStats.includes(opt.id) ? 'check_box' : 'check_box_outline_blank' }}
                        </span>
                        {{ __(opt.label) }}
                    </button>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-white/5">
                <button
                    type="button"
                    @click="backToList"
                    :disabled="submitting"
                    class="px-4 py-2 rounded-lg border border-white/10 text-on-surface-variant hover:text-white hover:border-white/20 font-headline text-xs font-bold uppercase tracking-widest transition disabled:opacity-50"
                >
                    {{ __('Back') }}
                </button>
                <button
                    type="button"
                    @click="confirmPick"
                    :disabled="submitting || !canConfirm"
                    class="px-5 py-2 rounded-lg bg-cyan-500/15 border border-cyan-400/50 text-cyan-100 hover:bg-cyan-500/25 font-headline text-xs font-bold uppercase tracking-widest transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
                >
                    <span v-if="submitting" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                    <span v-else class="material-symbols-outlined text-base">check</span>
                    {{ __('Confirm') }}
                </button>
            </div>
        </div>
    </GlassModal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.25); }
</style>
