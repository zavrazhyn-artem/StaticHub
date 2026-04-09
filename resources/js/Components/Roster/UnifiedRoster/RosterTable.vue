<script setup>
import { ref, computed, nextTick, provide } from 'vue';
import RosterRow from './RosterRow.vue';
import InfoTooltip from '@/Components/UI/InfoTooltip.vue';

// ── Row height tokens (single source of truth) ──
const ROW_HEIGHTS = {
    main: 'h-[72px]',
    alt:  'h-[42px]',
};
provide('rowHeights', ROW_HEIGHTS);

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
    if (!q) return props.groupedRoster;
    const result = {};
    for (const [roleKey, members] of Object.entries(props.groupedRoster)) {
        result[roleKey] = members.filter(m => m.main_character?.name?.toLowerCase().includes(q));
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
    <div class="w-full bg-surface-container-high rounded-2xl border border-white/5 max-h-[55vh] overflow-y-auto overflow-x-auto roster-scroll">
        <table class="text-left text-[11px] border-collapse w-full">
            <!-- thead ──────────────────────────────────────── -->
            <thead class="sticky top-0 z-20">
                <!-- Group header row -->
                <tr class="bg-[#1a1a1a] text-gray-500 text-[8px] uppercase tracking-widest font-bold h-8" style="box-shadow: inset 0 -1px 0 #222">
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
                        <th :colspan="slots.length" class="p-2 text-center border-l border-[#222] uppercase tracking-widest font-bold text-gray-400 w-[1000px]">
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
                <tr class="bg-[#111111] text-cyan-400 text-[9px] uppercase tracking-widest font-bold h-10" style="box-shadow: inset 0 -1px 0 #222">
                    <th class="px-4 py-1 w-[200px] min-w-[200px]">
                        <div class="flex items-center gap-1">
                            <template v-if="showSearch">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    class="w-full bg-white/10 border border-white/10 rounded px-1.5 py-px text-[10px] text-white placeholder-gray-500 focus:outline-none focus:border-cyan-500/50 font-normal normal-case tracking-normal leading-tight h-5"
                                    :placeholder="__('Search...')"
                                    @keydown.escape="toggleSearch"
                                    ref="searchInput"
                                />
                                <button @click="toggleSearch" class="text-gray-500 hover:text-white transition-colors shrink-0">
                                    <span class="material-symbols-outlined text-[14px]">close</span>
                                </button>
                            </template>
                            <template v-else>
                                <span>{{ __('Name') }}</span>
                                <span class="flex-1"></span>
                                <button @click="toggleSearch" class="text-gray-500 hover:text-cyan-400 transition-colors">
                                    <span class="material-symbols-outlined text-[14px]">search</span>
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
                                <div class="mx-auto text-[9px] text-on-surface-variant font-medium normal-case whitespace-normal break-words leading-tight">
                                    {{ bossName }}
                                </div>
                            </th>
                        </template>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th class="p-2 text-center border-l border-[#222] w-[100px]">{{ __('Issues') }}</th>
                        <th class="p-1 text-center border-l border-[#222] text-[7px] min-w-[52px]">{{ __('UPGRADES MISSING') }}</th>
                        <th v-for="slot in slots" :key="slot" class="p-1 text-center border-l border-[#222] text-[7px] min-w-[52px]">
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
                        <td colspan="100%" class="py-1 px-4 text-[10px] font-bold uppercase tracking-widest text-cyan-400">
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
</template>

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
