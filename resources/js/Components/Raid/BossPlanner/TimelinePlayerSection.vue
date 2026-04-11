<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    roster: { type: Array, required: true },
    assignments: { type: Array, default: () => [] },
    phases: { type: Array, default: () => [] },
    fightDuration: { type: Number, required: true },
    pxPerSec: { type: Number, required: true },
    panX: { type: Number, required: true },
    contentWidth: { type: Number, required: true },
    viewportWidth: { type: Number, required: true },
    leftWidth: { type: Number, default: 180 },
    rightPadding: { type: Number, default: 24 },
    canManage: { type: Boolean, default: false },
    selectedPlayerId: { type: Number, default: null },
    draggedCd: { type: Object, default: null },
    dragHoverTime: { type: Number, default: null },
    phaseEditMode: { type: Boolean, default: false },
    selectedAssignmentId: { type: String, default: null },
    playerCooldowns: { type: Object, default: () => ({}) },
    focusedCast: { type: Object, default: null },
});
const emit = defineEmits([
    'pan-start', 'wheel', 'click-empty', 'select-player',
    'drop-cd', 'drag-over', 'drag-leave', 'remove-phase',
    'select-assignment', 'move-assignment',
]);

// Layout constants
const COLLAPSED_ROW = 36;
const HEADER_ROW = 32;
const SUB_ROW = 26;
const ICON_SIZE = 22;
const SUB_ICON = 18;
const TOP_PADDING = 8;
const BOTTOM_PADDING = 16;
const LEFT_PADDING = 12;
const AVATAR_SIZE = 26;
const SUB_LABEL_INDENT = 28;

const formatTime = (sec) => `${Math.floor(sec / 60)}:${String(Math.floor(sec) % 60).padStart(2, '0')}`;
const remoteIconUrl = (iconName) => `https://wow.zamimg.com/images/wow/icons/large/${iconName}.jpg`;

const classColor = (cls) => ({
    'Warrior': '#C69B6D', 'Paladin': '#F48CBA', 'Hunter': '#ABD473',
    'Rogue': '#FFF468', 'Priest': '#FFFFFF', 'Death Knight': '#C41F3B',
    'Shaman': '#0070DD', 'Mage': '#3FC7EB', 'Warlock': '#8788EE',
    'Monk': '#00FF98', 'Druid': '#FF7C0A', 'Demon Hunter': '#A330C9', 'Evoker': '#33937F',
})[cls] || '#FFFFFF';

// ─── Row layout (cumulative Y per player, expand selected) ───
const rowLayout = computed(() => {
    const layout = [];
    let y = TOP_PADDING;
    for (const char of props.roster) {
        const expanded = props.selectedPlayerId === char.id;
        const cds = expanded ? (props.playerCooldowns[char.spec_slug] || []) : [];
        const height = expanded
            ? HEADER_ROW + Math.max(1, cds.length) * SUB_ROW
            : COLLAPSED_ROW;
        layout.push({ char, y, height, expanded, cds });
        y += height;
    }
    return layout;
});

const sectionHeight = computed(() => {
    const last = rowLayout.value[rowLayout.value.length - 1];
    return (last ? last.y + last.height : TOP_PADDING) + BOTTOM_PADDING;
});

// Compute Y position for a single assignment icon
const assignmentY = (row, a) => {
    if (!row.expanded) {
        return row.y + (COLLAPSED_ROW - ICON_SIZE) / 2;
    }
    const idx = row.cds.findIndex(cd => cd.spell_id === a.spell_id);
    if (idx === -1) {
        // Spell not in this player's CD list — show in header row
        return row.y + (HEADER_ROW - ICON_SIZE) / 2;
    }
    return row.y + HEADER_ROW + idx * SUB_ROW + (SUB_ROW - ICON_SIZE) / 2;
};

const cooldownForAssignment = (assignment) => {
    const char = props.roster.find(c => c.id === assignment.character_id);
    if (!char || !char.spec_slug) return 0;
    const list = props.playerCooldowns[char.spec_slug] || [];
    const cd = list.find(c => c.spell_id === assignment.spell_id);
    return cd?.cooldown || 0;
};

const assignmentsForPlayer = (charId) =>
    props.assignments.filter(a => a.character_id === charId);

// Conflict: another assignment with same spell on same player within cooldown window
const isInConflict = (a) => {
    const cd = cooldownForAssignment(a);
    if (cd <= 0) return false;
    return props.assignments.some(other => {
        if (other.id === a.id) return false;
        if (other.character_id !== a.character_id) return false;
        if (other.spell_id !== a.spell_id) return false;
        return Math.abs(other.time - a.time) < cd;
    });
};

// Role separators based on row layout
const roleSeparators = computed(() => {
    const seps = [];
    for (let i = 1; i < props.roster.length; i++) {
        if (props.roster[i].assigned_role !== props.roster[i - 1].assigned_role) {
            seps.push(rowLayout.value[i].y);
        }
    }
    return seps;
});

// Gridlines
const gridLines = () => {
    const lines = [];
    const minor = props.pxPerSec < 4 ? 30 : props.pxPerSec < 8 ? 15 : 5;
    const major = props.pxPerSec < 8 ? 60 : 30;
    for (let t = minor; t < props.fightDuration; t += minor) {
        lines.push({ time: t, major: t % major === 0 });
    }
    return lines;
};

// ─── Pan / wheel / click ───
const onMouseDown = (e) => {
    if (props.phaseEditMode) return;
    if (e.target.closest('[data-interactive]')) return;
    if (e.button !== 0 && e.button !== 1) return;
    e.preventDefault();
    emit('pan-start', e.clientX);
};
const onWheel = (e) => {
    e.preventDefault();
    const rect = e.currentTarget.getBoundingClientRect();
    const localX = e.clientX - rect.left - props.leftWidth;
    emit('wheel', { localX, deltaY: e.deltaY });
};
const onClick = (e) => {
    if (!props.phaseEditMode) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const localX = e.clientX - rect.left - props.leftWidth;
    if (localX < 0) return;
    emit('click-empty', localX);
};
const onDragOver = (e) => {
    if (!props.draggedCd) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    const rect = e.currentTarget.getBoundingClientRect();
    const localX = e.clientX - rect.left - props.leftWidth;
    emit('drag-over', localX);
};
const onDragLeave = () => emit('drag-leave');
const onDrop = (e) => {
    e.preventDefault();
    if (!props.draggedCd) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const localX = e.clientX - rect.left - props.leftWidth;
    if (localX < 0) return;
    emit('drop-cd', localX);
};

// ─── Drag-to-move assignment ───
const dragState = ref(null);
const previewTime = ref(null);

const startAssignmentDrag = (a, e) => {
    if (!props.canManage) return;
    e.stopPropagation();
    dragState.value = {
        id: a.id,
        startClientX: e.clientX,
        origTime: a.time,
        moved: false,
    };
    previewTime.value = a.time;
    window.addEventListener('mousemove', onAssignmentDragMove);
    window.addEventListener('mouseup', onAssignmentDragUp);
};
const SNAP_WINDOW_SEC = 20;
const snapToFocus = (t) => {
    if (props.focusedCast && Math.abs(t - props.focusedCast.time) <= SNAP_WINDOW_SEC) {
        return props.focusedCast.time;
    }
    return t;
};

const onAssignmentDragMove = (e) => {
    if (!dragState.value) return;
    const dx = e.clientX - dragState.value.startClientX;
    if (Math.abs(dx) > 3) dragState.value.moved = true;
    const deltaSec = Math.round(dx / props.pxPerSec);
    let newTime = dragState.value.origTime + deltaSec;
    if (newTime < 0) newTime = 0;
    if (newTime > props.fightDuration) newTime = props.fightDuration;
    newTime = snapToFocus(newTime);
    previewTime.value = newTime;
    // Live commit so the edit panel time field updates as the user drags
    if (dragState.value.moved && newTime !== dragState.value.lastEmittedTime) {
        emit('move-assignment', { id: dragState.value.id, time: newTime });
        dragState.value.lastEmittedTime = newTime;
    }
};
const onAssignmentDragUp = () => {
    window.removeEventListener('mousemove', onAssignmentDragMove);
    window.removeEventListener('mouseup', onAssignmentDragUp);
    if (!dragState.value) return;
    const { id, moved } = dragState.value;
    if (!moved) emit('select-assignment', id);
    // If moved: the latest time was already emitted in the last mousemove
    dragState.value = null;
    previewTime.value = null;
};

const isDraggingAssignment = (id) => dragState.value?.id === id;
const renderTimeFor = (a) => isDraggingAssignment(a.id) ? previewTime.value : a.time;
</script>

<template>
    <svg :width="viewportWidth + leftWidth + rightPadding" :height="sectionHeight"
        class="select-none block bg-[#0a0a0c]"
        @mousedown="onMouseDown" @wheel.prevent="onWheel" @click="onClick"
        @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop"
        :class="phaseEditMode ? 'cursor-crosshair' : 'cursor-grab'">
        <defs>
            <clipPath id="player-section-viewport">
                <rect :x="leftWidth" y="0" :width="viewportWidth" :height="sectionHeight" />
            </clipPath>
        </defs>

        <!-- Panned content -->
        <g clip-path="url(#player-section-viewport)">
            <g :transform="`translate(${leftWidth - panX}, 0)`">
                <!-- Gridlines -->
                <line v-for="g in gridLines()" :key="'g' + g.time"
                    :x1="g.time * pxPerSec" :x2="g.time * pxPerSec"
                    :y1="0" :y2="sectionHeight"
                    :stroke="g.major ? '#FFFFFF22' : '#FFFFFF0A'"
                    stroke-width="0.5" pointer-events="none" />

                <!-- Phase lines -->
                <line v-for="(phase, i) in phases" :key="'p' + i"
                    :x1="phase.time * pxPerSec" :x2="phase.time * pxPerSec"
                    :y1="0" :y2="sectionHeight"
                    stroke="#E74C3C" stroke-width="3" pointer-events="none" />

                <!-- Focused cast line -->
                <line v-if="focusedCast"
                    :x1="focusedCast.time * pxPerSec" :x2="focusedCast.time * pxPerSec"
                    :y1="0" :y2="sectionHeight"
                    stroke="#06B6D4" stroke-width="1.5" stroke-dasharray="5 4" pointer-events="none" />

                <!-- Per-row backgrounds -->
                <g v-for="(row, i) in rowLayout" :key="'rowbg' + row.char.id">
                    <!-- Selected expansion: stronger background -->
                    <rect v-if="row.expanded"
                        :x="0" :y="row.y"
                        :width="contentWidth" :height="row.height - 2"
                        fill="#FCD34D08" pointer-events="none" />
                    <!-- Header row underline when expanded -->
                    <line v-if="row.expanded"
                        :x1="0" :x2="contentWidth"
                        :y1="row.y + HEADER_ROW" :y2="row.y + HEADER_ROW"
                        stroke="#FCD34D33" stroke-width="0.5" pointer-events="none" />
                    <!-- Sub-row striping when expanded -->
                    <rect v-for="(cd, j) in row.cds" :key="'sub' + cd.spell_id"
                        v-show="row.expanded"
                        :x="0" :y="row.y + HEADER_ROW + j * SUB_ROW"
                        :width="contentWidth" :height="SUB_ROW - 1"
                        :fill="j % 2 === 0 ? '#FFFFFF03' : 'transparent'" pointer-events="none" />
                    <!-- Collapsed zebra -->
                    <rect v-if="!row.expanded"
                        :x="0" :y="row.y"
                        :width="contentWidth" :height="row.height - 2"
                        :fill="i % 2 === 0 ? '#FFFFFF03' : 'transparent'" pointer-events="none" />
                </g>

                <!-- Drag hover line -->
                <g v-if="dragHoverTime !== null && draggedCd">
                    <line :x1="dragHoverTime * pxPerSec" :x2="dragHoverTime * pxPerSec"
                        y1="0" :y2="sectionHeight"
                        stroke="#FCD34D" stroke-width="1.5" stroke-dasharray="4 4" pointer-events="none" />
                    <text :x="dragHoverTime * pxPerSec" y="14"
                        fill="#FCD34D" font-size="11" font-weight="700" text-anchor="middle" pointer-events="none">
                        {{ formatTime(dragHoverTime) }}
                    </text>
                </g>

                <!-- Cooldown recharge bars -->
                <g v-for="row in rowLayout" :key="'cd' + row.char.id">
                    <template v-for="a in assignmentsForPlayer(row.char.id)" :key="'bar' + a.id">
                        <rect v-if="cooldownForAssignment(a) > 0"
                            :x="renderTimeFor(a) * pxPerSec"
                            :y="assignmentY(row, a) + 2"
                            :width="cooldownForAssignment(a) * pxPerSec"
                            :height="ICON_SIZE - 4"
                            :fill="isInConflict(a) ? '#EF444433' : classColor(row.char.playable_class) + '22'"
                            :stroke="isInConflict(a) ? '#EF4444AA' : classColor(row.char.playable_class) + '55'"
                            stroke-width="0.5"
                            rx="2"
                            pointer-events="none" />
                    </template>
                </g>

                <!-- Assignments (draggable) -->
                <g v-for="row in rowLayout" :key="'a' + row.char.id">
                    <g v-for="a in assignmentsForPlayer(row.char.id)" :key="a.id" data-interactive>
                        <image :href="remoteIconUrl(a.icon)"
                            :x="renderTimeFor(a) * pxPerSec - ICON_SIZE / 2"
                            :y="assignmentY(row, a)"
                            :width="ICON_SIZE" :height="ICON_SIZE"
                            :style="canManage ? 'cursor: grab;' : ''"
                            @mousedown.stop="startAssignmentDrag(a, $event)">
                            <title>{{ a.spell_name }} · {{ formatTime(renderTimeFor(a)) }} — click to edit, drag to move</title>
                        </image>
                        <rect
                            :x="renderTimeFor(a) * pxPerSec - ICON_SIZE / 2 - (selectedAssignmentId === a.id ? 2 : 0)"
                            :y="assignmentY(row, a) - (selectedAssignmentId === a.id ? 2 : 0)"
                            :width="ICON_SIZE + (selectedAssignmentId === a.id ? 4 : 0)"
                            :height="ICON_SIZE + (selectedAssignmentId === a.id ? 4 : 0)"
                            fill="transparent"
                            :stroke="isInConflict(a) ? '#EF4444' : (selectedAssignmentId === a.id ? '#FCD34D' : classColor(row.char.playable_class))"
                            :stroke-width="(isInConflict(a) || selectedAssignmentId === a.id) ? 2.5 : 2"
                            pointer-events="none" />
                        <text v-if="isDraggingAssignment(a.id)"
                            :x="renderTimeFor(a) * pxPerSec"
                            :y="assignmentY(row, a) - 4"
                            fill="#FCD34D" font-size="10" font-weight="700" text-anchor="middle" pointer-events="none">
                            {{ formatTime(renderTimeFor(a)) }}
                        </text>
                    </g>
                </g>
            </g>
        </g>

        <!-- Left mask -->
        <rect x="0" y="0" :width="leftWidth" :height="sectionHeight" fill="#0a0a0c" />

        <!-- Player labels (clickable to expand/collapse) -->
        <g v-for="row in rowLayout" :key="'label' + row.char.id">
            <!-- Header row (player name + avatar) — height = HEADER_ROW or COLLAPSED_ROW -->
            <rect
                :x="0" :y="row.y"
                :width="leftWidth - 4"
                :height="(row.expanded ? HEADER_ROW : COLLAPSED_ROW) - 2"
                :fill="row.expanded ? '#FCD34D14' : 'transparent'"
                rx="4"
                data-interactive
                style="cursor: pointer;"
                @click.stop="emit('select-player', row.char.id)" />
            <image v-if="row.char.avatar_url" :href="row.char.avatar_url"
                :x="leftWidth - LEFT_PADDING - AVATAR_SIZE"
                :y="row.y + ((row.expanded ? HEADER_ROW : COLLAPSED_ROW) - AVATAR_SIZE) / 2"
                :width="AVATAR_SIZE" :height="AVATAR_SIZE"
                preserveAspectRatio="xMidYMid slice"
                pointer-events="none" />
            <rect v-if="row.char.avatar_url"
                :x="leftWidth - LEFT_PADDING - AVATAR_SIZE"
                :y="row.y + ((row.expanded ? HEADER_ROW : COLLAPSED_ROW) - AVATAR_SIZE) / 2"
                :width="AVATAR_SIZE" :height="AVATAR_SIZE"
                rx="4"
                fill="transparent" :stroke="classColor(row.char.playable_class)" stroke-width="1.5"
                pointer-events="none" />
            <text
                :x="leftWidth - LEFT_PADDING - AVATAR_SIZE - 6"
                :y="row.y + (row.expanded ? HEADER_ROW : COLLAPSED_ROW) / 2 + 4"
                :fill="classColor(row.char.playable_class)"
                font-size="11" font-weight="600" text-anchor="end" pointer-events="none">
                {{ row.char.name }}
            </text>

            <!-- Sub-row labels: each CD as a small icon on the left -->
            <g v-if="row.expanded">
                <template v-for="(cd, j) in row.cds" :key="'sub-label' + cd.spell_id">
                    <image :href="remoteIconUrl(cd.icon)"
                        :x="leftWidth - LEFT_PADDING - SUB_ICON"
                        :y="row.y + HEADER_ROW + j * SUB_ROW + (SUB_ROW - SUB_ICON) / 2"
                        :width="SUB_ICON" :height="SUB_ICON"
                        pointer-events="none" />
                    <rect
                        :x="leftWidth - LEFT_PADDING - SUB_ICON"
                        :y="row.y + HEADER_ROW + j * SUB_ROW + (SUB_ROW - SUB_ICON) / 2"
                        :width="SUB_ICON" :height="SUB_ICON"
                        fill="transparent" stroke="#FFFFFF22" stroke-width="0.5"
                        pointer-events="none" />
                    <text
                        :x="leftWidth - LEFT_PADDING - SUB_ICON - 6"
                        :y="row.y + HEADER_ROW + j * SUB_ROW + SUB_ROW / 2 + 3"
                        fill="#FFFFFFAA"
                        font-size="9" text-anchor="end" pointer-events="none">
                        {{ cd.name }}
                    </text>
                </template>
            </g>
        </g>

        <!-- Role separators -->
        <g>
            <line v-for="(yPos, i) in roleSeparators" :key="'sep' + i"
                :x1="0" :x2="viewportWidth + leftWidth"
                :y1="yPos - 1" :y2="yPos - 1"
                stroke="#FFFFFF22" stroke-width="1" stroke-dasharray="3 3"
                pointer-events="none" />
        </g>
    </svg>
</template>
