<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useDodgePanel } from '@/composables/useDodgePanel';
const { __ } = useTranslation();

const props = defineProps({
    character: { type: Object, required: true },
    cooldowns: { type: Array, default: () => [] },
    disabledSpellIds: { type: Array, default: () => [] },
    typeFilters: { type: Object, default: () => ({ personal: true, external: true, raid: true, utility: true }) },
    canManage: { type: Boolean, default: false },
    // The panel teleports to body, escaping the parent tab's v-show. Pass
    // `visible` so the panel can hide itself when the user switches tabs
    // while preserving its drag position and selection state.
    visible: { type: Boolean, default: true },
});
const emit = defineEmits(['close', 'drag-start', 'toggle-cd']);

const position = ref({ x: window.innerWidth - 360, y: 120 });
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const panelRef = ref(null);

const startDrag = (e) => {
    isDragging.value = true;
    dragStart.value = { x: e.clientX - position.value.x, y: e.clientY - position.value.y };
    e.preventDefault();
};
const onMouseMove = (e) => {
    if (!isDragging.value) return;
    position.value = { x: e.clientX - dragStart.value.x, y: e.clientY - dragStart.value.y };
};
const onMouseUp = () => { isDragging.value = false; };

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
});

const { isDodging } = useDodgePanel({
    elementRef: panelRef,
    position,
    enabled: () => !isDragging.value,
});

const remoteIconUrl = (iconName) => `https://wow.zamimg.com/images/wow/icons/large/${iconName}.jpg`;

const classColor = computed(() => ({
    'Warrior': '#C69B6D', 'Paladin': '#F48CBA', 'Hunter': '#ABD473',
    'Rogue': '#FFF468', 'Priest': '#FFFFFF', 'Death Knight': '#C41F3B',
    'Shaman': '#0070DD', 'Mage': '#3FC7EB', 'Warlock': '#8788EE',
    'Monk': '#00FF98', 'Druid': '#FF7C0A', 'Demon Hunter': '#A330C9', 'Evoker': '#33937F',
})[props.character.playable_class] || '#FFFFFF');

const onCdDragStart = (cd, e) => {
    if (!props.canManage) { e.preventDefault(); return; }
    e.dataTransfer.effectAllowed = 'copy';
    e.dataTransfer.setData('text/plain', cd.spell_id + '');
    emit('drag-start', cd);
};

const isDisabled = (spellId) => props.disabledSpellIds.includes(spellId);

const onToggle = (cd) => {
    emit('toggle-cd', { spell_id: cd.spell_id, enabled: isDisabled(cd.spell_id) });
};

const cdsByType = computed(() => {
    const groups = { external: [], raid: [], utility: [], personal: [] };
    for (const cd of props.cooldowns) {
        const t = cd.type || 'personal';
        if (props.typeFilters[t] === false) continue;
        if (!groups[t]) groups[t] = [];
        groups[t].push(cd);
    }
    return groups;
});
</script>

<template>
    <Teleport to="body">
        <div ref="panelRef"
            v-show="visible"
            class="fixed z-[260] w-[280px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
            :class="isDodging ? 'panel-dodging' : ''"
            :style="{ left: position.x + 'px', top: position.y + 'px' }"
            @mousedown.stop @click.stop>
            <!-- Header (draggable) -->
            <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                @mousedown="startDrag">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                    <img v-if="character.avatar_url" :src="character.avatar_url"
                        class="w-6 h-6 rounded object-cover border"
                        :style="{ borderColor: classColor }">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold truncate" :style="{ color: classColor }">{{ character.name }}</div>
                        <div class="text-[8px] text-on-surface-variant/50 truncate">
                            {{ character.spec_name || '?' }} {{ character.playable_class }}
                        </div>
                    </div>
                </div>
                <button @click="emit('close')" class="text-on-surface-variant/50 hover:text-white transition-colors shrink-0">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <!-- CD list -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3">
                <div v-if="cooldowns.length === 0" class="text-center py-6">
                    <span class="material-symbols-outlined text-3xl text-on-surface-variant/15">shield</span>
                    <p class="text-[9px] text-on-surface-variant/40 mt-2">
                        {{ __('No cooldowns defined for this spec yet.') }}
                    </p>
                </div>
                <div v-for="(group, type) in cdsByType" :key="type">
                    <div v-if="group.length > 0">
                        <div class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40 px-1 pb-1.5">
                            {{ type === 'external' ? __('External Saves') : type === 'raid' ? __('Raid CDs') : type === 'utility' ? __('Utility') : __('Personal') }}
                        </div>
                        <div class="grid grid-cols-2 gap-1.5">
                            <div v-for="cd in group" :key="cd.spell_id"
                                :draggable="canManage && !isDisabled(cd.spell_id)"
                                @dragstart="onCdDragStart(cd, $event)"
                                class="group relative flex items-center gap-2 p-1.5 rounded-lg border transition-all"
                                :class="[
                                    isDisabled(cd.spell_id) ? 'bg-white/[0.02] border-white/5 opacity-40' : 'bg-white/5 border-white/10',
                                    canManage && !isDisabled(cd.spell_id) ? 'cursor-grab hover:border-primary/40 hover:bg-white/10' : 'cursor-default',
                                ]"
                                :title="cd.name + ' — ' + cd.cooldown + 's CD' + (cd.requires_talent ? ' (talent)' : '')">
                                <img :src="remoteIconUrl(cd.icon)"
                                    draggable="false"
                                    class="w-7 h-7 rounded shrink-0 select-none"
                                    :class="isDisabled(cd.spell_id) ? 'grayscale' : ''">
                                <div class="min-w-0 flex-1">
                                    <div class="text-[9px] font-bold text-white truncate"
                                        :class="isDisabled(cd.spell_id) ? 'line-through' : ''">{{ cd.name }}</div>
                                    <div class="text-[8px] text-on-surface-variant/40 truncate">
                                        {{ cd.cooldown }}s<span v-if="cd.requires_talent" class="text-yellow-500/60"> · talent</span>
                                    </div>
                                </div>
                                <button v-if="canManage" @click.stop="onToggle(cd)"
                                    class="opacity-0 group-hover:opacity-100 shrink-0 w-5 h-5 rounded flex items-center justify-center transition-all"
                                    :class="isDisabled(cd.spell_id) ? 'bg-green-500/20 text-green-400 hover:bg-green-500/30' : 'bg-red-500/20 text-red-400 hover:bg-red-500/30'"
                                    :title="isDisabled(cd.spell_id) ? __('Enable') : __('Hide for this character')">
                                    <span class="material-symbols-outlined text-xs">{{ isDisabled(cd.spell_id) ? 'visibility' : 'visibility_off' }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
