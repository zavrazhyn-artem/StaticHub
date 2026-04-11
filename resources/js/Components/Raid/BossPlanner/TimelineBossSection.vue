<script setup>
const props = defineProps({
    abilities: { type: Array, required: true },
    phases: { type: Array, default: () => [] },
    fightDuration: { type: Number, required: true },
    pxPerSec: { type: Number, required: true },
    panX: { type: Number, required: true },
    contentWidth: { type: Number, required: true },
    viewportWidth: { type: Number, required: true },
    leftWidth: { type: Number, default: 180 },
    rightPadding: { type: Number, default: 24 },
    canManage: { type: Boolean, default: false },
    phaseEditMode: { type: Boolean, default: false },
    focusedCast: { type: Object, default: null },
    hiddenAbilityIds: { type: Array, default: () => [] },
});
const emit = defineEmits(['pan-start', 'wheel', 'click-empty', 'remove-phase', 'focus-cast', 'toggle-hidden-ability', 'start-phase-drag', 'rename-phase']);

const ROW_HEIGHT = 26;
const ICON_SIZE = 20;
const TOP_PADDING = 12;
const BOTTOM_PADDING = 24;
const LEFT_PADDING = 12;

const sectionHeight = () =>
    TOP_PADDING + props.abilities.length * ROW_HEIGHT + BOTTOM_PADDING;

const formatTime = (sec) => `${Math.floor(sec / 60)}:${String(Math.floor(sec) % 60).padStart(2, '0')}`;
const iconUrl = (filename) => `/images/cooldowns/${filename}`;

const gridLines = () => {
    const lines = [];
    const minor = props.pxPerSec < 4 ? 30 : props.pxPerSec < 8 ? 15 : 5;
    const major = props.pxPerSec < 8 ? 60 : 30;
    for (let t = minor; t < props.fightDuration; t += minor) {
        lines.push({ time: t, major: t % major === 0 });
    }
    return lines;
};

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
</script>

<template>
    <svg :width="viewportWidth + leftWidth + rightPadding" :height="sectionHeight()"
        class="select-none block bg-[#0a0a0c]"
        @mousedown="onMouseDown" @wheel.prevent="onWheel" @click="onClick"
        :class="phaseEditMode ? 'cursor-crosshair' : 'cursor-grab'">
        <defs>
            <clipPath id="boss-section-viewport">
                <rect :x="leftWidth" y="0" :width="viewportWidth" :height="sectionHeight()" />
            </clipPath>
        </defs>

        <!-- Panned content (gridlines, casts, phases) -->
        <g clip-path="url(#boss-section-viewport)">
            <g :transform="`translate(${leftWidth - panX}, 0)`">
                <line v-for="g in gridLines()" :key="'g' + g.time"
                    :x1="g.time * pxPerSec" :x2="g.time * pxPerSec"
                    :y1="0" :y2="sectionHeight() - 14"
                    :stroke="g.major ? '#FFFFFF33' : '#FFFFFF11'"
                    stroke-width="0.5" pointer-events="none" />
                <text v-for="g in gridLines().filter(l => l.major)" :key="'l' + g.time"
                    :x="g.time * pxPerSec" :y="sectionHeight() - 4"
                    fill="#FFFFFF66" font-size="10" text-anchor="middle" pointer-events="none">
                    {{ formatTime(g.time) }}
                </text>

                <g v-for="(phase, i) in phases" :key="'p' + i">
                    <line :x1="phase.time * pxPerSec" :x2="phase.time * pxPerSec"
                        :y1="0" :y2="sectionHeight()"
                        stroke="#E74C3C" stroke-width="3"
                        data-interactive
                        :style="canManage ? 'cursor: ew-resize;' : ''"
                        @mousedown.stop="canManage && emit('start-phase-drag', { index: phase.segmentIndex ?? (i + 1), clientX: $event.clientX })"
                        @click.stop
                        @contextmenu.prevent.stop />
                    <text :x="phase.time * pxPerSec + 4" y="12"
                        fill="#E74C3C" font-size="10" font-weight="700"
                        data-interactive
                        :style="canManage ? 'cursor: text;' : ''"
                        @click.stop
                        @dblclick.stop="canManage && emit('rename-phase', phase.segmentIndex ?? (i + 1))">
                        {{ phase.label }}
                    </text>
                </g>

                <g v-for="(ab, i) in abilities" :key="'row' + ab.spell_id">
                    <template v-for="cast in ab.default_casts" :key="cast">
                        <!-- Duration bar (channeled / DoT visualization) -->
                        <rect v-if="ab.duration_sec > 0"
                            :x="cast * pxPerSec + ICON_SIZE / 2"
                            :y="TOP_PADDING + i * ROW_HEIGHT + 4"
                            :width="ab.duration_sec * pxPerSec"
                            :height="ICON_SIZE - 8"
                            :fill="ab.color"
                            opacity="0.25"
                            pointer-events="none" />
                        <text :x="cast * pxPerSec + ICON_SIZE / 2 + 2"
                            :y="TOP_PADDING + i * ROW_HEIGHT + 14"
                            fill="#FFFFFFAA" font-size="10" text-anchor="start" pointer-events="none">
                            {{ formatTime(cast) }}
                        </text>
                        <g data-interactive style="cursor: pointer;"
                            @click.stop="emit('focus-cast', { time: cast, spell_id: ab.spell_id, ability_name: ab.name, color: ab.color })">
                            <image v-if="ab.icon_filename" :href="iconUrl(ab.icon_filename)"
                                :x="cast * pxPerSec - ICON_SIZE / 2"
                                :y="TOP_PADDING + i * ROW_HEIGHT"
                                :width="ICON_SIZE" :height="ICON_SIZE" />
                            <rect :x="cast * pxPerSec - ICON_SIZE / 2"
                                :y="TOP_PADDING + i * ROW_HEIGHT"
                                :width="ICON_SIZE" :height="ICON_SIZE"
                                fill="transparent"
                                :stroke="focusedCast && focusedCast.time === cast && focusedCast.spell_id === ab.spell_id ? '#06B6D4' : ab.color"
                                :stroke-width="focusedCast && focusedCast.time === cast && focusedCast.spell_id === ab.spell_id ? 2.5 : 1.5" />
                        </g>
                    </template>
                </g>

                <!-- Focused cast vertical line (cyan dashed) -->
                <line v-if="focusedCast"
                    :x1="focusedCast.time * pxPerSec" :x2="focusedCast.time * pxPerSec"
                    y1="0" :y2="sectionHeight()"
                    stroke="#06B6D4" stroke-width="1.5" stroke-dasharray="5 4" pointer-events="none" />
            </g>
        </g>

        <!-- Left mask + labels (always visible) -->
        <rect x="0" y="0" :width="leftWidth" :height="sectionHeight()" fill="#0a0a0c" />
        <g v-for="(ab, i) in abilities" :key="'label' + ab.spell_id">
            <rect
                :x="0" :y="TOP_PADDING + i * ROW_HEIGHT - 2"
                :width="leftWidth - 4" :height="ROW_HEIGHT"
                :fill="hiddenAbilityIds.includes(ab.spell_id) ? '#FFFFFF06' : 'transparent'"
                data-interactive
                :style="canManage ? 'cursor: context-menu;' : ''"
                @contextmenu.prevent.stop="canManage && emit('toggle-hidden-ability', ab.spell_id)" />
            <image v-if="ab.icon_filename" :href="iconUrl(ab.icon_filename)"
                :x="leftWidth - LEFT_PADDING - ICON_SIZE"
                :y="TOP_PADDING + i * ROW_HEIGHT"
                :width="ICON_SIZE" :height="ICON_SIZE"
                :opacity="hiddenAbilityIds.includes(ab.spell_id) ? 0.3 : 1"
                pointer-events="none" />
            <rect v-if="ab.icon_filename"
                :x="leftWidth - LEFT_PADDING - ICON_SIZE"
                :y="TOP_PADDING + i * ROW_HEIGHT"
                :width="ICON_SIZE" :height="ICON_SIZE"
                fill="transparent" :stroke="ab.color" stroke-width="1.5"
                :opacity="hiddenAbilityIds.includes(ab.spell_id) ? 0.3 : 1"
                pointer-events="none" />
            <text :x="leftWidth - LEFT_PADDING - ICON_SIZE - 6"
                :y="TOP_PADDING + i * ROW_HEIGHT + 14"
                :fill="ab.color" font-size="11" font-weight="600" text-anchor="end"
                :opacity="hiddenAbilityIds.includes(ab.spell_id) ? 0.3 : 1"
                :text-decoration="hiddenAbilityIds.includes(ab.spell_id) ? 'line-through' : 'none'"
                pointer-events="none">
                {{ ab.name }}
            </text>
        </g>
    </svg>
</template>
