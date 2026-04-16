<script setup>
import { ref, computed, nextTick, provide } from 'vue';
import RosterRow from './RosterRow.vue';
import InfoTooltip from '@/Components/UI/InfoTooltip.vue';
import { useGearSpecSwitch } from '@/composables/useGearSpecSwitch';

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

const filteredRoster = computed(() => {
    const q = searchQuery.value.toLowerCase().trim();
    const isolating = isolated.value && selectedIds.value.size > 0;
    if (!q && !isolating) return props.groupedRoster;
    const result = {};
    for (const [roleKey, members] of Object.entries(props.groupedRoster)) {
        result[roleKey] = members.filter(m => {
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
</script>

<template>
    <div class="relative w-full">
    <div class="w-full bg-surface-container-high rounded-2xl border border-white/5 max-h-[55vh] overflow-y-auto overflow-x-auto roster-scroll">
        <table class="text-left text-2xs border-collapse w-full">
            <!-- thead ──────────────────────────────────────── -->
            <thead class="sticky top-0 z-20">
                <!-- Group header row -->
                <tr class="bg-[#1a1a1a] text-gray-500 text-5xs uppercase tracking-widest font-bold h-8" style="box-shadow: inset 0 -1px 0 #222">
                    <th class="compare-col" :class="compareMode ? 'compare-col-open' : 'compare-col-closed'"></th>
                    <th class="p-2 pl-4 w-[200px] min-w-[200px]">{{ __('Character') }}</th>

                    <template v-if="activeTab === 'summary'">
                        <th class="p-2 text-center border-l border-[#222] w-[60px]">{{ __('iLvL') }}</th>
                        <th colspan="6" class="p-2 text-center border-l border-[#222]">{{ __('Tier Pieces') }}</th>
                        <th class="p-2 text-center border-l border-[#222]">{{ __('M+ Runs') }}</th>
                        <th class="p-2 text-center border-l border-[#222]">{{ __('M+ Rating') }}</th>
                        <th class="p-2 text-center border-l border-[#222]">{{ __('Audit') }}</th>
                        <th class="p-2 text-center border-l border-[#222] w-[130px]">{{ __('Access Role') }}</th>
                        <th class="p-2 text-center border-l border-[#222] w-[130px]">{{ __('Roster Status') }}</th>
                        <th v-if="canKick" class="p-2 text-center border-l border-[#222] w-[60px]"></th>
                    </template>

                    <!-- One spanning header per raid instance -->
                    <template v-if="activeTab === 'raids'">
                        <th v-for="raid in raidColumns" :key="'rh-' + raid.name"
                            :colspan="raid.bosses.length"
                            class="p-2 text-center border-l border-[#222] uppercase tracking-widest font-bold text-gray-400">
                            {{ raid.name }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th :colspan="slots.length + 2" class="p-2 text-center border-l border-[#222] uppercase tracking-widest font-bold text-gray-400 w-[1000px]">
                            {{ __('Gear') }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'vault'">
                        <th colspan="3" class="p-2 text-center border-l border-[#222] uppercase tracking-widest font-bold text-gray-400">
                            {{ __('Raids') }}
                        </th>
                        <th colspan="3" class="p-2 text-center border-l border-white/10 uppercase tracking-widest font-bold text-gray-400">
                            {{ __('M+ Dungeons') }}
                        </th>
                        <th colspan="3" class="p-2 text-center border-l border-white/10 uppercase tracking-widest font-bold text-gray-400">
                            {{ __('Delves / World') }}
                        </th>
                    </template>
                </tr>

                <!-- Column sub-header row -->
                <tr class="bg-[#111111] text-emerald-400 text-4xs uppercase tracking-widest font-bold h-10" style="box-shadow: inset 0 -1px 0 #222">
                    <th class="compare-col text-center" :class="compareMode ? 'compare-col-open' : 'compare-col-closed'">
                        <span class="material-symbols-outlined text-sm text-emerald-400 compare-col-content">compare_arrows</span>
                    </th>
                    <th class="px-4 py-1 w-[200px] min-w-[200px]">
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
                                <button @click="toggleSearch" class="text-gray-500 hover:text-white transition-colors shrink-0">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </template>
                            <template v-else>
                                <span>{{ __('Name') }}</span>
                                <span class="flex-1"></span>
                                <button @click="toggleCompareMode"
                                        :class="['transition-colors', compareMode ? 'text-emerald-400' : 'text-gray-500 hover:text-emerald-400']"
                                        :title="__('Compare players')">
                                    <span class="material-symbols-outlined text-sm">compare_arrows</span>
                                </button>
                                <button @click="toggleSearch" class="text-gray-500 hover:text-emerald-400 transition-colors">
                                    <span class="material-symbols-outlined text-sm">search</span>
                                </button>
                            </template>
                        </div>
                    </th>

                    <template v-if="activeTab === 'summary'">
                        <th class="px-2 py-2 text-center w-[60px]">{{ __('Avg') }}</th>

                        <th class="p-2 text-center border-l border-[#222] w-[40px]">#</th>
                        <th v-for="slot in ['H','S','C','G','L']" :key="slot"
                            class="p-1 text-center text-on-surface-variant w-8">{{ slot }}</th>
                        <th class="px-2 py-2 border-l border-[#222]">
                            <span class="flex items-center justify-between">
                                <span class="flex-1 text-center">{{ __('Runs') }}</span>
                                <InfoTooltip :text="__('Only keys +10 and above are counted')" />
                            </span>
                        </th>
                        <th class="p-2 text-center border-l border-[#222]">{{ __('Rating') }}</th>
                        <th class="p-2 text-center border-l border-[#222]">{{ __('Issues') }}</th>
                        <th class="p-2 text-center border-l border-[#222] w-[130px]">{{ __('Role') }}</th>
                        <th class="p-2 text-center border-l border-[#222] w-[130px]">{{ __('Status') }}</th>
                        <th v-if="canKick" class="p-2 text-center border-l border-[#222] w-[60px]"></th>
                    </template>

                    <!-- One column per boss, horizontal label -->
                    <template v-if="activeTab === 'raids'">
                        <template v-for="raid in raidColumns" :key="raid.name">
                            <th v-for="bossName in raid.bosses" :key="bossName"
                                class="px-1 py-1 align-middle border-l border-white/[0.04] text-center min-w-[60px]"
                                :title="bossName">
                                <div class="mx-auto text-4xs text-on-surface-variant font-medium normal-case whitespace-normal break-words leading-tight">
                                    {{ bossName }}
                                </div>
                            </th>
                        </template>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th class="p-2 text-center border-l border-[#222] w-[100px]">{{ __('Issues') }}</th>
                        <th class="p-1 text-center border-l border-[#222] text-5xs min-w-[52px]">{{ __('UPGRADES MISSING') }}</th>
                        <th v-for="slot in slots" :key="slot" class="p-1 text-center border-l border-[#222] text-5xs min-w-[52px]">
                            {{ __(slotLabels[slot]) }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'vault'">
                        <th class="p-2 text-center border-l border-[#222] w-auto">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 3') }}</th>
                        <th class="p-2 text-center border-l border-white/10 w-auto">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 3') }}</th>
                        <th class="p-2 text-center border-l border-white/10 w-auto">{{ __('Slot 1') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 2') }}</th>
                        <th class="p-2 text-center w-auto">{{ __('Slot 3') }}</th>
                    </template>
                </tr>
            </thead>

            <!-- tbody ──────────────────────────────────────── -->
            <tbody class="divide-y divide-white/5">
                <template v-for="(members, roleKey) in filteredRoster" :key="roleKey">
                    <!-- Role group separator row -->
                    <tr v-if="members.length > 0 && roleKey !== 'unknown'"
                        class="bg-gray-800/80 border-y border-gray-700">
                        <td colspan="100%" class="py-1 px-4 text-3xs font-bold uppercase tracking-widest text-emerald-400">
                            <div class="flex items-center gap-2">
                                <img :src="roleIconSrc(roleKey)" class="w-3 h-3 opacity-80" :alt="roleKey">
                                <span>{{ __(roles.find(r => r.id === roleKey)?.labelKey || roleKey) }} ({{ members.length }})</span>
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
                <span class="material-symbols-outlined text-emerald-400 text-lg">compare_arrows</span>
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
                            ? 'bg-emerald-400 text-gray-900 hover:bg-emerald-300'
                            : 'bg-emerald-400/10 text-emerald-400 border border-emerald-400/30 hover:bg-emerald-400/20'
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
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-white hover:bg-white/5 transition-all"
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

/* Ensure all header cells are opaque so content doesn't bleed through */
thead th {
    background: inherit;
}

</style>
