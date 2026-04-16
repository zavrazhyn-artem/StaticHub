<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    step: { type: Object, default: null },
    activeTool: { type: String, default: 'select' },
    activeMarkerType: { type: String, default: 'skull' },
    canManage: { type: Boolean, default: false },
    groups: { type: Object, default: () => ({}) },
    maps: { type: Array, default: () => [] },
    hasPendingPlacement: { type: Boolean, default: false },
    shapeColor: { type: String, default: '#3B82F6' },
    shapeFilled: { type: Boolean, default: true },
    clearSelectionTrigger: { type: Number, default: 0 },
    highlightedMarkers: { type: Object, default: () => new Set() },
});

const emit = defineEmits(['update', 'move-group', 'place', 'action-start', 'selection-change', 'request-text']);

const svgRef = ref(null);
const containerRef = ref(null);
const canvasWidth = 960;
const canvasHeight = 540;

// ─── Zoom & Pan ───
const viewBox = reactive({ x: 0, y: 0, w: canvasWidth, h: canvasHeight });
const isPanning = ref(false);
const panStart = ref({ x: 0, y: 0, vx: 0, vy: 0 });
const zoomLevel = computed(() => Math.round((canvasWidth / viewBox.w) * 100));

const handleWheel = (e) => {
    e.preventDefault();
    const svg = svgRef.value;
    if (!svg) return;
    const rect = svg.getBoundingClientRect();
    // Mouse position as fraction of SVG
    const mx = (e.clientX - rect.left) / rect.width;
    const my = (e.clientY - rect.top) / rect.height;
    // Zoom factor
    const factor = e.deltaY < 0 ? 0.9 : 1.1;
    const newW = Math.max(200, Math.min(canvasWidth * 3, viewBox.w * factor));
    const newH = Math.max(112, Math.min(canvasHeight * 3, viewBox.h * factor));
    // Zoom toward mouse
    viewBox.x += (viewBox.w - newW) * mx;
    viewBox.y += (viewBox.h - newH) * my;
    viewBox.w = newW;
    viewBox.h = newH;
};

const startPan = (e) => {
    // Middle mouse always pans
    if (e.button === 1) {
        e.preventDefault();
        isPanning.value = true;
        panStart.value = { x: e.clientX, y: e.clientY, vx: viewBox.x, vy: viewBox.y };
    }
    // Left click on empty space (triggered from handleMouseDown when nothing else matched)
};

const startPanFromEmpty = (e) => {
    isPanning.value = true;
    panStart.value = { x: e.clientX, y: e.clientY, vx: viewBox.x, vy: viewBox.y };
};

const movePan = (e) => {
    if (!isPanning.value) return;
    const svg = svgRef.value;
    if (!svg) return;
    const rect = svg.getBoundingClientRect();
    const dx = (e.clientX - panStart.value.x) / rect.width * viewBox.w;
    const dy = (e.clientY - panStart.value.y) / rect.height * viewBox.h;
    viewBox.x = panStart.value.vx - dx;
    viewBox.y = panStart.value.vy - dy;
};

const endPan = () => { isPanning.value = false; };

const resetView = () => { viewBox.x = 0; viewBox.y = 0; viewBox.w = canvasWidth; viewBox.h = canvasHeight; };

// ─── Edge auto-scroll while dragging ───
const edgeScrollSpeed = 3; // SVG units per frame
const edgeZone = 40; // pixels from edge to trigger

const doEdgeScroll = (e) => {
    const svg = svgRef.value;
    if (!svg) return;
    if (!dragging.value && !resizing.value && !rotating.value) return;
    const rect = svg.getBoundingClientRect();
    const mx = e.clientX - rect.left;
    const my = e.clientY - rect.top;
    const w = rect.width;
    const h = rect.height;
    // Scale speed relative to current zoom
    const speed = edgeScrollSpeed * (viewBox.w / canvasWidth);
    let dx = 0, dy = 0;
    if (mx < edgeZone) dx = -speed * (1 - mx / edgeZone);
    else if (mx > w - edgeZone) dx = speed * (1 - (w - mx) / edgeZone);
    if (my < edgeZone) dy = -speed * (1 - my / edgeZone);
    else if (my > h - edgeZone) dy = speed * (1 - (h - my) / edgeZone);
    if (dx !== 0 || dy !== 0) {
        viewBox.x += dx;
        viewBox.y += dy;
    }
};

// Background map
const selectedMapIndex = ref(0);
const currentMapUrl = computed(() => props.maps[selectedMapIndex.value]?.url || props.maps[0]?.url || null);

// ─── Interaction state ───
let prevDragPointer = null;
const dragging = ref(null);
const dragOffset = ref({ x: 0, y: 0 });
const groupDragStartPositions = ref([]);
const isDrawing = ref(false);
const drawStart = ref({ x: 0, y: 0 });
const tempShape = ref(null);
const ctxMenu = ref({ show: false, x: 0, y: 0, type: '', index: -1 });

// ─── Selection state ───
const sel = reactive({ type: null, index: -1 }); // primary selected element (for handles)
const multiSel = ref([]); // multi-selection: [{ type, index }, ...]
const resizing = ref(null);
const rotating = ref(null);

// Get linkGroup of an element
const getLinkGroup = (type, index) => {
    if (type === 'marker') return markers.value[index]?.linkGroup;
    if (type === 'player') return players.value[index]?.linkGroup;
    if (type === 'shape') return shapes.value[index]?.linkGroup;
    if (type === 'label') return textLabels.value[index]?.linkGroup;
    if (type === 'arrow') return arrows.value[index]?.linkGroup;
    return null;
};

const findLinkedElements = (linkId) => {
    if (!linkId) return [];
    const result = [];
    markers.value.forEach((m, i) => { if (m.linkGroup === linkId) result.push({ type: 'marker', index: i }); });
    players.value.forEach((p, i) => { if (p.linkGroup === linkId) result.push({ type: 'player', index: i }); });
    shapes.value.forEach((s, i) => { if (s.linkGroup === linkId) result.push({ type: 'shape', index: i }); });
    textLabels.value.forEach((l, i) => { if (l.linkGroup === linkId) result.push({ type: 'label', index: i }); });
    arrows.value.forEach((a, i) => { if (a.linkGroup === linkId) result.push({ type: 'arrow', index: i }); });
    return result;
};

const selectElement = (type, index, addToMulti = false) => {
    if (addToMulti) {
        const existing = multiSel.value.findIndex(s => s.type === type && s.index === index);
        if (existing >= 0) {
            multiSel.value.splice(existing, 1);
        } else {
            multiSel.value.push({ type, index });
        }
    } else {
        // If element has linkGroup — auto-select all linked elements
        const linkId = getLinkGroup(type, index);
        if (linkId) {
            multiSel.value = findLinkedElements(linkId);
        } else {
            multiSel.value = [{ type, index }];
        }
    }
    // Primary = last or clicked
    if (multiSel.value.length > 0) {
        sel.type = type; sel.index = index;
    } else {
        sel.type = null; sel.index = -1;
    }
    emit('selection-change', { type: sel.type, index: sel.index, count: multiSel.value.length, items: [...multiSel.value] });
};
const clearSelection = () => {
    sel.type = null; sel.index = -1; multiSel.value = [];
    emit('selection-change', { type: null, index: -1, count: 0, items: [] });
};
const isSelected = (type, index) => multiSel.value.some(s => s.type === type && s.index === index);

watch(() => props.clearSelectionTrigger, () => { clearSelection(); });

// Get element position by type/index
const getElementPos = (type, index) => {
    if (type === 'marker') return markers.value[index];
    if (type === 'player') return players.value[index];
    if (type === 'shape') return shapes.value[index];
    if (type === 'label') return textLabels.value[index];
    if (type === 'arrow') {
        const a = arrows.value[index];
        if (!a) return null;
        // Virtual position = midpoint
        return { x: (a.from[0] + a.to[0]) / 2, y: (a.from[1] + a.to[1]) / 2, scale: a.scale ?? 1, rotation: a.rotation ?? 0 };
    }
    return null;
};

// ─── Keyboard handlers ───
const handleKeyDown = (e) => {
    if (!props.canManage) return;

    // Delete / Backspace — delete all selected
    if (e.key === 'Delete' || e.key === 'Backspace') {
        if (multiSel.value.length === 0) return;
        e.preventDefault();
        deleteSelected();
        return;
    }

    // Ctrl+G — group selected
    if ((e.ctrlKey || e.metaKey) && e.key === 'g') {
        if (multiSel.value.length < 2) return;
        e.preventDefault();
        groupSelected();
        return;
    }

    // Ctrl+C — copy selected
    if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
        if (multiSel.value.length === 0) return;
        e.preventDefault();
        copySelected();
        return;
    }

    // Ctrl+V — paste
    if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
        if (!clipboard.value) return;
        e.preventDefault();
        pasteClipboard();
        return;
    }

    // Escape — cancel active waypath
    if (e.key === 'Escape' && activeWaypath.value) {
        activeWaypath.value = null;
        return;
    }

    // Enter — finish active waypath
    if (e.key === 'Enter' && activeWaypath.value) {
        finishWaypath();
        return;
    }

    // Ctrl+D — duplicate
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        if (multiSel.value.length === 0) return;
        e.preventDefault();
        copySelected();
        pasteClipboard();
        return;
    }
};

const deleteSelected = () => {
    if (multiSel.value.length === 0) return;
    emit('action-start');
    // Collect indices to delete per type
    const toDelete = { players: [], markers: [], labels: [], shapes: [], arrows: [] };
    const typeMap = { player: 'players', marker: 'markers', label: 'labels', shape: 'shapes', arrow: 'arrows' };
    multiSel.value.forEach(s => {
        const key = typeMap[s.type];
        if (key) toDelete[key].push(s.index);
    });
    const patch = {};
    if (toDelete.players.length) patch.players = players.value.filter((_, i) => !toDelete.players.includes(i));
    if (toDelete.markers.length) patch.markers = markers.value.filter((_, i) => !toDelete.markers.includes(i));
    if (toDelete.labels.length) patch.labels = textLabels.value.filter((_, i) => !toDelete.labels.includes(i));
    if (toDelete.shapes.length) patch.shapes = shapes.value.filter((_, i) => !toDelete.shapes.includes(i));
    if (toDelete.arrows.length) patch.arrows = arrows.value.filter((_, i) => !toDelete.arrows.includes(i));
    if (Object.keys(patch).length) emit('update', patch);
    clearSelection();
};

// ─── Clipboard (Ctrl+C/V) ───
const clipboard = ref(null);

const copySelected = () => {
    if (multiSel.value.length === 0) return;
    const items = [];
    const arrMap = { marker: markers.value, player: players.value, shape: shapes.value, label: textLabels.value, arrow: arrows.value };
    multiSel.value.forEach(s => {
        const el = arrMap[s.type]?.[s.index];
        if (el) items.push({ type: s.type, data: JSON.parse(JSON.stringify(el)) });
    });
    clipboard.value = items;
};

const pasteClipboard = () => {
    if (!clipboard.value || clipboard.value.length === 0) return;
    emit('action-start');
    const offset = 30;
    const patch = {};
    const typeKeyMap2 = { marker: 'markers', player: 'players', shape: 'shapes', label: 'labels', arrow: 'arrows' };
    const arrMap = { marker: markers.value, player: players.value, shape: shapes.value, label: textLabels.value, arrow: arrows.value };

    clipboard.value.forEach(({ type, data }) => {
        const key = typeKeyMap2[type];
        if (!key) return;
        if (!patch[key]) patch[key] = [...arrMap[type]];
        const clone = JSON.parse(JSON.stringify(data));
        // Remove linkGroup from clones
        delete clone.linkGroup;
        // Offset position
        if (type === 'arrow') {
            clone.from = [clone.from[0] + offset, clone.from[1] + offset];
            clone.to = [clone.to[0] + offset, clone.to[1] + offset];
        } else if (clone.x !== undefined) {
            clone.x += offset;
            clone.y += offset;
        }
        patch[key].push(clone);
    });
    if (Object.keys(patch).length) emit('update', patch);
};

// ─── Rectangle selection (Shift+drag) ───
const rectSel = ref(null);

// ─── Waypoint path building ───
const activeWaypath = ref(null);

const finishWaypath = () => {
    if (!activeWaypath.value || activeWaypath.value.points.length < 2) {
        activeWaypath.value = null;
        return;
    }
    // Save as a series of arrows
    const pts = activeWaypath.value.points;
    const color = activeWaypath.value.color;
    const newArrows = [...arrows.value];
    for (let i = 0; i < pts.length - 1; i++) {
        newArrows.push({ from: pts[i], to: pts[i + 1], color, linkGroup: Date.now() });
    }
    emit('update', { arrows: newArrows });
    activeWaypath.value = null;
}; // { startX, startY, x, y, w, h }

const groupSelected = () => {
    // Find all selected markers and assign them a shared linkGroup id
    const markerIndices = multiSel.value.filter(s => s.type === 'marker').map(s => s.index);
    const playerIndices = multiSel.value.filter(s => s.type === 'player').map(s => s.index);
    if (markerIndices.length + playerIndices.length < 2) return;

    // Generate a unique linkGroup id
    const linkId = Date.now();
    const patch = {};
    if (markerIndices.length) {
        const updated = [...markers.value];
        markerIndices.forEach(i => { updated[i] = { ...updated[i], linkGroup: linkId }; });
        patch.markers = updated;
    }
    if (playerIndices.length) {
        const updated = [...players.value];
        playerIndices.forEach(i => { updated[i] = { ...updated[i], linkGroup: linkId }; });
        patch.players = updated;
    }
    if (Object.keys(patch).length) emit('update', patch);
    clearSelection();
};

// ─── Helpers ───
const classColors = {
    'Death Knight': '#C41F3B', 'Demon Hunter': '#A330C9', 'Druid': '#FF7C0A', 'Evoker': '#33937F',
    'Hunter': '#ABD473', 'Mage': '#3FC7EB', 'Monk': '#00FF98', 'Paladin': '#F48CBA',
    'Priest': '#FFFFFF', 'Rogue': '#FFF468', 'Shaman': '#0070DD', 'Warlock': '#8788EE', 'Warrior': '#C69B6D',
};
const markerImages = {
    skull: '/images/raidplan/raid-markers/skull.png', cross: '/images/raidplan/raid-markers/cross.png',
    square: '/images/raidplan/raid-markers/square.png', moon: '/images/raidplan/raid-markers/moon.png',
    triangle: '/images/raidplan/raid-markers/triangle.png', diamond: '/images/raidplan/raid-markers/diamond.png',
    circle_m: '/images/raidplan/raid-markers/circle.png', star: '/images/raidplan/raid-markers/star.png',
};
const roleAbbr = { tank: 'T', heal: 'H', mdps: 'M', rdps: 'R' };
const getPlayerGroup = (p) => p.group ? (props.groups[p.group] || null) : null;
const getClassColor = (cn) => classColors[cn] || '#888';

// Direction chevron: arc wedge on edge of circle, π/3 (60°) wide, pointing up (before rotation)
// Returns SVG path: two lines from circle edge meeting at a point above
const dirChevron = (cx, cy, r, s = 1) => {
    const radius = r * s;
    const tipLen = 3 * s;
    const halfAngle = Math.PI / 6;
    const lx = cx + Math.sin(-halfAngle) * radius;
    const ly = cy - Math.cos(-halfAngle) * radius;
    const rx = cx + Math.sin(halfAngle) * radius;
    const ry = cy - Math.cos(halfAngle) * radius;
    const tx = cx;
    const ty = cy - radius - tipLen;
    return `M ${lx} ${ly} L ${tx} ${ty} L ${rx} ${ry}`;
};

// ─── Data ───
const players = computed(() => props.step?.players || []);
const markers = computed(() => props.step?.markers || []);
const shapes = computed(() => props.step?.shapes || []);
const arrows = computed(() => props.step?.arrows || []);
const textLabels = computed(() => props.step?.labels || []);
const groupTokens = computed(() => {
    const tokens = [];
    for (const [gId, g] of Object.entries(props.groups)) {
        const mp = players.value.filter(p => p.group === Number(gId));
        if (!mp.length) continue;
        tokens.push({ id: Number(gId), label: g.label, color: g.color,
            x: mp.reduce((s, p) => s + p.x, 0) / mp.length,
            y: mp.reduce((s, p) => s + p.y, 0) / mp.length, count: mp.length });
    }
    return tokens;
});
const isDrawingTool = computed(() => ['circle', 'rect', 'triangle', 'arrow', 'line', 'cone', 'text', 'waypoint'].includes(props.activeTool));

// ─── SVG coord (accounts for zoom/pan viewBox) ───
const getSvgPoint = (e) => {
    const svg = svgRef.value;
    if (!svg) return { x: 0, y: 0 };
    const r = svg.getBoundingClientRect();
    return {
        x: viewBox.x + ((e.clientX - r.left) / r.width) * viewBox.w,
        y: viewBox.y + ((e.clientY - r.top) / r.height) * viewBox.h,
    };
};

// ─── Get element transform string ───
const getTransform = (item) => {
    const s = item.scale ?? 1;
    const r = item.rotation ?? 0;
    if (s === 1 && r === 0) return '';
    return `rotate(${r} ${item.x} ${item.y}) scale(${s})`;
};
// Wrap in a g with transform centered on item
const itemTransformStyle = (item) => {
    const s = item.scale ?? 1;
    const r = item.rotation ?? 0;
    if (s === 1 && r === 0) return undefined;
    return `transform-origin: ${item.x}px ${item.y}px; transform: rotate(${r}deg) scale(${s})`;
};

// ─── Selection box computed ───
// ─── Selection bounding box ───
const getItemBounds = (type, index) => {
    let item, r = 20;
    if (type === 'marker') { item = markers.value[index]; r = (item?.type === 'group-token' ? 22 : item?.type === 'emoji' ? 14 : 18) * (item?.scale ?? 1); }
    else if (type === 'player') { item = players.value[index]; r = 20 * (item?.scale ?? 1); }
    else if (type === 'label') { item = textLabels.value[index]; r = 14 * (item?.scale ?? 1); }
    else if (type === 'shape') {
        const s = shapes.value[index]; if (!s) return null;
        const sc = s.scale ?? 1;
        if (s.type === 'circle') return { x: s.x, y: s.y, hw: s.radius * sc, hh: s.radius * sc };
        if (s.type === 'triangle') return { x: s.x, y: s.y, hw: s.radius * sc, hh: s.radius * sc };
        if (s.type === 'rect') return { x: s.x + (s.width * sc) / 2, y: s.y + (s.height * sc) / 2, hw: (s.width * sc) / 2, hh: (s.height * sc) / 2 };
        if (s.type === 'cone') return { x: s.x, y: s.y, hw: s.radius * sc * 0.6, hh: s.radius * sc * 0.6 };
        return null;
    }
    else if (type === 'arrow') {
        const a = arrows.value[index]; if (!a) return null;
        const cx = (a.from[0] + a.to[0]) / 2, cy = (a.from[1] + a.to[1]) / 2;
        const hw = Math.abs(a.to[0] - a.from[0]) / 2 + 8;
        const hh = Math.abs(a.to[1] - a.from[1]) / 2 + 8;
        return { x: cx, y: cy, hw, hh };
    }
    if (!item) return null;
    return { x: item.x, y: item.y, hw: r + 4, hh: r + 4 };
};

const selBox = computed(() => {
    if (multiSel.value.length === 0) return null;

    if (multiSel.value.length === 1) {
        const s = multiSel.value[0];
        const b = getItemBounds(s.type, s.index);
        if (!b) return null;
        const item = getElementPos(s.type, s.index);
        return { ...b, rotation: item?.rotation ?? 0, item, isGroup: false };
    }

    // Multi-selection: compute combined bounding box
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    multiSel.value.forEach(s => {
        const b = getItemBounds(s.type, s.index);
        if (!b) return;
        minX = Math.min(minX, b.x - b.hw); minY = Math.min(minY, b.y - b.hh);
        maxX = Math.max(maxX, b.x + b.hw); maxY = Math.max(maxY, b.y + b.hh);
    });
    if (minX === Infinity) return null;
    const cx = (minX + maxX) / 2, cy = (minY + maxY) / 2;
    return { x: cx, y: cy, hw: (maxX - minX) / 2, hh: (maxY - minY) / 2, rotation: 0, item: { x: cx, y: cy, scale: 1, rotation: 0 }, isGroup: true };
});

// ─── Handle positions for selection box ───
const handlePositions = computed(() => {
    const b = selBox.value;
    if (!b) return [];
    const rad = (b.rotation ?? 0) * Math.PI / 180;
    const cos = Math.cos(rad), sin = Math.sin(rad);
    const rot = (dx, dy) => ({ x: b.x + dx * cos - dy * sin, y: b.y + dx * sin + dy * cos });
    return [
        { id: 'tl', ...rot(-b.hw, -b.hh) }, { id: 'tr', ...rot(b.hw, -b.hh) },
        { id: 'bl', ...rot(-b.hw, b.hh) }, { id: 'br', ...rot(b.hw, b.hh) },
        { id: 'rot', ...rot(0, -b.hh - 20) },
    ];
});

// ─── Mouse handlers ───
const handleMouseDown = (e) => {
    if (e.button !== 0) return;
    if (isPanning.value) return;
    ctxMenu.value.show = false;
    if (!props.canManage) return;
    const point = getSvgPoint(e);

    // Handle resize/rotate handle drag
    if (resizing.value || rotating.value) return;

    if (props.hasPendingPlacement) { emit('action-start'); emit('place', { x: point.x, y: point.y }); return; }

    // Drawing tools
    if (props.activeTool === 'marker') {
        emit('action-start');
        emit('update', { markers: [...markers.value, { type: props.activeMarkerType, x: point.x, y: point.y, label: '', scale: 1, rotation: 0 }] });
        return;
    }
    if (props.activeTool === 'text') {
        emit('request-text', { x: point.x, y: point.y, color: props.shapeColor, screenX: e.clientX, screenY: e.clientY });
        return;
    }
    if (['circle', 'rect', 'triangle'].includes(props.activeTool)) {
        emit('action-start');
        isDrawing.value = true; drawStart.value = point;
        tempShape.value = { type: props.activeTool, x: point.x, y: point.y, width: 0, height: 0, radius: 0, color: props.shapeFilled ? props.shapeColor + '4D' : 'none', stroke: props.shapeColor, filled: props.shapeFilled };
        return;
    }
    if (props.activeTool === 'arrow') { emit('action-start'); isDrawing.value = true; drawStart.value = point; tempShape.value = { type: 'arrow', from: [point.x, point.y], to: [point.x, point.y], color: props.shapeColor }; return; }
    if (props.activeTool === 'line') { emit('action-start'); isDrawing.value = true; drawStart.value = point; tempShape.value = { type: 'line', from: [point.x, point.y], to: [point.x, point.y], color: props.shapeColor }; return; }
    if (props.activeTool === 'cone') { emit('action-start'); isDrawing.value = true; drawStart.value = point; tempShape.value = { type: 'cone', x: point.x, y: point.y, radius: 0, angle: 0, spread: 0.5, color: props.shapeFilled ? props.shapeColor + '40' : 'none', stroke: props.shapeColor, filled: props.shapeFilled }; return; }

    // Waypoint tool — add point to active path or start new
    if (props.activeTool === 'waypoint') {
        if (activeWaypath.value) {
            activeWaypath.value.points.push([point.x, point.y]);
        } else {
            emit('action-start');
            activeWaypath.value = { points: [[point.x, point.y]], color: props.shapeColor };
        }
        return;
    }

    // Shift+drag on empty = rectangle selection
    if (e.shiftKey) {
        clearSelection();
        rectSel.value = { startX: point.x, startY: point.y, x: point.x, y: point.y, w: 0, h: 0 };
        return;
    }

    // Click on empty canvas — start panning + deselect
    clearSelection();
    startPanFromEmpty(e);
};

const handleMouseMove = (e) => {
    // Dispatch drag position so floating panels can dodge out of the way
    if (dragging.value || resizing.value || rotating.value) {
        const now = performance.now();
        let dx = 0, dy = 0;
        if (prevDragPointer && now - prevDragPointer.t < 150) {
            dx = e.clientX - prevDragPointer.x;
            dy = e.clientY - prevDragPointer.y;
        }
        prevDragPointer = { x: e.clientX, y: e.clientY, t: now };
        window.dispatchEvent(new CustomEvent('bossplanner-drag-pointer', {
            detail: { x: e.clientX, y: e.clientY, dx, dy },
        }));
    }

    // Pan
    if (isPanning.value) { movePan(e); return; }

    // Rectangle selection drag
    if (rectSel.value) {
        const point = getSvgPoint(e);
        rectSel.value.x = Math.min(rectSel.value.startX, point.x);
        rectSel.value.y = Math.min(rectSel.value.startY, point.y);
        rectSel.value.w = Math.abs(point.x - rectSel.value.startX);
        rectSel.value.h = Math.abs(point.y - rectSel.value.startY);
        return;
    }

    // Auto-scroll when dragging near edges
    doEdgeScroll(e);

    const point = getSvgPoint(e);

    // Resize handle drag — apply to all selected (batch)
    if (resizing.value) {
        const r = resizing.value;
        const currentDist = Math.hypot(point.x - r.centerX, point.y - r.centerY);
        const factor = Math.max(0.2, Math.min(5, currentDist / r.origDist));
        const changes = [];
        (r.origScales || []).forEach(({ type, index, scale }) => {
            changes.push({ type, index, patch: { scale: Math.round(scale * factor * 100) / 100 } });
        });
        if (changes.length) batchUpdate(changes);
        return;
    }

    // Rotation handle drag — rotate all around stored center (batch)
    if (rotating.value) {
        const rv = rotating.value;
        const angle = Math.atan2(point.y - rv.centerY, point.x - rv.centerX) * 180 / Math.PI + 90;
        const delta = angle - rv.startAngle;
        const rad = delta * Math.PI / 180;
        const cos = Math.cos(rad), sin = Math.sin(rad);
        const changes = [];
        rv.origPositions.forEach(({ type, index, x, y, rotation }) => {
            const dx = x - rv.centerX, dy = y - rv.centerY;
            changes.push({ type, index, patch: {
                x: rv.centerX + dx * cos - dy * sin,
                y: rv.centerY + dx * sin + dy * cos,
                rotation: Math.round(((rotation + delta) % 360 + 360) % 360),
            }});
        });
        if (changes.length) batchUpdate(changes);
        return;
    }

    // Normal drag — move all selected together (batch)
    if (dragging.value) {
        const { type, index } = dragging.value;
        const newX = point.x - dragOffset.value.x;
        const newY = point.y - dragOffset.value.y;
        if (type === 'group') {
            const dx = newX - dragging.value.startX, dy = newY - dragging.value.startY;
            const updated = [...players.value];
            groupDragStartPositions.value.forEach(({ idx, ox, oy }) => { updated[idx] = { ...updated[idx], x: ox + dx, y: oy + dy }; });
            emit('update', { players: updated });
        } else if (multiSel.value.length > 1) {
            const dv = dragging.value;
            const dx = newX - dv.origX, dy = newY - dv.origY;
            const changes = dv.origPositions.map(({ type: t, index: i, x: ox, y: oy }) => ({
                type: t, index: i, patch: { x: ox + dx, y: oy + dy },
            }));
            batchUpdate(changes);
        } else {
            updateElementProp(type, index, { x: newX, y: newY });
        }
        return;
    }

    // Drawing
    if (isDrawing.value && tempShape.value) {
        const t = tempShape.value;
        if (t.type === 'circle' || t.type === 'triangle') { t.radius = Math.hypot(point.x - drawStart.value.x, point.y - drawStart.value.y); t.x = drawStart.value.x; t.y = drawStart.value.y; }
        else if (t.type === 'rect') { t.x = Math.min(drawStart.value.x, point.x); t.y = Math.min(drawStart.value.y, point.y); t.width = Math.abs(point.x - drawStart.value.x); t.height = Math.abs(point.y - drawStart.value.y); }
        else if (t.type === 'arrow' || t.type === 'line') { t.to = [point.x, point.y]; }
        else if (t.type === 'cone') { t.radius = Math.hypot(point.x - drawStart.value.x, point.y - drawStart.value.y); t.angle = Math.atan2(point.y - drawStart.value.y, point.x - drawStart.value.x); }
    }
};

const handleMouseUp = () => {
    // Finalize rectangle selection
    if (rectSel.value) {
        const r = rectSel.value;
        if (r.w > 5 && r.h > 5) {
            // Find all elements inside the rectangle
            const inRect = (x, y) => x >= r.x && x <= r.x + r.w && y >= r.y && y <= r.y + r.h;
            const selected = [];
            markers.value.forEach((m, i) => { if (inRect(m.x, m.y)) selected.push({ type: 'marker', index: i }); });
            players.value.forEach((p, i) => { if (!p.group && inRect(p.x, p.y)) selected.push({ type: 'player', index: i }); });
            shapes.value.forEach((s, i) => { if (inRect(s.x, s.y)) selected.push({ type: 'shape', index: i }); });
            textLabels.value.forEach((l, i) => { if (inRect(l.x, l.y)) selected.push({ type: 'label', index: i }); });
            arrows.value.forEach((a, i) => { const cx = (a.from[0]+a.to[0])/2, cy = (a.from[1]+a.to[1])/2; if (inRect(cx, cy)) selected.push({ type: 'arrow', index: i }); });
            // Also select group tokens
            groupTokens.value.forEach(gt => {
                if (inRect(gt.x, gt.y)) {
                    // Select all group members
                    players.value.forEach((p, i) => { if (p.group === gt.id) selected.push({ type: 'player', index: i }); });
                }
            });
            if (selected.length > 0) {
                multiSel.value = selected;
                sel.type = selected[0].type; sel.index = selected[0].index;
                emit('selection-change', { type: sel.type, index: sel.index, count: selected.length, items: selected });
            }
        }
        rectSel.value = null;
        return;
    }
    if (resizing.value) { resizing.value = null; return; }
    if (rotating.value) { rotating.value = null; return; }
    if (dragging.value) { dragging.value = null; groupDragStartPositions.value = []; return; }
    if (isDrawing.value && tempShape.value) {
        isDrawing.value = false;
        const t = tempShape.value;
        if (t.type === 'arrow') emit('update', { arrows: [...arrows.value, { from: t.from, to: t.to, color: t.color }] });
        else if (t.type === 'line') emit('update', { arrows: [...arrows.value, { from: t.from, to: t.to, color: t.color, noHead: true }] });
        else emit('update', { shapes: [...shapes.value, { ...t, scale: 1, rotation: 0 }] });
        tempShape.value = null;
    }
};

// ─── Update element properties ───
const typeKeyMap = { player: 'players', marker: 'markers', label: 'labels', shape: 'shapes', arrow: 'arrows' };
const typeArrMap = () => ({ player: players.value, marker: markers.value, label: textLabels.value, shape: shapes.value, arrow: arrows.value });

const updateElementProp = (type, index, patch) => {
    const key = typeKeyMap[type];
    if (!key) return;
    const arr = typeArrMap()[type];
    if (!arr || !arr[index]) return;
    const updated = [...arr];
    if (type === 'arrow' && (patch.x !== undefined || patch.y !== undefined)) {
        // Translate x/y to from/to shift
        const a = updated[index];
        const cx = (a.from[0] + a.to[0]) / 2, cy = (a.from[1] + a.to[1]) / 2;
        const dx = (patch.x ?? cx) - cx, dy = (patch.y ?? cy) - cy;
        updated[index] = { ...a, from: [a.from[0] + dx, a.from[1] + dy], to: [a.to[0] + dx, a.to[1] + dy], ...(patch.scale !== undefined ? { scale: patch.scale } : {}), ...(patch.rotation !== undefined ? { rotation: patch.rotation } : {}) };
    } else {
        updated[index] = { ...updated[index], ...patch };
    }
    emit('update', { [key]: updated });
};

// Batch update multiple elements in one emit
const batchUpdate = (changes) => {
    const arrCopies = {};
    for (const { type, index, patch } of changes) {
        const key = typeKeyMap[type];
        if (!key) continue;
        if (!arrCopies[key]) arrCopies[key] = [...typeArrMap()[type]];
        if (!arrCopies[key][index]) continue;
        if (type === 'arrow' && (patch.x !== undefined || patch.y !== undefined)) {
            const a = arrCopies[key][index];
            const cx = (a.from[0] + a.to[0]) / 2, cy = (a.from[1] + a.to[1]) / 2;
            const dx = (patch.x ?? cx) - cx, dy = (patch.y ?? cy) - cy;
            arrCopies[key][index] = { ...a, from: [a.from[0] + dx, a.from[1] + dy], to: [a.to[0] + dx, a.to[1] + dy],
                ...(patch.scale !== undefined ? { scale: patch.scale } : {}),
                ...(patch.rotation !== undefined ? { rotation: patch.rotation } : {}) };
        } else {
            arrCopies[key][index] = { ...arrCopies[key][index], ...patch };
        }
    }
    if (Object.keys(arrCopies).length) emit('update', arrCopies);
};

// Arrow head: filled triangle at end, line shortened so it doesn't poke through
const arrowHeadSize = 12;
const arrowHeadPoints = (arrow) => {
    const dx = arrow.to[0] - arrow.from[0];
    const dy = arrow.to[1] - arrow.from[1];
    const len = Math.hypot(dx, dy) || 1;
    const ux = dx / len, uy = dy / len; // unit direction
    const px = -uy, py = ux; // perpendicular
    // Tip at arrow.to, base pulled back
    const tx = arrow.to[0], ty = arrow.to[1];
    const bx = tx - ux * arrowHeadSize, by = ty - uy * arrowHeadSize;
    const hw = arrowHeadSize * 0.45; // half-width
    return `${tx},${ty} ${bx + px * hw},${by + py * hw} ${bx - px * hw},${by - py * hw}`;
};
const arrowShortenedEnd = (arrow) => {
    const dx = arrow.to[0] - arrow.from[0];
    const dy = arrow.to[1] - arrow.from[1];
    const len = Math.hypot(dx, dy) || 1;
    const shorten = Math.min(arrowHeadSize * 0.7, len * 0.4);
    return { x: arrow.to[0] - (dx / len) * shorten, y: arrow.to[1] - (dy / len) * shorten };
};

// Triangle: equilateral, centered at cx,cy with given radius (circumradius)
const trianglePath = (cx, cy, radius) => {
    const a1 = -Math.PI / 2, a2 = a1 + 2 * Math.PI / 3, a3 = a1 + 4 * Math.PI / 3;
    return `${cx + Math.cos(a1) * radius},${cy + Math.sin(a1) * radius} ${cx + Math.cos(a2) * radius},${cy + Math.sin(a2) * radius} ${cx + Math.cos(a3) * radius},${cy + Math.sin(a3) * radius}`;
};

const conePath = (cx, cy, radius, angle, spread = 0.5) => {
    const s = spread;
    const x1 = cx + Math.cos(angle - s) * radius, y1 = cy + Math.sin(angle - s) * radius;
    const x2 = cx + Math.cos(angle + s) * radius, y2 = cy + Math.sin(angle + s) * radius;
    const largeArc = s > Math.PI / 2 ? 1 : 0;
    return `M ${cx} ${cy} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`;
};

// ─── Element interactions ───
const startDrag = (e, type, index, item) => {
    if (e.button !== 0 || !props.canManage) return;
    // Locked elements can be selected but not dragged
    if (item.locked) { e.stopPropagation(); selectElement(type, index, e.ctrlKey || e.metaKey); return; }
    e.stopPropagation();
    const isCtrl = e.ctrlKey || e.metaKey;
    selectElement(type, index, isCtrl);

    // Don't start drag if ctrl-clicking (multi-select only)
    if (isCtrl) return;

    emit('action-start');
    const point = getSvgPoint(e);
    // Store original positions of all selected for multi-drag
    const origPositions = multiSel.value.map(s => {
        const el = getElementPos(s.type, s.index);
        return { type: s.type, index: s.index, x: el?.x ?? 0, y: el?.y ?? 0 };
    });
    dragging.value = { type, index, origX: item.x, origY: item.y, origPositions };
    dragOffset.value = { x: point.x - item.x, y: point.y - item.y };
};

const startGroupDrag = (e, groupId, gt) => {
    if (e.button !== 0 || !props.canManage || isDrawingTool.value) return;
    e.stopPropagation();
    emit('action-start');
    clearSelection();
    const point = getSvgPoint(e);
    const positions = [];
    players.value.forEach((p, idx) => { if (p.group === groupId) positions.push({ idx, ox: p.x, oy: p.y }); });
    groupDragStartPositions.value = positions;
    dragging.value = { type: 'group', index: groupId, startX: gt.x, startY: gt.y };
    dragOffset.value = { x: point.x - gt.x, y: point.y - gt.y };
};

// ─── Resize handle drag ───
const startResize = (e, handleId) => {
    if (e.button !== 0) return;
    e.stopPropagation();
    emit('action-start');
    const point = getSvgPoint(e);
    const box = selBox.value;
    if (!box) return;
    // Store original scales of all selected elements
    const origScales = multiSel.value.map(s => {
        const el = getElementPos(s.type, s.index);
        return { type: s.type, index: s.index, scale: el?.scale ?? 1 };
    });
    resizing.value = {
        handle: handleId,
        centerX: box.x, centerY: box.y,
        origDist: Math.hypot(point.x - box.x, point.y - box.y) || 1,
        origScales,
    };
};

// ─── Rotation handle drag ───
const startRotate = (e) => {
    if (e.button !== 0) return;
    e.stopPropagation();
    emit('action-start');
    const box = selBox.value;
    if (!box) return;
    const point = getSvgPoint(e);
    const startAngle = Math.atan2(point.y - box.y, point.x - box.x) * 180 / Math.PI + 90;
    const origPositions = multiSel.value.map(s => {
        const el = getElementPos(s.type, s.index);
        return { type: s.type, index: s.index, x: el?.x ?? 0, y: el?.y ?? 0, rotation: el?.rotation ?? 0 };
    });
    // Store center at start — won't change during drag
    rotating.value = { startAngle, origPositions, centerX: box.x, centerY: box.y };
};

// ─── Context menu ───
const handleContextMenu = (e, type, index) => {
    if (!props.canManage) return;
    e.preventDefault(); e.stopPropagation();
    // If the element is not already in multi-selection, select only it
    // If it IS in multi-selection, keep the full selection intact
    if (!isSelected(type, index)) {
        selectElement(type, index);
    }
    ctxMenu.value = { show: true, x: e.clientX, y: e.clientY, type, index };
};

const deleteFromCtx = () => {
    const { type, index } = ctxMenu.value;
    // If multiple selected — delete all
    if (multiSel.value.length > 1) {
        deleteSelected();
    } else if (type === 'group') {
        emit('update', { players: players.value.filter(p => p.group !== index) });
    } else {
        // Single delete
        const typeMap = { player: 'players', marker: 'markers', label: 'labels', shape: 'shapes', arrow: 'arrows' };
        const key = typeMap[type];
        if (key) {
            const arr = { player: players, marker: markers, label: textLabels, shape: shapes, arrow: arrows }[type]?.value;
            if (arr) emit('update', { [key]: arr.filter((_, i) => i !== index) });
        }
        clearSelection();
    }
    ctxMenu.value.show = false;
};

const addLabelFromCtx = () => {
    const { type, index } = ctxMenu.value;
    const text = prompt('Enter label:');
    if (!text) { ctxMenu.value.show = false; return; }
    if (type === 'marker') updateElementProp('marker', index, { label: text });
    else if (type === 'player') updateElementProp('player', index, { customLabel: text });
    ctxMenu.value.show = false;
};

// Check if context-menu target has a linkGroup
const ctxSelectedHasLinkGroup = computed(() => {
    const { type, index } = ctxMenu.value;
    const item = type === 'marker' ? markers.value[index]
        : type === 'player' ? players.value[index]
        : null;
    return item?.linkGroup != null;
});

const ungroupFromCtx = () => {
    const { type, index } = ctxMenu.value;
    const item = type === 'marker' ? markers.value[index]
        : type === 'player' ? players.value[index]
        : null;
    if (!item?.linkGroup) { ctxMenu.value.show = false; return; }

    const linkId = item.linkGroup;
    const patch = {};
    // Remove linkGroup from all elements with same linkId
    const updatedMarkers = markers.value.map(m => m.linkGroup === linkId ? { ...m, linkGroup: null } : m);
    const updatedPlayers = players.value.map(p => p.linkGroup === linkId ? { ...p, linkGroup: null } : p);
    if (updatedMarkers.some((m, i) => m !== markers.value[i])) patch.markers = updatedMarkers;
    if (updatedPlayers.some((p, i) => p !== players.value[i])) patch.players = updatedPlayers;
    if (Object.keys(patch).length) emit('update', patch);
    ctxMenu.value.show = false;
};

const toggleLockFromCtx = () => {
    const { type, index } = ctxMenu.value;
    const el = getElementPos(type, index);
    if (el) updateElementProp(type, index, { locked: !el.locked });
    ctxMenu.value.show = false;
};

const ctxElementLocked = computed(() => {
    const { type, index } = ctxMenu.value;
    const el = getElementPos(type, index);
    return el?.locked ?? false;
});

// Double-click label for inline text edit
const handleDblClickLabel = (e, index) => {
    e.stopPropagation();
    emit('request-text', {
        x: textLabels.value[index].x,
        y: textLabels.value[index].y,
        color: textLabels.value[index].color,
        screenX: e.clientX,
        screenY: e.clientY,
        editIndex: index,
    });
};

const closeCtx = () => { ctxMenu.value.show = false; };

const handleDrop = (e) => {
    e.preventDefault();
    try {
        const data = JSON.parse(e.dataTransfer.getData('application/json'));
        const point = getSvgPoint(e);
        emit('update', { players: [...players.value, {
            character_id: data.id, character_name: data.name,
            class_name: data.playable_class, role: data.assigned_role || data.main_spec?.role || 'rdps',
            x: point.x, y: point.y, scale: 1, rotation: 0,
        }] });
    } catch (err) { /* ignore */ }
};
const handleDragOver = (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; };

defineExpose({ svgRef });
</script>

<template>
    <div ref="containerRef" class="relative w-full h-full overflow-hidden bg-[#0a0a0c]"
        @drop="handleDrop" @dragover="handleDragOver" @click="closeCtx">

        <!-- Zoom controls overlay -->
        <div class="absolute top-2 right-2 z-10 flex items-center gap-1 bg-black/60 backdrop-blur-sm rounded-lg p-1 border border-white/10">
            <!-- Map variants -->
            <template v-if="maps.length > 1">
                <button v-for="(m, i) in maps" :key="i" @click="selectedMapIndex = i"
                    class="px-2 py-0.5 rounded text-5xs font-bold transition-all"
                    :class="selectedMapIndex === i ? 'bg-orange-500/20 text-orange-400' : 'text-on-surface-variant/40 hover:text-white'">{{ m.label }}</button>
                <div class="w-px h-4 bg-white/10 mx-0.5"></div>
            </template>
            <span class="text-4xs font-bold text-on-surface-variant/50 px-1 min-w-[36px] text-center">{{ zoomLevel }}%</span>
            <button @click="resetView" class="w-6 h-6 rounded flex items-center justify-center text-on-surface-variant/50 hover:text-white hover:bg-white/10 transition-all" :title="__('Reset zoom')">
                <span class="material-symbols-outlined text-sm">fit_screen</span>
            </button>
        </div>

        <svg ref="svgRef" :viewBox="`${viewBox.x} ${viewBox.y} ${viewBox.w} ${viewBox.h}`"
            class="w-full h-full select-none outline-none"
            tabindex="0"
            :class="{
                'cursor-crosshair': isDrawingTool || activeTool === 'marker' || hasPendingPlacement,
                'cursor-grab': !isDrawingTool && !hasPendingPlacement && activeTool === 'select' && !dragging && !isPanning,
                'cursor-grabbing': isPanning,
            }"
            @mousedown="(e) => { startPan(e); handleMouseDown(e); }"
            @mousemove="handleMouseMove"
            @mouseup="(e) => { endPan(); handleMouseUp(); }"
            @mouseleave="(e) => { endPan(); handleMouseUp(); }"
            @wheel.prevent="handleWheel"
            @keydown="handleKeyDown"
            @dblclick="finishWaypath"
            @contextmenu.prevent>
            <defs>
                <clipPath id="circleClip" clipPathUnits="objectBoundingBox"><circle cx="0.5" cy="0.5" r="0.5"/></clipPath>
                <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern>
            </defs>

            <!-- BG -->
            <rect width="100%" height="100%" fill="rgba(10,10,12,1)" />
            <image v-if="currentMapUrl" :href="currentMapUrl" x="0" y="0" :width="canvasWidth" :height="canvasHeight" preserveAspectRatio="xMidYMid slice" opacity="0.9"/>
            <rect v-else width="100%" height="100%" fill="url(#grid)" />
            <rect v-if="currentMapUrl" width="100%" height="100%" fill="url(#grid)" opacity="0.3" />
            <line :x1="canvasWidth/2" y1="0" :x2="canvasWidth/2" :y2="canvasHeight" stroke="rgba(255,255,255,0.03)" stroke-width="1" stroke-dasharray="8"/>
            <line x1="0" :y1="canvasHeight/2" :x2="canvasWidth" :y2="canvasHeight/2" stroke="rgba(255,255,255,0.03)" stroke-width="1" stroke-dasharray="8"/>

            <!-- Shapes -->
            <g v-for="(shape, i) in shapes" :key="'s-' + i"
               :style="itemTransformStyle(shape)" :opacity="shape.opacity ?? 1"
               @mousedown="startDrag($event, 'shape', i, shape)"
               @contextmenu="handleContextMenu($event, 'shape', i)" class="cursor-grab">
                <!-- Selection glow (behind) -->
                <template v-if="isSelected('shape', i)">
                    <circle v-if="shape.type === 'circle'" :cx="shape.x" :cy="shape.y" :r="shape.radius * (shape.scale ?? 1) + 4" fill="none" stroke="rgba(59,130,246,0.4)" stroke-width="6"/>
                    <rect v-else-if="shape.type === 'rect'" :x="shape.x - 3" :y="shape.y - 3" :width="shape.width * (shape.scale ?? 1) + 6" :height="shape.height * (shape.scale ?? 1) + 6" fill="none" stroke="rgba(59,130,246,0.4)" stroke-width="6" rx="6"/>
                    <polygon v-else-if="shape.type === 'triangle'" :points="trianglePath(shape.x, shape.y, shape.radius * (shape.scale ?? 1) + 4)" fill="none" stroke="rgba(59,130,246,0.4)" stroke-width="6"/>
                    <path v-else-if="shape.type === 'cone'" :d="conePath(shape.x, shape.y, shape.radius * (shape.scale ?? 1) + 3, shape.angle, shape.spread)" fill="none" stroke="rgba(59,130,246,0.4)" stroke-width="6"/>
                </template>
                <!-- Actual shape -->
                <circle v-if="shape.type === 'circle'" :cx="shape.x" :cy="shape.y" :r="shape.radius * (shape.scale ?? 1)" :fill="shape.color" :stroke="shape.stroke" stroke-width="2"/>
                <rect v-else-if="shape.type === 'rect'" :x="shape.x" :y="shape.y" :width="shape.width * (shape.scale ?? 1)" :height="shape.height * (shape.scale ?? 1)" :fill="shape.color" :stroke="shape.stroke" stroke-width="2" rx="4"/>
                <polygon v-else-if="shape.type === 'triangle'" :points="trianglePath(shape.x, shape.y, shape.radius * (shape.scale ?? 1))" :fill="shape.color" :stroke="shape.stroke" stroke-width="2" stroke-linejoin="round"/>
                <path v-else-if="shape.type === 'cone'" :d="conePath(shape.x, shape.y, shape.radius * (shape.scale ?? 1), shape.angle, shape.spread)" :fill="shape.color" :stroke="shape.stroke" stroke-width="2"/>
            </g>

            <!-- Temp shape -->
            <g v-if="tempShape" opacity="0.6">
                <circle v-if="tempShape.type === 'circle'" :cx="tempShape.x" :cy="tempShape.y" :r="tempShape.radius" :fill="tempShape.color" :stroke="tempShape.stroke" stroke-width="2"/>
                <rect v-else-if="tempShape.type === 'rect'" :x="tempShape.x" :y="tempShape.y" :width="tempShape.width" :height="tempShape.height" :fill="tempShape.color" :stroke="tempShape.stroke" stroke-width="2" rx="4"/>
                <polygon v-else-if="tempShape.type === 'triangle'" :points="trianglePath(tempShape.x, tempShape.y, tempShape.radius)" :fill="tempShape.color" :stroke="tempShape.stroke" stroke-width="2" stroke-linejoin="round"/>
                <g v-else-if="['arrow','line'].includes(tempShape.type)">
                    <line :x1="tempShape.from[0]" :y1="tempShape.from[1]"
                        :x2="tempShape.type === 'arrow' ? arrowShortenedEnd(tempShape).x : tempShape.to[0]"
                        :y2="tempShape.type === 'arrow' ? arrowShortenedEnd(tempShape).y : tempShape.to[1]"
                        :stroke="tempShape.color" stroke-width="2.5" stroke-linecap="round"/>
                    <polygon v-if="tempShape.type === 'arrow'"
                        :points="arrowHeadPoints(tempShape)" :fill="tempShape.color"/>
                </g>
                <path v-else-if="tempShape.type === 'cone'" :d="conePath(tempShape.x, tempShape.y, tempShape.radius, tempShape.angle, tempShape.spread)" :fill="tempShape.color" :stroke="tempShape.stroke" stroke-width="2"/>
            </g>

            <!-- Arrows & Lines -->
            <g v-for="(arrow, i) in arrows" :key="'a-' + i"
                :transform="arrow.rotation ? `rotate(${arrow.rotation} ${(arrow.from[0]+arrow.to[0])/2} ${(arrow.from[1]+arrow.to[1])/2})` : undefined"
                @mousedown="startDrag($event, 'arrow', i, { x: (arrow.from[0]+arrow.to[0])/2, y: (arrow.from[1]+arrow.to[1])/2 })"
                @contextmenu="handleContextMenu($event, 'arrow', i)" class="cursor-grab">
                <line :x1="arrow.from[0]" :y1="arrow.from[1]"
                    :x2="arrow.noHead ? arrow.to[0] : arrowShortenedEnd(arrow).x"
                    :y2="arrow.noHead ? arrow.to[1] : arrowShortenedEnd(arrow).y"
                    :stroke="arrow.color || '#FFFFFF'" :stroke-width="isSelected('arrow', i) ? 3.5 : 2.5" stroke-linecap="round"/>
                <polygon v-if="!arrow.noHead"
                    :points="arrowHeadPoints(arrow)"
                    :fill="arrow.color || '#FFFFFF'"/>
                <!-- Selection glow -->
                <line v-if="isSelected('arrow', i)"
                    :x1="arrow.from[0]" :y1="arrow.from[1]" :x2="arrow.to[0]" :y2="arrow.to[1]"
                    stroke="rgba(59,130,246,0.4)" stroke-width="8" stroke-linecap="round"/>
                <!-- Invisible wider hit area for easier clicking -->
                <line :x1="arrow.from[0]" :y1="arrow.from[1]" :x2="arrow.to[0]" :y2="arrow.to[1]"
                    stroke="transparent" stroke-width="12"/>
            </g>

            <!-- Markers -->
            <g v-for="(marker, i) in markers" :key="'m-' + i"
               :transform="(marker.rotation ?? 0) !== 0 ? `rotate(${marker.rotation} ${marker.x} ${marker.y})` : undefined"
               :opacity="highlightedMarkers.size > 0 && !highlightedMarkers.has(i) ? 0.3 : (marker.opacity ?? 1)"
               @mousedown="startDrag($event, 'marker', i, marker)"
               @contextmenu="handleContextMenu($event, 'marker', i)" class="cursor-grab">
                <!-- Emoji -->
                <template v-if="marker.type === 'emoji'">
                    <text :x="marker.x" :y="marker.y + 2" text-anchor="middle" dominant-baseline="central"
                        :font-size="24 * (marker.scale ?? 1)"
                        :stroke="isSelected('marker', i) ? '#3B82F6' : 'none'" :stroke-width="isSelected('marker', i) ? 1 : 0"
                        style="font-family: 'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif;">{{ marker.emoji }}</text>
                </template>
                <template v-else-if="marker.type === 'group-token'">
                    <circle :cx="marker.x" :cy="marker.y" :r="20 * (marker.scale ?? 1)" fill="rgba(0,0,0,0.5)" :stroke="isSelected('marker', i) ? '#fff' : marker.color" :stroke-width="isSelected('marker', i) ? 3 : 2.5"/>
                    <path v-if="marker.showDirection !== false" :d="dirChevron(marker.x, marker.y, 20, marker.scale ?? 1)" fill="none" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
                    <text :x="marker.x" :y="marker.y + 1" text-anchor="middle" dominant-baseline="middle" :fill="marker.color" :font-size="12 * (marker.scale ?? 1)" font-weight="900" style="font-family: system-ui;">{{ marker.groupLabel }}</text>
                </template>
                <template v-else-if="marker.type === 'icon'">
                    <circle :cx="marker.x" :cy="marker.y" :r="16 * (marker.scale ?? 1)" fill="rgba(0,0,0,0.6)" :stroke="isSelected('marker', i) ? '#fff' : 'rgba(255,255,255,0.2)'" :stroke-width="isSelected('marker', i) ? 3 : 1.5"/>
                    <path v-if="marker.showDirection !== false && !marker.isAbility" :d="dirChevron(marker.x, marker.y, 16, marker.scale ?? 1)" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
                    <image :href="marker.src" :x="marker.x - 11 * (marker.scale ?? 1)" :y="marker.y - 11 * (marker.scale ?? 1)" :width="22 * (marker.scale ?? 1)" :height="22 * (marker.scale ?? 1)" clip-path="url(#circleClip)"/>
                </template>
                <template v-else>
                    <circle :cx="marker.x" :cy="marker.y" :r="16 * (marker.scale ?? 1)" fill="rgba(0,0,0,0.5)" :stroke="isSelected('marker', i) ? '#fff' : 'rgba(255,255,255,0.15)'" :stroke-width="isSelected('marker', i) ? 3 : 1.5"/>
                    <path v-if="marker.showDirection" :d="dirChevron(marker.x, marker.y, 16, marker.scale ?? 1)" fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
                    <image :href="markerImages[marker.type] || markerImages.skull" :x="marker.x - 11 * (marker.scale ?? 1)" :y="marker.y - 11 * (marker.scale ?? 1)" :width="22 * (marker.scale ?? 1)" :height="22 * (marker.scale ?? 1)" clip-path="url(#circleClip)"/>
                </template>
                <!-- Label: roster players show name in class color, others show custom label in white -->
                <text v-if="marker.playerData" :x="marker.x" :y="marker.y + 22 * (marker.scale ?? 1)"
                    text-anchor="middle" dominant-baseline="middle"
                    :fill="classColors[marker.playerData.className] || '#fff'" font-size="9" font-weight="700"
                    style="font-family: system-ui;" paint-order="stroke" stroke="#000" stroke-width="3">{{ marker.label || marker.playerData.name }}</text>
                <text v-else-if="marker.label" :x="marker.x" :y="marker.y + 22 * (marker.scale ?? 1)"
                    text-anchor="middle" dominant-baseline="middle" fill="white" font-size="9" font-weight="700"
                    style="font-family: system-ui;" paint-order="stroke" stroke="#000" stroke-width="3">{{ marker.label }}</text>
            </g>

            <!-- Highlight pulse rings for "Show Me" -->
            <g v-for="(marker, i) in markers" :key="'hl-' + i">
                <circle v-if="highlightedMarkers.has(i)"
                    :cx="marker.x" :cy="marker.y" r="24"
                    fill="none" stroke="#FBBF24" stroke-width="3" opacity="0.8">
                    <animate attributeName="r" from="20" to="30" dur="1s" repeatCount="indefinite"/>
                    <animate attributeName="opacity" from="0.8" to="0" dur="1s" repeatCount="indefinite"/>
                </circle>
            </g>

            <!-- Players (ungrouped) -->
            <g v-for="(player, i) in players" :key="'p-' + i" v-show="!player.group"
               :transform="`rotate(${player.rotation ?? 0} ${player.x} ${player.y})`"
               @mousedown="startDrag($event, 'player', i, player)"
               @contextmenu="handleContextMenu($event, 'player', i)" class="cursor-grab">
                <circle :cx="player.x" :cy="player.y" :r="18 * (player.scale ?? 1)"
                    :fill="getClassColor(player.class_name) + '40'"
                    :stroke="isSelected('player', i) ? '#fff' : getClassColor(player.class_name)" :stroke-width="isSelected('player', i) ? 3 : 2"/>
                <path v-if="player.showDirection !== false" :d="dirChevron(player.x, player.y, 18, player.scale ?? 1)"
                    fill="none" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.9"/>
                <text :x="player.x" :y="player.y - 1" text-anchor="middle" dominant-baseline="middle"
                    :fill="getClassColor(player.class_name)" :font-size="11 * (player.scale ?? 1)" font-weight="900" style="font-family: system-ui;">{{ roleAbbr[player.role] || 'D' }}</text>
                <text :x="player.x" :y="player.y + 10 * (player.scale ?? 1)" text-anchor="middle" dominant-baseline="middle"
                    :fill="getClassColor(player.class_name)" :font-size="8 * (player.scale ?? 1)" font-weight="700" style="font-family: system-ui;" opacity="0.9">{{ player.customLabel || (player.character_name || '').substring(0, 8) }}</text>
            </g>

            <!-- Group tokens -->
            <g v-for="gt in groupTokens" :key="'gt-' + gt.id"
               @mousedown="startGroupDrag($event, gt.id, gt)"
               @contextmenu="handleContextMenu($event, 'group', gt.id)" class="cursor-grab">
                <circle :cx="gt.x" :cy="gt.y" r="24" fill="rgba(0,0,0,0.5)" :stroke="gt.color" stroke-width="3"/>
                <text :x="gt.x" :y="gt.y - 2" text-anchor="middle" dominant-baseline="middle" :fill="gt.color" font-size="14" font-weight="900" style="font-family: system-ui;">{{ gt.label }}</text>
                <text :x="gt.x" :y="gt.y + 11" text-anchor="middle" dominant-baseline="middle" fill="rgba(255,255,255,0.6)" font-size="8" font-weight="700" style="font-family: system-ui;">{{ gt.count }}p</text>
            </g>

            <!-- Text labels -->
            <text v-for="(label, i) in textLabels" :key="'l-' + i"
                :x="label.x" :y="label.y" text-anchor="middle" dominant-baseline="middle"
                :fill="label.color || '#FFFFFF'" :font-size="(label.fontSize || 14) * (label.scale ?? 1)"
                :opacity="label.opacity ?? 1"
                font-weight="700" style="font-family: system-ui;"
                :transform="`rotate(${label.rotation ?? 0} ${label.x} ${label.y})`"
                @mousedown="startDrag($event, 'label', i, label)"
                @dblclick.stop="handleDblClickLabel($event, i)"
                @contextmenu="handleContextMenu($event, 'label', i)"
                :stroke="isSelected('label', i) ? '#fff' : 'none'" :stroke-width="isSelected('label', i) ? 0.5 : 0"
                class="cursor-grab">{{ label.text }}</text>

            <!-- ═══ Selection handles ═══ -->
            <g v-if="selBox && canManage">
                <!-- Bounding box (dashed) -->
                <rect
                    :x="selBox.x - selBox.hw" :y="selBox.y - selBox.hh"
                    :width="selBox.hw * 2" :height="selBox.hh * 2"
                    fill="none" stroke="rgba(59,130,246,0.6)" stroke-width="1.5" stroke-dasharray="4 3"
                    :transform="`rotate(${selBox.rotation} ${selBox.x} ${selBox.y})`"
                />
                <!-- Center point (visible for groups) -->
                <g v-if="selBox.isGroup">
                    <line :x1="selBox.x - 6" :y1="selBox.y" :x2="selBox.x + 6" :y2="selBox.y" stroke="rgba(59,130,246,0.8)" stroke-width="1.5"/>
                    <line :x1="selBox.x" :y1="selBox.y - 6" :x2="selBox.x" :y2="selBox.y + 6" stroke="rgba(59,130,246,0.8)" stroke-width="1.5"/>
                </g>
                <!-- Corner resize handles -->
                <circle v-for="h in handlePositions.filter(h => h.id !== 'rot')" :key="h.id"
                    :cx="h.x" :cy="h.y" r="5"
                    fill="#3B82F6" stroke="white" stroke-width="1.5"
                    class="cursor-nwse-resize"
                    @mousedown.stop="startResize($event, h.id)"/>
                <!-- Rotation handle -->
                <circle v-if="handlePositions.find(h => h.id === 'rot')"
                    :cx="handlePositions.find(h => h.id === 'rot').x"
                    :cy="handlePositions.find(h => h.id === 'rot').y" r="5"
                    fill="#10B981" stroke="white" stroke-width="1.5"
                    style="cursor: url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2224%22 height=%2224%22 viewBox=%220 0 24 24%22><path fill=%22white%22 stroke=%22black%22 stroke-width=%221%22 d=%22M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z%22/></svg>') 12 12, grab;"
                    @mousedown.stop="startRotate($event)"/>
            </g>

            <!-- Active waypoint path preview -->
            <g v-if="activeWaypath && activeWaypath.points.length >= 1" opacity="0.7">
                <template v-for="(pt, pi) in activeWaypath.points" :key="'wp-' + pi">
                    <line v-if="pi > 0"
                        :x1="activeWaypath.points[pi-1][0]" :y1="activeWaypath.points[pi-1][1]"
                        :x2="pt[0]" :y2="pt[1]"
                        :stroke="activeWaypath.color" stroke-width="2" stroke-dasharray="6 3"/>
                    <circle :cx="pt[0]" :cy="pt[1]" r="4" :fill="activeWaypath.color" stroke="white" stroke-width="1"/>
                </template>
            </g>

            <!-- Rectangle selection visual -->
            <rect v-if="rectSel && rectSel.w > 0"
                :x="rectSel.x" :y="rectSel.y" :width="rectSel.w" :height="rectSel.h"
                fill="rgba(59,130,246,0.1)" stroke="rgba(59,130,246,0.6)" stroke-width="1" stroke-dasharray="6 3"/>

            <!-- Empty state -->
            <text v-if="!players.length && !markers.length && !shapes.length && !arrows.length && !textLabels.length && !groupTokens.length"
                :x="canvasWidth/2" :y="canvasHeight/2" text-anchor="middle" dominant-baseline="middle"
                fill="rgba(255,255,255,0.08)" font-size="20" font-weight="700" style="font-family: system-ui;">{{ __('Drag players here or use tools to draw') }}</text>
        </svg>
    </div>

    <!-- Context menu -->
    <Teleport to="body">
        <div v-if="ctxMenu.show"
            class="fixed z-[300] bg-[#1e1e22] border border-white/10 rounded-lg shadow-2xl py-1 min-w-[160px]"
            :style="{ left: ctxMenu.x + 'px', top: ctxMenu.y + 'px' }">
            <!-- Group selected -->
            <button v-if="multiSel.length >= 2" @click="groupSelected(); ctxMenu.show = false"
                class="w-full flex items-center justify-between px-3 py-1.5 text-left text-blue-400 hover:bg-blue-500/10 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">link</span>
                    <span class="text-3xs font-bold">{{ __('Group Selected') }}</span>
                </div>
                <span class="text-5xs text-on-surface-variant/40 font-mono">Ctrl+G</span>
            </button>
            <!-- Ungroup -->
            <button v-if="ctxSelectedHasLinkGroup" @click="ungroupFromCtx"
                class="w-full flex items-center justify-between px-3 py-1.5 text-left text-orange-400 hover:bg-orange-500/10 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">link_off</span>
                    <span class="text-3xs font-bold">{{ __('Ungroup') }}</span>
                </div>
            </button>
            <!-- Lock/Unlock -->
            <button v-if="multiSel.length <= 1" @click="toggleLockFromCtx"
                class="w-full flex items-center justify-between px-3 py-1.5 text-left text-on-surface-variant hover:bg-white/5 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">{{ ctxElementLocked ? 'lock_open' : 'lock' }}</span>
                    <span class="text-3xs font-bold">{{ ctxElementLocked ? __('Unlock') : __('Lock') }}</span>
                </div>
            </button>
            <!-- Delete -->
            <button @click="deleteFromCtx"
                class="w-full flex items-center justify-between px-3 py-1.5 text-left text-red-400 hover:bg-red-500/10 transition-colors">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">delete</span>
                    <span class="text-3xs font-bold">{{ multiSel.length > 1 ? __('Delete') + ` ${multiSel.length} ` + __('items') : __('Delete') }}</span>
                </div>
                <span class="text-5xs text-on-surface-variant/40 font-mono">Del</span>
            </button>
        </div>
    </Teleport>
</template>
