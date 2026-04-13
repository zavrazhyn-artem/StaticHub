<script setup>
import { ref, computed } from 'vue';
import GlassModal from '../UI/GlassModal.vue';
import BuffChecklist from './BuffChecklist.vue';
import CompositionWarnings from './CompositionWarnings.vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    allStaticAlts: { type: Object, default: () => ({}) },
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, default: () => [] },
    splitCount: { type: Number, default: 2 },
    buffConfig: { type: Object, default: () => ({}) },
    csrfToken: { type: String, required: true },
    saveSplitsUrl: { type: String, required: true },
    initialAssignments: { type: Object, default: () => ({}) }, // { charId: splitGroup }
});

const emit = defineEmits(['update-split-count']);

const splitLabels = ['A', 'B', 'C', 'D'];
const SPLIT_LIMIT = 30;
const TANK_LIMIT = 2;
const localSplitCount = ref(props.splitCount);

// Build user list: each user is a card with all their characters
const users = computed(() => {
    const userMap = {};
    for (const [userId, chars] of Object.entries(props.allStaticAlts)) {
        const mainChar = chars.find(c => c.role === 'main') || chars[0];
        userMap[userId] = {
            userId: Number(userId),
            displayName: mainChar?.name || 'Unknown',
            mainClass: mainChar?.playable_class || '',
            characters: chars,
        };
    }

    // Mark signed-up users
    const allChars = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (props.mainRoster[role] || []).forEach(c => allChars.push(c));
    }
    props.absentRoster.forEach(c => allChars.push(c));
    const signedUpUserIds = new Set(allChars.map(c => c.user_id));

    return Object.values(userMap).filter(u => signedUpUserIds.has(u.userId) && u.characters.length > 0);
});

// Assignments: { charId: splitGroup } — init from backend data
const assignments = ref(
    Object.fromEntries(
        Object.entries(props.initialAssignments || {}).map(([charId, group]) => [Number(charId), group])
    )
);

// Per-user: which splits they're in and with which chars
const userSplitInfo = (user) => {
    const info = {}; // { splitNum: charId }
    for (const char of user.characters) {
        const split = assignments.value[char.id];
        if (split) info[split] = char;
    }
    return info;
};

// Available chars for a user (not assigned anywhere)
const userAvailableChars = (user) => user.characters.filter(c => !assignments.value[c.id]);

// Split groups by role
const splitGroups = computed(() => {
    const groups = {};
    for (let i = 1; i <= localSplitCount.value; i++) {
        groups[i] = { tank: [], heal: [], mdps: [], rdps: [], total: 0 };
    }
    for (const user of users.value) {
        for (const char of user.characters) {
            const split = assignments.value[char.id];
            if (split && groups[split]) {
                const role = char.main_spec?.role || 'rdps';
                groups[split][role].push({ ...char, userId: user.userId, userName: user.displayName });
                groups[split].total++;
            }
        }
    }
    return groups;
});

// Pool users who have available chars
const poolUsers = computed(() => {
    return users.value
        .map(u => ({ ...u, available: userAvailableChars(u), splitInfo: userSplitInfo(u) }))
        .filter(u => u.available.length > 0);
});

// Buff classes per split
const splitClasses = (n) => {
    const g = splitGroups.value[n];
    if (!g) return [];
    const classes = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) g[role].forEach(c => classes.push(c.playable_class));
    return classes;
};

// Split main roster shape for warnings
const splitAsMainRoster = (n) => {
    const g = splitGroups.value[n];
    return g || { tank: [], heal: [], mdps: [], rdps: [] };
};

// Drag & drop
const dragging = ref(null);
const dragOverSplit = ref(null);

const onDragStart = (e, char, userId, fromSplit = null, isSpecificChar = false) => {
    dragging.value = { charId: char.id, userId, fromSplit, char, isSpecificChar };
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(char.id));
};

const onDragOver = (e, splitNum) => { e.preventDefault(); dragOverSplit.value = splitNum; };
const onDragLeave = () => { dragOverSplit.value = null; };
const onDragEnd = () => { dragging.value = null; dragOverSplit.value = null; };

const removeFromSplit = (charId) => {
    delete assignments.value[charId];
    assignments.value = { ...assignments.value };
};

// Audit modal per split
const auditSplit = ref(null);

// Warning count for a split
const splitWarningCount = (n) => {
    let count = 0;
    const classes = new Set(splitClasses(n));
    for (const providers of Object.values(props.buffConfig.buffs_debuffs || {})) {
        if (!providers.some(c => classes.has(c))) count++;
    }
    const u = props.buffConfig.utility || {};
    if (u['Bloodlust'] && !u['Bloodlust'].some(c => classes.has(c))) count++;
    if (u['Combat Resurrection'] && !u['Combat Resurrection'].some(c => classes.has(c))) count++;
    const g = splitGroups.value[n];
    if ((g?.tank?.length || 0) < 2) count++;
    if ((g?.heal?.length || 0) < 2) count++;
    return count;
};

// Save
const saving = ref(false);
const saved = ref(false);
const saveSplits = async () => {
    saving.value = true;
    const payload = Object.entries(assignments.value).map(([charId, split]) => ({
        character_id: Number(charId), split_group: split,
    }));
    if (payload.length === 0) { saving.value = false; return; }
    try {
        await fetch(props.saveSplitsUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ assignments: payload }),
        });
        saved.value = true;
        setTimeout(() => { saved.value = false; }, 2000);
    } catch (e) { console.error('Failed to save splits:', e); }
    saving.value = false;
};

const roleIcons = { tank: 'tank.svg', heal: 'heal.svg', mdps: 'melee.svg', rdps: 'range.svg' };
const roleColors = { tank: 'text-blue-400', heal: 'text-green-500', mdps: 'text-red-500', rdps: 'text-red-500' };

// Expanded items
const expandedUser = ref(null);       // pool: userId
const expandedSplitChar = ref(null);  // split: charId

// Get full char data with specs from allStaticAlts
const getCharDetails = (charId) => {
    for (const alts of Object.values(props.allStaticAlts)) {
        const found = alts.find(a => a.id === charId);
        if (found) return found;
    }
    return null;
};

// Character picker popup: shows when dropping a user with multiple chars
const charPicker = ref(null); // { userId, targetSplit, chars: [] }

// Conflict highlight: flash a charId in a split when duplicate attempted
const conflictCharId = ref(null);
let conflictTimer = null;

const showConflict = (userId, splitNum) => {
    // Find which char of this user is already in this split
    const user = users.value.find(u => u.userId === userId);
    if (!user) return;
    const existing = user.characters.find(c => assignments.value[c.id] === splitNum);
    if (!existing) return;

    conflictCharId.value = existing.id;
    clearTimeout(conflictTimer);
    conflictTimer = setTimeout(() => { conflictCharId.value = null; }, 1500);
};

// Check if a user already has a character in a specific split
const userHasCharInSplit = (userId, splitNum) => {
    const user = users.value.find(u => u.userId === userId);
    if (!user) return false;
    return user.characters.some(c => assignments.value[c.id] === splitNum);
};

const onDropWithPicker = (e, targetSplit) => {
    e.preventDefault();
    dragOverSplit.value = null;
    if (!dragging.value) return;
    const { charId, userId, fromSplit, char } = dragging.value;

    if (targetSplit === 0) {
        delete assignments.value[charId];
        assignments.value = { ...assignments.value };
        dragging.value = null;
        return;
    }

    if (targetSplit === fromSplit) { dragging.value = null; return; }

    // Block: user already has a char in this split — highlight existing
    if (fromSplit !== targetSplit && userHasCharInSplit(userId, targetSplit)) {
        showConflict(userId, targetSplit);
        dragging.value = null;
        return;
    }

    const g = splitGroups.value[targetSplit];
    if (g?.total >= SPLIT_LIMIT) { dragging.value = null; return; }

    const { isSpecificChar } = dragging.value;

    // From pool as user card (not specific char): show picker
    if (!fromSplit && !isSpecificChar) {
        const user = users.value.find(u => u.userId === userId);
        const available = user ? userAvailableChars(user) : [char];
        charPicker.value = { userId, targetSplit, chars: available };
        dragging.value = null;
        return;
    }

    // Specific char from pool or moving between splits: direct assign
    const role = char.main_spec?.role || 'rdps';
    if (role === 'tank' && (g?.tank?.length || 0) >= TANK_LIMIT) { dragging.value = null; return; }
    assignments.value[charId] = targetSplit;
    assignments.value = { ...assignments.value };
    dragging.value = null;
};

const pickChar = (charId) => {
    if (!charPicker.value) return;
    const { targetSplit, userId } = charPicker.value;
    const g = splitGroups.value[targetSplit];
    if (g?.total >= SPLIT_LIMIT) { charPicker.value = null; return; }
    if (userHasCharInSplit(userId, targetSplit)) {
        showConflict(userId, targetSplit);
        charPicker.value = null;
        return;
    }
    assignments.value[charId] = targetSplit;
    assignments.value = { ...assignments.value };
    charPicker.value = null;
};

// Add / remove split
const addSplit = () => {
    if (localSplitCount.value < 4) {
        localSplitCount.value++;
        emit('update-split-count', localSplitCount.value);
    }
};
const removeSplit = (n) => {
    if (localSplitCount.value <= 2) return;
    // Unassign everyone from this split
    for (const [charId, split] of Object.entries(assignments.value)) {
        if (split === n) delete assignments.value[charId];
        // Shift higher splits down
        if (split > n) assignments.value[charId] = split - 1;
    }
    assignments.value = { ...assignments.value };
    localSplitCount.value--;
    emit('update-split-count', localSplitCount.value);
};
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/50">{{ __('Split Raid') }}</span>
                <button
                    v-if="localSplitCount < 4"
                    @click="addSplit"
                    class="flex items-center gap-1 px-2 py-1 rounded text-[8px] font-black uppercase tracking-widest bg-white/5 border border-white/10 text-on-surface-variant hover:text-white hover:bg-white/10 transition-all"
                >
                    <span class="material-symbols-outlined text-[12px]">add</span>
                    {{ __('Add Split') }}
                </button>
            </div>
            <button
                @click="saveSplits" :disabled="saving"
                class="flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border transition-all"
                :class="saved ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-fuchsia-400/10 border-fuchsia-400/30 text-fuchsia-400 hover:bg-fuchsia-400/20'"
            >
                <span class="material-symbols-outlined text-sm">{{ saved ? 'check' : 'save' }}</span>
                {{ saved ? __('Saved!') : saving ? __('Saving...') : __('Save Splits') }}
            </button>
        </div>

        <!-- Canvas: full-width grid -->
        <div class="grid gap-3" :style="{ gridTemplateColumns: `minmax(200px, 1fr) repeat(${localSplitCount}, minmax(200px, 1fr))` }">
            <!-- Pool: user cards -->
            <div
                class="bg-surface-container/60 border rounded-xl overflow-hidden transition-colors"
                :class="dragOverSplit === 0 ? 'border-fuchsia-400/50 bg-fuchsia-400/5' : 'border-white/5'"
                @dragover.prevent="onDragOver($event, 0)" @dragleave="onDragLeave" @drop="onDropWithPicker($event, 0)"
            >
                <div class="px-3 py-2 border-b border-white/5 bg-white/[0.02]">
                    <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/60">
                        {{ __('Roster') }} ({{ poolUsers.length }})
                    </span>
                </div>
                <div class="p-1.5 space-y-1 max-h-[60vh] overflow-y-auto">
                    <div
                        v-for="user in poolUsers"
                        :key="user.userId"
                        class="bg-white/[0.03] border border-white/5 rounded-lg overflow-hidden"
                    >
                        <div
                            class="flex items-center gap-2 px-2 py-1.5 cursor-grab active:cursor-grabbing hover:bg-white/5 transition-colors"
                            draggable="true"
                            @dragstart="onDragStart($event, user.available[0], user.userId)"
                            @dragend="onDragEnd"
                            @click="expandedUser = expandedUser === user.userId ? null : user.userId"
                        >
                            <div class="w-1 h-6 rounded-full opacity-60" :class="'bg-wow-' + (user.mainClass || '').toLowerCase().replace(/ /g, '-')"></div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-bold truncate" :class="'text-wow-' + (user.mainClass || '').toLowerCase().replace(/ /g, '-')">
                                    {{ user.displayName }}
                                </div>
                                <div class="text-[8px] text-on-surface-variant/40">
                                    {{ user.available.length }} char{{ user.available.length > 1 ? 's' : '' }}
                                    <template v-if="Object.keys(userSplitInfo(user)).length">
                                        · in {{ Object.keys(userSplitInfo(user)).map(s => splitLabels[s - 1]).join(', ') }}
                                    </template>
                                </div>
                            </div>
                            <span class="material-symbols-outlined text-[14px] text-on-surface-variant/30 transition-transform" :class="expandedUser === user.userId ? 'rotate-180' : ''">expand_more</span>
                        </div>

                        <!-- Expanded: all available chars with specs -->
                        <div v-if="expandedUser === user.userId" class="border-t border-white/5 px-1.5 py-1 space-y-0.5">
                            <div
                                v-for="char in user.available" :key="char.id"
                                draggable="true"
                                @dragstart.stop="onDragStart($event, char, user.userId, null, true)"
                                @dragend="onDragEnd"
                                class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-white/10 cursor-grab active:cursor-grabbing text-[9px]"
                            >
                                <img v-if="char.main_spec?.icon_url" :src="char.main_spec.icon_url" class="w-4 h-4 rounded">
                                <span class="font-bold" :class="'text-wow-' + (char.playable_class || '').toLowerCase().replace(/ /g, '-')">{{ char.name }}</span>
                                <span class="text-on-surface-variant/40">{{ char.main_spec?.name }} · {{ char.item_level }}</span>
                                <span v-if="char.role === 'main'" class="text-fuchsia-400 text-[7px]">★</span>
                                <!-- Specs badges -->
                                <span
                                    v-for="spec in (getCharDetails(char.id)?.specs || []).filter(s => s.id !== char.main_spec?.id)"
                                    :key="spec.id"
                                    class="text-[7px] text-on-surface-variant/30 px-1 border border-white/5 rounded"
                                >{{ spec.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="poolUsers.length === 0" class="text-center py-4 text-[9px] text-on-surface-variant/30 italic">{{ __('All assigned') }}</div>
                </div>
            </div>

            <!-- Split columns (full-width, flexible) -->
            <div
                v-for="splitNum in localSplitCount" :key="splitNum"
                class="bg-surface-container/60 border rounded-xl overflow-hidden transition-colors"
                :class="dragOverSplit === splitNum ? 'border-fuchsia-400/50 bg-fuchsia-400/5' : 'border-white/5'"
                @dragover="onDragOver($event, splitNum)" @dragleave="onDragLeave" @drop="onDropWithPicker($event, splitNum)"
            >
                <!-- Header: name + audit button + delete -->
                <div class="px-3 py-2 border-b border-white/5 bg-white/[0.02] flex items-center justify-between">
                    <span class="text-[9px] font-black uppercase tracking-widest text-fuchsia-400">
                        Split {{ splitLabels[splitNum - 1] }}
                    </span>
                    <div class="flex items-center gap-1.5">
                        <button
                            @click="auditSplit = splitNum"
                            class="flex items-center gap-1 text-[9px] font-bold border rounded px-1.5 py-0.5 transition-all"
                            :class="splitWarningCount(splitNum) > 0
                                ? 'bg-red-500/10 border-red-500/20 text-red-400'
                                : 'bg-green-500/10 border-green-500/20 text-green-400'"
                        >
                            <span class="material-symbols-outlined text-[11px]">{{ splitWarningCount(splitNum) > 0 ? 'warning' : 'check' }}</span>
                            {{ splitGroups[splitNum]?.total || 0 }}/{{ SPLIT_LIMIT }}
                        </button>
                        <button
                            v-if="localSplitCount > 2"
                            @click="removeSplit(splitNum)"
                            class="material-symbols-outlined text-[14px] text-on-surface-variant/30 hover:text-red-400 transition-all"
                            :title="__('Remove split')"
                        >delete</button>
                    </div>
                </div>

                <!-- Roles -->
                <div class="p-1.5 space-y-1 max-h-[60vh] overflow-y-auto">
                    <div v-for="role in ['tank', 'heal', 'mdps', 'rdps']" :key="role">
                        <div v-if="splitGroups[splitNum]?.[role]?.length > 0" class="mb-1">
                            <div class="flex items-center gap-1 px-1 mb-0.5">
                                <img :src="'/images/roles/' + roleIcons[role]" class="w-3 h-3 opacity-50">
                                <span class="text-[8px] font-black uppercase tracking-widest" :class="roleColors[role]">{{ splitGroups[splitNum][role].length }}</span>
                            </div>
                            <div class="space-y-0.5">
                                <div
                                    v-for="char in splitGroups[splitNum][role]" :key="char.id"
                                    draggable="true"
                                    @dragstart="onDragStart($event, char, char.userId, splitNum)"
                                    @dragend="onDragEnd"
                                    class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/10 cursor-grab active:cursor-grabbing group transition-all"
                                    :class="conflictCharId === char.id ? 'ring-2 ring-yellow-400 bg-yellow-400/10 animate-pulse' : ''"
                                >
                                    <img v-if="char.main_spec?.icon_url" :src="char.main_spec.icon_url" class="w-5 h-5 rounded border border-white/10">
                                    <div class="flex-1 min-w-0">
                                        <span class="text-[10px] font-bold truncate block" :class="'text-wow-' + (char.playable_class || '').toLowerCase().replace(/ /g, '-')">{{ char.name }}</span>
                                        <span class="text-[8px] text-on-surface-variant/40">{{ char.main_spec?.name }} · {{ getCharDetails(char.id)?.item_level || '' }}</span>
                                    </div>
                                    <button @click.stop="removeFromSplit(char.id)" class="material-symbols-outlined text-[11px] text-transparent group-hover:text-red-400 transition-all">close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!splitGroups[splitNum]?.total" class="text-center py-6 text-[9px] text-on-surface-variant/20 italic">{{ __('Drop players here') }}</div>
                </div>
            </div>
        </div>

        <!-- Character picker popup (when dropping user with multiple chars) -->
        <GlassModal :show="charPicker !== null" max-width="max-w-xs" @close="charPicker = null">
            <div v-if="charPicker" class="p-4">
                <div class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/50 mb-3">
                    {{ __('Choose character for Split') }} {{ splitLabels[(charPicker.targetSplit || 1) - 1] }}
                </div>
                <div class="space-y-1">
                    <button
                        v-for="char in charPicker.chars" :key="char.id"
                        @click="pickChar(char.id)"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border border-white/10 hover:border-white/20 hover:bg-white/5 transition-all text-left"
                    >
                        <img v-if="char.main_spec?.icon_url" :src="char.main_spec.icon_url" class="w-6 h-6 rounded border border-white/10">
                        <div class="flex-1 min-w-0">
                            <div class="text-[11px] font-bold" :class="'text-wow-' + (char.playable_class || '').toLowerCase().replace(/ /g, '-')">{{ char.name }}</div>
                            <div class="text-[9px] text-on-surface-variant/50">{{ char.main_spec?.name }} · {{ char.item_level }} ilvl
                                <span v-if="char.role === 'main'" class="text-fuchsia-400">★ main</span>
                            </div>
                        </div>
                    </button>
                </div>
            </div>
        </GlassModal>

        <!-- Audit modal -->
        <GlassModal :show="auditSplit !== null" max-width="max-w-xl" @close="auditSplit = null">
            <div v-if="auditSplit" class="p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black uppercase tracking-widest text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg">fact_check</span>
                        {{ __('Split') }} {{ splitLabels[(auditSplit || 1) - 1] }} {{ __('Audit') }}
                    </h3>
                    <button @click="auditSplit = null" class="text-on-surface-variant hover:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <CompositionWarnings
                    :main-roster="splitAsMainRoster(auditSplit)"
                    :role-limits="{ total: 30, tank: 2, heal: { min: 2, max: 6 }, dps: { min: 22, max: 26 } }"
                    :buff-config="buffConfig"
                    :roster-classes="splitClasses(auditSplit)"
                    :bench-history="{}"
                />
                <BuffChecklist :buff-config="buffConfig" :roster-classes="splitClasses(auditSplit)" />
            </div>
        </GlassModal>
    </div>
</template>

<style scoped>
.text-wow-warrior      { color: #C69B6D; }
.text-wow-paladin      { color: #F48CBA; }
.text-wow-hunter       { color: #ABD473; }
.text-wow-rogue        { color: #FFF468; }
.text-wow-priest       { color: #FFFFFF; }
.text-wow-death-knight { color: #C41F3B; }
.text-wow-shaman       { color: #0070DD; }
.text-wow-mage         { color: #3FC7EB; }
.text-wow-warlock      { color: #8788EE; }
.text-wow-monk         { color: #00FF98; }
.text-wow-druid        { color: #FF7C0A; }
.text-wow-demon-hunter { color: #A330C9; }
.text-wow-evoker       { color: #33937F; }

/* bg variants for pool user bar */
.bg-wow-warrior      { background: #C69B6D; }
.bg-wow-paladin      { background: #F48CBA; }
.bg-wow-hunter       { background: #ABD473; }
.bg-wow-rogue        { background: #FFF468; }
.bg-wow-priest       { background: #FFFFFF; }
.bg-wow-death-knight { background: #C41F3B; }
.bg-wow-shaman       { background: #0070DD; }
.bg-wow-mage         { background: #3FC7EB; }
.bg-wow-warlock      { background: #8788EE; }
.bg-wow-monk         { background: #00FF98; }
.bg-wow-druid        { background: #FF7C0A; }
.bg-wow-demon-hunter { background: #A330C9; }
.bg-wow-evoker       { background: #33937F; }
</style>
