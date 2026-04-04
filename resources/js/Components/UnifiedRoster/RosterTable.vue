<script setup>
import { computed } from 'vue';
import RosterRow from './RosterRow.vue';

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
    roleIconSrc: { type: Function, required: true },
    tierCount: { type: Function, required: true },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
});

const emit = defineEmits([
    'toggle-row',
    'update-access-role',
    'update-roster-status',
    'kick-member'
]);

const allRaids = computed(() => {
    // Find the first character that actually has raid data
    const charWithRaids = props.groupedRoster && Object.values(props.groupedRoster).flat().find(m => m.main_character?.raids !== undefined && m.main_character?.raids !== null)?.main_character;
    if (!charWithRaids) return {};

    const raidsObj = charWithRaids.raids;
    if (Array.isArray(raidsObj)) return {}; // Failsafe if backend returned empty array instead of object

    return raidsObj;
});
const slots = [
    'HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'WRIST',
    'HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2',
    'TRINKET_1', 'TRINKET_2', 'MAIN_HAND', 'OFF_HAND'
];

const slotLabels = {
    'HEAD': 'Head', 'NECK': 'Neck', 'SHOULDER': 'Shoulder', 'BACK': 'Back', 'CHEST': 'Chest', 'WRIST': 'Wrist',
    'HANDS': 'Hands', 'WAIST': 'Waist', 'LEGS': 'Legs', 'FEET': 'Feet', 'FINGER_1': 'Ring', 'FINGER_2': 'Ring',
    'TRINKET_1': 'Trinket', 'TRINKET_2': 'Trinket', 'MAIN_HAND': 'Main Hand', 'OFF_HAND': 'Off Hand'
};
</script>

<template>
    <div class="w-full bg-surface-container-high rounded-2xl border border-white/5">
        <table class="w-full text-left text-[11px] border-collapse">
            <!-- thead ──────────────────────────────────────── -->
            <thead>
                <!-- Group header row -->
                <tr class="bg-black/20 text-gray-500 text-[8px] uppercase tracking-widest font-bold border-b border-white/5 h-8">
                    <th class="p-2 pl-4 w-[200px] min-w-[200px]">{{ __('Character') }}</th>
                    <!-- iLvL hidden in raids and gear -->
                    <th v-if="activeTab !== 'raids' && activeTab !== 'gear'" class="p-2 text-center border-l border-white/5 w-[60px]">{{ __('iLvL') }}</th>

                    <template v-if="activeTab === 'summary'">
                        <th colspan="6" class="p-2 text-center border-l border-white/5">{{ __('Tier Pieces') }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('M+ Runs') }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('M+ Rating') }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('Audit') }}</th>
                        <th class="p-2 text-center border-l border-white/5 w-[130px]">{{ __('Access Role') }}</th>
                        <th class="p-2 text-center border-l border-white/5 w-[130px]">{{ __('Roster Status') }}</th>
                    </template>

                    <!-- One spanning header per raid instance -->
                    <template v-if="activeTab === 'raids'">
                        <th v-for="(bosses, raidName) in allRaids" :key="'rh-' + raidName"
                            :colspan="bosses.length"
                            class="p-2 text-center border-l border-white/5 uppercase tracking-widest font-bold text-gray-400">
                            {{ raidName }}
                        </th>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th :colspan="slots.length" class="p-2 text-center border-l border-white/5 uppercase tracking-widest font-bold text-gray-400 w-[1000px]">
                            {{ __('Gear') }}
                        </th>
                    </template>
                </tr>

                <!-- Column sub-header row -->
                <tr class="bg-black/40 text-cyan-400 text-[9px] uppercase tracking-widest font-bold border-b border-white/5 h-10">
                    <th class="px-4 py-2 w-[200px] min-w-[200px]">{{ __('Name') }}</th>
                    <th v-if="activeTab !== 'raids' && activeTab !== 'gear'" class="px-2 py-2 text-center w-[60px]">{{ __('Avg') }}</th>

                    <template v-if="activeTab === 'summary'">
                        <th class="p-2 text-center border-l border-white/5 w-[40px]">#</th>
                        <th v-for="slot in ['H','S','C','G','L']" :key="slot"
                            class="p-1 text-center text-on-surface-variant w-8">{{ slot }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('Runs') }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('Rating') }}</th>
                        <th class="p-2 text-center border-l border-white/5">{{ __('Issues') }}</th>
                        <th class="p-2 text-center border-l border-white/5 w-[130px]">{{ __('Role') }}</th>
                        <th class="p-2 text-center border-l border-white/5 w-[130px]">{{ __('Status') }}</th>
                    </template>

                    <!-- One column per boss, horizontal label -->
                    <template v-if="activeTab === 'raids'">
                        <template v-for="(bosses, raidName) in allRaids" :key="raidName">
                            <th v-for="boss in bosses" :key="boss.name"
                                class="px-1 py-1 align-middle border-l border-white/[0.04] text-center min-w-[60px]"
                                :title="boss.name">
                                <div class="mx-auto text-[9px] text-on-surface-variant font-medium normal-case whitespace-normal break-words leading-tight">
                                    {{ boss.name }}
                                </div>
                            </th>
                        </template>
                    </template>

                    <template v-if="activeTab === 'gear'">
                        <th class="p-2 text-center border-l border-white/5 w-[100px]">{{ __('Issues') }}</th>
                        <th class="p-1 text-center border-l border-white/5 text-[7px] min-w-[52px]">{{ __('UPGRADES MISSING') }}</th>
                        <th v-for="slot in slots" :key="slot" class="p-1 text-center border-l border-white/5 text-[7px] min-w-[52px]">
                            {{ slotLabels[slot] }}
                        </th>
                    </template>
                </tr>
            </thead>

            <!-- tbody ──────────────────────────────────────── -->
            <tbody class="divide-y divide-white/5">
                <template v-for="(members, roleKey) in groupedRoster" :key="roleKey">
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
                                :role-icon-src="roleIconSrc"
                                :tier-count="tierCount"
                                :has-audit-issues="hasAuditIssues"
                                :audit-title="auditTitle"
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
                                    :role-icon-src="roleIconSrc"
                                    :tier-count="tierCount"
                                    :has-audit-issues="hasAuditIssues"
                                    :audit-title="auditTitle"
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
