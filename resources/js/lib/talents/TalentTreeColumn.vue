<script setup>
import { computed, onMounted, nextTick } from 'vue';
import { iconUrl, heroIconUrl, FALLBACK_ICON_URL, wowheadTalentUrl } from './data.js';

function onIconError(e) {
    if (e.target && e.target.src !== FALLBACK_ICON_URL) {
        e.target.src = FALLBACK_ICON_URL;
    }
}

const props = defineProps({
    title:       { type: String, required: true },
    subtitle:    { type: String, default: null },         // e.g. hero tree name like "Druid of the Claw"
    nodes:       { type: Object, required: true },        // { nodeId: { id, row, column, type, spells, previousNodeIds, maxRanks?... } }
    selectedMap: { type: Object, default: () => ({}) },   // { nodeId: { rank, choice, granted } }
    apexNode:    { type: Object, default: null },         // spec-only: capstone talent rendered below the grid
    metaNode:    { type: Object, default: null },         // hero-only: top "select-a-tree" node + chosen entry display
    checkpoints: { type: Array,  default: () => [] },     // [{ row, points }] level/point gates
    checkpointsSide: { type: String, default: 'left' },   // 'left' | 'right' — which side to anchor lock markers
    totalPoints: { type: Number, default: 0 },            // max points possible (for Spent: N/total display)
    editable:    { type: Boolean, default: false },        // enable click-to-cycle ranks
    iconSize:    { type: Number, default: 28 },
    xGap:        { type: Number, default: 2 },
    yGap:        { type: Number, default: 14 },
});
const emit = defineEmits(['node-changed']);

// ── Layout math ─────────────────────────────────────────────────────────────
const cellX = computed(() => props.iconSize + props.xGap);
const cellY = computed(() => props.iconSize + props.yGap);

const nodeList = computed(() => Object.values(props.nodes || {}));

// ── Wowhead tooltips — load power.js once globally on first mount ───────────
function ensureWowheadScript() {
    if (window.$WowheadPower || document.querySelector('script[data-wowhead-power]')) return;
    window.whTooltips = { colorLinks: true, iconizeLinks: true, renameLinks: true };
    const s = document.createElement('script');
    s.src = 'https://wow.zamimg.com/widgets/power.js';
    s.async = true;
    s.dataset.wowheadPower = '1';
    document.head.appendChild(s);
}

onMounted(async () => {
    ensureWowheadScript();
    // Re-scan DOM after nodes are rendered so power.js picks them up.
    await nextTick();
    if (window.$WowheadPower?.refreshLinks) window.$WowheadPower.refreshLinks();
});

// Normalize column/row offsets — class/spec grids start at col 1 (not 0),
// so we subtract the min to remove the leading empty gutter.
const offsets = computed(() => {
    const list = nodeList.value;
    if (!list.length) return { minRow: 0, minCol: 0 };
    return {
        minRow: Math.min(...list.map((n) => Number(n.row))),
        minCol: Math.min(...list.map((n) => Number(n.column))),
    };
});

function px(node, axis) {
    const off  = axis === 'col' ? offsets.value.minCol : offsets.value.minRow;
    const v    = axis === 'col' ? Number(node.column) : Number(node.row);
    const size = axis === 'col' ? cellX.value : cellY.value;
    return (v - off) * size;
}

const grid = computed(() => {
    const list = nodeList.value;
    if (!list.length) return { widthPx: 0, heightPx: 0 };
    const cols = Math.max(...list.map((n) => Number(n.column) - offsets.value.minCol)) + 1;
    const rows = Math.max(...list.map((n) => Number(n.row)    - offsets.value.minRow)) + 1;
    return {
        widthPx:  cols * cellX.value,
        heightPx: rows * cellY.value,
    };
});

// ── Connection lines (SVG overlay) ───────────────────────────────────────────
const lines = computed(() => {
    const result = [];
    const byId = props.nodes || {};
    const half = props.iconSize / 2;

    for (const node of nodeList.value) {
        const prevIds = node.previousNodeIds || [];
        for (const prevId of prevIds) {
            const prev = byId[prevId];
            if (!prev) continue;

            const x1 = px(prev, 'col') + half;
            const y1 = px(prev, 'row') + half;
            const x2 = px(node, 'col') + half;
            const y2 = px(node, 'row') + half;

            const fromSelected = isNodeActive(prevId);
            const toSelected   = isNodeActive(node.id);
            const active = fromSelected && toSelected;

            result.push({ x1, y1, x2, y2, active });
        }
    }
    return result;
});

function isNodeActive(id) {
    const entry = props.selectedMap?.[id];
    if (!entry) return false;
    return entry.granted || (entry.rank !== 0);
}

// ── Per-node rendering helpers ───────────────────────────────────────────────
function pickSpell(node) {
    // For choice nodes, show the chosen entry; otherwise the first spell.
    const sel = props.selectedMap?.[node.id];
    const spells = node.spells || [];
    if (!spells.length) return null;
    if (sel?.choice != null && spells[sel.choice]) return spells[sel.choice];
    return spells[0];
}

function rankLabel(node) {
    const sel = props.selectedMap?.[node.id];
    const max = pickSpell(node)?.maxRanks ?? 1;
    if (!sel) return `0/${max}`;
    if (sel.granted) return `${max}/${max}`;
    if (sel.rank === -1) return `${max}/${max}`;
    return `${Math.max(0, sel.rank)}/${max}`;
}

function nodeShape(node) {
    // "round" or "square"; choice nodes get a special diamond style
    return node.type || 'square';
}

// Choice node helpers — render BOTH spells side-by-side, highlight the chosen one.
function isChoiceSelected(node, sideIndex) {
    const sel = props.selectedMap?.[node.id];
    if (!sel) return false;
    return sel.choice === sideIndex;
}

// Choice octagon stroke colour: green/gold/grey to match the state semantics.
function choiceStrokeColor(node) {
    const state = nodeStateClass(node);
    if (state === 'invested' || state === 'maxed' || state === 'granted') return '#f5c842';
    if (state === 'reachable')   return '#74D146';
    if (state === 'unreachable') return '#2a2a30';
    return '#4b5563';   // unselected (readonly)
}

// Compute Wowhead URL with current rank for any node.
function nodeHref(node) {
    const spell = pickSpell(node);
    if (!spell?.spellId) return null;
    const sel = props.selectedMap?.[node.id];
    let rank = 0;
    if (sel) {
        const max = spell.maxRanks ?? 1;
        rank = (sel.rank === -1) ? max : Math.max(0, sel.rank);
    }
    return wowheadTalentUrl(spell.name, [spell], rank);
}

// ── Click-to-cycle (editable mode) ──────────────────────────────────────────
function totalMaxRanks(node) {
    // Choice nodes: 1 (you pick one). Regular nodes: max of their first spell.
    // Apex nodes: sum of spell maxRanks (handled by caller via apex variant).
    if (node.type === 'choice') return 1;
    return node.spells?.[0]?.maxRanks ?? 1;
}

function currentInvestedRank(node) {
    const sel = props.selectedMap?.[node.id];
    if (!sel || sel.granted) return 0;
    const max = totalMaxRanks(node);
    return sel.rank === -1 ? max : Math.max(0, sel.rank);
}

/**
 * Is this node currently reachable for investment?
 *  - Tree-points gate: `spentAmountRequired` ≤ current spent
 *  - Prerequisite gate: at least one `previousNodeIds[*]` must already be active
 *    (starter nodes with empty `previousNodeIds` are always reachable)
 */
function isReachable(node) {
    if (!node) return false;
    if ((node.spentAmountRequired ?? 0) > spent.value) return false;

    const prevIds = node.previousNodeIds || [];
    if (prevIds.length === 0) return true;

    for (const prevId of prevIds) {
        const prev = props.selectedMap?.[prevId];
        if (prev && (prev.granted || prev.rank === -1 || (prev.rank ?? 0) > 0)) return true;
    }
    return false;
}

function cycleNode(node, event) {
    if (!props.editable) return;  // not editable → leave default <a> navigation
    event.preventDefault();
    event.stopPropagation();

    // Default class/spec talents are auto-granted and can't be cycled or removed.
    if (node.alreadyMaxedOut) return;
    // Hero root (always granted when hero is active) — also immovable.
    const sel0 = props.selectedMap?.[node.id];
    if (sel0?.granted) return;

    const sel = sel0;
    const max = totalMaxRanks(node);
    const current = currentInvestedRank(node);

    let next;
    let pointsDelta = 0;

    if (node.type === 'choice') {
        // none → choice 0 → choice 1 → none
        if (!sel || current === 0) { next = { rank: 1, choice: 0, granted: false }; pointsDelta = +1; }
        else if (sel.choice === 0) { next = { rank: 1, choice: 1, granted: false }; pointsDelta =  0; }
        else                       { next = null;                                   pointsDelta = -1; }
    } else {
        // 0 → 1 → 2 → ... → max → 0
        const nextRank = current >= max ? 0 : current + 1;
        if (nextRank === 0)        { next = null;                                       pointsDelta = -current; }
        else if (nextRank === max) { next = { rank: -1, choice: null, granted: false }; pointsDelta = nextRank - current; }
        else                       { next = { rank: nextRank, choice: null, granted: false }; pointsDelta = +1; }
    }

    // Budget check — refuse to add points if tree is already at its max pool.
    if (pointsDelta > 0 && props.totalPoints > 0 && spent.value + pointsDelta > props.totalPoints) {
        return;  // silently refuse — full budget
    }
    // Reachability check — adding a point requires the node to be reachable
    // (point gate met + at least one prereq active). Removing points is always allowed.
    if (pointsDelta > 0 && current === 0 && !isReachable(node)) {
        return;  // first-rank investment refused — chain prerequisite not met
    }

    emit('node-changed', { nodeId: node.id, entry: next });
}

// Apex has its own cycling — total max = sum of sub-spell maxRanks (e.g. 4).
// Apex points count toward the spec tree's budget, so we apply the same cap.
function cycleApex(event) {
    if (!props.editable || !props.apexNode) return;
    event.preventDefault();

    const max     = apexMaxRanks.value;
    const current = apexRank.value;
    const nextRank = current >= max ? 0 : current + 1;
    const pointsDelta = nextRank === 0 ? -current : nextRank - current;

    // Budget cap — apex eats from spec tree's 34-point pool.
    if (pointsDelta > 0 && props.totalPoints > 0 && spent.value + pointsDelta > props.totalPoints) {
        return;
    }

    let entry;
    if (nextRank === 0)        entry = null;
    else if (nextRank === max) entry = { rank: -1, choice: null, granted: false };
    else                       entry = { rank: nextRank, choice: null, granted: false };

    emit('node-changed', { nodeId: props.apexNode.id, entry });
}

function nodeStateClass(node) {
    const sel = props.selectedMap?.[node.id];
    if (!sel) {
        if (!props.editable) return 'unselected';      // readonly: just dim
        return isReachable(node) ? 'reachable' : 'unreachable';
    }
    if (sel.granted)      return 'granted';
    if (sel.rank === -1)  return 'maxed';
    if (sel.rank > 0)     return 'invested';
    return 'unselected';
}

// ── Spent counter: sum of points across all displayed nodes ─────────────────
// Apex talents count toward the spec tree's point pool (e.g. 4 apex points
// out of the 34 spec budget), so we add `apexRank` when an apexNode is
// supplied. Hero meta isn't a real talent — excluded.
const spent = computed(() => {
    let total = 0;
    for (const node of nodeList.value) {
        const sel = props.selectedMap?.[node.id];
        if (!sel || sel.granted) continue;
        const spell = pickSpell(node);
        const max = spell?.maxRanks ?? 1;
        const rank = sel.rank === -1 ? max : sel.rank;
        total += Math.max(0, rank);
    }
    if (props.apexNode) total += apexRank.value;
    return total;
});

// ── Checkpoint markers: { yPx, label } per checkpoint ───────────────────────
const checkpointMarkers = computed(() => props.checkpoints.map((cp) => ({
    yPx:  (cp.row - offsets.value.minRow) * cellY.value - 4,
    label: cp.points,
})));

// ── Apex helpers ────────────────────────────────────────────────────────────
const apexMaxRanks = computed(() => {
    if (!props.apexNode?.spells) return 1;
    return props.apexNode.spells.reduce((sum, s) => sum + (s.maxRanks ?? 1), 0);
});
const apexRank = computed(() => {
    const sel = props.selectedMap?.[props.apexNode?.id];
    if (!sel) return 0;
    if (sel.rank === -1) return apexMaxRanks.value;
    return Math.max(0, sel.rank);
});
const apexStateClass = computed(() => {
    if (apexRank.value > 0) return 'active';
    return props.editable ? 'reachable' : 'inactive';
});
const apexHref = computed(() => props.apexNode
    ? wowheadTalentUrl(props.apexNode.name, props.apexNode.spells, apexRank.value)
    : null);
</script>

<template>
    <div class="talent-tree-column">
        <header class="tree-header">
            <div class="tree-title">
                <span class="tree-title-main">{{ title }}</span>
                <span v-if="subtitle" class="tree-subtitle">{{ subtitle }}</span>
            </div>
            <div v-if="totalPoints" class="tree-spent">
                <span class="spent-value">{{ spent }}</span><span class="spent-sep"> / </span><span class="spent-total">{{ totalPoints }}</span>
            </div>
        </header>

        <!-- Hero meta visualization (top of hero column) -->
        <div v-if="metaNode" class="hero-meta-band">
            <a
                class="hero-meta-icon"
                :href="metaNode.spellId ? `https://www.wowhead.com/spell=${metaNode.spellId}` : null"
                :data-wowhead="metaNode.spellId ? `spell=${metaNode.spellId}` : null"
                target="_blank" rel="noopener noreferrer"
            >
                <img :src="heroIconUrl(metaNode.icon)" alt="" @error="onIconError" />
            </a>
        </div>

        <div class="tree-grid-wrapper"
             :style="{ width: grid.widthPx + 'px', height: grid.heightPx + 'px' }">

            <!-- Checkpoint row markers -->
            <div
                v-for="(cp, idx) in checkpointMarkers" :key="'cp-' + idx"
                class="checkpoint-marker"
                :class="checkpointsSide === 'right' ? 'cp-right' : 'cp-left'"
                :style="{ top: cp.yPx + 'px' }"
            >
                <span class="checkpoint-points">{{ cp.label }}</span>
                <span class="material-symbols-outlined checkpoint-lock">lock</span>
            </div>

            <!-- Connections (SVG, behind icons) -->
            <svg
                class="tree-connections"
                :width="grid.widthPx"
                :height="grid.heightPx"
                :viewBox="`0 0 ${grid.widthPx} ${grid.heightPx}`"
            >
                <line v-for="(ln, idx) in lines" :key="idx"
                      :x1="ln.x1" :y1="ln.y1" :x2="ln.x2" :y2="ln.y2"
                      :class="ln.active ? 'line-active' : 'line-inactive'" />
            </svg>

            <!-- Talent nodes -->
            <a
                v-for="node in nodeList"
                :key="node.id"
                class="talent-node"
                :class="[nodeStateClass(node), 'shape-' + nodeShape(node)]"
                :style="{
                    left:   px(node, 'col') + 'px',
                    top:    px(node, 'row') + 'px',
                    width:  iconSize + 'px',
                    height: iconSize + 'px',
                }"
                :href="nodeHref(node)"
                :target="editable ? null : '_blank'"
                rel="noopener noreferrer"
                @click="cycleNode(node, $event)"
                @contextmenu.prevent="editable && !node.alreadyMaxedOut && !selectedMap?.[node.id]?.granted
                    && emit('node-changed', { nodeId: node.id, entry: null })"
            >
                <img v-if="pickSpell(node)?.icon"
                     :src="iconUrl(pickSpell(node).icon)"
                     :alt="pickSpell(node)?.name"
                     class="talent-icon"
                     loading="lazy"
                     @error="onIconError" />
                <!-- Choice nodes only: SVG octagon frame, perfectly aligned with the icon's clip-path -->
                <svg v-if="nodeShape(node) === 'choice'"
                     class="choice-frame"
                     :viewBox="`0 0 100 100`"
                     preserveAspectRatio="none"
                     aria-hidden="true">
                    <polygon
                        points="30,0 70,0 100,30 100,70 70,100 30,100 0,70 0,30"
                        fill="none"
                        stroke-width="8"
                        :stroke="choiceStrokeColor(node)"
                    />
                </svg>
                <span class="rank-badge">{{ rankLabel(node) }}</span>
            </a>
        </div>

        <!-- Apex / capstone (spec tree only) — rank-aware Wowhead tooltip URL -->
        <a v-if="apexNode"
            class="apex-node"
            :class="apexStateClass"
            :href="apexHref"
            :target="editable ? null : '_blank'"
            rel="noopener noreferrer"
            @click="cycleApex($event)"
            @contextmenu.prevent="editable && emit('node-changed', { nodeId: apexNode.id, entry: null })"
        >
            <div class="apex-icon-wrap">
                <img :src="iconUrl(apexNode.icon)" alt="" @error="onIconError" />
                <span class="rank-badge apex-rank">{{ apexRank }}/{{ apexMaxRanks }}</span>
            </div>
            <span class="apex-name">{{ apexNode.name }}</span>
        </a>
    </div>
</template>

<style scoped>
.talent-tree-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    color: #e5e7eb;
    background: linear-gradient(180deg, rgba(10, 15, 20, 0.85) 0%, rgba(5, 8, 12, 0.95) 100%);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.6);
}

/* Header band — title + spent counter */
.tree-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    width: 100%;
    padding: 10px 16px;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 6px;
    font-family: var(--font-headline, inherit);
}
.tree-title {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}
.tree-title-main {
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: #d4d4d8;
}
.tree-subtitle {
    font-size: 10px;
    color: #f5c842;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-top: 2px;
}
.tree-spent {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 14px;
    font-weight: 700;
}
.spent-value { color: #f5c842; }
.spent-sep   { color: #6b7280; }
.spent-total { color: #d4d4d8; }

/* Hero column: meta icon strip above the grid */
.hero-meta-band {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    margin-bottom: 2px;
}
.hero-meta-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    border: 2px solid #f5c842;
    background: #0a0a0d;
    box-shadow: 0 0 14px rgba(245,200,66,0.45);
    display: block;
    overflow: hidden;
    text-decoration: none;
}
.hero-meta-icon img {
    width: 100%; height: 100%; display: block; object-fit: cover;
}
.hero-meta-name {
    font-size: 11px;
    color: #f5c842;
    font-family: var(--font-headline, inherit);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

/* Checkpoint markers (Req Level / point gates). Anchored left or right edge. */
.checkpoint-marker {
    position: absolute;
    display: flex;
    align-items: center;
    gap: 2px;
    color: #cd5b61;
    font-size: 11px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    pointer-events: none;
}
.checkpoint-marker.cp-left  { left: -34px; }
.checkpoint-marker.cp-right { right: -34px; flex-direction: row-reverse; }
.checkpoint-lock { font-size: 12px !important; }

/* Apex capstone below spec grid */
.apex-node {
    margin-top: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    text-decoration: none;
}
.apex-icon-wrap {
    position: relative;
    display: block;
}
.apex-node img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid #4b5563;
    display: block;
    filter: grayscale(0.6) brightness(0.7);
    transition: border-color 0.15s, filter 0.15s, box-shadow 0.15s;
}
.apex-node.active img {
    border-color: #f5c842;
    box-shadow: 0 0 12px rgba(245,200,66,0.5);
    filter: none;
}
.apex-node.reachable img {
    border-color: #74D146;
    box-shadow: 0 0 8px rgba(116,209,70,0.4);
    filter: grayscale(0.3) brightness(0.85);
}
.apex-node.reachable .apex-name { color: #74D146; }
.apex-rank {
    position: absolute;
    bottom: -4px;
    left: 50%;
    transform: translateX(-50%);
}
.apex-name {
    font-size: 11px;
    color: #d4d4d8;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}
.apex-node.active .apex-name { color: #f5c842; }
.tree-grid-wrapper {
    position: relative;
    margin-top: 8px;
}
.tree-connections {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.line-active   { stroke: #f5c842; stroke-width: 3.5; opacity: 1; filter: drop-shadow(0 0 4px rgba(245,200,66,0.6)); }
.line-inactive { stroke: #4b5563; stroke-width: 2.5; opacity: 0.4; }

.talent-node {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0a0a0d;
    border: 2px solid #4b5563;
    box-shadow: 0 0 0 2px rgba(0,0,0,0.8), inset 0 0 8px rgba(0,0,0,0.5);
    filter: grayscale(1) brightness(0.5);
    transition: filter 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
    cursor: pointer;
    text-decoration: none;
}
.talent-node:hover {
    transform: scale(1.15);
    z-index: 10;
    filter: grayscale(0) brightness(1.1);
    box-shadow: 0 0 12px rgba(255,255,255,0.2), 0 0 0 2px rgba(0,0,0,0.8);
}

.shape-square  { border-radius: 6px; }
.shape-round   { border-radius: 50%; }

/* Choice nodes — square icon clipped to octagon, with an SVG frame drawn over
   the same polygon. Both share the SAME viewBox so they align perfectly. */
.shape-choice {
    border: none;
    background: transparent;
    box-shadow: none;
    overflow: visible;
}
.shape-choice .talent-icon {
    border-radius: 0 !important;
    clip-path: polygon(
        30% 0%, 70% 0%,
        100% 30%, 100% 70%,
        70% 100%, 30% 100%,
        0% 70%,  0% 30%
    ) !important;
}
.choice-frame {
    position: absolute;
    inset: -2px;          /* extend 2px past the icon for the border thickness */
    pointer-events: none;
    width: calc(100% + 4px);
    height: calc(100% + 4px);
    overflow: visible;
}

/* Centre the rank badge below the octagon — diagonal corners clip a rectangle
   into a visible triangle if placed at bottom-right. */
.shape-choice .rank-badge {
    bottom: -8px;
    right: auto;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}

.talent-icon {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: inherit;
    display: block;
}

.rank-badge {
    position: absolute;
    bottom: -6px;
    right: -4px;
    background: rgba(0,0,0,0.85);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 4px;
    border-radius: 3px;
    line-height: 1.2;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    pointer-events: none;
}

/* State colours:
   - unselected (readonly): dim grayscale (default .talent-node styles)
   - reachable (editable, not yet invested): GREEN border — click to invest
   - unreachable (editable, locked by chain/points): dimmer + not-allowed
   - invested/maxed/granted: GOLD border */
.talent-node.unselected  { /* default greyed */ }
.talent-node.reachable   {
    filter: grayscale(0) brightness(0.9);
    border-color: #74D146;
    box-shadow: 0 0 10px rgba(116,209,70,0.6), 0 0 0 2px rgba(0,0,0,0.8);
}
.talent-node.unreachable {
    filter: grayscale(1) brightness(0.3);
    cursor: not-allowed;
    border-color: #1f1f22;
}
.talent-node.granted,
.talent-node.invested,
.talent-node.maxed       {
    border-color: #f5c842;
    filter: grayscale(0) brightness(1.05);
    box-shadow: 0 0 14px rgba(245,200,66,0.7), 0 0 0 2px rgba(0,0,0,0.8);
}
</style>
