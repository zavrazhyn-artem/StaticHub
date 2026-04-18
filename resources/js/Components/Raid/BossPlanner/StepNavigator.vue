<script setup>
import { ref, nextTick } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const addBtnRef = ref(null);
const menuPos = ref({ x: 0, y: 0 });
const MENU_WIDTH = 170;
const toggleMenu = async () => {
    if (showMenu.value) { showMenu.value = false; return; }
    const rect = addBtnRef.value?.getBoundingClientRect();
    if (rect) {
        const vw = window.innerWidth;
        let x = rect.left;
        if (x + MENU_WIDTH > vw - 8) x = Math.max(8, rect.right - MENU_WIDTH);
        menuPos.value = { x, y: rect.bottom + 4 };
    }
    showMenu.value = true;
};
const closeMenuOnOutside = (e) => {
    if (!showMenu.value) return;
    if (addBtnRef.value && addBtnRef.value.contains(e.target)) return;
    showMenu.value = false;
};
if (typeof window !== 'undefined') {
    window.addEventListener('mousedown', closeMenuOnOutside);
}

const props = defineProps({
    steps: { type: Array, required: true },
    currentIndex: { type: Number, default: 0 },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'add', 'copy', 'remove', 'rename', 'reorder']);

const showMenu = ref(false);
const editingIndex = ref(-1);
const editText = ref('');
const editInput = ref(null);

// Drag reorder
const dragIndex = ref(-1);
const dragOverIndex = ref(-1);

const handleAdd = () => { emit('add'); showMenu.value = false; };
const handleCopy = () => { emit('copy'); showMenu.value = false; };

const startRename = (i) => {
    if (!props.canManage) return;
    editingIndex.value = i;
    editText.value = props.steps[i].label;
    nextTick(() => { editInput.value?.focus(); editInput.value?.select(); });
};

const finishRename = () => {
    if (editingIndex.value >= 0 && editText.value.trim()) {
        emit('rename', { index: editingIndex.value, label: editText.value.trim() });
    }
    editingIndex.value = -1;
};

const onDragStart = (e, i) => { dragIndex.value = i; e.dataTransfer.effectAllowed = 'move'; };
const onDragOver = (e, i) => { e.preventDefault(); dragOverIndex.value = i; };
const onDragLeave = () => { dragOverIndex.value = -1; };
const onDrop = (e, i) => {
    e.preventDefault();
    if (dragIndex.value >= 0 && dragIndex.value !== i) {
        emit('reorder', { from: dragIndex.value, to: i });
    }
    dragIndex.value = -1;
    dragOverIndex.value = -1;
};
const onDragEnd = () => { dragIndex.value = -1; dragOverIndex.value = -1; };
</script>

<template>
    <div class="step-scroll flex items-center gap-2 pb-1 overflow-x-auto flex-nowrap min-w-0 flex-1">
        <template v-for="(step, i) in steps" :key="i">
            <!-- Editing mode -->
            <div v-if="editingIndex === i" class="shrink-0">
                <div class="flex items-center gap-1">
                    <input
                        ref="editInput"
                        v-model="editText"
                        @blur="finishRename"
                        @keydown.enter="finishRename"
                        @keydown.escape="editingIndex = -1"
                        @mousedown.stop
                        class="h-[30px] px-3 rounded-lg border border-orange-500/50 bg-white/10 text-4xs font-black uppercase tracking-wider text-white outline-none w-24"
                    >
                    <button @click="finishRename" class="w-5 h-5 rounded bg-orange-500/80 text-white flex items-center justify-center hover:bg-orange-500 transition-all">
                        <span class="material-symbols-outlined text-xs">check</span>
                    </button>
                </div>
            </div>
            <!-- Normal mode (draggable) -->
            <button
                v-else
                :draggable="canManage"
                @click="emit('select', i)"
                @dblclick.stop="startRename(i)"
                @dragstart="onDragStart($event, i)"
                @dragover="onDragOver($event, i)"
                @dragleave="onDragLeave"
                @drop="onDrop($event, i)"
                @dragend="onDragEnd"
                class="shrink-0 group flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-4xs font-black uppercase tracking-wider transition-all"
                :class="[
                    currentIndex === i ? 'bg-white/10 border-white/20 text-white' : 'bg-white/[0.02] border-white/5 text-on-surface-variant hover:text-white hover:border-white/15',
                    dragOverIndex === i && dragIndex !== i ? 'border-orange-500 border-2' : '',
                    dragIndex === i ? 'opacity-40' : '',
                ]"
            >
                <span class="w-4 h-4 rounded-full bg-orange-500/20 text-orange-500 flex items-center justify-center text-5xs font-black">
                    {{ i + 1 }}
                </span>
                {{ step.label }}
                <button
                    v-if="canManage && steps.length > 1"
                    @click.stop="emit('remove', i)"
                    class="opacity-0 group-hover:opacity-100 text-error-dim hover:text-red-400 transition-all ml-1"
                >
                    <span class="material-symbols-outlined text-xs">close</span>
                </button>
            </button>
        </template>

        <!-- Add button (inside scroll, follows tabs) -->
        <button
            v-if="canManage"
            ref="addBtnRef"
            @click="toggleMenu"
            class="add-step-btn shrink-0 w-7 h-7 rounded-lg border border-dashed border-white/10 text-on-surface-variant hover:text-orange-500 hover:border-orange-500/30 transition-all flex items-center justify-center"
        >
            <span class="material-symbols-outlined text-sm">add</span>
        </button>
    </div>

    <!-- Dropdown teleported to body so it's not clipped -->
    <Teleport to="body">
        <div
            v-if="showMenu"
            class="fixed bg-[#1e1e22] border border-white/10 rounded-lg shadow-2xl py-1 min-w-[160px] z-[300]"
            :style="{ left: menuPos.x + 'px', top: menuPos.y + 'px' }"
            @click.stop @mousedown.stop
        >
            <button @click="handleAdd"
                class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-on-surface-variant hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-sm">add</span>
                <span class="text-3xs font-semibold">{{ __('Create New') }}</span>
            </button>
            <button @click="handleCopy"
                class="w-full flex items-center gap-2 px-3 py-1.5 text-left text-on-surface-variant hover:bg-white/5 transition-colors">
                <span class="material-symbols-outlined text-sm">content_copy</span>
                <span class="text-3xs font-semibold">{{ __('Copy Current') }}</span>
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.step-scroll::-webkit-scrollbar {
    height: 6px;
}
.step-scroll::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.03);
    border-radius: 16px;
}
.step-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 16px;
}
.step-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.25);
}
.step-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.15) transparent;
}
.add-step-btn {
    position: sticky;
    right: 0;
    background: #0a0a0c;
    box-shadow: -8px 0 12px -6px #0a0a0c;
    z-index: 2;
}
</style>
