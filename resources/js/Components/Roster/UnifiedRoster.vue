<script setup>
import { ref, computed, watch, onMounted, getCurrentInstance } from 'vue';
import axios from 'axios';
import TabGear from './TabGear.vue';
import RosterTabs from './UnifiedRoster/RosterTabs.vue';
import RosterTable from './UnifiedRoster/RosterTable.vue';
import GlassModal from '../UI/GlassModal.vue';
import SearchableSelect from '../UI/SearchableSelect.vue';

// ---------------------------------------------------------------------------
// i18n helper
// ---------------------------------------------------------------------------
const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------
const props = defineProps({
    staticId:    { type: Number, required: true },
    initialData: { type: Object,  default: null  },
});

// ---------------------------------------------------------------------------
// Week selector
// ---------------------------------------------------------------------------
const rawWeeks        = props.initialData?.available_weeks ?? [];
const currentWeek     = ref(props.initialData?.current_week ?? '');
const selectedWeek    = ref(props.initialData?.current_week ?? '');
const weekLoading     = ref(false);
const isLive          = computed(() => selectedWeek.value === currentWeek.value);

/** Options for SearchableSelect, sorted descending (current week first). */
const weekOptions = computed(() =>
    [...rawWeeks].reverse().map(w => ({
        id:   w.key,
        name: w.current ? __('Live Week') : `${__('Week')} ${w.number}`,
    }))
);

/** Get the week number for a given period key. */
const weekNumber = (key) => {
    const w = rawWeeks.find(w => w.key === key);
    return w ? w.number : null;
};

// Keyed cache: periodKey → { characterId → weeklyData }
const snapshotCache   = {};
// Original live weekly fields per character, keyed by characterId
let liveWeeklyData    = {};

const WEEKLY_FIELDS = [
    'weekly_runs_count', 'week_regular_mythic', 'raids',
    'vault_weekly_runs', 'vault_world_runs', 'vault_raid_slots',
    'prey_weekly', 'weekly_quests', 'weekly_event_done', 'week_delves',
];

/** Extract weekly fields from a character object. */
const extractWeekly = (char) => {
    const data = {};
    for (const f of WEEKLY_FIELDS) {
        data[f] = char[f] ?? null;
    }
    return data;
};

/** Apply weekly data overlay onto roster members (mutates in place). */
const applyWeeklyOverlay = (overlay) => {
    for (const member of roster.value) {
        if (member.main_character) {
            const snap = overlay?.[member.main_character.id];
            if (snap) {
                for (const f of WEEKLY_FIELDS) {
                    member.main_character[f] = snap[f] ?? null;
                }
            } else {
                for (const f of WEEKLY_FIELDS) {
                    member.main_character[f] = null;
                }
            }
        }
        for (const alt of (member.alts || [])) {
            const snap = overlay?.[alt.id];
            if (snap) {
                for (const f of WEEKLY_FIELDS) {
                    alt[f] = snap[f] ?? null;
                }
            } else {
                for (const f of WEEKLY_FIELDS) {
                    alt[f] = null;
                }
            }
        }
    }
};

/** Restore live weekly data from the stored originals. */
const restoreLiveData = () => {
    for (const member of roster.value) {
        if (member.main_character) {
            const live = liveWeeklyData[member.main_character.id];
            if (live) {
                for (const f of WEEKLY_FIELDS) {
                    member.main_character[f] = live[f] ?? null;
                }
            }
        }
        for (const alt of (member.alts || [])) {
            const live = liveWeeklyData[alt.id];
            if (live) {
                for (const f of WEEKLY_FIELDS) {
                    alt[f] = live[f] ?? null;
                }
            }
        }
    }
};

/** Store live weekly data from the current roster state. */
const storeLiveData = () => {
    liveWeeklyData = {};
    for (const member of roster.value) {
        if (member.main_character) {
            liveWeeklyData[member.main_character.id] = extractWeekly(member.main_character);
        }
        for (const alt of (member.alts || [])) {
            liveWeeklyData[alt.id] = extractWeekly(alt);
        }
    }
};

const onWeekChange = async (week) => {
    selectedWeek.value = week;

    if (week === currentWeek.value) {
        restoreLiveData();
        return;
    }

    // Check cache first
    if (snapshotCache[week]) {
        applyWeeklyOverlay(snapshotCache[week]);
        return;
    }

    weekLoading.value = true;
    try {
        const { data } = await axios.get(`/statics/${props.staticId}/roster/weekly-snapshot`, {
            params: { week },
        });
        snapshotCache[week] = data.snapshot || {};
        applyWeeklyOverlay(snapshotCache[week]);
    } catch (err) {
        console.error('Failed to fetch weekly snapshot:', err);
    } finally {
        weekLoading.value = false;
    }
};

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
const roster             = ref([]);
const currentUserAccess  = ref('member');
const loading            = ref(true);
const currentTab         = ref(localStorage.getItem('rosterActiveTab') || 'summary');
const expandedRows       = ref(new Set());
const selectedDifficulty = ref(localStorage.getItem('rosterSelectedDifficulty'));

const showAuditModal     = ref(false);
const selectedAuditChar  = ref(null);

const openAuditModal = (char) => {
    selectedAuditChar.value = char;
    showAuditModal.value = true;
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const getHighestActiveDifficulty = () => {
    if (!roster.value || roster.value.length === 0) return 'H';

    const difficulties = [
        { name: 'Mythic', key: 'M', code: 'M' },
        { name: 'Heroic', key: 'H', code: 'H' },
        { name: 'Normal', key: 'N', code: 'N' },
        { name: 'LFR',    key: 'LFR', code: 'LFR' }
    ];

    for (const diff of difficulties) {
        const hasKill = roster.value.some(member => {
            const raids = member.main_character?.raids;
            if (!raids) return false;

            return Object.values(raids).some(bosses =>
                bosses.some(boss => boss[diff.key] === true)
            );
        });

        if (hasKill) return diff.code;
    }

    return 'N';
};

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
const roles = [
    { id: 'tank', labelKey: 'Tanks',   max: 2,  color: 'text-blue-400'  },
    { id: 'heal', labelKey: 'Healers', max: 4,  color: 'text-green-400' },
    { id: 'dps',  labelKey: 'DPS',     max: 14, color: 'text-red-400'   },
];

const classColors = {
    'Death Knight': 'text-[#C41F3B]', 'Demon Hunter': 'text-[#A330C9]',
    'Druid':        'text-[#FF7C0A]', 'Evoker':       'text-[#33937F]',
    'Hunter':       'text-[#ABD473]', 'Mage':         'text-[#3FC7EB]',
    'Monk':         'text-[#00FF98]', 'Paladin':      'text-[#F48CBA]',
    'Priest':       'text-[#FFFFFF]', 'Rogue':        'text-[#FFF468]',
    'Shaman':       'text-[#0070DD]', 'Warlock':      'text-[#8788EE]',
    'Warrior':      'text-[#C69B6D]',
};

const tierColors = {
    'Myth':       'text-orange-500 font-black',
    'Hero':       'text-purple-500 font-bold',
    'Champion':   'text-blue-500   font-bold',
    'Veteran':    'text-green-500  font-bold',
    'Adventurer': 'text-teal-500   font-bold',
    'Explorer':   'text-gray-400   font-bold',
    '-':          'text-gray-700',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const roleIconSrc = (role) => {
    const map = {
        TANK: 'tank',  tank:   'tank',
        HEALER: 'heal', heal:  'heal', healer: 'heal',
        DPS: 'melee',  dps:   'melee', mdps:   'melee', rdps: 'range',
    };
    return `/images/roles/${role ? (map[role] ?? 'help') : 'help'}.svg`;
};

const normalizeRoleKey = (role) => {
    if (!role) return 'unknown';
    const map = {
        TANK: 'tank', tank: 'tank',
        HEALER: 'heal', heal: 'heal', healer: 'heal',
        DPS: 'dps', mdps: 'dps', rdps: 'dps',
    };
    return map[role] ?? 'unknown';
};

const tierCount = (pieces) => {
    if (!pieces) return 0;
    return Object.values(pieces).filter(v => v !== '-').length;
};

const killMarkClass = computed(() => ({
    M:   'text-orange-400',
    H:   'text-purple-400',
    N:   'text-blue-400',
    LFR: 'text-green-400',
}[selectedDifficulty.value] ?? 'text-green-400'));

const hasAuditIssues = (char) => {
    if (!char) return false;
    return (char.missing_enchants_slots?.length ?? 0) > 0
        || (char.low_quality_enchants_slots?.length ?? 0) > 0
        || (char.empty_sockets_count ?? 0) > 0;
};

const auditIssueCount = (char) => {
    if (!char) return 0;
    return (char.missing_enchants_slots?.length ?? 0)
        + (char.low_quality_enchants_slots?.length ?? 0)
        + (char.empty_sockets_count ?? 0);
};

const auditTitle = (char) => {
    if (!char) return '';
    const parts = [];
    if (char.missing_enchants_slots?.length > 0)
        parts.push(__('Missing enchants:') + ` ${char.missing_enchants_slots.join(', ')}`);
    if (char.low_quality_enchants_slots?.length > 0)
        parts.push(__('Low quality enchants:') + ` ${char.low_quality_enchants_slots.join(', ')}`);
    if (char.empty_sockets_count > 0)
        parts.push(__('Empty sockets:') + ` ${char.empty_sockets_count}`);
    return parts.join(' | ');
};

// ---------------------------------------------------------------------------
// Data fetching
// ---------------------------------------------------------------------------
const fetchRoster = async () => {
    if (props.initialData) {
        roster.value            = props.initialData.roster ?? [];
        currentUserAccess.value = props.initialData.current_user_access ?? 'member';

        if (!selectedDifficulty.value) {
            selectedDifficulty.value = getHighestActiveDifficulty();
        }

        storeLiveData();
        loading.value           = false;
        return;
    }
    loading.value = true;
    try {
        const response          = await axios.get(`/statics/${props.staticId}/roster/data`);
        roster.value            = response.data.roster ?? [];
        currentUserAccess.value = response.data.current_user_access ?? 'member';

        if (!selectedDifficulty.value) {
            selectedDifficulty.value = getHighestActiveDifficulty();
        }

        storeLiveData();
    } catch (err) {
        console.error('Failed to fetch roster:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRoster);

watch(currentTab, (val) => {
    localStorage.setItem('rosterActiveTab', val);
    if (val === 'treasury') {
        window.location.href = `/statics/${props.staticId}/treasury`;
    } else if (val === 'settings') {
        window.location.href = `/statics/${props.staticId}/settings/schedule`;
    }
});
watch(selectedDifficulty, val => localStorage.setItem('rosterSelectedDifficulty', val));

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------
const coreRoster = computed(() => roster.value.filter(m => m.roster_status === 'core'));

const stats = computed(() => {
    const counts = { tank: 0, heal: 0, dps: 0 };
    coreRoster.value.forEach(member => {
        const key = normalizeRoleKey(member.main_character?.main_spec?.role);
        if (key in counts) counts[key]++;
    });
    return counts;
});

const groupedRoster = computed(() => {
    const groups = { tank: [], heal: [], dps: [], unknown: [] };
    roster.value.forEach(member => {
        const key = normalizeRoleKey(member.main_character?.main_spec?.role);
        (groups[key] ?? groups.unknown).push(member);
    });
    return groups;
});

// Raid columns from backend config — always present regardless of weekly data
const raidColumns = computed(() => props.initialData?.raid_columns ?? []);

// ---------------------------------------------------------------------------
// Management actions
// ---------------------------------------------------------------------------
const canManageAccess = computed(() => ['leader', 'officer'].includes(currentUserAccess.value));
const canManageStatus = computed(() => ['leader', 'officer'].includes(currentUserAccess.value));
const canKick = computed(() => ['leader', 'officer'].includes(currentUserAccess.value));

const toggleRow = (memberId) => {
    if (expandedRows.value.has(memberId)) {
        expandedRows.value.delete(memberId);
    } else {
        expandedRows.value.add(memberId);
    }
};

const updateAccessRole = async (member, newRole) => {
    try {
        await axios.patch(`/statics/${props.staticId}/roster/${member.id}/access-role`, { access_role: newRole });
        member.access_role = newRole;
    } catch (err) {
        console.error('Failed to update access role:', err);
        alert(__('Failed to update access role. Check permissions.'));
    }
};

const updateRosterStatus = async (member, newStatus) => {
    try {
        await axios.patch(`/statics/${props.staticId}/roster/${member.id}/roster-status`, { roster_status: newStatus });
        member.roster_status = newStatus;
    } catch (err) {
        console.error('Failed to update roster status:', err);
        alert(__('Failed to update roster status. Check permissions.'));
    }
};

const kickMember = async (member) => {
    if (!confirm(__('Remove {name} from this static?', { name: member.name }))) return;
    try {
        await axios.delete(`/statics/${props.staticId}/roster/${member.id}/kick`);
        roster.value = roster.value.filter(m => m.id !== member.id);
    } catch (err) {
        console.error('Failed to kick member:', err);
        alert(err.response?.data?.message || __('Failed to remove member.'));
    }
};
</script>

<template>
    <div class="space-y-8">

        <!-- ── Week Header + Role Summary ──────────────────────────────── -->
        <div class="flex items-center justify-between bg-surface-container-high rounded-xl border border-white/5 px-4 py-2">
            <!-- Status indicator (left) -->
            <div class="flex items-center gap-2">
                <template v-if="isLive">
                    <span class="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_6px_#22c55e]"></span>
                    <span class="text-2xs font-black uppercase tracking-widest text-green-400">{{ __('Live') }}</span>
                </template>
                <template v-else>
                    <span class="material-symbols-outlined text-sm text-amber-400">history</span>
                    <span class="text-2xs font-black uppercase tracking-widest text-amber-400">{{ __('Week') }} {{ weekNumber(selectedWeek) }}</span>
                </template>
                <span v-if="weekLoading" class="material-symbols-outlined animate-spin text-sm text-emerald-400 ml-1">sync</span>
            </div>

            <!-- Role counts (center) -->
            <div class="flex items-center gap-4">
                <div v-for="role in roles" :key="role.id" class="flex items-center gap-1.5">
                    <img :src="roleIconSrc(role.id)" class="w-4 h-4 opacity-70" :alt="__(role.labelKey)">
                    <span class="text-2xs font-bold" :class="role.color">{{ stats[role.id] }}</span>
                    <span class="text-3xs text-gray-600">/{{ role.max }}</span>
                </div>
            </div>

            <!-- Week selector (right) -->
            <div class="w-52">
                <SearchableSelect
                    :model-value="selectedWeek"
                    @update:model-value="onWeekChange"
                    :options="weekOptions"
                    :use-search="false"
                    icon="date_range"
                    :placeholder="__('Select week...')"
                    compact
                />
            </div>
        </div>

        <RosterTabs
            v-model:activeTab="currentTab"
            v-model:selectedDifficulty="selectedDifficulty"
            :can-manage-status="canManageStatus"
        />

        <!-- ── Loading ──────────────────────────────────────────────────── -->
        <div v-if="loading" class="flex justify-center p-12">
            <span class="material-symbols-outlined animate-spin text-4xl text-emerald-400">sync</span>
        </div>

        <!-- ── Content ──────────────────────────────────────────────────── -->
        <div v-else class="space-y-4">

            <!-- ── Main Roster Table (all tabs including vault) ──────── -->
            <RosterTable
                :grouped-roster="groupedRoster"
                :active-tab="currentTab"
                :selected-difficulty="selectedDifficulty"
                :expanded-rows="expandedRows"
                :raid-columns="raidColumns"
                :roles="roles"
                :class-colors="classColors"
                :tier-colors="tierColors"
                :kill-mark-class="killMarkClass"
                :can-manage-status="canManageStatus"
                :can-manage-access="canManageAccess"
                :can-kick="canKick"
                :role-icon-src="roleIconSrc"
                :tier-count="tierCount"
                :has-audit-issues="hasAuditIssues"
                :audit-title="auditTitle"
                @open-audit-modal="openAuditModal"
                @toggle-row="toggleRow"
                @update-access-role="updateAccessRole"
                @update-roster-status="updateRosterStatus"
                @kick-member="kickMember"
            />
        </div>

        <!-- ── Audit Issues Modal ───────────────────────────────────────── -->
        <GlassModal :show="showAuditModal" @close="showAuditModal = false" max-width="max-w-lg">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-400/10 border border-amber-400/20 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-400">warning</span>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white leading-tight">
                                {{ __('Audit Issues') }}
                            </h3>
                            <p class="text-xs text-on-surface-variant font-medium uppercase tracking-wider" :class="classColors[selectedAuditChar?.class]">
                                {{ selectedAuditChar?.name }}
                            </p>
                        </div>
                    </div>
                    <button @click="showAuditModal = false" class="text-on-surface-variant hover:text-white transition-colors">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Missing Enchants -->
                    <div v-if="selectedAuditChar?.missing_enchants_slots?.length > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-red-400">auto_fix_high</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Missing Enchants') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="slot in selectedAuditChar.missing_enchants_slots" :key="slot"
                                  class="px-2 py-1 rounded bg-red-500/10 border border-red-500/20 text-3xs font-semibold text-red-300">
                                {{ slot }}
                            </span>
                        </div>
                    </div>

                    <!-- Low Quality Enchants -->
                    <div v-if="selectedAuditChar?.low_quality_enchants_slots?.length > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-amber-400">auto_fix_high</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Low Quality Enchants') }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="slot in selectedAuditChar.low_quality_enchants_slots" :key="slot"
                                  class="px-2 py-1 rounded bg-amber-500/10 border border-amber-500/20 text-3xs font-semibold text-amber-300">
                                {{ slot }}
                            </span>
                        </div>
                    </div>

                    <!-- Empty Sockets -->
                    <div v-if="selectedAuditChar?.empty_sockets_count > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-amber-400">hexagon</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Empty Sockets') }}</span>
                        </div>
                        <div class="text-2xl font-black text-white px-2">
                            {{ selectedAuditChar.empty_sockets_count }}
                        </div>
                    </div>

                    <!-- Upgrades Missing -->
                    <div v-if="(selectedAuditChar?.upgrades_missing ?? 0) > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-blue-400">upgrade</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Upgrades Missing') }}</span>
                        </div>
                        <div class="text-2xl font-black text-white px-2">
                            {{ selectedAuditChar.upgrades_missing }}
                        </div>
                    </div>

                    <!-- Spark Gear -->
                    <div v-if="(selectedAuditChar?.sparks_equipped ?? 0) > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-emerald-400">flash_on</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Sparks Equipped') }}</span>
                        </div>
                        <div class="text-2xl font-black text-white px-2">
                            {{ selectedAuditChar.sparks_equipped }}
                        </div>
                    </div>

                    <!-- Embellished Items -->
                    <div v-if="selectedAuditChar?.embellished_items?.length > 0" class="bg-white/5 rounded-xl border border-white/10 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-sm text-purple-400">diamond</span>
                            <span class="text-3xs font-black uppercase tracking-wider text-on-surface-variant">{{ __('Embellished Items') }}</span>
                        </div>
                        <div class="space-y-1">
                            <div v-for="(emb, i) in selectedAuditChar.embellished_items" :key="i"
                                 class="flex items-center gap-2 text-sm">
                                <span class="text-purple-300 font-bold">{{ emb.name }}</span>
                                <span class="text-gray-500 text-xs">ilvl {{ emb.ilvl }}</span>
                                <span v-if="emb.spell_name" class="text-gray-400 text-xs italic">{{ emb.spell_name }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!selectedAuditChar?.missing_enchants_slots?.length && !selectedAuditChar?.low_quality_enchants_slots?.length && !selectedAuditChar?.empty_sockets_count && !(selectedAuditChar?.upgrades_missing > 0)" class="text-center py-8">
                        <span class="material-symbols-outlined text-4xl text-green-500 mb-2">check_circle</span>
                        <p class="text-sm text-gray-400">{{ __('No issues found') }}</p>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button @click="showAuditModal = false"
                            class="px-6 py-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white text-sm font-bold transition-all">
                        {{ __('Close') }}
                    </button>
                </div>
            </div>
        </GlassModal>

    </div>
</template>
