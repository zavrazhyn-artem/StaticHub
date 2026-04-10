<script setup>
import { ref, computed, watch } from 'vue';
import { useWowClasses } from '../../composables/useWowClasses.js';

const props = defineProps({
    encounterSlug: { type: String, default: null },
    encounterName: { type: String, default: 'All Encounters' },
    encounterRosters: { type: Object, default: () => ({}) },
    mainRoster: { type: Object, required: true },
    absentRoster: { type: Array, default: () => [] },
    planningStats: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    csrfToken: { type: String, required: true },
    routes: { type: Object, required: true },
});

const emit = defineEmits(['assign', 'remove']);

const { getClassColor } = useWowClasses();

const currentRoster = computed(() => {
    if (!props.encounterSlug) return null;
    return props.encounterRosters[props.encounterSlug] || { selected: [], queued: [], benched: [] };
});

const hasEncounterRoster = computed(() => {
    return props.encounterSlug && currentRoster.value &&
        (currentRoster.value.selected.length > 0 ||
         currentRoster.value.queued.length > 0);
});

// All available characters from main + absent roster for assignment
const allCharacters = computed(() => {
    const chars = [];
    for (const role of ['tank', 'heal', 'mdps', 'rdps']) {
        (props.mainRoster[role] || []).forEach(c => chars.push(c));
    }
    props.absentRoster.forEach(c => chars.push(c));
    return chars;
});

// Characters not yet assigned to this encounter
const unassignedCharacters = computed(() => {
    if (!currentRoster.value) return allCharacters.value;
    const assignedIds = new Set([
        ...currentRoster.value.selected.map(r => r.character_id),
        ...currentRoster.value.queued.map(r => r.character_id),
        ...currentRoster.value.benched.map(r => r.character_id),
    ]);
    return allCharacters.value.filter(c => !assignedIds.has(c.id));
});

const roleGroups = computed(() => {
    const groups = {
        tank: { label: 'Tank', icon: 'tank.svg', color: 'text-blue-400', chars: [] },
        heal: { label: 'Heal', icon: 'heal.svg', color: 'text-green-500', chars: [] },
        mdps: { label: 'Melee', icon: 'melee.svg', color: 'text-red-500', chars: [] },
        rdps: { label: 'Ranged', icon: 'range.svg', color: 'text-red-500', chars: [] },
    };

    if (!currentRoster.value) return groups;

    currentRoster.value.selected.forEach(entry => {
        const role = entry.role || 'rdps';
        if (groups[role]) groups[role].chars.push({ ...entry, _status: 'selected' });
    });

    return groups;
});

const queuedChars = computed(() => {
    if (!currentRoster.value) return [];
    return currentRoster.value.queued || [];
});

const getStatForChar = (charId) => {
    return props.planningStats[charId] || null;
};

const showAssignPanel = ref(false);

const assignCharacter = (characterId, status = 'selected') => {
    emit('assign', {
        encounter_slug: props.encounterSlug,
        character_id: characterId,
        selection_status: status,
    });
};

const removeCharacter = (characterId) => {
    emit('remove', {
        encounter_slug: props.encounterSlug,
        character_id: characterId,
    });
};
</script>

<template>
    <div class="space-y-4">
        <!-- Encounter header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-xl text-primary">swords</span>
                <h3 class="text-sm font-black uppercase tracking-widest text-white">{{ encounterName }}</h3>
            </div>
            <div v-if="encounterSlug && canManage" class="flex items-center gap-2">
                <button
                    @click="showAssignPanel = !showAssignPanel"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all"
                    :class="showAssignPanel
                        ? 'bg-primary/20 text-primary border border-primary/30'
                        : 'bg-white/5 text-on-surface-variant hover:text-white border border-white/10'"
                >
                    <span class="material-symbols-outlined text-sm">person_add</span>
                    Assign
                </button>
            </div>
        </div>

        <!-- Per-encounter selected roster by role -->
        <div v-if="encounterSlug && hasEncounterRoster" class="space-y-3">
            <div
                v-for="(group, roleKey) in roleGroups"
                :key="roleKey"
            >
                <div v-if="group.chars.length > 0" class="bg-surface-container/60 border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-3 py-1.5 border-b border-white/5 flex items-center gap-2 bg-white/[0.02]">
                        <img :src="'/images/roles/' + group.icon" class="w-3.5 h-3.5 opacity-80" :alt="group.label">
                        <span class="text-[9px] font-black uppercase tracking-widest" :class="group.color">{{ group.label }}</span>
                        <span class="text-[9px] font-bold text-on-surface-variant ml-auto">{{ group.chars.length }}</span>
                    </div>
                    <div class="p-1.5 space-y-0.5">
                        <div
                            v-for="entry in group.chars"
                            :key="entry.character_id"
                            class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-white/5 transition-colors group"
                        >
                            <div class="flex items-center gap-2">
                                <div class="w-1 h-6 rounded-full opacity-60"
                                    :class="getClassColor(entry.class_name)"></div>
                                <img
                                    v-if="entry.spec?.icon_url"
                                    :src="entry.spec.icon_url"
                                    class="w-5 h-5 rounded"
                                    :alt="entry.spec?.name"
                                >
                                <div>
                                    <div
                                        class="text-[11px] font-bold leading-none"
                                        :class="'text-wow-' + (entry.class_name || '').toLowerCase().replace(' ', '-')"
                                    >{{ entry.character_name }}</div>
                                    <div class="text-[8px] text-on-surface-variant mt-0.5">{{ entry.spec?.name || '' }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="getStatForChar(entry.character_id)" class="text-[8px] text-on-surface-variant">
                                    <span class="text-primary font-bold">{{ getStatForChar(entry.character_id).percentage }}%</span>
                                    <span class="opacity-60 ml-0.5">attendance</span>
                                </div>
                                <button
                                    v-if="canManage"
                                    @click="removeCharacter(entry.character_id)"
                                    class="opacity-0 group-hover:opacity-100 text-error-dim hover:text-red-400 transition-all p-0.5"
                                >
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Queued section -->
            <div v-if="queuedChars.length > 0" class="bg-surface-container/60 border border-yellow-500/10 rounded-xl overflow-hidden">
                <div class="px-3 py-1.5 border-b border-yellow-500/10 flex items-center gap-2 bg-yellow-500/[0.03]">
                    <span class="material-symbols-outlined text-sm text-yellow-500/70">hourglass_top</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-yellow-500/70">Queued</span>
                    <span class="text-[9px] font-bold text-on-surface-variant ml-auto">{{ queuedChars.length }}</span>
                </div>
                <div class="p-1.5 space-y-0.5">
                    <div
                        v-for="entry in queuedChars"
                        :key="entry.character_id"
                        class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-white/5 transition-colors group opacity-60"
                    >
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-6 rounded-full opacity-40"
                                :class="getClassColor(entry.class_name)"></div>
                            <div>
                                <div
                                    class="text-[11px] font-bold leading-none"
                                    :class="'text-wow-' + (entry.class_name || '').toLowerCase().replace(' ', '-')"
                                >{{ entry.character_name }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                v-if="canManage"
                                @click="assignCharacter(entry.character_id, 'selected')"
                                class="opacity-0 group-hover:opacity-100 text-green-400 hover:text-green-300 transition-all p-0.5"
                                title="Promote to selected"
                            >
                                <span class="material-symbols-outlined text-sm">arrow_upward</span>
                            </button>
                            <button
                                v-if="canManage"
                                @click="removeCharacter(entry.character_id)"
                                class="opacity-0 group-hover:opacity-100 text-error-dim hover:text-red-400 transition-all p-0.5"
                            >
                                <span class="material-symbols-outlined text-sm">close</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No encounter selected or no assignments: show info -->
        <div v-else-if="encounterSlug && !hasEncounterRoster" class="text-center py-8">
            <span class="material-symbols-outlined text-3xl text-on-surface-variant/30">assignment</span>
            <p class="text-xs text-on-surface-variant/50 mt-2">No roster assignments for this encounter</p>
            <button
                v-if="canManage"
                @click="showAssignPanel = true"
                class="mt-3 px-4 py-2 rounded-xl bg-primary/10 border border-primary/30 text-primary text-[10px] font-black uppercase tracking-widest hover:bg-primary/20 transition-all"
            >
                <span class="material-symbols-outlined text-sm align-middle mr-1">add</span>
                Assign Players
            </button>
        </div>

        <!-- Assignment panel (slide in) -->
        <div v-if="showAssignPanel && encounterSlug" class="bg-surface-container-high/80 border border-white/10 rounded-xl p-3 space-y-2">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant">Available Players</span>
                <button @click="showAssignPanel = false" class="text-on-surface-variant hover:text-white">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
            <div
                v-for="char in unassignedCharacters"
                :key="char.id"
                class="flex items-center justify-between px-2 py-1.5 rounded-lg hover:bg-white/5 transition-colors cursor-pointer group"
                @click="assignCharacter(char.id, 'selected')"
            >
                <div class="flex items-center gap-2">
                    <img :src="char.avatar_url" class="w-6 h-6 rounded object-cover border border-white/10">
                    <div>
                        <div
                            class="text-[10px] font-bold"
                            :class="'text-wow-' + (char.playable_class || '').toLowerCase().replace(' ', '-')"
                        >{{ char.name }}</div>
                        <div class="text-[8px] text-on-surface-variant">{{ char.main_spec?.name || '' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button
                        @click.stop="assignCharacter(char.id, 'queued')"
                        class="opacity-0 group-hover:opacity-100 text-yellow-400 text-[8px] font-bold px-2 py-0.5 rounded bg-yellow-400/10 border border-yellow-400/20 hover:bg-yellow-400/20 transition-all"
                    >Queue</button>
                    <span class="material-symbols-outlined text-sm text-green-400 opacity-0 group-hover:opacity-100">add_circle</span>
                </div>
            </div>
            <div v-if="unassignedCharacters.length === 0" class="text-center py-3">
                <span class="text-[9px] text-on-surface-variant/50">All players assigned</span>
            </div>
        </div>
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
</style>
