<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import TabGear from './TabGear.vue';
import RosterTabs from './UnifiedRoster/RosterTabs.vue';
import RosterTable from './UnifiedRoster/RosterTable.vue';

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------
const props = defineProps({
    staticId:    { type: Number, required: true },
    initialData: { type: Object,  default: null  },
});

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
const roster             = ref([]);
const currentUserAccess  = ref('member');
const loading            = ref(true);
const currentTab         = ref(localStorage.getItem('rosterActiveTab') || 'summary');
const expandedRows       = ref(new Set());
const selectedDifficulty = ref(localStorage.getItem('rosterSelectedDifficulty'));

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
    { id: 'tank', label: 'Tanks',   max: 2,  barColor: 'bg-blue-500'  },
    { id: 'heal', label: 'Healers', max: 4,  barColor: 'bg-green-500' },
    { id: 'dps',  label: 'DPS',     max: 14, barColor: 'bg-red-500'   },
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
    'M': 'text-orange-500 font-black',
    'H': 'text-purple-500 font-bold',
    'N': 'text-blue-500   font-bold',
    'F': 'text-green-500  font-bold',
    '-': 'text-gray-700',
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
    return (char.missing_enchants_slots?.length ?? 0) > 0 || (char.empty_sockets_count ?? 0) > 0;
};

const auditTitle = (char) => {
    if (!char) return '';
    const parts = [];
    if (char.missing_enchants_slots?.length > 0)
        parts.push(`Missing enchants: ${char.missing_enchants_slots.join(', ')}`);
    if (char.empty_sockets_count > 0)
        parts.push(`Empty sockets: ${char.empty_sockets_count}`);
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
    } catch (err) {
        console.error('Failed to fetch roster:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchRoster);

watch(currentTab,         val => localStorage.setItem('rosterActiveTab', val));
watch(selectedDifficulty, val => localStorage.setItem('rosterSelectedDifficulty', val));

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------
const coreRoster = computed(() => roster.value.filter(m => m.roster_status === 'core'));

const stats = computed(() => {
    const counts = { tank: 0, heal: 0, dps: 0 };
    coreRoster.value.forEach(member => {
        const key = normalizeRoleKey(member.main_character?.combat_role);
        if (key in counts) counts[key]++;
    });
    return counts;
});

const groupedRoster = computed(() => {
    const groups = { tank: [], heal: [], dps: [], unknown: [] };
    roster.value.forEach(member => {
        const key = normalizeRoleKey(member.main_character?.combat_role);
        (groups[key] ?? groups.unknown).push(member);
    });
    return groups;
});

const getFirstRaidBosses = (raids) => {
    if (!raids || Array.isArray(raids)) return [];
    const values = Object.values(raids);
    if (values.length === 0) return [];
    const first = values[0];
    return Array.isArray(first) ? first : [];
};

const raidColumns = computed(() => {
    let firstRaidName = '';
    let bosses = [];

    outer: for (const members of Object.values(groupedRoster.value)) {
        for (const member of members) {
            const raids = member.main_character?.raids;
            if (!raids || Array.isArray(raids)) continue;
            const keys = Object.keys(raids);
            if (keys.length === 0) continue;
            const name = keys[0];
            if (name) {
                firstRaidName = name;
                bosses = getFirstRaidBosses(raids).map(b => b.name);
                break outer;
            }
        }
    }

    if (!firstRaidName) return [];
    return [{ name: firstRaidName, bosses }];
});

// ---------------------------------------------------------------------------
// Management actions
// ---------------------------------------------------------------------------
const canManageAccess = computed(() => currentUserAccess.value === 'leader' || currentUserAccess.value === 'admin');
const canManageStatus = computed(() => ['leader', 'officer', 'admin'].includes(currentUserAccess.value));

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
        alert('Failed to update access role. Check permissions.');
    }
};

const updateRosterStatus = async (member, newStatus) => {
    try {
        await axios.patch(`/statics/${props.staticId}/roster/${member.id}/roster-status`, { roster_status: newStatus });
        member.roster_status = newStatus;
    } catch (err) {
        console.error('Failed to update roster status:', err);
        alert('Failed to update roster status. Check permissions.');
    }
};

const kickMember = async (member) => {
    if (!confirm(`Remove ${member.name} from this static?`)) return;
    try {
        await axios.delete(`/statics/${props.staticId}/roster/${member.id}/kick`);
        roster.value = roster.value.filter(m => m.id !== member.id);
    } catch (err) {
        console.error('Failed to kick member:', err);
        alert(err.response?.data?.message || 'Failed to remove member.');
    }
};
</script>

<template>
    <div class="space-y-8">

        <!-- ── Role Summary Widgets ─────────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-for="role in roles" :key="role.id"
                 class="bg-surface-container-high rounded-2xl border border-white/5 p-4 flex items-center gap-4 relative overflow-hidden group">
                <div class="w-12 h-12 rounded-xl bg-white/5 flex items-center justify-center border border-white/10 group-hover:border-blue-500/30 transition-all">
                    <img :src="roleIconSrc(role.id)" class="w-6 h-6 opacity-80" :alt="role.label">
                </div>
                <div>
                    <div class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest mb-0.5">{{ role.label }}</div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-black text-white font-headline">{{ stats[role.id] }}</span>
                        <span class="text-[10px] font-bold text-on-surface-variant">/ {{ role.max }}</span>
                    </div>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-1 bg-white/5">
                    <div class="h-full transition-all duration-700"
                         :class="role.barColor"
                         :style="{ width: Math.min(100, (stats[role.id] / role.max) * 100) + '%' }">
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Tabs & Difficulty ────────────────────────────────────────── -->
        <RosterTabs
            v-model:activeTab="currentTab"
            v-model:selectedDifficulty="selectedDifficulty"
        />

        <!-- ── Loading ──────────────────────────────────────────────────── -->
        <div v-if="loading" class="flex justify-center p-12">
            <span class="material-symbols-outlined animate-spin text-4xl text-primary">sync</span>
        </div>

        <!-- ── Content ──────────────────────────────────────────────────── -->
        <div v-else class="space-y-4">

            <!-- ── Main Roster Table ─────────────────────────────────── -->
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
                :role-icon-src="roleIconSrc"
                :tier-count="tierCount"
                :has-audit-issues="hasAuditIssues"
                :audit-title="auditTitle"
                @toggle-row="toggleRow"
                @update-access-role="updateAccessRole"
                @update-roster-status="updateRosterStatus"
                @kick-member="kickMember"
            />

            <!-- ── Unassigned / No Character ─────────────────────────── -->
            <div v-if="groupedRoster.unknown.length > 0" class="space-y-3 pt-6 border-t border-white/5">
                <div class="flex items-center gap-2 border-b border-white/5 pb-2">
                    <span class="material-symbols-outlined text-sm text-gray-500">help</span>
                    <h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant">
                        No Character Linked ({{ groupedRoster.unknown.length }})
                    </h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="member in groupedRoster.unknown" :key="member.id"
                         class="bg-surface-container-high/40 rounded-xl border border-white/5 p-3 flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                                <span class="material-symbols-outlined text-gray-600">person</span>
                            </div>
                            <div>
                                <div class="font-bold text-white text-sm tracking-tight">{{ member.name }}</div>
                                <div class="text-[9px] text-on-surface-variant font-black uppercase tracking-widest">No Character Linked</div>
                            </div>
                        </div>
                        <div v-if="canManageStatus" class="opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="kickMember(member)"
                                    class="text-error hover:text-white text-[10px] font-black uppercase tracking-widest bg-error/10 hover:bg-error px-2 py-1 rounded transition-colors">
                                Kick
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</template>
