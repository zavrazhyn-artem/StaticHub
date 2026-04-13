<script setup>
import { ref, computed } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    characters: { type: Array, required: true },
    groups: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['assign-group', 'remove-from-group', 'add-group', 'remove-group', 'place-formation']);

const GROUP_COLORS = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];

const groupEntries = computed(() =>
    Object.entries(props.groups).map(([id, g]) => ({ id: Number(id), ...g })).sort((a, b) => a.id - b.id)
);

const unassignedCharacters = computed(() => {
    const allGroupedIds = new Set();
    for (const g of Object.values(props.groups)) (g.members || []).forEach(id => allGroupedIds.add(id));
    return props.characters.filter(c => !allGroupedIds.has(c.id));
});

const getChar = (id) => props.characters.find(c => c.id === id);

const collapsed = ref({});
const toggleCollapse = (groupId) => { collapsed.value[groupId] = !collapsed.value[groupId]; };

const addGroup = () => {
    const existingIds = Object.keys(props.groups).map(Number);
    let nextId = 1;
    while (existingIds.includes(nextId) && nextId <= 6) nextId++;
    if (nextId > 6) return;
    emit('add-group', { id: nextId, label: `G${nextId}`, color: GROUP_COLORS[(nextId - 1) % GROUP_COLORS.length], members: [] });
};

const handleDragStart = (e, charId) => {
    e.dataTransfer.setData('text/plain', String(charId));
    e.dataTransfer.effectAllowed = 'move';
};

const handleGroupDrop = (e, groupId) => {
    e.preventDefault();
    const charId = Number(e.dataTransfer.getData('text/plain'));
    if (charId) emit('assign-group', { characterId: charId, groupId });
};
const handleGroupDragOver = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; };

const assignToGroup = (charId, groupId) => { emit('assign-group', { characterId: charId, groupId }); };

const formations = computed(() => [
    { id: 'spread', label: __('Spread (circle)'), icon: 'radio_button_unchecked' },
    { id: 'stack', label: __('Stack (tight)'), icon: 'fiber_manual_record' },
    { id: 'line', label: __('Line'), icon: 'horizontal_rule' },
    { id: 'vline', label: __('Column'), icon: 'drag_handle' },
    { id: 'triangle', label: __('Triangle'), icon: 'change_history' },
    { id: 'tworows', label: __('Two rows'), icon: 'view_week' },
]);

const classSlug = (cls) => (cls || '').toLowerCase().replace(/\s+/g, '-').replace(/'/g, '');
</script>

<template>
    <div class="h-full flex flex-col text-xs">
        <!-- Header -->
        <div class="shrink-0 px-3 py-2 border-b border-white/5 flex items-center justify-between">
            <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant">{{ __('Roster & Groups') }}</span>
            <button
                v-if="canManage && Object.keys(groups).length < 6"
                @click="addGroup"
                class="text-orange-500 hover:text-white transition-colors" :title="__('Add Group')"
            >
                <span class="material-symbols-outlined text-sm">add</span>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto scrollbar-thin">
            <!-- Groups -->
            <div v-for="group in groupEntries" :key="group.id" class="border-b border-white/5">
                <div class="flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-white/5 transition-colors"
                    @click="toggleCollapse(group.id)"
                    @drop="handleGroupDrop($event, group.id)" @dragover="handleGroupDragOver">
                    <div class="w-2.5 h-2.5 rounded-sm shrink-0" :style="{ backgroundColor: group.color }"></div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-white flex-1">{{ group.label }}</span>
                    <span class="text-[8px] text-on-surface-variant/50">{{ (group.members || []).length }}</span>
                    <button v-if="canManage" @click.stop="emit('remove-group', group.id)"
                        class="text-on-surface-variant/30 hover:text-red-400 transition-colors" :title="__('Remove group')">
                        <span class="material-symbols-outlined text-xs">close</span>
                    </button>
                    <span class="material-symbols-outlined text-xs text-on-surface-variant/30 transition-transform"
                        :class="collapsed[group.id] ? '' : 'rotate-180'">expand_more</span>
                </div>

                <div v-show="!collapsed[group.id]" class="pb-1 px-1.5"
                    @drop="handleGroupDrop($event, group.id)" @dragover="handleGroupDragOver">
                    <div v-for="memberId in (group.members || [])" :key="memberId"
                        class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/5 transition-colors group/member"
                        draggable="true" @dragstart="handleDragStart($event, memberId)">
                        <div class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: group.color }"></div>
                        <img v-if="getChar(memberId)?.avatar_url" :src="getChar(memberId).avatar_url" class="w-4 h-4 rounded object-cover">
                        <span class="text-[9px] font-bold truncate flex-1"
                            :class="'text-wow-' + classSlug(getChar(memberId)?.playable_class)">{{ getChar(memberId)?.name || memberId }}</span>
                        <button v-if="canManage" @click.stop="emit('remove-from-group', { characterId: memberId, groupId: group.id })"
                            class="opacity-0 group-hover/member:opacity-100 text-on-surface-variant/40 hover:text-red-400 transition-all">
                            <span class="material-symbols-outlined text-[10px]">close</span>
                        </button>
                    </div>
                    <div v-if="(group.members || []).length === 0"
                        class="px-2 py-3 text-center border border-dashed border-white/10 rounded-md mx-1 my-1">
                        <span class="text-[8px] text-on-surface-variant/30">{{ __('Drag players here') }}</span>
                    </div>
                    <!-- Formation presets -->
                    <div v-if="canManage && (group.members || []).length >= 2" class="flex items-center gap-0.5 px-1 py-1">
                        <span class="text-[7px] text-on-surface-variant/30 mr-1">{{ __('Place') }}:</span>
                        <button v-for="f in formations" :key="f.id" @click.stop="emit('place-formation', { groupId: group.id, formation: f.id })"
                            class="w-5 h-5 rounded flex items-center justify-center text-on-surface-variant/40 hover:text-white hover:bg-white/10 transition-all"
                            :title="f.label">
                            <span class="material-symbols-outlined text-xs">{{ f.icon }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Unassigned players -->
            <div>
                <div class="px-3 py-1.5 flex items-center gap-2">
                    <span class="material-symbols-outlined text-xs text-on-surface-variant/40">person</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/50 flex-1">{{ __('Unassigned') }}</span>
                    <span class="text-[8px] text-on-surface-variant/30">{{ unassignedCharacters.length }}</span>
                </div>
                <div class="pb-1 px-1.5">
                    <div v-for="char in unassignedCharacters" :key="char.id"
                        class="flex items-center gap-1.5 px-2 py-1 rounded-md hover:bg-white/5 cursor-grab transition-colors group/member"
                        draggable="true" @dragstart="handleDragStart($event, char.id)">
                        <img v-if="char.avatar_url" :src="char.avatar_url" class="w-4 h-4 rounded object-cover border border-white/10">
                        <span class="text-[9px] font-bold truncate flex-1"
                            :class="'text-wow-' + classSlug(char.playable_class)">{{ char.name }}</span>
                        <!-- Quick-assign buttons -->
                        <div v-if="canManage && groupEntries.length > 0"
                            class="opacity-0 group-hover/member:opacity-100 flex items-center gap-0.5 transition-all">
                            <button v-for="g in groupEntries" :key="g.id"
                                @click.stop="assignToGroup(char.id, g.id)"
                                class="w-4 h-4 rounded-sm flex items-center justify-center text-[7px] font-black text-white hover:scale-110 transition-transform"
                                :style="{ backgroundColor: g.color + '80' }" :title="__('Assign to') + ' ' + g.label">{{ g.id }}</button>
                        </div>
                    </div>
                    <div v-if="unassignedCharacters.length === 0" class="py-2 text-center">
                        <span class="text-[8px] text-on-surface-variant/30">{{ __('All assigned to groups') }}</span>
                    </div>
                </div>
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
