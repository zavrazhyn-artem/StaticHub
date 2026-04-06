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

const props = defineProps({
    event: { type: Object, required: true },
    userCharacters: { type: Array, required: true },
    selectedCharacterId: { type: Number, default: null },
    currentAttendance: { type: Object, default: null },
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, required: true },
    characterSpecs: { type: Object, default: () => ({}) },
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

// Toast
const showToast = ref(!!props.successMessage);
const toastMessage = ref(props.successMessage);
if (props.successMessage) {
    setTimeout(() => { showToast.value = false; }, 5000);
}

// Role metadata (used for joined-role label only — grid uses its own copy)
const ROLES = {
    tank: { label: __('Tank') },
    heal: { label: __('Healer') },
    mdps: { label: __('Melee') },
    rdps: { label: __('Ranged') },
};

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
</script>

<template>
    <div class="space-y-6">
        <!-- Toast -->
        <ToastNotification :show="showToast" :message="toastMessage" />

        <!-- Header: back link, title, date/role chips, action buttons -->
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
            @rsvp="showRSVPModal = true"
            @edit="showEdit = true"
            @delete="showDeleteConfirm = true"
        />

        <!-- Event description note -->
        <div v-if="event.description" class="bg-white/5 border-l-2 border-primary/40 p-4 rounded-r-xl backdrop-blur-sm">
            <p class="text-on-surface-variant text-xs font-medium italic">{{ event.description }}</p>
        </div>

        <!-- Tab bar -->
<!--        <div class="space-y-6">-->
<!--            <div class="flex items-center gap-4 border-b border-white/10">-->
<!--                <button-->
<!--                    @click="activeTab = 'roster'"-->
<!--                    class="pb-4 px-2 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all"-->
<!--                    :class="activeTab === 'roster' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'"-->
<!--                >Roster Breakdown</button>-->

<!--                <button-->
<!--                    v-if="event.ai_analysis"-->
<!--                    @click="activeTab = 'analysis'"-->
<!--                    class="pb-4 px-2 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center gap-2"-->
<!--                    :class="activeTab === 'analysis' ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-white'"-->
<!--                >-->
<!--                    <span class="material-symbols-outlined text-sm">psychology</span>-->
<!--                    Tactical Analysis-->
<!--                </button>-->

<!--                <a-->
<!--                    v-if="event.wcl_report_id"-->
<!--                    :href="'https://www.warcraftlogs.com/reports/' + event.wcl_report_id"-->
<!--                    target="_blank"-->
<!--                    class="pb-4 px-2 border-b-2 border-transparent text-on-surface-variant hover:text-[#ff7d0a] font-headline text-[10px] font-black uppercase tracking-[0.2em] transition-all flex items-center gap-2"-->
<!--                >-->
<!--                    <span class="material-symbols-outlined text-sm">analytics</span>-->
<!--                    Warcraft Logs-->
<!--                </a>-->
<!--            </div>-->

<!--            &lt;!&ndash; Roster tab &ndash;&gt;-->
<!--            <div v-show="activeTab === 'roster'">-->
<!--                <RosterGrid :main-roster="mainRoster" />-->
<!--            </div>-->

<!--            &lt;!&ndash; Analysis tab &ndash;&gt;-->
<!--            <AnalysisTab-->
<!--                v-if="event.ai_analysis"-->
<!--                v-show="activeTab === 'analysis'"-->
<!--                :analysis-html="event.ai_analysis_html"-->
<!--            />-->
<!--        </div>-->
        <RosterGrid :main-roster="mainRoster" :absent-roster="absentRoster" @open-comment="openComment" />

        <!-- Modals -->
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
