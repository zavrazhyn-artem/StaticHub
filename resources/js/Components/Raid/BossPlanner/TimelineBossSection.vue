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
    // Compact row height — used for bosses with 20+ abilities where the
    // default layout eats too much vertical space above the raid section.
    compact: { type: Boolean, default: false },
});
const emit = defineEmits(['pan-start', 'wheel', 'click-empty', 'remove-phase', 'focus-cast', 'toggle-hidden-ability', 'start-phase-drag', 'rename-phase']);

import { computed } from 'vue';
const ROW_HEIGHT_DEFAULT = 26;
const ROW_HEIGHT_COMPACT = 16;
const ICON_SIZE_DEFAULT = 20;
const ICON_SIZE_COMPACT = 12;
const TOP_PADDING = 12;
const BOTTOM_PADDING = 24;
const LEFT_PADDING = 12;

// Row height / icon size depend on compact mode — use computed refs so
// template expressions stay declarative.
const ROW_HEIGHT = computed(() => props.compact ? ROW_HEIGHT_COMPACT : ROW_HEIGHT_DEFAULT);
const ICON_SIZE = computed(() => props.compact ? ICON_SIZE_COMPACT : ICON_SIZE_DEFAULT);

const sectionHeight = () =>
    TOP_PADDING + props.abilities.length * ROW_HEIGHT.value + BOTTOM_PADDING;

const formatTime = (sec) => `${Math.floor(sec / 60)}:${String(Math.floor(sec) % 60).padStart(2, '0')}`;
const iconUrl = (filename) => `/images/cooldowns/${filename}`;

// Priority drives the icon ring: high = thicker, low = thinner.
const strokeWidthFor = (priority, isFocused) => {
    if (isFocused) return 2.5;
    if (priority === 'high') return 2.25;
    if (priority === 'low') return 1.0;
    return 1.5;
};

// Build a concise tooltip string for an ability cast.
const tooltipFor = (ability, cast) => {
    const parts = [`${ability.name} · ${formatTime(cast)}`];
    if (ability.priority) parts.push(`priority: ${ability.priority}`);
    if (ability.recommended_response?.length) parts.push(`response: ${ability.recommended_response.join(', ')}`);
    if (ability.notes) parts.push('', ability.notes);
    return parts.join('\n');
};

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
                        :stroke="phase.draggable ? '#E74C3C' : '#FFFFFF44'"
                        :stroke-width="phase.draggable ? 3 : 1.5"
                        :stroke-dasharray="phase.draggable ? '0' : '4 3'"
                        data-interactive
                        :style="(canManage && phase.draggable) ? 'cursor: ew-resize;' : 'cursor: default;'"
                        @mousedown.stop="canManage && phase.draggable && emit('start-phase-drag', { index: phase.segmentIndex ?? (i + 1), clientX: $event.clientX })"
                        @click.stop
                        @contextmenu.prevent.stop />
                    <text :x="phase.time * pxPerSec + 4" y="12"
                        :fill="phase.draggable ? '#E74C3C' : '#FFFFFF66'"
                        font-size="10" font-weight="700"
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
                            @click.stop="emit('focus-cast', {
                                time: cast,
                                spell_id: ab.spell_id,
                                name: ab.name,
                                ability_name: ab.name,
                                icon_filename: ab.icon_filename,
                                color: ab.color,
                                school: ab.school,
                                priority: ab.priority,
                                recommended_response: ab.recommended_response,
                                notes: ab.notes,
                                duration_sec: ab.duration_sec,
                            })">
                            <title>{{ tooltipFor(ab, cast) }}</title>
                            <image v-if="ab.icon_filename" :href="iconUrl(ab.icon_filename)"
                                :x="cast * pxPerSec - ICON_SIZE / 2"
                                :y="TOP_PADDING + i * ROW_HEIGHT"
                                :width="ICON_SIZE" :height="ICON_SIZE" />
                            <rect :x="cast * pxPerSec - ICON_SIZE / 2"
                                :y="TOP_PADDING + i * ROW_HEIGHT"
                                :width="ICON_SIZE" :height="ICON_SIZE"
                                fill="transparent"
                                :stroke="focusedCast && focusedCast.time === cast && focusedCast.spell_id === ab.spell_id ? '#06B6D4' : ab.color"
                                :stroke-width="strokeWidthFor(ab.priority, focusedCast && focusedCast.time === cast && focusedCast.spell_id === ab.spell_id)" />
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
