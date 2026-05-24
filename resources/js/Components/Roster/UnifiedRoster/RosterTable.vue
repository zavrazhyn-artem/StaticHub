<script setup>
import { ref, computed, nextTick, provide, getCurrentInstance, onMounted, onBeforeUnmount, watch } from 'vue';
import RosterRow from './RosterRow.vue';
import InfoTooltip from '@/Components/UI/InfoTooltip.vue';
import { useGearSpecSwitch } from '@/composables/useGearSpecSwitch';

const { proxy } = getCurrentInstance();
const __ = (key) => proxy.__(key);

// ── Row height tokens (single source of truth) ──
const ROW_HEIGHTS = {
    main: 'h-[72px]',
    alt:  'h-[42px]',
};
provide('rowHeights', ROW_HEIGHTS);

const refreshWowheadLinks = () => {
    if (window.$WowheadPower?.refreshLinks) {
        window.$WowheadPower.refreshLinks();
    }
};
const specSwitch = useGearSpecSwitch(refreshWowheadLinks);
provide('specSwitch', specSwitch);

const searchQuery = ref('');
const showSearch = ref(false);
const searchInput = ref(null);

const toggleSearch = () => {
    showSearch.value = !showSearch.value;
    if (!showSearch.value) {
        searchQuery.value = '';
    } else {
        nextTick(() => searchInput.value?.focus());
    }
};

// ── Compare mode ──
const compareMode = ref(false);
const selectedIds = ref(new Set());
const isolated = ref(false);

const toggleCompareMode = () => {
    compareMode.value = !compareMode.value;
    if (!compareMode.value) {
        selectedIds.value = new Set();
        isolated.value = false;
    }
};

const toggleMemberSelection = (id) => {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
    if (isolated.value && next.size === 0) {
        isolated.value = false;
    }
};

const clearSelection = () => {
    selectedIds.value = new Set();
    isolated.value = false;
};

const toggleIsolate = () => {
    if (selectedIds.value.size === 0) return;
    isolated.value = !isolated.value;
};

const selectedCount = computed(() => selectedIds.value.size);

const props = defineProps({
    groupedRoster: { type: Object, required: true },
    activeTab: { type: String, required: true },
    selectedDifficulty: { type: String, required: true },
    expandedRows: { type: Object, required: true }, // Set
    raidColumns: { type: Array, required: true },
    roles: { type: Array, required: true },
    classColors: { type: Object, required: true },
    tierColors: { type: Object, required: true },
    killMarkClass: { type: [String, Object], required: true },
    canManageStatus: { type: Boolean, required: true },
    canManageAccess: { type: Boolean, required: true },
    canKick: { type: Boolean, default: false },
    roleIconSrc: { type: Function, required: true },
    tierCount: { type: Function, required: true },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
});

const emit = defineEmits([
    'toggle-row',
    'update-access-role',
    'update-roster-status',
    'kick-member',
    'open-audit-modal'
]);

// raidColumns prop is always populated from config — no need to derive from character data
const slots = [
    'HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'WRIST',
    'HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2',
    'TRINKET_1', 'TRINKET_2', 'MAIN_HAND', 'OFF_HAND'
];

// ── Filter pills ──
const activeFilter = ref('all');

const ROLE_COLORS = { tank: '#3a8dff', heal: '#4ADE80', dps: '#ff5063' };
const roleColor = (key) => ROLE_COLORS[key] ?? '#adaaad';

const allMembers = computed(() => Object.values(props.groupedRoster).flat());

const filterCounts = computed(() => ({
    all:   allMembers.value.length,
    tank:  (props.groupedRoster.tank  ?? []).length,
    heal:  (props.groupedRoster.heal  ?? []).length,
    dps:   (props.groupedRoster.dps   ?? []).length,
    core:  allMembers.value.filter(m => m.roster_status === 'core').length,
    bench: allMembers.value.filter(m => m.roster_status === 'bench').length,
}));

const filterPills = computed(() => [
    { key: 'all',   label: __('All'),    color: '#4fd3f7' },
    { key: 'tank',  label: __('Tanks'),  color: '#3a8dff' },
    { key: 'heal',  label: __('Healers'),color: '#4ADE80' },
    { key: 'dps',   label: __('DPS'),    color: '#ff5063' },
    { key: 'core',  label: __('Core'),   color: '#39FF14' },
    { key: 'bench', label: __('Bench'),  color: '#ff6e84' },
]);

const filteredRoster = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const isolating = isolated.value && selectedIds.value.size > 0;
    const f = activeFilter.value;

    if (!q && !isolating && f === 'all') return props.groupedRoster;

    const result = {};
    for (const [roleKey, members] of Object.entries(props.groupedRoster)) {
        if (['tank', 'heal', 'dps'].includes(f) && roleKey !== f) {
            result[roleKey] = [];
            continue;
        }
        result[roleKey] = members.filter(m => {
            if (f === 'core'  && m.roster_status !== 'core')  return false;
            if (f === 'bench' && m.roster_status !== 'bench') return false;
            if (q && !m.main_character?.name?.toLowerCase().includes(q)) return false;
            if (isolating && !selectedIds.value.has(m.id)) return false;
            return true;
        });
    }
    return result;
});

const slotLabels = {
    'HEAD': 'Head', 'NECK': 'Neck', 'SHOULDER': 'Shoulder', 'BACK': 'Cloak', 'CHEST': 'Chest', 'WRIST': 'Wrist',
    'HANDS': 'Hands', 'WAIST': 'Waist', 'LEGS': 'Legs', 'FEET': 'Feet', 'FINGER_1': 'Ring', 'FINGER_2': 'Ring',
    'TRINKET_1': 'Trinket', 'TRINKET_2': 'Trinket', 'MAIN_HAND': 'Main Hand', 'OFF_HAND': 'Off Hand'
};

// ── Thead height tracking — guard div mirrors actual thead height ──
const theadEl = ref(null);
const theadHeight = ref(54);
let theadResizeObs = null;
const measureThead = () => {
    if (theadEl.value) theadHeight.value = theadEl.value.offsetHeight;
};
onMounted(() => {
    nextTick(() => {
        measureThead();
        if (theadEl.value && typeof ResizeObserver !== 'undefined') {
            theadResizeObs = new ResizeObserver(measureThead);
            theadResizeObs.observe(theadEl.value);
        }
    });
});
onBeforeUnmount(() => {
    if (theadResizeObs) theadResizeObs.disconnect();
});
watch(() => props.activeTab, () => nextTick(measureThead));

</script>

<template>
    <div class="relative w-full h-full flex flex-col">

    <!-- ── Filter Pills ──────────────────────────────────────────────── -->
    <div class="flex items-center gap-1.5 mb-2.5 flex-wrap shrink-0">
        <button v-for="pill in filterPills" :key="pill.key"
                @click="activeFilter = pill.key"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-2xs font-bold transition-all"
                :style="activeFilter === pill.key
                    ? { background: pill.color + '22', border: `1px solid ${pill.color}66`, color: pill.color }
                    : { background: 'transparent', border: '1px solid rgba(255,255,255,0.06)', color: '#adaaad' }">
            {{ pill.label }}
            <span class="font-mono text-[9px] px-1 rounded"
                  :style="activeFilter === pill.key
                      ? { background: pill.color + '33', color: pill.color }
                      : { background: 'rgba(255,255,255,0.04)', color: '#767577' }">
                {{ filterCounts[pill.key] }}
            </span>
        </button>
    </div>

    <div class="w-full flex-1 min-h-0 overflow-y-auto overflow-x-auto roster-scroll relative">
        <!-- Full-thead-area guard: opaque backdrop sized to actual thead height -->
        <div class="sticky top-0 z-[19] pointer-events-none roster-thead-guard"
             :style="{ height: theadHeight + 'px', marginBottom: -theadHeight + 'px' }"></div>
        <table class="text-left text-2xs border-collapse w-full">
            <!-- thead ──────────────────────────────────────── -->
            <thead ref="theadEl" class="sticky top-0 z-20 roster-thead-solid">
                <!-- ── Row 1: Group / section labels ──────────────── -->
                <tr class="text-5xs font-black uppercase tracking-[0.12em] h-[18px]"
                    style="background: #131315; box-shadow: inset 0 -1px 0 rgba(255,255,255,0.06);">
                    <th class="compare-col" :class="compareMode ? 'compare-col-open' : 'compare-col-closed'"></th>
                    <th class="p-2 pl-4 w-[12.5rem] min-w-[12.5rem]" style="color: #767577;">
                        {{ __('Character') }}
                    </th>

                    <template v-if="activeTab === 'summary'">
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[70px]" style="color: #767577;">ILVL</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[100px]" style="color: #767577;">{{ __('Tier Pieces') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[140px]" style="color: #767577;">M+ {{ __('Runs') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[90px]" style="color: #767577;">{{ __('Rating') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[120px]" style="color: #767577;">{{ __('Audit') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[130px]" style="color: #767577;">{{ __('Access Role') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[130px]" style="color: #767577;">{{ __('Roster Status') }}</th>
                        <th v-if="canKick" class="border-l border-white/[0.06] min-w-[60px]"></th>
                    </template>

                    <template v-if="activeTab === 'raids'">
                        <th v-for="raid in raidColumns" :key="'rh-' + raid.name"
                            :colspan="raid.bosses.length"
                            class="p-1 text-center border-l border-white/[0.06]"
                            style="color: #4fd3f7;">
                            {{ raid.name }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th :colspan="slots.length + 2" class="p-1 text-center border-l border-white/[0.06]" style="color: #767577;">
                            {{ __('Gear') }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'vault'">
                        <th colspan="3" class="p-1 text-center border-l border-white/[0.06]" style="color: #a855f7;">{{ __('Raids') }}</th>
                        <th colspan="3" class="p-1 text-center border-l border-white/[0.06]" style="color: #FB923C;">{{ __('M+ Dungeons') }}</th>
                        <th colspan="3" class="p-1 text-center border-l border-white/[0.06]" style="color: #4fd3f7;">{{ __('Delves / World') }}</th>
                    </template>
                </tr>

                <!-- ── Row 2: Column sub-headers ──────────────────── -->
                <tr class="text-4xs font-black uppercase tracking-[0.1em] h-9"
                    style="background: #0e0e10; box-shadow: inset 0 -1px 0 rgba(255,255,255,0.06); color: #767577;">
                    <th class="compare-col text-center" :class="compareMode ? 'compare-col-open' : 'compare-col-closed'">
                        <span class="material-symbols-outlined text-sm compare-col-content" style="color: #39FF14;">compare_arrows</span>
                    </th>
                    <th class="px-4 py-1 w-[12.5rem] min-w-[12.5rem]">
                        <div class="flex items-center gap-1">
                            <template v-if="showSearch">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full bg-white/10 border border-white/10 rounded px-1.5 py-px text-3xs text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 font-normal normal-case tracking-normal leading-tight h-5"
                                    :placeholder="__('Search...')"
                                    @keydown.escape="toggleSearch"
                                    ref="searchInput"
                                />
                                <button @click="toggleSearch" class="text-gray-400 hover:text-white transition-colors shrink-0">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </template>
                            <template v-else>
                                <span style="color: #767577;">{{ __('Name') }}</span>
                                <span class="flex-1"></span>
                                <button @click="toggleCompareMode"
                                        class="transition-colors"
                                        :style="compareMode ? { color: '#39FF14' } : { color: '#767577' }"
                                        :title="__('Compare players')">
                                    <span class="material-symbols-outlined text-sm">compare_arrows</span>
                                </button>
                                <button @click="toggleSearch" class="transition-colors hover:text-white" style="color: #767577;">
                                    <span class="material-symbols-outlined text-sm">search</span>
                                </button>
                            </template>
                        </div>
                    </th>

                    <template v-if="activeTab === 'summary'">
                        <th class="px-2 py-1 text-center min-w-[70px] border-l border-white/[0.06]"></th>
                        <th class="px-2 py-1 text-center border-l border-white/[0.06] min-w-[100px]">
                            <div class="inline-flex gap-1 justify-center" style="color: #52525b;">
                                <span v-for="s in ['H','S','C','G','L']" :key="s" class="font-bold" style="font-size: 8px;">{{ s }}</span>
                            </div>
                        </th>
                        <th class="px-2 py-1 border-l border-white/[0.06] min-w-[140px]">
                            <span class="flex items-center justify-center gap-1">
                                <span>{{ __('Runs') }}</span>
                                <InfoTooltip :text="__('Only keys +10 and above are counted')" />
                            </span>
                        </th>
                        <th class="px-2 py-1 text-center border-l border-white/[0.06] min-w-[90px]">{{ __('Rating') }}</th>
                        <th class="px-2 py-1 text-center border-l border-white/[0.06] min-w-[120px]">{{ __('Issues') }}</th>
                        <th class="px-2 py-1 text-center border-l border-white/[0.06] min-w-[130px]">{{ __('Role') }}</th>
                        <th class="px-2 py-1 text-center border-l border-white/[0.06] min-w-[130px]">{{ __('Status') }}</th>
                        <th v-if="canKick" class="border-l border-white/[0.06] min-w-[60px]"></th>
                    </template>

                    <template v-if="activeTab === 'raids'">
                        <template v-for="raid in raidColumns" :key="raid.name">
                            <th v-for="bossName in raid.bosses" :key="bossName"
                                class="px-1 py-1 align-middle border-l border-white/[0.04] text-center min-w-[60px]"
                                :title="bossName">
                                <div class="mx-auto normal-case font-medium whitespace-normal break-words leading-tight" style="font-size: 9px; color: #767577;">
                                    {{ bossName }}
                                </div>
                            </th>
                        </template>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th class="p-2 text-center border-l border-white/[0.06] w-[6.25rem]">{{ __('Issues') }}</th>
                        <th class="p-1 text-center border-l border-white/[0.06] min-w-[52px]" style="font-size: 8px;">{{ __('UPGRADES MISSING') }}</th>
                        <th v-for="slot in slots" :key="slot"
                            class="p-1 text-center border-l border-white/[0.06] min-w-[52px]" style="font-size: 8px;">
                            {{ __(slotLabels[slot]) }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'vault'">
                        <th class="p-2 text-center border-l border-white/[0.06]">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 3') }}</th>
                        <th class="p-2 text-center border-l border-white/[0.06]">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 3') }}</th>
                        <th class="p-2 text-center border-l border-white/[0.06]">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center">{{ __('Slot 3') }}</th>
                    </template>
                </tr>
            </thead>

            <!-- tbody ──────────────────────────────────────── -->
            <tbody class="divide-y divide-white/5">
                <template v-for="(members, roleKey) in filteredRoster" :key="roleKey">
                    <!-- Role group separator row -->
                    <tr v-if="members.length > 0 && roleKey !== 'unknown'">
                        <td colspan="100%"
                            class="py-1.5"
                            :style="{
                                paddingLeft: '14px',
                                background: `linear-gradient(90deg, ${roleColor(roleKey)}18 0%, ${roleColor(roleKey)}06 50%, transparent 100%)`,
                                borderTop: `1px solid ${roleColor(roleKey)}33`,
                                borderBottom: `1px solid ${roleColor(roleKey)}22`,
                                borderLeft: `3px solid ${roleColor(roleKey)}`,
                            }">
                            <div class="flex items-center gap-2">
                                <span class="text-3xs font-black uppercase tracking-[0.14em]"
                                      :style="{ color: roleColor(roleKey) }">
                                    {{ __(roles.find(r => r.id === roleKey)?.labelKey || roleKey) }}
                                </span>
                                <span class="font-mono text-3xs font-bold" style="color: #767577;">
                                    ({{ members.length }})
                                </span>
                            </div>
                        </td>
                    </tr>

                    <template v-for="member in members" :key="member.id">
                        <template v-if="roleKey !== 'unknown'">
                            <!-- Main character row -->
                            <RosterRow
                                :member="member"
                                :char="member.main_character"
                                :grouped-roster="groupedRoster"
                                :active-tab="activeTab"
                                :selected-difficulty="selectedDifficulty"
                                :expanded="expandedRows.has(member.id)"
                                :class-colors="classColors"
                                :tier-colors="tierColors"
                                :kill-mark-class="killMarkClass"
                                :raid-columns="raidColumns"
                                :can-manage-status="canManageStatus"
                                :can-manage-access="canManageAccess"
                                :can-kick="canKick"
                                :role-icon-src="roleIconSrc"
                                :tier-count="tierCount"
                                :has-audit-issues="hasAuditIssues"
                                :audit-title="auditTitle"
                                :compare-mode="compareMode"
                                :compare-selected="selectedIds.has(member.id)"
                                :compare-isolated="isolated"
                                @toggle-compare="toggleMemberSelection(member.id)"
                                @open-audit-modal="(c) => emit('open-audit-modal', c)"
                                @toggle-expand="(id) => emit('toggle-row', id)"
                                @update-access-role="(m, r) => emit('update-access-role', m, r)"
                                @update-roster-status="(m, s) => emit('update-roster-status', m, s)"
                                @kick-member="(m) => emit('kick-member', m)"
                            />

                            <!-- Alt rows -->
                            <template v-if="expandedRows.has(member.id)">
                                <RosterRow
                                    v-for="(alt, index) in (member.alts || [])"
                                    :key="'alt-' + (alt?.id || index)"
                                    :member="member"
                                    :char="alt"
                                    :grouped-roster="groupedRoster"
                                    :is-alt="true"
                                    :compare-mode="compareMode"
                                    :active-tab="activeTab"
                                    :selected-difficulty="selectedDifficulty"
                                    :class-colors="classColors"
                                    :tier-colors="tierColors"
                                    :kill-mark-class="killMarkClass"
                                    :raid-columns="raidColumns"
                                    :can-manage-status="canManageStatus"
                                    :can-manage-access="canManageAccess"
                                    :can-kick="canKick"
                                    :role-icon-src="roleIconSrc"
                                    :tier-count="tierCount"
                                    :has-audit-issues="hasAuditIssues"
                                    :audit-title="auditTitle"
                                    @open-audit-modal="(c) => emit('open-audit-modal', c)"
                                    @update-access-role="(m, r) => emit('update-access-role', m, r)"
                                    @update-roster-status="(m, s) => emit('update-roster-status', m, s)"
                                    @kick-member="(m) => emit('kick-member', m)"
                                />
                            </template>
                        </template>
                    </template>
                </template>
            </tbody>
        </table>
    </div>

    <!-- Floating compare action bar -->
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 translate-y-3"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-3"
    >
        <div v-if="compareMode"
             class="fixed left-1/2 -translate-x-1/2 bottom-6 z-50 flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-surface-container-high/95 backdrop-blur border border-white/10 shadow-2xl">
            <div class="flex items-center gap-2 pr-3 border-r border-white/10">
                <span class="material-symbols-outlined text-lg" style="color:#39FF14">compare_arrows</span>
                <span class="text-2xs uppercase tracking-wider font-bold text-on-surface-variant">
                    {{ __('Selected') }}:
                </span>
                <span class="text-xs font-bold text-white tabular-nums min-w-[1.5ch] text-center">
                    {{ selectedCount }}
                </span>
            </div>

            <button
                @click="toggleIsolate"
                :disabled="selectedCount === 0"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-2xs font-bold uppercase tracking-wider transition-all',
                    selectedCount === 0
                        ? 'bg-white/5 text-gray-600 cursor-not-allowed'
                        : isolated
                            ? 'bg-[#39FF14] text-gray-900 hover:opacity-80'
                            : 'bg-[#39FF14]/10 text-[#39FF14] border border-[#39FF14]/30 hover:bg-[#39FF14]/20'
                ]"
            >
                <span class="material-symbols-outlined text-sm">
                    {{ isolated ? 'visibility' : 'filter_list' }}
                </span>
                {{ isolated ? __('Show all') : __('Isolate') }}
            </button>

            <button
                @click="clearSelection"
                :disabled="selectedCount === 0"
                :class="[
                    'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-2xs font-bold uppercase tracking-wider transition-all',
                    selectedCount === 0
                        ? 'bg-white/5 text-gray-600 cursor-not-allowed'
                        : 'bg-white/5 text-on-surface-variant hover:bg-white/10 hover:text-white'
                ]"
            >
                <span class="material-symbols-outlined text-sm">restart_alt</span>
                {{ __('Clear') }}
            </button>

            <button
                @click="toggleCompareMode"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-white hover:bg-white/5 transition-all"
                :title="__('Exit compare mode')"
            >
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
    </transition>
    </div>
</template>

<style>
/* Compare column animated expand/collapse (unscoped so it applies to RosterRow too) */
.compare-col {
    overflow: hidden;
    white-space: nowrap;
    transition: width 220ms ease, min-width 220ms ease, max-width 220ms ease, padding 220ms ease;
}
.compare-col-open {
    width: 36px;
    min-width: 36px;
    max-width: 36px;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.compare-col-closed {
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}
.compare-col-content {
    display: inline-flex;
    transition: opacity 180ms ease, transform 220ms ease;
}
.compare-col-closed .compare-col-content {
    opacity: 0;
    transform: scale(0.5);
}
.compare-col-open .compare-col-content {
    opacity: 1;
    transform: scale(1);
}

/* Visible vertical dividers in the sticky thead. Use real border-left (same mechanism
   as body), just with a stronger opaque-equivalent colour so the line shows through
   the per-cell background and the opaque guard behind. Same mechanism = pixel-perfect
   alignment with body's border-l. */
.roster-thead-solid > tr > th.border-l {
    border-left-color: rgba(255, 255, 255, 0.10) !important;
}
</style>

<style scoped>
/* Dark scrollbar matching rounded-2xl container */
.roster-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.roster-scroll::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 16px;
    margin-top: 8px;
    margin-bottom: 8px;
}
.roster-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16px;
}
.roster-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
.roster-scroll::-webkit-scrollbar-corner {
    background: transparent;
}
/* Firefox */
.roster-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}

/* Sticky thead must be 100% opaque. Two issues: per-cell background, AND the 1px
   transparent border-left between th cells (which lets body show through during scroll).
   Fix: remove border-left and redraw the divider as inset box-shadow (no layout space,
   cell background extends behind it). */
.roster-thead-solid {
    background-color: #0e0e10;
}
/* Full-area opaque mask under the entire thead. Sits below thead (z-19 < z-20)
   but above body cells. Height is set inline from JS-measured thead height so it
   tracks any tab/content changes that affect the header's actual size. */
.roster-thead-guard {
    width: 100%;
    background-color: #0e0e10;
}
.roster-thead-solid > tr:nth-child(1) > th {
    background-color: #131315;
}
.roster-thead-solid > tr:nth-child(2) > th {
    background-color: #0e0e10;
}

</style>
