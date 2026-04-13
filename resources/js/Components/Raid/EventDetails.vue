<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();
import GlassModal from '../UI/GlassModal.vue';
import ToastNotification from '../UI/ToastNotification.vue';
import RaidHeader from './RaidHeader.vue';
import RsvpModal from './RsvpModal.vue';
import CompactRosterGrid from './CompactRosterGrid.vue';
import BuffChecklist from './BuffChecklist.vue';
import CompositionWarnings from './CompositionWarnings.vue';
import CharacterDetailModal from './CharacterDetailModal.vue';
import EditEventModal from './EditEventModal.vue';
import DeleteConfirmModal from './DeleteConfirmModal.vue';
import CommentModal from './CommentModal.vue';
import EncounterSidebar from './EncounterSidebar.vue';
import SplitRaidCanvas from './SplitRaidCanvas.vue';
import EventPlanSelector from './EventPlanSelector.vue';
import SharedPlanView from './BossPlanner/SharedPlanView.vue';

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
    buffConfig: { type: Object, default: () => ({}) },
    roleLimits: { type: Object, default: () => ({}) },
    allStaticAlts: { type: Object, default: () => ({}) },
    weeklyRaidData: { type: Object, default: () => ({}) },
    benchHistory: { type: Object, default: () => ({}) },
    splitAssignments: { type: Object, default: () => ({}) },
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
const showCharacterDetail = ref(false);
const selectedCharacter = ref(null);
const showAuditModal = ref(false);
const showPlanViewer = ref(false);

const openComment = (data) => {
    commentModalData.value = data;
    showCommentModal.value = true;
};

const handleCharacterClick = (char) => {
    selectedCharacter.value = char;
    showCharacterDetail.value = true;
};

// Local reactive roster copies (so bench/unbench can mutate without reload)
const localMainRoster = ref({
    tank: [...(props.mainRoster.tank || [])],
    heal: [...(props.mainRoster.heal || [])],
    mdps: [...(props.mainRoster.mdps || [])],
    rdps: [...(props.mainRoster.rdps || [])],
});
const localAbsentRoster = ref([...props.absentRoster]);

// Compute roster classes for buff checklist
const rosterClasses = computed(() => {
    const classes = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (localMainRoster.value[role] || []).forEach(c => {
            if (c.pivot?.status !== 'absent' && c.pivot?.status !== 'tentative') {
                classes.push(c.playable_class);
            }
        });
    }
    return classes;
});

// Handle character swap from detail modal
const handleSwapCharacter = async ({ fromCharId, toCharId, userId }) => {
    if (!selectedEncounter.value) {
        // All Encounters: swap in localMainRoster reactively
        let newChar = null;
        for (const alts of Object.values(props.allStaticAlts)) {
            const alt = alts.find(a => a.id === toCharId);
            if (alt) { newChar = alt; break; }
        }

        // Find and replace in localMainRoster
        for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
            const idx = localMainRoster.value[role].findIndex(c => c.id === fromCharId);
            if (idx !== -1) {
                const oldChar = localMainRoster.value[role][idx];
                const newRole = newChar?.main_spec?.role || role;
                const swapped = {
                    ...oldChar,
                    id: toCharId,
                    name: newChar?.name || oldChar.name,
                    playable_class: newChar?.playable_class || oldChar.playable_class,
                    item_level: newChar?.item_level || oldChar.item_level,
                    equipped_item_level: newChar?.item_level || oldChar.equipped_item_level,
                    avatar_url: newChar?.avatar_url || oldChar.avatar_url,
                    main_spec: newChar?.main_spec || oldChar.main_spec,
                    assigned_role: newRole,
                    pivot: { ...oldChar.pivot, status: 'present' },
                };
                // Remove from old role
                localMainRoster.value[role].splice(idx, 1);
                // Add to correct role
                (localMainRoster.value[newRole] || localMainRoster.value.rdps).push(swapped);
                break;
            }
        }

        // Also check absent roster
        const absIdx = localAbsentRoster.value.findIndex(c => c.id === fromCharId);
        if (absIdx !== -1) {
            const oldChar = localAbsentRoster.value[absIdx];
            const newRole = newChar?.main_spec?.role || oldChar.assigned_role || 'rdps';
            const swapped = {
                ...oldChar,
                id: toCharId,
                name: newChar?.name || oldChar.name,
                playable_class: newChar?.playable_class || oldChar.playable_class,
                main_spec: newChar?.main_spec || oldChar.main_spec,
                assigned_role: newRole,
                avatar_url: newChar?.avatar_url || oldChar.avatar_url,
            };
            localAbsentRoster.value.splice(absIdx, 1);
            localAbsentRoster.value.push(swapped);
        }

        // Propagate to unlocked saved encounter rosters
        for (const [slug, roster] of Object.entries(localEncounterRosters.value)) {
            if (lockedBosses.value.has(slug)) continue;
            for (const group of ['selected', 'queued', 'benched']) {
                roster[group] = (roster[group] || []).map(entry => {
                    if (entry.character_id === fromCharId) {
                        return {
                            ...entry,
                            character_id: toCharId,
                            character_name: newChar?.name || entry.character_name,
                            class_name: newChar?.playable_class || entry.class_name,
                            role: newChar?.main_spec?.role || entry.role,
                            spec: newChar?.main_spec || entry.spec,
                        };
                    }
                    return entry;
                });
            }
        }

        showCharacterDetail.value = false;

        // Persist via override attendance
        try {
            await fetch(props.routes.overrideAttendance, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ character_id: toCharId, status: 'present' }),
            });
        } catch (e) {
            console.error('Failed to swap character:', e);
        }
    } else {
        // Specific boss: update only this boss's encounter roster
        const slug = selectedEncounter.value;
        // Look up new character from roster OR from alts
        let newChar = getAllCharacters().find(c => c.id === toCharId);
        if (!newChar) {
            for (const alts of Object.values(props.allStaticAlts)) {
                const alt = alts.find(a => a.id === toCharId);
                if (alt) {
                    newChar = {
                        id: alt.id,
                        name: alt.name,
                        playable_class: alt.playable_class,
                        assigned_role: alt.main_spec?.role || 'rdps',
                        main_spec: alt.main_spec,
                        avatar_url: alt.avatar_url,
                        item_level: alt.item_level,
                    };
                    break;
                }
            }
        }

        // Materialize roster if this boss has no saved data yet
        const hasSaved = localEncounterRosters.value[slug]
            && ((localEncounterRosters.value[slug].selected?.length || 0) > 0
                || (localEncounterRosters.value[slug].queued?.length || 0) > 0);

        if (!hasSaved) {
            // Copy current main roster into encounter roster for this boss
            const materialized = { selected: [], queued: [], benched: [] };
            for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
                (localMainRoster.value[role] || []).forEach(c => {
                    materialized.selected.push({
                        character_id: c.id,
                        character_name: c.name,
                        class_name: c.playable_class,
                        role: c.assigned_role || c.main_spec?.role || role,
                        spec: c.main_spec || null,
                        selection_status: 'selected',
                        position_order: materialized.selected.length,
                    });
                });
            }
            localEncounterRosters.value[slug] = materialized;
        }

        // Find old entry's status to preserve it
        let oldStatus = 'selected';
        for (const group of ['selected', 'queued', 'benched']) {
            if ((localEncounterRosters.value[slug][group] || []).some(e => e.character_id === fromCharId)) {
                oldStatus = group;
                break;
            }
        }

        // Swap the character in encounter roster
        for (const group of ['selected', 'queued', 'benched']) {
            localEncounterRosters.value[slug][group] = localEncounterRosters.value[slug][group].map(entry => {
                if (entry.character_id === fromCharId) {
                    return {
                        ...entry,
                        character_id: toCharId,
                        character_name: newChar?.name || entry.character_name,
                        class_name: newChar?.playable_class || entry.class_name,
                        role: newChar?.assigned_role || newChar?.main_spec?.role || entry.role,
                        spec: newChar?.main_spec || entry.spec,
                    };
                }
                return entry;
            });
        }

        // Persist: remove old, assign new (two small requests, no bulk delete)
        try {
            await fetch(props.routes.encounterRemove, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ encounter_slug: slug, character_id: fromCharId }),
            });
            await fetch(props.routes.encounterAssign, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ encounter_slug: slug, character_id: toCharId, selection_status: oldStatus }),
            });
        } catch (e) {
            console.error('Failed to swap character for encounter:', e);
        }

        showCharacterDetail.value = false;
    }
};

// Bench / Unbench handlers (RL override) — reactive, no reload
const moveCharacterStatus = (characterId, newStatus) => {
    const isBenching = newStatus === 'tentative' || newStatus === 'absent';

    // 1. Update localMainRoster (All Encounters view)
    if (isBenching) {
        for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
            const idx = localMainRoster.value[role].findIndex(c => c.id === characterId);
            if (idx !== -1) {
                const foundChar = { ...localMainRoster.value[role][idx] };
                localMainRoster.value[role].splice(idx, 1);
                foundChar.pivot = { ...foundChar.pivot, status: newStatus };
                foundChar.assigned_role = role;
                localAbsentRoster.value.push(foundChar);
                break;
            }
        }
    } else {
        const idx = localAbsentRoster.value.findIndex(c => c.id === characterId);
        if (idx !== -1) {
            const foundChar = { ...localAbsentRoster.value[idx] };
            localAbsentRoster.value.splice(idx, 1);
            foundChar.pivot = { ...foundChar.pivot, status: newStatus };
            const role = foundChar.assigned_role || foundChar.main_spec?.role || 'rdps';
            (localMainRoster.value[role] || localMainRoster.value.rdps).push(foundChar);
        }
    }

    // 2. Propagate to saved encounter rosters — skip LOCKED bosses
    let propagated = false;
    for (const [slug, roster] of Object.entries(localEncounterRosters.value)) {
        if (lockedBosses.value.has(slug)) continue;
        if (!roster.selected && !roster.queued) continue;

        if (isBenching) {
            const selIdx = (roster.selected || []).findIndex(e => e.character_id === characterId);
            if (selIdx !== -1) {
                const [entry] = roster.selected.splice(selIdx, 1);
                entry.selection_status = 'queued';
                if (!roster.queued) roster.queued = [];
                roster.queued.push(entry);
                propagated = true;
            }
        } else {
            const qIdx = (roster.queued || []).findIndex(e => e.character_id === characterId);
            if (qIdx !== -1) {
                const [entry] = roster.queued.splice(qIdx, 1);
                entry.selection_status = 'selected';
                if (!roster.selected) roster.selected = [];
                roster.selected.push(entry);
                propagated = true;
            }
        }
    }

    // 3. Persist encounter rosters if any were changed
    if (propagated) {
        persistEncounterRosters();
    }
};

// Debounced persist for all encounter rosters
let encounterPersistTimer = null;
const persistEncounterRosters = () => {
    clearTimeout(encounterPersistTimer);
    encounterPersistTimer = setTimeout(async () => {
        const allAssignments = [];
        for (const [slug, groups] of Object.entries(localEncounterRosters.value)) {
            for (const status of ['selected', 'queued', 'benched']) {
                for (const entry of (groups[status] || [])) {
                    allAssignments.push({
                        encounter_slug: slug,
                        character_id: entry.character_id,
                        selection_status: status,
                        position_order: entry.position_order || 0,
                    });
                }
            }
        }
        if (allAssignments.length === 0) return;
        try {
            await fetch(props.routes.encounterRoster, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ assignments: allAssignments }),
            });
        } catch (e) {
            console.error('Failed to persist encounter rosters:', e);
        }
    }, 500);
};

const updateAttendanceStatus = async (characterId, status) => {
    // Optimistic UI
    moveCharacterStatus(characterId, status);

    try {
        await fetch(props.routes.overrideAttendance, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ character_id: characterId, status }),
        });
    } catch (e) {
        console.error('Failed to update attendance:', e);
    }
};

// Handle spec change — update role reactively
const handleChangeSpec = async ({ characterId, spec }) => {
    const newRole = spec.role || 'rdps';

    if (selectedEncounter.value) {
        // Per-boss: update encounter roster entry
        modifyEncounterRoster(selectedEncounter.value, characterId, null, spec);
    } else {
        // All Encounters: move character to new role group in localMainRoster
        let foundChar = null;
        for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
            const idx = localMainRoster.value[role].findIndex(c => c.id === characterId);
            if (idx !== -1) {
                foundChar = { ...localMainRoster.value[role][idx] };
                localMainRoster.value[role].splice(idx, 1);
                foundChar.main_spec = spec;
                foundChar.assigned_role = newRole;
                (localMainRoster.value[newRole] || localMainRoster.value.rdps).push(foundChar);
                break;
            }
        }
        // Also check absent
        const absIdx = localAbsentRoster.value.findIndex(c => c.id === characterId);
        if (absIdx !== -1) {
            localAbsentRoster.value[absIdx] = {
                ...localAbsentRoster.value[absIdx],
                main_spec: spec,
                assigned_role: newRole,
            };
        }

        // Propagate to unlocked saved encounter rosters
        for (const [slug, roster] of Object.entries(localEncounterRosters.value)) {
            if (lockedBosses.value.has(slug)) continue;
            for (const group of ['selected', 'queued', 'benched']) {
                roster[group] = (roster[group] || []).map(entry => {
                    if (entry.character_id === characterId) {
                        return { ...entry, role: newRole, spec };
                    }
                    return entry;
                });
            }
        }
    }

    // Update selected character for modal reactivity
    if (selectedCharacter.value?.id === characterId) {
        selectedCharacter.value = { ...selectedCharacter.value, main_spec: spec, assigned_role: newRole };
    }

    showCharacterDetail.value = false;

    // Persist
    try {
        await fetch(props.routes.overrideAttendance, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ character_id: characterId, status: 'present', spec_id: spec.id }),
        });
    } catch (e) {
        console.error('Failed to change spec:', e);
    }
};

const handleBench = (characterId) => {
    if (selectedEncounter.value) {
        // On a boss: always local change only (boss → All never propagates)
        modifyEncounterRoster(selectedEncounter.value, characterId, 'queued');
    } else {
        // All Encounters: global, propagates to unlocked bosses
        updateAttendanceStatus(characterId, 'tentative');
    }
};

const handleUnbench = (characterId) => {
    if (selectedEncounter.value) {
        // On a boss: always local change only
        modifyEncounterRoster(selectedEncounter.value, characterId, 'selected');
    } else {
        // All Encounters: global, propagates to unlocked bosses
        updateAttendanceStatus(characterId, 'present');
    }
};

// Materialize encounter roster if needed, then move character or change spec
const modifyEncounterRoster = async (slug, characterId, targetStatus, newSpec = null) => {
    // Materialize if no saved roster
    const hasSaved = localEncounterRosters.value[slug]
        && ((localEncounterRosters.value[slug].selected?.length || 0) > 0
            || (localEncounterRosters.value[slug].queued?.length || 0) > 0);

    if (!hasSaved) {
        const materialized = { selected: [], queued: [], benched: [] };
        for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
            (localMainRoster.value[role] || []).forEach(c => {
                materialized.selected.push({
                    character_id: c.id,
                    character_name: c.name,
                    class_name: c.playable_class,
                    role: c.assigned_role || c.main_spec?.role || role,
                    spec: c.main_spec || null,
                    selection_status: 'selected',
                    position_order: materialized.selected.length,
                });
            });
        }
        // Also add absent roster as queued
        localAbsentRoster.value.forEach(c => {
            materialized.queued.push({
                character_id: c.id,
                character_name: c.name,
                class_name: c.playable_class,
                role: c.assigned_role || c.main_spec?.role || 'rdps',
                spec: c.main_spec || null,
                selection_status: 'queued',
                position_order: materialized.queued.length,
            });
        });
        localEncounterRosters.value[slug] = materialized;
    }

    // Modify character in encounter roster
    const roster = localEncounterRosters.value[slug];

    if (newSpec && !targetStatus) {
        // Spec change only — update role + spec in place, no group move
        for (const group of ['selected', 'queued', 'benched']) {
            roster[group] = (roster[group] || []).map(entry => {
                if (entry.character_id === characterId) {
                    return { ...entry, role: newSpec.role || entry.role, spec: newSpec };
                }
                return entry;
            });
        }
    } else {
        // Move character between groups (bench/unbench)
        let entry = null;
        for (const group of ['selected', 'queued', 'benched']) {
            const idx = (roster[group] || []).findIndex(e => e.character_id === characterId);
            if (idx !== -1) {
                [entry] = roster[group].splice(idx, 1);
                break;
            }
        }
        if (entry) {
            entry.selection_status = targetStatus;
            roster[targetStatus].push(entry);
        }
    }

    // Persist full roster for this boss (not just one character)
    try {
        const allAssignments = [];
        // Collect ALL encounter rosters (this boss + others) for bulk save
        for (const [s, groups] of Object.entries(localEncounterRosters.value)) {
            for (const status of ['selected', 'queued', 'benched']) {
                for (const entry of (groups[status] || [])) {
                    allAssignments.push({
                        encounter_slug: s,
                        character_id: entry.character_id,
                        selection_status: status,
                        position_order: entry.position_order || 0,
                    });
                }
            }
        }
        await fetch(props.routes.encounterRoster, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ assignments: allAssignments }),
        });
    } catch (e) {
        console.error('Failed to modify encounter roster:', e);
    }
};

// Feature toggles
const bossRosterEnabled = ref(props.event.boss_roster_enabled ?? false);
const splitEnabled = ref(props.event.split_enabled ?? false);
const splitCount = ref(props.event.split_count ?? 1);
const activeSplit = ref(null); // null = All, 1/2/3/4 = specific split

const splitLabels = ['A', 'B', 'C', 'D'];

const toggleFeature = async (feature, value) => {
    const payload = { [feature]: value };

    // Mutually exclusive: enabling one disables the other
    if (feature === 'boss_roster_enabled' && value) {
        bossRosterEnabled.value = true;
        splitEnabled.value = false;
        activeSplit.value = null;
        payload.split_enabled = false;
    } else if (feature === 'boss_roster_enabled') {
        bossRosterEnabled.value = false;
    }

    if (feature === 'split_enabled' && value) {
        splitEnabled.value = true;
        bossRosterEnabled.value = false;
        selectedEncounter.value = null;
        payload.boss_roster_enabled = false;
        if (splitCount.value < 2) {
            splitCount.value = 2;
            payload.split_count = 2;
        }
    } else if (feature === 'split_enabled') {
        splitEnabled.value = false;
        activeSplit.value = null;
    }

    if (feature === 'split_count') splitCount.value = value;

    try {
        await fetch(props.routes.eventSettings, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
    } catch (e) {
        console.error('Failed to update setting:', e);
    }
};

const handleAssignSplit = async (characterId, group) => {
    // Optimistic: update local pivot
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (localMainRoster.value[role] || []).forEach(c => {
            if (c.id === characterId && c.pivot) c.pivot.split_group = group;
        });
    }
    localAbsentRoster.value.forEach(c => {
        if (c.id === characterId && c.pivot) c.pivot.split_group = group;
    });

    try {
        await fetch(props.routes.splitGroup, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ character_id: characterId, split_group: group }),
        });
    } catch (e) {
        console.error('Failed to assign split:', e);
    }
};

// Filtered roster by active split
const splitFilteredMainRoster = computed(() => {
    if (!splitEnabled.value || activeSplit.value === null) return activeMainRoster.value;
    const filtered = {};
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        filtered[role] = (activeMainRoster.value[role] || []).filter(
            c => c.pivot?.split_group === activeSplit.value || !c.pivot?.split_group
        );
    }
    return filtered;
});

const splitFilteredAbsentRoster = computed(() => {
    if (!splitEnabled.value || activeSplit.value === null) return activeAbsentRoster.value;
    return activeAbsentRoster.value.filter(
        c => c.pivot?.split_group === activeSplit.value || !c.pivot?.split_group
    );
});

// Effective role limits: no limits when splits enabled
const effectiveRoleLimits = computed(() => {
    if (splitEnabled.value) return { total: 99, tank: 99, heal: { min: 0, max: 99 }, dps: { min: 0, max: 99 } };
    return props.roleLimits;
});

// Roster classes for current view (split-aware)
const effectiveRosterClasses = computed(() => {
    const classes = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (splitFilteredMainRoster.value[role] || []).forEach(c => classes.push(c.playable_class));
    }
    return classes;
});

// Tab state
const activeTab = ref('roster');

// Encounter selection
const selectedEncounter = ref(null);
const selectedEncounterName = computed(() => {
    if (!selectedEncounter.value) return __('All Encounters');
    const enc = props.encounters.find(e => e.slug === selectedEncounter.value);
    return enc?.name || selectedEncounter.value;
});

// Assigned plan for current boss
const selectedEncounterPlan = computed(() => {
    if (!selectedEncounter.value) return null;
    const planId = (props.event.assigned_plans || {})[selectedEncounter.value];
    if (!planId) return null;
    const encounters = props.plannerData?.encounters || [];
    const enc = encounters.find(e => e.slug === selectedEncounter.value);
    return enc?.plans?.find(p => p.id === planId) || null;
});

const selectedEncounterData = computed(() => {
    if (!selectedEncounter.value) return null;
    return (props.plannerData?.encounters || []).find(e => e.slug === selectedEncounter.value) || null;
});

const selectedEncounterMaps = computed(() => {
    return selectedEncounterData.value?.maps || [];
});

const selectedEncounterPortrait = computed(() => {
    return selectedEncounterData.value?.portrait || '';
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
    (localMainRoster.value.tank || []).forEach(c => { if (c.pivot?.status === 'present') counts.tank++; });
    (localMainRoster.value.heal || []).forEach(c => { if (c.pivot?.status === 'present') counts.heal++; });
    (localMainRoster.value.mdps || []).forEach(c => { if (c.pivot?.status === 'present') counts.dps++; });
    (localMainRoster.value.rdps || []).forEach(c => { if (c.pivot?.status === 'present') counts.dps++; });
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

// Main roster present count (for sidebar inherited display)
const mainRosterPresentCount = computed(() => {
    let count = 0;
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        count += (localMainRoster.value[role] || []).length;
    }
    return count;
});

// Selected encounters (null = all)
const localSelectedEncounters = ref(props.event.selected_encounters ?? null);

const isEncounterSelected = (slug) => {
    if (!localSelectedEncounters.value) return true; // null = all selected
    return localSelectedEncounters.value.includes(slug);
};

const handleToggleEncounter = async ({ slug, selected }) => {
    // Optimistic update
    if (selected) {
        if (!localSelectedEncounters.value) return;
        localSelectedEncounters.value = [...localSelectedEncounters.value, slug];
        if (localSelectedEncounters.value.length >= props.encounters.length) {
            localSelectedEncounters.value = null;
        }
    } else {
        if (!localSelectedEncounters.value) {
            localSelectedEncounters.value = props.encounters
                .map(e => e.slug)
                .filter(s => s !== slug);
        } else {
            localSelectedEncounters.value = localSelectedEncounters.value.filter(s => s !== slug);
        }
    }

    persistSelectedEncounters();
};

const handleToggleAll = async (selectAll) => {
    localSelectedEncounters.value = selectAll ? null : [];
    persistSelectedEncounters();
};

// Single bulk save — no race conditions
let persistTimer = null;
const persistSelectedEncounters = () => {
    clearTimeout(persistTimer);
    persistTimer = setTimeout(async () => {
        try {
            await fetch(props.routes.toggleEncounter, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': props.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    selected_encounters: localSelectedEncounters.value,
                }),
            });
        } catch (e) {
            console.error('Failed to save encounter selection:', e);
        }
    }, 300); // debounce for rapid clicks
};

// Local encounter roster state for reactivity
const localEncounterRosters = ref({ ...props.encounterRosters });

// Locked bosses: Set of slugs. Locked = frozen, no cross-boss propagation.
const lockedBosses = ref(new Set(props.event.locked_encounters || []));

const toggleBossLock = (slug) => {
    const newSet = new Set(lockedBosses.value);
    if (newSet.has(slug)) {
        newSet.delete(slug);
    } else {
        // Materialize roster before locking so it has its own data
        const hasSaved = localEncounterRosters.value[slug]
            && ((localEncounterRosters.value[slug].selected?.length || 0) > 0
                || (localEncounterRosters.value[slug].queued?.length || 0) > 0);

        if (!hasSaved) {
            const materialized = { selected: [], queued: [], benched: [] };
            for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
                (localMainRoster.value[role] || []).forEach(c => {
                    materialized.selected.push({
                        character_id: c.id,
                        character_name: c.name,
                        class_name: c.playable_class,
                        role: c.assigned_role || c.main_spec?.role || role,
                        spec: c.main_spec || null,
                        selection_status: 'selected',
                        position_order: materialized.selected.length,
                    });
                });
            }
            localAbsentRoster.value.forEach(c => {
                materialized.queued.push({
                    character_id: c.id,
                    character_name: c.name,
                    class_name: c.playable_class,
                    role: c.assigned_role || c.main_spec?.role || 'rdps',
                    spec: c.main_spec || null,
                    selection_status: 'queued',
                    position_order: materialized.queued.length,
                });
            });
            localEncounterRosters.value[slug] = materialized;
        }
        newSet.add(slug);
    }
    const isLocking = newSet.has(slug);
    lockedBosses.value = newSet;

    // Persist lock state
    fetch(props.routes.eventSettings, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ locked_encounters: [...newSet] }),
    }).catch(e => console.error('Failed to save lock state:', e));

    // If locking, also persist the materialized roster
    if (isLocking) persistEncounterRosters();
};


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

const handleBulkAssign = async (assignments) => {
    // Optimistic update first
    for (const payload of assignments) {
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
        // Remove from other groups first
        for (const g of ['selected', 'queued', 'benched']) {
            localEncounterRosters.value[slug][g] = localEncounterRosters.value[slug][g].filter(
                r => r.character_id !== payload.character_id
            );
        }
        localEncounterRosters.value[slug][payload.selection_status].push(entry);
    }

    // Single bulk request
    try {
        // Merge with existing assignments for other encounters
        const existingAssignments = [];
        for (const [slug, groups] of Object.entries(localEncounterRosters.value)) {
            for (const status of ['selected', 'queued', 'benched']) {
                for (const entry of (groups[status] || [])) {
                    existingAssignments.push({
                        encounter_slug: slug,
                        character_id: entry.character_id,
                        selection_status: status,
                        position_order: entry.position_order || 0,
                    });
                }
            }
        }

        await fetch(props.routes.encounterRoster, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ assignments: existingAssignments }),
        });
    } catch (e) {
        console.error('Failed to bulk assign:', e);
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
        (localMainRoster.value[role] || []).forEach(c => chars.push(c));
    }
    localAbsentRoster.value.forEach(c => chars.push(c));
    return chars;
};

// Build mainRoster-shaped object from encounter roster for CompactRosterGrid
const encounterRosterAsMainRoster = computed(() => {
    if (!selectedEncounter.value) return null;

    const saved = localEncounterRosters.value[selectedEncounter.value];
    const hasSaved = saved && (saved.selected?.length > 0 || saved.queued?.length > 0);

    // If no saved roster for this boss, use the main roster directly (inherited)
    if (!hasSaved) return null;

    // Build char lookup from main roster + absent + all static alts
    const allChars = getAllCharacters();
    const charMap = {};
    allChars.forEach(c => { charMap[c.id] = c; });
    // Also include alts that might have been swapped in
    for (const alts of Object.values(props.allStaticAlts)) {
        for (const alt of alts) {
            if (!charMap[alt.id]) {
                charMap[alt.id] = {
                    id: alt.id,
                    name: alt.name,
                    playable_class: alt.playable_class,
                    item_level: alt.item_level,
                    equipped_item_level: alt.item_level,
                    avatar_url: alt.avatar_url,
                    user_id: null,
                    main_spec: alt.main_spec,
                    assigned_role: alt.main_spec?.role || 'rdps',
                    pivot: { status: 'present' },
                };
            }
        }
    }

    const roster = { tank: [], heal: [], mdps: [], rdps: [] };
    const absent = [];

    for (const entry of (saved.selected || [])) {
        const char = charMap[entry.character_id];
        if (!char) continue;
        const clone = { ...char, pivot: { ...char.pivot, status: 'present' } };
        const role = entry.role || char.assigned_role || char.main_spec?.role || 'rdps';
        (roster[role] || roster.rdps).push(clone);
    }

    for (const entry of (saved.queued || [])) {
        const char = charMap[entry.character_id];
        if (!char) continue;
        const clone = { ...char, pivot: { ...char.pivot, status: 'tentative' }, assigned_role: entry.role || char.assigned_role || 'rdps' };
        absent.push(clone);
    }

    return { mainRoster: roster, absentRoster: absent };
});

// Active roster data for the current view (All Encounters or per-boss)
const activeMainRoster = computed(() => {
    if (!selectedEncounter.value) return localMainRoster.value;
    return encounterRosterAsMainRoster.value?.mainRoster || localMainRoster.value;
});

const activeAbsentRoster = computed(() => {
    if (!selectedEncounter.value) return localAbsentRoster.value;
    return encounterRosterAsMainRoster.value?.absentRoster || localAbsentRoster.value;
});

// Audit issue count for badge
const auditIssueCount = computed(() => {
    let count = 0;
    const classes = new Set(effectiveRosterClasses.value);

    // Missing buffs
    for (const providers of Object.values(props.buffConfig.buffs_debuffs || {})) {
        if (!providers.some(c => classes.has(c))) count++;
    }
    // Missing utility (only critical: lust, bres)
    const utility = props.buffConfig.utility || {};
    if (utility['Bloodlust'] && !utility['Bloodlust'].some(c => classes.has(c))) count++;
    if (utility['Combat Resurrection'] && !utility['Combat Resurrection'].some(c => classes.has(c))) count++;

    // Role issues
    const r = splitFilteredMainRoster.value;
    const tanks = (r.tank || []).length;
    const heals = (r.heal || []).length;
    const limits = effectiveRoleLimits.value;
    if (tanks < (limits.tank ?? 2)) count++;
    if (heals < (limits.heal?.min ?? 2)) count++;

    return count;
});

// Active roster classes for buff checklist (recalculate per boss)
const activeRosterClasses = computed(() => {
    const classes = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (activeMainRoster.value[role] || []).forEach(c => {
            classes.push(c.playable_class);
        });
    }
    return classes;
});
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
        <div v-if="event.description" class="bg-white/5 border-l-2 border-fuchsia-400/40 p-4 rounded-r-xl backdrop-blur-sm">
            <p class="text-on-surface-variant text-xs font-medium italic">{{ event.description }}</p>
        </div>

        <!-- Feature toggles (RL only) -->
        <div v-if="canManageSchedule" class="flex items-center gap-3 flex-wrap">
            <button
                @click="toggleFeature('boss_roster_enabled', !bossRosterEnabled)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border transition-all"
                :class="bossRosterEnabled
                    ? 'bg-fuchsia-400/10 border-fuchsia-400/30 text-fuchsia-400'
                    : 'bg-white/5 border-white/10 text-on-surface-variant hover:text-white'"
            >
                <span class="material-symbols-outlined text-sm">{{ bossRosterEnabled ? 'check_box' : 'check_box_outline_blank' }}</span>
                Boss Roster
            </button>
            <button
                @click="toggleFeature('split_enabled', !splitEnabled)"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border transition-all"
                :class="splitEnabled
                    ? 'bg-fuchsia-400/10 border-fuchsia-400/30 text-fuchsia-400'
                    : 'bg-white/5 border-white/10 text-on-surface-variant hover:text-white'"
            >
                <span class="material-symbols-outlined text-sm">{{ splitEnabled ? 'check_box' : 'check_box_outline_blank' }}</span>
                Split Raid
            </button>
        </div>


        <!-- Main tab bar (only when boss roster enabled) -->
        <div v-if="bossRosterEnabled" class="flex items-center justify-between border-b border-white/10">
            <div class="flex items-center gap-1">
                <button
                    @click="activeTab = 'roster'"
                    class="pb-3 px-4 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.15em] transition-all"
                    :class="activeTab === 'roster'
                        ? 'border-fuchsia-400 text-fuchsia-400'
                        : 'border-transparent text-on-surface-variant hover:text-white'"
                >
                    <span class="material-symbols-outlined text-sm align-middle mr-1">groups</span>
                    Roster
                </button>
                <button
                    @click="activeTab = 'planner'"
                    class="pb-3 px-4 border-b-2 font-headline text-[10px] font-black uppercase tracking-[0.15em] transition-all"
                    :class="activeTab === 'planner'
                        ? 'border-fuchsia-400 text-fuchsia-400'
                        : 'border-transparent text-on-surface-variant hover:text-white'"
                >
                    <span class="material-symbols-outlined text-sm align-middle mr-1">map</span>
                    {{ __('Plans') }}
                </button>
            </div>

            <!-- Audit button -->
            <button
                @click="showAuditModal = true"
                class="flex items-center gap-1.5 px-3 py-1.5 mb-1 rounded-lg text-[9px] font-black uppercase tracking-widest border transition-all"
                :class="auditIssueCount > 0
                    ? 'bg-red-500/10 border-red-500/20 text-red-400 hover:bg-red-500/20'
                    : 'bg-green-500/10 border-green-500/20 text-green-400 hover:bg-green-500/20'"
            >
                <span class="material-symbols-outlined text-sm">{{ auditIssueCount > 0 ? 'warning' : 'check_circle' }}</span>
                Audit
                <span
                    v-if="auditIssueCount > 0"
                    class="w-4 h-4 rounded-full bg-red-500 text-white flex items-center justify-center text-[8px] font-black"
                >{{ auditIssueCount }}</span>
            </button>
        </div>

        <!-- Content area with shared sidebar -->
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Encounter sidebar (only when boss roster enabled) -->
            <div v-if="bossRosterEnabled && encounters.length > 0" class="lg:w-64 shrink-0">
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
                        :selected-encounters="localSelectedEncounters"
                        :locked-bosses="lockedBosses"
                        :assigned-plans="event.assigned_plans || {}"
                        :can-manage="canManageSchedule"
                        :difficulty="event.difficulty"
                        :main-roster-count="mainRosterPresentCount"
                        @select="(slug) => { selectedEncounter = slug; sidebarOpen = false; }"
                        @toggle-encounter="handleToggleEncounter"
                        @toggle-all="handleToggleAll"
                        @toggle-lock="toggleBossLock"
                    />
                </div>
            </div>

            <!-- Main content area -->
            <div class="flex-1 min-w-0">
                <!-- ═══ ROSTER (always visible when not on Plans tab) ═══ -->
                <div v-show="activeTab === 'roster' || !bossRosterEnabled">
                    <!-- Split mode: full canvas -->
                    <SplitRaidCanvas
                        v-if="splitEnabled"
                        :all-static-alts="allStaticAlts"
                        :main-roster="localMainRoster"
                        :absent-roster="localAbsentRoster"
                        :split-count="splitCount"
                        :buff-config="buffConfig"
                        :csrf-token="csrfToken"
                        :save-splits-url="routes.saveSplits"
                        :initial-assignments="splitAssignments"
                        @update-split-count="(n) => toggleFeature('split_count', n)"
                    />
                    <!-- Normal mode: compact grid -->
                    <CompactRosterGrid
                        v-if="!splitEnabled"
                        :main-roster="activeMainRoster"
                        :absent-roster="activeAbsentRoster"
                        :role-limits="effectiveRoleLimits"
                        :weekly-raid-data="weeklyRaidData"
                        :bench-history="benchHistory"
                        :planning-stats="planningStats"
                        :encounter-slug="selectedEncounter"
                        :difficulty="event.difficulty"
                        :can-manage="canManageSchedule"
                        :locked="!!selectedEncounter && lockedBosses.has(selectedEncounter)"
                        @character-click="handleCharacterClick"
                        @bench="handleBench"
                        @unbench="handleUnbench"
                    />
                </div>

                <!-- ═══ PLANS TAB (only with boss roster) ═══ -->
                <div v-if="bossRosterEnabled" v-show="activeTab === 'planner'">
                    <EventPlanSelector
                        :planner-data="plannerData"
                        :boss-planner-url="bossPlannerUrl"
                        :selected-encounter="selectedEncounter"
                        :assigned-plans="event.assigned_plans || {}"
                        :can-manage="canManageSchedule"
                        :csrf-token="csrfToken"
                        :assign-url="routes.assignPlan"
                        @view-plan="(plan) => { showPlanViewer = true; }"
                    />
                </div>
            </div>
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

        <!-- Audit Modal -->
        <GlassModal :show="showAuditModal" max-width="max-w-xl" @close="showAuditModal = false">
            <div class="p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">fact_check</span>
                        Raid Audit
                    </h3>
                    <button @click="showAuditModal = false" class="text-on-surface-variant hover:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <CompositionWarnings
                    :main-roster="splitFilteredMainRoster"
                    :role-limits="effectiveRoleLimits"
                    :buff-config="buffConfig"
                    :roster-classes="effectiveRosterClasses"
                    :bench-history="benchHistory"
                />
                <BuffChecklist
                    :buff-config="buffConfig"
                    :roster-classes="effectiveRosterClasses"
                />
            </div>
        </GlassModal>

        <CharacterDetailModal
            :show="showCharacterDetail"
            :character="selectedCharacter"
            :all-static-alts="allStaticAlts"
            :weekly-raid-data="weeklyRaidData"
            :bench-history="benchHistory"
            :planning-stats="planningStats"
            :encounters="encounters"
            :difficulty="event.difficulty"
            :can-manage="canManageSchedule"
            @close="showCharacterDetail = false"
            @swap-character="handleSwapCharacter"
            @change-spec="handleChangeSpec"
        />

        <!-- Plan Viewer Modal -->
        <GlassModal :show="showPlanViewer" max-width="max-w-5xl" @close="showPlanViewer = false">
            <div v-if="selectedEncounterPlan" class="p-2">
                <div class="flex justify-end mb-2">
                    <button @click="showPlanViewer = false" class="text-on-surface-variant hover:text-white p-1">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <SharedPlanView
                    :plan="selectedEncounterPlan"
                    :boss-name="selectedEncounterName"
                    :maps="selectedEncounterMaps"
                    :portrait="selectedEncounterPortrait"
                    :static-name="event.static?.name || ''"
                    :my-character-ids="userCharacters.map(c => c.id)"
                />
            </div>
        </GlassModal>
    </div>
</template>
