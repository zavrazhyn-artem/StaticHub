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
};

// Track display colors — matches Roster/UnifiedRoster.vue tier palette so the
// modal feels consistent with the rest of the app.
const TRACK_COLORS = {
    'Myth':       'text-orange-500',
    'Hero':       'text-purple-500',
    'Champion':   'text-blue-500',
    'Veteran':    'text-green-500',
    'Adventurer': 'text-teal-500',
    'Explorer':   'text-gray-400',
};

const SOURCE_TABS = [
    { id: 'all',      label: 'All',      icon: 'list' },
    { id: 'raid',     label: 'Raid',     icon: 'castle' },
    { id: 'dungeon',  label: 'Mythic+',  icon: 'door_front' },
    { id: 'catalyst', label: 'Catalyst', icon: 'auto_awesome' },
];

// ----------------------------------------------------------------------------
// State
// ----------------------------------------------------------------------------

const loading = ref(false);
const items = ref([]);
const tracks = ref([]);
const pickerClassId = ref(null);
const pickerSpecId = ref(null);

const selectedTrackName = ref('Myth');
const selectedLevel = ref(6);
const activeTab = ref('all');
const submitting = ref(false);

const slotLabel = computed(() => __(SLOT_LABELS[props.slot] || props.slot));

const selectedTrack = computed(() => tracks.value.find(t => t.name === selectedTrackName.value) ?? null);

const selectedIlvl = computed(() => selectedTrack.value?.ilvls?.[selectedLevel.value] ?? null);

const filteredItems = computed(() => {
    if (activeTab.value === 'all') return items.value;
    return items.value.filter(i => i.source_type === activeTab.value);
});

const trackOptions = computed(() => tracks.value.map(t => ({
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
    const bonus = selectedTrack.value?.level_to_bonus_id?.[selectedLevel.value];
    if (bonus) parts.push(`bonus=${bonus}`);
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
        activeTab.value = 'all';
        submitting.value = false;
        pickError.value = '';
        fetchPicker();
    }
}, { immediate: false });

// When the user picks a different track, snap level to that track's max.
watch(selectedTrackName, () => {
    if (selectedTrack.value) selectedLevel.value = selectedTrack.value.max;
});

// ----------------------------------------------------------------------------
// Submit
// ----------------------------------------------------------------------------

const pickError = ref('');

const pickItem = async (item) => {
    if (submitting.value) return;
    submitting.value = true;
    pickError.value = '';
    try {
        const url = props.setSlotUrlTemplate.replace('__ID__', props.listId);
        const resp = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                slot: props.slot,
                item_id: item.id,
                track: selectedTrackName.value,
                level: selectedLevel.value,
            }),
        });
        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || data.success === false) {
            pickError.value = data.error || __('Failed to update slot');
            submitting.value = false;
            return;
        }
        // Hand the refreshed list back to the parent so it can update the
        // paper-doll without a page reload, then close the modal.
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
                <span class="material-symbols-outlined text-cyan-400 text-base">checkroom</span>
                {{ __('Pick item for') }}
                <span class="text-cyan-300">{{ slotLabel }}</span>
            </h3>
            <button type="button" @click="close" class="text-on-surface-variant hover:text-white transition">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <!-- Track + level + source tabs -->
        <div class="px-6 py-3 border-b border-white/5 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mr-1">{{ __('Track') }}</span>
                <StyledSelect
                    v-model="selectedTrackName"
                    :options="trackOptions"
                    min-width="110px"
                />
                <span class="text-[10px] text-on-surface-variant font-headline font-bold uppercase tracking-widest mx-1">{{ __('Level') }}</span>
                <StyledSelect
                    v-model="selectedLevel"
                    :options="levelOptions"
                    min-width="70px"
                />
                <span v-if="selectedIlvl" class="px-2.5 py-1.5 rounded-lg bg-cyan-500/10 border border-cyan-400/40 text-cyan-200 font-mono text-xs font-bold">
                    {{ __('iLvl') }} {{ selectedIlvl }}
                </span>
            </div>

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

        <!-- Item list — fixed height so the modal doesn't reflow when the
             user switches source tabs and the item count changes. -->
        <div class="h-[60vh] overflow-y-auto custom-scrollbar p-4">
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
    </GlassModal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,.05); }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,.25); }
</style>
