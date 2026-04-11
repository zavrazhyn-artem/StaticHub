<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useDodgePanel } from '@/composables/useDodgePanel';
const { __ } = useTranslation();

const props = defineProps({
    assignment: { type: Object, required: true },
    fightDuration: { type: Number, default: 360 },
    canManage: { type: Boolean, default: false },
    cooldownSec: { type: Number, default: 0 },
});
const emit = defineEmits(['close', 'update', 'remove']);

const position = ref({ x: window.innerWidth - 360, y: 200 });
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

const formatTime = (sec) => {
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec) % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
};

const timeInput = ref(formatTime(props.assignment.time));
const noteInput = ref(props.assignment.note || '');

// Sync local input when the parent updates the assignment (arrow keys, drag, etc.)
watch(() => props.assignment.time, (t) => { timeInput.value = formatTime(t); });
watch(() => props.assignment.note, (n) => { noteInput.value = n || ''; });

const commitTime = () => {
    const str = String(timeInput.value || '').trim();
    let total = 0;
    if (str.includes(':')) {
        const [m, s] = str.split(':').map(Number);
        total = (m || 0) * 60 + (s || 0);
    } else total = Number(str) || 0;
    if (total >= 0 && total <= props.fightDuration) {
        emit('update', { ...props.assignment, time: total });
    } else {
        timeInput.value = formatTime(props.assignment.time);
    }
};
const commitNote = () => {
    emit('update', { ...props.assignment, note: noteInput.value });
};

const classColor = computed(() => ({
    'Warrior': '#C69B6D', 'Paladin': '#F48CBA', 'Hunter': '#ABD473',
    'Rogue': '#FFF468', 'Priest': '#FFFFFF', 'Death Knight': '#C41F3B',
    'Shaman': '#0070DD', 'Mage': '#3FC7EB', 'Warlock': '#8788EE',
    'Monk': '#00FF98', 'Druid': '#FF7C0A', 'Demon Hunter': '#A330C9', 'Evoker': '#33937F',
})[props.assignment.class] || '#FFFFFF');
</script>

<template>
    <Teleport to="body">
        <div ref="panelRef"
            class="fixed z-[265] w-[260px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
            :class="isDodging ? 'panel-dodging' : ''"
            :style="{ left: position.x + 'px', top: position.y + 'px' }"
            @mousedown.stop @click.stop>
            <!-- Header (draggable) -->
            <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                @mousedown="startDrag">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary">{{ __('Assignment') }}</span>
                </div>
                <button @click="emit('close')" class="text-on-surface-variant/50 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <div class="p-3 space-y-3">
                <!-- Spell + character info -->
                <div class="flex items-center gap-2">
                    <img :src="remoteIconUrl(assignment.icon)" class="w-10 h-10 rounded border border-white/10">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold text-white truncate">{{ assignment.spell_name }}</div>
                        <div class="text-[9px] truncate" :style="{ color: classColor }">{{ assignment.character_name }}</div>
                        <div v-if="cooldownSec > 0" class="text-[8px] text-on-surface-variant/40">{{ cooldownSec }}s {{ __('cooldown') }}</div>
                    </div>
                </div>

                <!-- Time input -->
                <div class="space-y-1">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Time') }}</label>
                    <input
                        v-model="timeInput"
                        @change="commitTime"
                        @blur="commitTime"
                        @keydown.enter="commitTime"
                        :disabled="!canManage"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-[10px] text-white font-mono outline-none focus:border-primary/50"
                        placeholder="0:00"
                    >
                </div>

                <!-- Note -->
                <div class="space-y-1">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Note (optional)') }}</label>
                    <input
                        v-model="noteInput"
                        @change="commitNote"
                        @blur="commitNote"
                        :disabled="!canManage"
                        type="text"
                        placeholder="e.g. Tank cover"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-[10px] text-white outline-none focus:border-primary/50"
                    >
                </div>

                <!-- Remove -->
                <button
                    v-if="canManage"
                    @click="emit('remove', assignment.id)"
                    class="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 text-[9px] font-black uppercase tracking-widest transition-all"
                >
                    <span class="material-symbols-outlined text-xs">delete</span>
                    {{ __('Remove Assignment') }}
                </button>

                <div class="text-[8px] text-on-surface-variant/30 text-center">
                    {{ __('Drag the icon on the timeline to move it') }}
                </div>
            </div>
        </div>
    </Teleport>
</template>
