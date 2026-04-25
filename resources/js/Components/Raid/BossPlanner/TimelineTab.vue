<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import { useTimelineViewport } from '@/composables/useTimelineViewport';
import TimelineBossSection from './TimelineBossSection.vue';
import TimelinePlayerSection from './TimelinePlayerSection.vue';
import PlayerCdPanel from './PlayerCdPanel.vue';
import AssignmentEditPanel from './AssignmentEditPanel.vue';
import ConditionalAbilitiesStrip from './ConditionalAbilitiesStrip.vue';
import AbilityDetailPanel from './AbilityDetailPanel.vue';
import { buildMrtNote } from '@/utils/mrtNote';
const { __ } = useTranslation();

const props = defineProps({
    timeline: { type: Object, default: () => ({}) },
    encounter: { type: Object, default: () => ({}) },
    difficulty: { type: String, default: 'mythic' },
    bossAbilities: { type: Array, default: () => [] },
    defaultPhaseSegments: { type: Array, default: () => [] },
    conditionalAbilities: { type: Array, default: () => [] },
    roster: { type: Array, default: () => [] },
    playerCooldowns: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    cooldownToggleBase: { type: String, default: '' },
    csrfToken: { type: String, default: '' },
    // Active editor tab (cooldowns|map|...). Floating panels that <Teleport>
    // to body need this to know when to hide — parent's v-show doesn't reach
    // teleported children, so we gate their display with v-show inside.
    activeTab: { type: String, default: 'cooldowns' },
    // Logged-in user's character IDs in this roster. Drives the "Show Me"
    // highlight that pulses the user's rows.
    myCharacterIds: { type: Array, default: () => [] },
    // Show Me toggle owned by the page header — one button controls both the
    // map and the cooldowns tab highlights.
    showingMe: { type: Boolean, default: false },
});
const emit = defineEmits(['update', 'toggle-character-cooldown']);

// ─── Phase segments: read from timeline, fall back to encounter defaults ───
// Each segment: { id, phase_id, name, start, duration, seed_duration, is_intermission }
// We always return freshly constructed objects so downstream computeds always
// see new references when anything inside phase_segments changes — otherwise
// a shared array reference could mask per-element mutations during drag.
const effectiveSegments = computed(() => {
    const saved = props.timeline?.phase_segments;
    if (Array.isArray(saved) && saved.length > 0) {
        return saved.map(s => ({
            id: s.id,
            phase_id: s.phase_id,
            name: s.name,
            start: s.start,
            duration: s.duration,
            seed_duration: s.seed_duration ?? s.duration,
            is_intermission: s.is_intermission,
        }));
    }
    return (props.defaultPhaseSegments || []).map(s => ({
        id: s.segment_id,
        phase_id: s.phase_id,
        name: s.phase_name,
        start: s.seed_start,
        duration: s.seed_duration,
        seed_duration: s.seed_duration,
        is_intermission: s.is_intermission,
    }));
});

// ─── Cast derivation within a phase ───
// Casts are stored phase-relative (offset seconds from the phase's start).
// - Phase SHRUNK below seed duration → drop casts past the new end (they hide).
// - Phase at seed duration → return seed offsets unchanged.
// - Phase STRETCHED past seed duration → detect the observed cast pattern and
//   extrapolate forwards so new casts follow the same rhythm.
//
// Cluster detection: look for the smallest clusterSize K such that
// offsets[i+K] − offsets[i] is constant for every i. That constant is the
// cycle period, and the first K offsets are the base cluster we replicate.
// Handles paired-burst abilities (e.g., Oblivion's Wrath fires twice 18s
// apart, cycle repeats every 186s) and regular intervals alike.
const detectClusterPeriod = (sorted) => {
    const n = sorted.length;
    if (n < 2) return null;
    for (let k = 1; k <= Math.floor(n / 2); k++) {
        if (n < k * 2) break;
        let period = null;
        let valid = true;
        for (let i = 0; i + k < n; i++) {
            const diff = sorted[i + k] - sorted[i];
            if (period === null) period = diff;
            else if (Math.abs(diff - period) > 2) { valid = false; break; }
        }
        if (valid && period > 0) return { clusterSize: k, period };
    }
    return null;
};

const deriveSegmentCasts = (seedOffsets, seedDuration, currentDuration) => {
    if (!seedOffsets || seedOffsets.length === 0) return [];
    const sorted = [...seedOffsets].sort((a, b) => a - b);

    if (currentDuration < seedDuration) {
        return sorted.filter(o => o < currentDuration);
    }
    if (currentDuration === seedDuration || sorted.length < 2) return sorted;

    const detection = detectClusterPeriod(sorted);
    if (detection) {
        const { clusterSize, period } = detection;
        const base = sorted.slice(0, clusterSize);
        const extended = [...sorted];
        let clusterStart = sorted[sorted.length - clusterSize];
        let guard = 100;
        while (guard-- > 0) {
            clusterStart += period;
            if (clusterStart >= currentDuration) break;
            for (let k = 0; k < clusterSize; k++) {
                const t = clusterStart + (base[k] - base[0]);
                if (t < currentDuration) extended.push(t);
            }
        }
        extended.sort((a, b) => a - b);
        return extended;
    }

    // Fallback — no clean cluster detected. Use median gap from the last
    // cast as a rough extrapolator. Gate with spanRatio so short scripted
    // burst abilities don't sprout ghost casts.
    const first = sorted[0];
    const last = sorted[sorted.length - 1];
    const spanRatio = (last - first) / Math.max(1, seedDuration - first);
    if (spanRatio < 0.5) return sorted;

    const gaps = [];
    for (let i = 1; i < sorted.length; i++) gaps.push(sorted[i] - sorted[i - 1]);
    gaps.sort((a, b) => a - b);
    const medianGap = gaps[Math.floor(gaps.length / 2)];
    if (medianGap <= 0) return sorted;

    const extended = [...sorted];
    let next = last + medianGap;
    let guard = 200;
    while (next < currentDuration && guard-- > 0) {
        extended.push(next);
        next += medianGap;
    }
    return extended;
};

const derivedBossAbilities = computed(() => {
    const segs = effectiveSegments.value;
    return props.bossAbilities.map(ab => {
        const bySeg = {};
        for (const c of ab.default_casts || []) {
            // Flat-legacy fallback: treat plain numbers as s1 offsets
            if (typeof c === 'number') {
                if (!bySeg.s1) bySeg.s1 = [];
                bySeg.s1.push(c);
                continue;
            }
            if (!c || !c.segment_id) continue;
            if (!bySeg[c.segment_id]) bySeg[c.segment_id] = [];
            bySeg[c.segment_id].push(c.offset || 0);
        }
        const absolute = [];
        for (const seg of segs) {
            const seedOffsets = bySeg[seg.id] || [];
            if (seedOffsets.length === 0) continue;
            const derived = deriveSegmentCasts(seedOffsets, seg.seed_duration, seg.duration);
            for (const offset of derived) {
                absolute.push(seg.start + offset);
            }
        }
        absolute.sort((a, b) => a - b);
        return { ...ab, default_casts: absolute };
    });
});

// Derived phase boundaries (skip the first — it's at time 0, not a boundary).
// Enriches each marker with its trigger kind so the timeline can style
// HP-gated (draggable) vs time-scripted (locked) boundaries differently.
const derivedPhaseMarkers = computed(() =>
    effectiveSegments.value.slice(1).map((s, idxFromOne) => {
        const segmentIndex = idxFromOne + 1;
        const trigger = props.defaultPhaseSegments?.[segmentIndex]?.trigger || {};
        return {
            time: s.start,
            label: s.name,
            segmentIndex,
            is_intermission: s.is_intermission,
            draggable: trigger.type === 'hp_below',
            trigger_type: trigger.type || 'time_from_pull',
        };
    })
);

const LEFT_WIDTH = 180;
const RIGHT_PADDING = 24;

// Fight duration is derived from segments (sum of durations)
const fightDurationRef = computed(() => {
    const segs = effectiveSegments.value;
    if (segs.length === 0) return props.timeline?.fight_duration || 360;
    const last = segs[segs.length - 1];
    return last.start + last.duration;
});
const assignments = computed(() => props.timeline?.assignments || []);
const hiddenAbilities = computed(() => new Set(props.timeline?.hidden_abilities || []));

// When showHidden is on, the hidden list is rendered but visually dimmed.
const showHiddenAbilities = ref(false);

// Focus mode now does exactly two things: hide every other player's row and
// force boss abilities into compact mode. The boss-ability list itself is not
// filtered — the planner still wants to see the full incoming boss timeline
// while focusing on one player's CDs underneath.
const focusedAbilitySpellIds = computed(() => null);
const effectiveCompactMode = computed(() => compactMode.value || (focusMode.value && !!selectedPlayer.value));
const rosterForDisplay = computed(() => {
    if (focusMode.value && selectedPlayer.value) {
        return sortedRoster.value.filter(c => c.id === selectedPlayer.value.id);
    }
    return sortedRoster.value;
});

const visibleAbilities = computed(() => {
    const base = derivedBossAbilities.value;
    let list = base;
    if (!showHiddenAbilities.value) {
        list = list.filter(a => !hiddenAbilities.value.has(a.spell_id));
    }
    // Priority filter
    list = list.filter(a => priorityFilters.value[a.priority] !== false);
    // Focus mode
    if (focusedAbilitySpellIds.value) {
        list = list.filter(a => focusedAbilitySpellIds.value.has(a.spell_id));
    }
    return list;
});
const hiddenAbilityIdArr = computed(() => [...hiddenAbilities.value]);

const toggleHiddenAbility = (spellId) => {
    if (!props.canManage) return;
    const next = new Set(hiddenAbilities.value);
    if (next.has(spellId)) next.delete(spellId); else next.add(spellId);
    emit('update', { ...props.timeline, hidden_abilities: [...next] });
};

// CD type filter (session-local, not persisted) — read by PlayerCdPanel.
const cdTypeFilters = ref({ personal: true, external: true, raid: true, utility: true });

// ─── Boss-ability priority filter (for bosses with 20+ abilities) ──────
// Toggle H/M/L in the toolbar; abilities with priority not in the selected
// set are hidden from the timeline. All three enabled by default.
const priorityFilters = ref({ high: true, medium: true, low: true });
const togglePriority = (level) => {
    priorityFilters.value = { ...priorityFilters.value, [level]: !priorityFilters.value[level] };
};

// ─── Focus mode ───────────────────────────────────────────────────────
// When a player is selected, focus mode narrows the boss-ability timeline to
// only abilities that player has an assignment on, OR whose recommended_response
// matches their role. Exits on second click on the same player.
const focusMode = ref(false);
const toggleFocusMode = () => { focusMode.value = !focusMode.value; };

// ─── Compact / expanded row mode ──────────────────────────────────────
// Compact halves boss-ability row height for bosses like Midnight Falls.
const compactMode = ref(false);
const toggleCompactMode = () => { compactMode.value = !compactMode.value; };

// ─── Show Me flag is owned by BossPlannerPage (one button drives both
//     tabs). We receive it as a prop and forward to the player section.

const ROLE_ORDER = { tank: 0, heal: 1, mdps: 2, rdps: 2 };
const sortedRoster = computed(() =>
    [...props.roster].sort((a, b) => {
        const ra = ROLE_ORDER[a.assigned_role] ?? 9;
        const rb = ROLE_ORDER[b.assigned_role] ?? 9;
        if (ra !== rb) return ra - rb;
        return (a.name || '').localeCompare(b.name || '');
    })
);

// Cleanup orphan assignments — characters not in current roster
watch([assignments, sortedRoster], ([asgs, roster]) => {
    if (!props.canManage) return;
    const ids = new Set(roster.map(c => c.id));
    const cleaned = asgs.filter(a => ids.has(a.character_id));
    if (cleaned.length !== asgs.length) {
        emit('update', { ...props.timeline, assignments: cleaned });
    }
}, { immediate: false });

// Container & viewport (shared between boss + player sections)
const containerRef = ref(null);
const viewport = useTimelineViewport({
    fightDuration: fightDurationRef,
    leftWidth: LEFT_WIDTH,
    rightPadding: RIGHT_PADDING,
});
const updateContainerWidth = () => {
    if (containerRef.value) viewport.setContainerWidth(containerRef.value.clientWidth);
};
let resizeObserver = null;
const onKeyDown = (e) => {
    if (e.target?.tagName === 'INPUT' || e.target?.tagName === 'TEXTAREA') return;
    if (e.key === 'Escape') {
        if (focusedCast.value) { e.preventDefault(); focusedCast.value = null; return; }
        if (selectedAssignmentId.value) { e.preventDefault(); selectedAssignmentId.value = null; return; }
    }
    if (!selectedAssignmentId.value) return;
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault();
    const a = selectedAssignment.value;
    if (!a) return;
    const delta = e.key === 'ArrowLeft' ? -1 : 1;
    let next = a.time + delta;
    if (next < 0) next = 0;
    if (next > fightDurationRef.value) next = fightDurationRef.value;
    if (next === a.time) return;
    moveAssignment({ id: a.id, time: next });
};
onMounted(() => {
    updateContainerWidth();
    if (containerRef.value && typeof ResizeObserver !== 'undefined') {
        resizeObserver = new ResizeObserver(() => updateContainerWidth());
        resizeObserver.observe(containerRef.value);
    }
    window.addEventListener('resize', updateContainerWidth);
    window.addEventListener('keydown', onKeyDown);
});
onUnmounted(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', updateContainerWidth);
    window.removeEventListener('keydown', onKeyDown);
});

// Pan handler bridge: section emits clientX, we drive viewport.startPan
let activePanMove = null;
const onPanStart = (clientX) => { activePanMove = viewport.startPan(clientX); };
const onPanWindowMove = (e) => { if (activePanMove) activePanMove(e.clientX); };
const onPanWindowUp = () => { activePanMove = null; viewport.stopPan(); };
onMounted(() => { window.addEventListener('mousemove', onPanWindowMove); window.addEventListener('mouseup', onPanWindowUp); });
onUnmounted(() => { window.removeEventListener('mousemove', onPanWindowMove); window.removeEventListener('mouseup', onPanWindowUp); });

const onWheel = ({ localX, deltaY }) => viewport.zoomAt(localX, deltaY);

// Phase editing no longer creates NEW phases at click — segments are fixed from
// the seed. Officer only resizes existing segments by dragging their boundaries.
const phaseEditMode = ref(false);
const onClickEmpty = () => {
    // No-op: adding phases manually would break cast attribution. Segments come
    // from the WCL seed and can only be stretched/shrunk.
};

// Segment boundary drag — moving s(i).start increases s(i-1).duration and shifts
// all subsequent segments by the same delta. Minimum segment duration = 5s.
const MIN_SEGMENT_DURATION = 5;
let segmentDragState = null;
const onStartPhaseDrag = ({ index, clientX }) => {
    if (!props.canManage) return;
    // `index` here is the segmentIndex from derivedPhaseMarkers (real index in segments)
    if (index <= 0) return;
    // Only HP-gated phases (i1/i2 intermissions on Crown of the Cosmos and
    // Belo'ren) are user-draggable. Everything else is time-scripted and
    // locked to its seed timing.
    const trigger = props.defaultPhaseSegments?.[index]?.trigger || {};
    if (trigger.type !== 'hp_below') return;
    segmentDragState = {
        index,
        startClientX: clientX,
        origSegments: JSON.parse(JSON.stringify(effectiveSegments.value)),
    };
    window.addEventListener('mousemove', onSegmentDragMove);
    window.addEventListener('mouseup', onSegmentDragUp);
};
const onSegmentDragMove = (e) => {
    if (!segmentDragState) return;
    const { index, startClientX, origSegments } = segmentDragState;
    const dx = e.clientX - startClientX;
    const deltaSec = Math.round(dx / viewport.pxPerSec.value);
    const prev = origSegments[index - 1];
    const phaseTrigger = props.defaultPhaseSegments?.[index]?.trigger || {};
    // Clamp newStart within the phase's configured min_time/max_time (absolute
    // seconds from pull). Falls back to a 5s floor if bounds aren't set.
    const minStart = Math.max(
        prev.start + MIN_SEGMENT_DURATION,
        phaseTrigger.min_time ?? 0,
    );
    const maxStart = phaseTrigger.max_time ?? Number.POSITIVE_INFINITY;
    let newStart = origSegments[index].start + deltaSec;
    if (newStart < minStart) newStart = minStart;
    if (newStart > maxStart) newStart = maxStart;
    const realDelta = newStart - origSegments[index].start;
    const next = origSegments.map(s => ({ ...s }));
    next[index - 1].duration = newStart - next[index - 1].start;
    for (let j = index; j < next.length; j++) {
        next[j].start = origSegments[j].start + realDelta;
    }
    emit('update', { ...props.timeline, phase_segments: next });
};
const onSegmentDragUp = () => {
    window.removeEventListener('mousemove', onSegmentDragMove);
    window.removeEventListener('mouseup', onSegmentDragUp);
    segmentDragState = null;
};

// Legacy phase remove / rename now operate on segment names only (segment can't
// be deleted because casts depend on it).
const removePhase = () => { /* no-op — segments are structural */ };
const renamePhase = (segmentIndex) => {
    if (!props.canManage) return;
    const seg = effectiveSegments.value[segmentIndex];
    if (!seg) return;
    const label = window.prompt('Phase name:', seg.name);
    if (!label) return;
    const next = effectiveSegments.value.map((s, i) => i === segmentIndex ? { ...s, name: label.trim() } : s);
    emit('update', { ...props.timeline, phase_segments: next });
};

// Selected player (single floating panel)
const selectedPlayerId = ref(null);
const selectedPlayer = computed(() => sortedRoster.value.find(c => c.id === selectedPlayerId.value) || null);
const selectedPlayerCds = computed(() => {
    const slug = selectedPlayer.value?.spec_slug;
    return slug ? (props.playerCooldowns[slug] || []) : [];
});

// Local override map — Vue prop proxies are shallow, so mutating
// roster[i].disabled_cd_spell_ids does not trigger reactivity. Keep our own
// reactive copy that overlays on top of the prop value.
const localDisabledIds = ref({}); // { [character_id]: number[] }
const disabledIdsForChar = (char) => {
    if (!char) return [];
    const local = localDisabledIds.value[char.id];
    if (local !== undefined) return local;
    return char.disabled_cd_spell_ids || [];
};
const selectedPlayerDisabledIds = computed(() => disabledIdsForChar(selectedPlayer.value));

const selectPlayer = (id) => {
    selectedPlayerId.value = (selectedPlayerId.value === id) ? null : id;
};

const onToggleCharacterCd = async ({ spell_id, enabled }) => {
    if (!selectedPlayer.value || !props.canManage) return;
    const char = selectedPlayer.value;
    const current = new Set(disabledIdsForChar(char));
    if (enabled) current.delete(spell_id); else current.add(spell_id);
    localDisabledIds.value = { ...localDisabledIds.value, [char.id]: [...current] };

    if (!props.cooldownToggleBase) return;
    try {
        await fetch(`${props.cooldownToggleBase}/${char.id}/cooldown-toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ spell_id, enabled }),
        });
    } catch (e) {
        console.error('Failed to toggle cooldown:', e);
    }
};

// Focused boss cast — snap target for drag-drop
const focusedCast = ref(null); // { time, spell_id, ability_name, color }
const onFocusCast = (cast) => {
    if (focusedCast.value
        && focusedCast.value.time === cast.time
        && focusedCast.value.spell_id === cast.spell_id) {
        focusedCast.value = null;
    } else {
        focusedCast.value = cast;
    }
};
const clearFocus = () => { focusedCast.value = null; };

// Drag CD from floating panel → drop on player section
const SNAP_WINDOW_SEC = 20;
const draggedCd = ref(null);
const dragHoverTime = ref(null);
const onPanelDragStart = (cd) => { draggedCd.value = cd; };

// If focused cast is within ±SNAP_WINDOW_SEC of cursor time, snap to it.
const resolveDropTime = (localX) => {
    const cursor = viewport.xToTime(localX);
    if (focusedCast.value && Math.abs(cursor - focusedCast.value.time) <= SNAP_WINDOW_SEC) {
        return focusedCast.value.time;
    }
    return cursor;
};

const onPlayerDragOver = (localX) => {
    if (localX < 0) { dragHoverTime.value = null; return; }
    dragHoverTime.value = resolveDropTime(localX);
};
const onPlayerDragLeave = () => { dragHoverTime.value = null; };
const onPlayerDrop = (localX) => {
    if (!draggedCd.value || !selectedPlayer.value || !props.canManage) return;
    if (localX < 0) return;
    const time = resolveDropTime(localX);
    emit('update', {
        ...props.timeline,
        assignments: [...assignments.value, {
            id: 'a' + Date.now() + Math.random().toString(36).slice(2, 6),
            time,
            character_id: selectedPlayer.value.id,
            spell_id: draggedCd.value.spell_id,
            icon: draggedCd.value.icon,
            spell_name: draggedCd.value.name,
            character_name: selectedPlayer.value.name,
            class: selectedPlayer.value.playable_class,
            note: '',
        }],
    });
    draggedCd.value = null;
    dragHoverTime.value = null;
};
const removeAssignment = (id) => {
    if (!props.canManage) return;
    if (selectedAssignmentId.value === id) selectedAssignmentId.value = null;
    emit('update', { ...props.timeline, assignments: assignments.value.filter(a => a.id !== id) });
};

// Selected assignment for edit popup
const selectedAssignmentId = ref(null);
const selectedAssignment = computed(() => assignments.value.find(a => a.id === selectedAssignmentId.value) || null);
const selectAssignment = (id) => {
    selectedAssignmentId.value = (selectedAssignmentId.value === id) ? null : id;
};
const moveAssignment = ({ id, time }) => {
    if (!props.canManage) return;
    emit('update', {
        ...props.timeline,
        assignments: assignments.value.map(a => a.id === id ? { ...a, time } : a),
    });
};
const updateAssignment = (next) => {
    if (!props.canManage) return;
    emit('update', {
        ...props.timeline,
        assignments: assignments.value.map(a => a.id === next.id ? next : a),
    });
};
const cooldownForSelected = computed(() => {
    if (!selectedAssignment.value) return 0;
    const char = sortedRoster.value.find(c => c.id === selectedAssignment.value.character_id);
    if (!char || !char.spec_slug) return 0;
    const list = props.playerCooldowns[char.spec_slug] || [];
    const cd = list.find(c => c.spell_id === selectedAssignment.value.spell_id);
    return cd?.cooldown || 0;
});

// Header controls — the Duration input stretches / shrinks the LAST segment so
// the overall fight time matches what the user typed. Segments before the last
// keep their current durations and positions.
const updateDuration = (e) => {
    const str = String(e.target.value || '').trim();
    let total = 0;
    if (str.includes(':')) {
        const [m, s] = str.split(':').map(Number);
        total = (m || 0) * 60 + (s || 0);
    } else total = Number(str) || 0;
    if (total <= 0 || total > 3600) return;

    const segs = effectiveSegments.value;
    if (segs.length === 0) {
        emit('update', { ...props.timeline, fight_duration: total });
        return;
    }
    const last = segs[segs.length - 1];
    const newLastDuration = Math.max(MIN_SEGMENT_DURATION, total - last.start);
    const next = segs.map((s, i) => i === segs.length - 1 ? { ...s, duration: newLastDuration } : s);
    emit('update', { ...props.timeline, phase_segments: next });
};
const formatTime = (sec) => `${Math.floor(sec / 60)}:${String(Math.floor(sec) % 60).padStart(2, '0')}`;

// Export the current plan as a MethodRaidTools / NSRT note and copy to
// clipboard. Raid lead pastes via `/rt note` in-game.
const mrtExportState = ref('idle'); // 'idle' | 'copied' | 'error'
const exportMrtNote = async () => {
    const note = buildMrtNote({
        encounter: props.encounter,
        difficulty: props.difficulty,
        segments: effectiveSegments.value,
        assignments: assignments.value,
        bossAbilities: props.bossAbilities,
    });
    try {
        await navigator.clipboard.writeText(note);
        mrtExportState.value = 'copied';
        setTimeout(() => { mrtExportState.value = 'idle'; }, 2000);
    } catch (e) {
        console.error('Clipboard write failed:', e);
        mrtExportState.value = 'error';
        setTimeout(() => { mrtExportState.value = 'idle'; }, 2000);
    }
};
</script>

<template>
    <div class="h-full flex flex-col bg-[#0a0a0c]">
        <!-- Top toolbar -->
        <div class="shrink-0 flex items-center gap-3 px-4 py-2 border-b border-white/5 bg-[#121214]">
            <div class="flex items-center gap-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/50">{{ __('Duration') }}</span>
                <input :value="formatTime(fightDurationRef)" @change="updateDuration" :disabled="!canManage"
                    class="w-16 bg-white/5 border border-white/10 rounded px-2 py-1 text-[10px] text-white font-mono outline-none focus:border-primary/50"
                    placeholder="6:00" />
            </div>
            <div class="h-5 w-px bg-white/10"></div>
            <button @click="viewport.reset()"
                class="flex items-center gap-1 px-2 py-1 rounded bg-white/5 text-on-surface-variant hover:text-white text-[9px] font-black uppercase tracking-widest transition-all">
                <span class="material-symbols-outlined text-xs">fit_screen</span>
                {{ __('Fit') }}
            </button>
            <div class="text-[9px] text-on-surface-variant/40 font-mono">{{ Math.round(viewport.zoom.value * 100) }}%</div>
            <div class="h-5 w-px bg-white/10"></div>
            <div class="text-[9px] text-on-surface-variant/50">
                {{ visibleAbilities.length }} {{ __('boss abilities') }} · {{ sortedRoster.length }} {{ __('players') }} · {{ assignments.length }} {{ __('assignments') }}
            </div>
            <div v-if="focusedCast"
                class="flex items-center gap-1.5 px-2 py-1 rounded bg-cyan-500/15 border border-cyan-500/30 text-cyan-400 max-w-xl">
                <span class="material-symbols-outlined text-xs">my_location</span>
                <span class="text-[9px] font-bold uppercase tracking-widest">
                    {{ __('Snap to') }} {{ focusedCast.ability_name }} · {{ Math.floor(focusedCast.time / 60) }}:{{ String(focusedCast.time % 60).padStart(2, '0') }}
                </span>
                <span v-if="focusedCast.priority" class="text-[8px] font-black uppercase px-1 py-0.5 rounded"
                    :class="{
                        'bg-red-500/20 text-red-300': focusedCast.priority === 'high',
                        'bg-amber-500/20 text-amber-300': focusedCast.priority === 'medium',
                        'bg-white/10 text-white/50': focusedCast.priority === 'low',
                    }">{{ __('priority_' + focusedCast.priority) }}</span>
                <span v-for="r in (focusedCast.recommended_response || [])" :key="r"
                    class="text-[7px] font-black uppercase tracking-wider px-1 py-0.5 rounded bg-primary/15 text-primary/80">
                    {{ __('response_' + r) === 'response_' + r ? r.replace(/_/g, ' ') : __('response_' + r) }}
                </span>
                <button @click="clearFocus" class="ml-1 hover:text-white">
                    <span class="material-symbols-outlined text-xs">close</span>
                </button>
            </div>
            <div v-if="focusedCast && focusedCast.notes" class="text-[9px] text-on-surface-variant/70 max-w-md truncate"
                :title="focusedCast.notes">
                {{ focusedCast.notes }}
            </div>
            <div class="ml-auto flex items-center gap-2">
                <!-- Priority filter H/M/L -->
                <div class="flex items-center gap-0.5 bg-white/5 rounded p-0.5" :title="__('Priority filter')">
                    <button v-for="lvl in ['high','medium','low']" :key="lvl"
                        @click="togglePriority(lvl)"
                        class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest transition-all"
                        :class="priorityFilters[lvl]
                            ? (lvl === 'high' ? 'bg-red-500/20 text-red-300' : lvl === 'medium' ? 'bg-amber-500/20 text-amber-300' : 'bg-white/15 text-white/60')
                            : 'text-on-surface-variant/30 hover:text-on-surface-variant/60'">
                        {{ lvl === 'high' ? 'H' : lvl === 'medium' ? 'M' : 'L' }}
                    </button>
                </div>

                <!-- Focus mode on selected player -->
                <button v-if="selectedPlayer" @click="toggleFocusMode"
                    :title="__('Focus on selected player (show only their abilities)')"
                    class="flex items-center gap-1 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest transition-all"
                    :class="focusMode ? 'bg-cyan-500/20 text-cyan-300' : 'bg-white/5 text-on-surface-variant hover:text-white'">
                    <span class="material-symbols-outlined text-xs">{{ focusMode ? 'person_search' : 'person' }}</span>
                    {{ focusMode ? __('Focused') : __('Focus') }}
                </button>

                <!-- Compact rows -->
                <button @click="toggleCompactMode"
                    :title="__('Compact row height for bosses with many abilities')"
                    class="flex items-center gap-1 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest transition-all"
                    :class="compactMode ? 'bg-white/20 text-white' : 'bg-white/5 text-on-surface-variant hover:text-white'">
                    <span class="material-symbols-outlined text-xs">{{ compactMode ? 'density_small' : 'density_medium' }}</span>
                    {{ compactMode ? __('Compact') : __('Row density: Normal') }}
                </button>

                <!-- Hidden abilities toggle -->
                <button v-if="hiddenAbilities.size > 0" @click="showHiddenAbilities = !showHiddenAbilities"
                    class="flex items-center gap-1 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest transition-all"
                    :class="showHiddenAbilities ? 'bg-white/20 text-white' : 'bg-white/5 text-on-surface-variant hover:text-white'">
                    <span class="material-symbols-outlined text-xs">{{ showHiddenAbilities ? 'visibility' : 'visibility_off' }}</span>
                    {{ showHiddenAbilities ? __('Hide hidden') : (__('Show hidden') + ' (' + hiddenAbilities.size + ')') }}
                </button>
                <!-- Copy MRT note → clipboard (Boss Planner orange accent) -->
                <button @click="exportMrtNote"
                    :title="__('Copy MethodRaidTools note to clipboard (paste via /rt note in-game)')"
                    class="flex items-center gap-1 px-2.5 py-1 rounded-lg border text-[9px] font-black uppercase tracking-widest transition-all"
                    :class="mrtExportState === 'copied'
                        ? 'bg-success-neon/15 border-success-neon/40 text-success-neon'
                        : mrtExportState === 'error'
                            ? 'bg-error/15 border-error/40 text-error'
                            : 'bg-orange-500/10 border-orange-500/30 text-orange-400 hover:bg-orange-500/20 hover:border-orange-500/50 hover:text-orange-300'">
                    <span class="material-symbols-outlined text-xs">
                        {{ mrtExportState === 'copied' ? 'check' : mrtExportState === 'error' ? 'error' : 'content_copy' }}
                    </span>
                    {{ mrtExportState === 'copied' ? __('Copied') : mrtExportState === 'error' ? __('Error') : __('Copy MRT') }}
                </button>
            </div>
        </div>
        <div class="shrink-0 px-4 py-1 bg-[#0a0a0c] text-[8px] text-on-surface-variant/30 border-b border-white/5">
            {{ __('Click boss cast to focus · Drag phase boundary to resize segment · Double-click boundary label to rename · Right-click ability label to hide · Drag empty to pan · Scroll to zoom') }}
        </div>

        <!-- Conditional mechanics strip (not on the fixed timeline) -->
        <ConditionalAbilitiesStrip :abilities="conditionalAbilities" />

        <!-- Sticky boss section -->
        <div ref="containerRef" class="shrink-0 border-b border-white/5">
            <TimelineBossSection
                :abilities="visibleAbilities"
                :phases="derivedPhaseMarkers"
                :fight-duration="fightDurationRef"
                :px-per-sec="viewport.pxPerSec.value"
                :pan-x="viewport.panX.value"
                :content-width="viewport.contentWidth.value"
                :viewport-width="viewport.viewportWidth.value"
                :left-width="LEFT_WIDTH"
                :right-padding="RIGHT_PADDING"
                :can-manage="canManage"
                :phase-edit-mode="phaseEditMode"
                :focused-cast="focusedCast"
                :hidden-ability-ids="hiddenAbilityIdArr"
                :compact="effectiveCompactMode"
                @pan-start="onPanStart"
                @wheel="onWheel"
                @click-empty="onClickEmpty"
                @remove-phase="removePhase"
                @focus-cast="onFocusCast"
                @toggle-hidden-ability="toggleHiddenAbility"
                @start-phase-drag="onStartPhaseDrag"
                @rename-phase="renamePhase"
            />
        </div>

        <!-- Scrollable player section -->
        <div class="flex-1 overflow-y-auto">
            <TimelinePlayerSection
                :roster="rosterForDisplay"
                :assignments="assignments"
                :phases="derivedPhaseMarkers"
                :fight-duration="fightDurationRef"
                :px-per-sec="viewport.pxPerSec.value"
                :pan-x="viewport.panX.value"
                :content-width="viewport.contentWidth.value"
                :viewport-width="viewport.viewportWidth.value"
                :left-width="LEFT_WIDTH"
                :right-padding="RIGHT_PADDING"
                :can-manage="canManage"
                :selected-player-id="selectedPlayerId"
                :selected-assignment-id="selectedAssignmentId"
                :dragged-cd="draggedCd"
                :drag-hover-time="dragHoverTime"
                :phase-edit-mode="phaseEditMode"
                :player-cooldowns="playerCooldowns"
                :focused-cast="focusedCast"
                :my-character-ids="myCharacterIds"
                :showing-me="showingMe && activeTab === 'cooldowns'"
                @pan-start="onPanStart"
                @wheel="onWheel"
                @click-empty="onClickEmpty"
                @remove-phase="removePhase"
                @select-player="selectPlayer"
                @drop-cd="onPlayerDrop"
                @drag-over="onPlayerDragOver"
                @drag-leave="onPlayerDragLeave"
                @select-assignment="selectAssignment"
                @move-assignment="moveAssignment"
            />
        </div>

        <!-- Floating CD panel for selected player.
             Teleport escapes the parent tab's v-show — use the `visible` prop
             to hide the panel when the user switches away, without losing its
             position or selection state. -->
        <PlayerCdPanel
            v-if="selectedPlayer"
            :character="selectedPlayer"
            :cooldowns="selectedPlayerCds"
            :disabled-spell-ids="selectedPlayerDisabledIds"
            :type-filters="cdTypeFilters"
            :can-manage="canManage"
            :visible="activeTab === 'cooldowns'"
            @close="selectedPlayerId = null"
            @drag-start="onPanelDragStart"
            @toggle-cd="onToggleCharacterCd"
        />

        <!-- Floating edit panel for selected assignment -->
        <AssignmentEditPanel
            v-if="selectedAssignment"
            :key="selectedAssignment.id"
            :assignment="selectedAssignment"
            :fight-duration="fightDurationRef"
            :can-manage="canManage"
            :cooldown-sec="cooldownForSelected"
            :visible="activeTab === 'cooldowns'"
            @close="selectedAssignmentId = null"
            @update="updateAssignment"
            @remove="removeAssignment"
        />

        <!-- Floating detail panel for the currently focused boss cast.
             No Teleport — root is the fixed div, so v-show suffices. -->
        <AbilityDetailPanel
            v-if="focusedCast"
            v-show="activeTab === 'cooldowns'"
            :key="(focusedCast.spell_id || 0) + ':' + focusedCast.time"
            :ability="focusedCast"
            @close="clearFocus"
        />
    </div>
</template>
