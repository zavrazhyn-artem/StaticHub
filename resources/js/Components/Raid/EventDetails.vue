<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();
import ToastNotification from '../UI/ToastNotification.vue';
import RaidHeader from './RaidHeader.vue';
import RsvpModal from './RsvpModal.vue';
import RosterGrid from './RosterGrid.vue';
import EditEventModal from './EditEventModal.vue';
import DeleteConfirmModal from './DeleteConfirmModal.vue';
import CommentModal from './CommentModal.vue';
import EncounterSidebar from './EncounterSidebar.vue';
import EncounterRosterPanel from './EncounterRosterPanel.vue';
import EventPlanSelector from './EventPlanSelector.vue';

const props = defineProps({
    event: { type: Object, required: true },
    userCharacters: { type: Array, required: true },
    selectedCharacterId: { type: Number, default: null },
    currentAttendance: { type: Object, default: null },
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, required: true },
    characterSpecs: { type: Object, default: () => ({}) },
    encounters: { type: Array, default: () => [] },
    encounterRosters: { type: Object, default: () => ({}) },
    plannerData: { type: Object, default: () => ({}) },
    planningStats: { type: Object, default: () => ({}) },
    bossPlannerUrl: { type: String, default: '' },
    authUserId: { type: Number, required: true },
    canManageSchedule: { type: Boolean, default: false },
    canAnnounceToDiscord: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    routes: { type: Object, required: true },
    successMessage: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
});

// Modal visibility state
const showEdit = ref(false);
const showDeleteConfirm = ref(false);
const showRSVPModal = ref(false);
const showCommentModal = ref(false);
const commentModalData = ref({ characterName: '', comment: '' });

const openComment = (data) => {
    commentModalData.value = data;
    showCommentModal.value = true;
};

// Tab state
const activeTab = ref('roster');

// Encounter selection
const selectedEncounter = ref(null);
const selectedEncounterName = computed(() => {
    if (!selectedEncounter.value) return __('All Encounters');
    const enc = props.encounters.find(e => e.slug === selectedEncounter.value);
    return enc?.name || selectedEncounter.value;
});

// Sidebar collapsed state (mobile)
const sidebarOpen = ref(false);

// Toast
const showToast = ref(!!props.successMessage);
const toastMessage = ref(props.successMessage);
if (props.successMessage) {
    setTimeout(() => { showToast.value = false; }, 5000);
}

// Role metadata (used for joined-role label only)
const ROLES = {
    tank: { label: __('Tank') },
    heal: { label: __('Healer') },
    mdps: { label: __('Melee') },
    rdps: { label: __('Ranged') },
};

// Difficulty metadata
const difficultyMeta = {
    mythic: { label: 'Mythic', color: 'text-orange-400', bg: 'bg-orange-400/10', border: 'border-orange-400/30' },
    heroic: { label: 'Heroic', color: 'text-purple-400', bg: 'bg-purple-400/10', border: 'border-purple-400/30' },
    normal: { label: 'Normal', color: 'text-green-400', bg: 'bg-green-400/10', border: 'border-green-400/30' },
    raid_finder: { label: 'LFR', color: 'text-blue-400', bg: 'bg-blue-400/10', border: 'border-blue-400/30' },
};

const eventDifficulty = computed(() => difficultyMeta[props.event.difficulty] || difficultyMeta.mythic);

// Status metadata
const statusMeta = {
    planned: { label: 'Planned', icon: 'event', color: 'text-blue-400' },
    in_progress: { label: 'In Progress', icon: 'play_circle', color: 'text-green-400' },
    completed: { label: 'Completed', icon: 'check_circle', color: 'text-on-surface-variant' },
};
const eventStatus = computed(() => statusMeta[props.event.status] || statusMeta.planned);

// Computed raid status summary for the header chips
const raidStatus = computed(() => {
    const counts = { tank: 0, heal: 0, dps: 0 };
    (props.mainRoster.tank || []).forEach(c => { if (c.pivot?.status === 'present') counts.tank++; });
    (props.mainRoster.heal || []).forEach(c => { if (c.pivot?.status === 'present') counts.heal++; });
    (props.mainRoster.mdps || []).forEach(c => { if (c.pivot?.status === 'present') counts.dps++; });
    (props.mainRoster.rdps || []).forEach(c => { if (c.pivot?.status === 'present') counts.dps++; });
    return [
        { label: 'tank.svg',  count: counts.tank, limit: 2,  color: 'text-blue-400' },
        { label: 'heal.svg',  count: counts.heal, limit: 4,  color: 'text-green-500' },
        { label: 'melee.svg', count: counts.dps,  limit: 14, color: 'text-red-500' },
    ];
});

const joinedCharacter = computed(() => {
    return props.currentAttendance
        ? props.userCharacters.find(c => c.id === props.currentAttendance.character_id)
        : null;
});

const joinedRoleLabel = computed(() => {
    if (!props.currentAttendance || !joinedCharacter.value) return '';
    const roleKey = joinedCharacter.value.assigned_role || 'rdps';
    return ROLES[roleKey]?.label || roleKey;
});

// Local encounter roster state for reactivity
const localEncounterRosters = ref({ ...props.encounterRosters });

const handleAssign = async (payload) => {
    try {
        const response = await fetch(props.routes.encounterAssign, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (response.ok) {
            // Optimistic update: add to local state
            const slug = payload.encounter_slug;
            if (!localEncounterRosters.value[slug]) {
                localEncounterRosters.value[slug] = { selected: [], queued: [], benched: [] };
            }
            const allChars = getAllCharacters();
            const char = allChars.find(c => c.id === payload.character_id);
            const entry = {
                character_id: payload.character_id,
                character_name: char?.name || '',
                class_name: char?.playable_class || '',
                role: char?.assigned_role || char?.main_spec?.role || 'rdps',
                spec: char?.main_spec || null,
                selection_status: payload.selection_status,
                position_order: payload.position_order || 0,
            };
            const group = payload.selection_status;
            // Remove from other groups first
            for (const g of ['selected', 'queued', 'benched']) {
                localEncounterRosters.value[slug][g] = localEncounterRosters.value[slug][g].filter(
                    r => r.character_id !== payload.character_id
                );
            }
            localEncounterRosters.value[slug][group].push(entry);
        }
    } catch (e) {
        console.error('Failed to assign character:', e);
    }
};

const handleRemove = async (payload) => {
    try {
        const response = await fetch(props.routes.encounterRemove, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (response.ok) {
            const slug = payload.encounter_slug;
            if (localEncounterRosters.value[slug]) {
                for (const g of ['selected', 'queued', 'benched']) {
                    localEncounterRosters.value[slug][g] = localEncounterRosters.value[slug][g].filter(
                        r => r.character_id !== payload.character_id
                    );
                }
            }
        }
    } catch (e) {
        console.error('Failed to remove character:', e);
    }
};

const getAllCharacters = () => {
    const chars = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (props.mainRoster[role] || []).forEach(c => chars.push(c));
    }
    props.absentRoster.forEach(c => chars.push(c));
    return chars;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Toast -->
        <ToastNotification :show="showToast" :message="toastMessage" />

        <!-- Header: back link, title, date/role chips, difficulty, status, action buttons -->
        <RaidHeader
            :event="event"
            :raid-status="raidStatus"
            :current-attendance="currentAttendance"
            :joined-character="joinedCharacter"
            :joined-role-label="joinedRoleLabel"
            :can-manage-schedule="canManageSchedule"
            :can-announce-to-discord="canAnnounceToDiscord"
            :csrf-token="csrfToken"
            :routes="routes"
            :difficulty="eventDifficulty"
            :status="eventStatus"
            :is-optional="event.is_optional"
            @rsvp="showRSVPModal = true"
            @edit="showEdit = true"
            @delete="showDeleteConfirm = true"
        />

        <!-- Event description note -->
        <div v-if="event.description" class="bg-white/5 border-l-2 border-primary/40 p-4 rounded-r-xl backdrop-blur-sm">
            <p class="text-on-surface-variant text-xs font-medium italic">{{ event.description }}</p>
        </div>

        <!-- Main tab bar -->
        <div class="flex items-center gap-1 border-b border-white/10">
            <button
                @click="activeTab = 'roster'"
                class="pb-3 px-4 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.15em] transition-all"
                :class="activeTab === 'roster'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-on-surface-variant hover:text-white'"
            >
                <span class="material-symbols-outlined text-sm align-middle mr-1">groups</span>
                Roster
            </button>
            <button
                @click="activeTab = 'planner'"
                class="pb-3 px-4 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.15em] transition-all"
                :class="activeTab === 'planner'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-on-surface-variant hover:text-white'"
            >
                <span class="material-symbols-outlined text-sm align-middle mr-1">map</span>
                Boss Planner
            </button>
        </div>

        <!-- Roster tab -->
        <div v-show="activeTab === 'roster'" class="flex flex-col lg:flex-row gap-6">
            <!-- Encounter sidebar -->
            <div v-if="encounters.length > 0" class="lg:w-64 shrink-0">
                <!-- Mobile toggle -->
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden w-full flex items-center justify-between px-4 py-3 bg-surface-container-high border border-white/10 rounded-xl mb-3"
                >
                    <span class="text-xs font-black uppercase tracking-widest text-on-surface-variant">
                        {{ selectedEncounterName }}
                    </span>
                    <span class="material-symbols-outlined text-on-surface-variant transition-transform"
                        :class="sidebarOpen ? 'rotate-180' : ''">expand_more</span>
                </button>

                <div
                    :class="sidebarOpen ? 'block' : 'hidden lg:block'"
                    class="bg-surface-container/60 border border-white/5 rounded-xl p-2 backdrop-blur-sm"
                >
                    <EncounterSidebar
                        :encounters="encounters"
                        :encounter-rosters="localEncounterRosters"
                        :selected-encounter="selectedEncounter"
                        :can-manage="canManageSchedule"
                        :difficulty="event.difficulty"
                        @select="(slug) => { selectedEncounter = slug; sidebarOpen = false; }"
                    />
                </div>
            </div>

            <!-- Main roster area -->
            <div class="flex-1 min-w-0">
                <!-- All encounters view: show standard roster grid -->
                <div v-if="!selectedEncounter">
                    <RosterGrid
                        :main-roster="mainRoster"
                        :absent-roster="absentRoster"
                        @open-comment="openComment"
                    />
                </div>

                <!-- Specific encounter view: show per-boss roster -->
                <div v-else>
                    <EncounterRosterPanel
                        :encounter-slug="selectedEncounter"
                        :encounter-name="selectedEncounterName"
                        :encounter-rosters="localEncounterRosters"
                        :main-roster="mainRoster"
                        :absent-roster="absentRoster"
                        :planning-stats="planningStats"
                        :can-manage="canManageSchedule"
                        :csrf-token="csrfToken"
                        :routes="routes"
                        @assign="handleAssign"
                        @remove="handleRemove"
                    />
                </div>
            </div>
        </div>

        <!-- Boss Plans tab -->
        <div v-show="activeTab === 'planner'">
            <EventPlanSelector
                :planner-data="plannerData"
                :boss-planner-url="bossPlannerUrl"
            />
        </div>

        <!-- Modals (unchanged logic) -->
        <RsvpModal
            v-if="showRSVPModal"
            :show="showRSVPModal"
            :user-characters="userCharacters"
            :selected-character-id="selectedCharacterId"
            :current-attendance="currentAttendance"
            :csrf-token="csrfToken"
            :rsvp-route="routes.rsvp"
            :character-specs="characterSpecs"
            @close="showRSVPModal = false"
        />

        <EditEventModal
            :show="showEdit"
            :event="event"
            :csrf-token="csrfToken"
            :routes="routes"
            @close="showEdit = false"
        />

        <DeleteConfirmModal
            :show="showDeleteConfirm"
            :csrf-token="csrfToken"
            :destroy-route="routes.destroy"
            @close="showDeleteConfirm = false"
        />

        <CommentModal
            :show="showCommentModal"
            :character-name="commentModalData.characterName"
            :comment="commentModalData.comment"
            @close="showCommentModal = false"
        />
    </div>
</template>
