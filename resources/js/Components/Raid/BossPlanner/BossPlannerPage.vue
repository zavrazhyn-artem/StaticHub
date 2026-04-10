<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();
import RaidMapCanvas from './RaidMapCanvas.vue';
import MapToolbar from './MapToolbar.vue';
import StepNavigator from './StepNavigator.vue';
import PlayerPalette from './PlayerPalette.vue';

const props = defineProps({
    plannerData: { type: Object, required: true },
    roster: { type: Array, default: () => [] },
    staticGroup: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
    myCharacterIds: { type: Array, default: () => [] },
    csrfToken: { type: String, required: true },
    routes: { type: Object, required: true },
});

const encounters = computed(() => props.plannerData.encounters || []);

// Group by instance
const instanceGroups = computed(() => {
    const groups = {};
    encounters.value.forEach((enc, i) => {
        if (!groups[enc.instance]) groups[enc.instance] = [];
        groups[enc.instance].push({ ...enc, _index: i });
    });
    return groups;
});

// Fullscreen editor state
const editorOpen = ref(false);
const editorEncounterIndex = ref(null);
const editorEncounter = computed(() =>
    editorEncounterIndex.value !== null ? encounters.value[editorEncounterIndex.value] : null
);

// Plan state inside editor
const localPlan = ref(null);
const currentStepIndex = ref(0);
const activeTool = ref('select');
const editorTab = ref('map');

// Current Selection — floating window state
const currentSelection = ref(null); // { type, img, label, color, ... }
const selectionLabel = ref('');
const selectionPos = ref({ x: 680, y: 80 });
const isDraggingSel = ref(false);
const selDragStart = ref({ x: 0, y: 0 });

const shapeLabels = { circle: 'Circle', rect: 'Rectangle', arrow: 'Arrow', line: 'Line', cone: 'Cone / Wedge', text: 'Text Label', waypoint: 'Path' };

// Whether this selection type has direction by default
const defaultShowDirection = (item) => {
    if (!item) return false;
    if (item.type === 'player' || item.type === 'group') return true;
    if (item.type === 'class' || item.type === 'portrait') return true;
    return false;
};
// Whether direction toggle is available
const canToggleDirection = (item) => {
    if (!item) return false;
    if (item.type === 'shape') return false;
    return true;
};

// For editing existing selected elements
const canToggleDirectionForElement = (type, item) => {
    if (!item) return false;
    if (type === 'shape') return false;
    if (type === 'marker' && !['icon', 'group-token', 'emoji'].includes(item.type)) return true; // raid markers too
    if (type === 'marker') return true;
    if (type === 'player') return true;
    return false;
};

const isShapeElement = (type) => type === 'shape';
const isTextLabel = (type) => type === 'label';

// ─── Edit selected element panel ───
const editSel = ref(null); // { type, index }
const clearCanvasTrigger = ref(0);
const canvasRef = ref(null);

// ─── Text input tooltip (appears at click point) ───
const textPopup = ref(null); // { x, y, color, screenX, screenY }
const textPopupInput = ref('');
const showTextPopup = ({ x, y, color, screenX, screenY, editIndex }) => {
    if (editIndex !== undefined) {
        // Editing existing label
        textPopupInput.value = currentStep.value?.labels?.[editIndex]?.text || '';
    } else {
        textPopupInput.value = '';
    }
    textPopup.value = { x, y, color, screenX: screenX || 400, screenY: screenY || 300, editIndex };
};
const confirmTextPopup = () => {
    if (!textPopup.value || !textPopupInput.value.trim()) { textPopup.value = null; return; }
    pushUndo();
    const { x, y, color, editIndex } = textPopup.value;
    if (editIndex !== undefined) {
        // Update existing label
        const existing = [...(currentStep.value?.labels || [])];
        existing[editIndex] = { ...existing[editIndex], text: textPopupInput.value.trim() };
        updateStepData({ labels: existing });
    } else {
        // Create new label
        const existing = [...(currentStep.value?.labels || [])];
        existing.push({ text: textPopupInput.value.trim(), x, y, fontSize: 14, color: color || '#FFFFFF', scale: 1, rotation: 0 });
        updateStepData({ labels: existing });
    }
    textPopup.value = null;
};
const cancelTextPopup = () => { textPopup.value = null; };

const showHelp = ref(false);

// ─── Show Me — highlight my characters ───
const showingMe = ref(false);

const highlightedMarkerIds = computed(() => {
    if (!showingMe.value) return new Set();
    const myIds = new Set(props.myCharacterIds);
    const markers = currentStep.value?.markers || [];
    const groups = currentStep.value?.groups || {};
    const ids = new Set();

    // Find my player markers
    markers.forEach((m, i) => {
        if (m.playerData && myIds.has(m.playerData.id)) ids.add(i);
    });

    // Find roster groups that contain my characters
    const myGroupIds = new Set();
    for (const [gId, g] of Object.entries(groups)) {
        if ((g.members || []).some(id => myIds.has(id))) myGroupIds.add(Number(gId));
    }

    // Highlight group-token markers for my groups
    if (myGroupIds.size > 0) {
        markers.forEach((m, i) => {
            if (m.type === 'group-token' && myGroupIds.has(m.groupId)) ids.add(i);
        });
    }

    return ids;
});

const toggleShowMe = () => { showingMe.value = !showingMe.value; };

// ─── Share plan ───
const shareUrl = ref('');
const showSharePopup = ref(false);

const sharePlan = async () => {
    if (!localPlan.value?.id) return;
    try {
        const resp = await fetch(`${props.routes.shareBase}/${localPlan.value.id}/share`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
        });
        if (resp.ok) {
            const data = await resp.json();
            shareUrl.value = data.share_url;
            showSharePopup.value = true;
        }
    } catch (e) { console.error('Share failed:', e); }
};

const unsharePlan = async () => {
    if (!localPlan.value?.id) return;
    try {
        await fetch(`${props.routes.shareBase}/${localPlan.value.id}/unshare`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
        });
        shareUrl.value = '';
        showSharePopup.value = false;
    } catch (e) { console.error('Unshare failed:', e); }
};

const copyShareUrl = () => {
    navigator.clipboard.writeText(shareUrl.value);
};

// ─── Export PNG ───
const exportPng = async () => {
    const svg = canvasRef.value?.svgRef;
    if (!svg) return;
    const clone = svg.cloneNode(true);

    // Reset viewBox to full canvas (ignore zoom/pan)
    clone.setAttribute('viewBox', '0 0 960 540');
    clone.setAttribute('width', '1920');
    clone.setAttribute('height', '1080');
    clone.removeAttribute('class');
    clone.removeAttribute('style');

    // Convert all <image> hrefs to base64 data URIs
    const images = clone.querySelectorAll('image');
    const origin = window.location.origin;
    const imgToBase64 = (url) => new Promise((resolve) => {
        // Try fetch first (works for same-origin)
        if (url.startsWith(origin) || url.startsWith('/') || url.startsWith('data:')) {
            const fullUrl = url.startsWith('/') ? origin + url : url;
            fetch(fullUrl).then(r => r.blob()).then(blob => {
                const fr = new FileReader();
                fr.onload = () => resolve(fr.result);
                fr.readAsDataURL(blob);
            }).catch(() => resolve(null));
        } else {
            // External URL — use img+canvas proxy
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => {
                const c = document.createElement('canvas');
                c.width = img.naturalWidth || 64;
                c.height = img.naturalHeight || 64;
                c.getContext('2d').drawImage(img, 0, 0);
                try { resolve(c.toDataURL('image/png')); } catch { resolve(null); }
            };
            img.onerror = () => resolve(null);
            img.src = url;
        }
    });

    await Promise.all([...images].map(async (imgEl) => {
        let href = imgEl.getAttribute('href') || imgEl.getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        if (!href || href.startsWith('data:')) return;
        const dataUrl = await imgToBase64(href);
        if (dataUrl) imgEl.setAttribute('href', dataUrl);
        else imgEl.remove(); // remove broken images
    }));

    const svgData = new XMLSerializer().serializeToString(clone);
    const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    const blobUrl = URL.createObjectURL(svgBlob);
    const img = new Image();
    img.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = 1920; canvas.height = 1080;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#0a0a0c';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(blobUrl);
        const link = document.createElement('a');
        link.download = `raid-plan-${editorEncounter.value?.slug || 'plan'}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    };
    img.src = blobUrl;
};
const editPos = ref({ x: 680, y: 300 });
const isDraggingEdit = ref(false);
const editDragStart = ref({ x: 0, y: 0 });
const startEditDrag = (e) => {
    isDraggingEdit.value = true;
    editDragStart.value = { x: e.clientX - editPos.value.x, y: e.clientY - editPos.value.y };
    e.preventDefault();
};
if (typeof window !== 'undefined') {
    const mv = (e) => { if (isDraggingEdit.value) editPos.value = { x: e.clientX - editDragStart.value.x, y: e.clientY - editDragStart.value.y }; };
    const up = () => { isDraggingEdit.value = false; };
    window.addEventListener('mousemove', mv);
    window.addEventListener('mouseup', up);
}

const handleSelectionChange = ({ type, index, count, items }) => {
    if (count >= 1 && type && index >= 0) {
        if (currentSelection.value) {
            currentSelection.value = null;
            selectionLabel.value = '';
            activeTool.value = 'select';
        }
        editSel.value = { type, index, count, items: items || [{ type, index }] };
    } else {
        editSel.value = null;
    }
};

const editElement = computed(() => {
    if (!editSel.value || !currentStep.value) return null;
    const { type, index } = editSel.value;
    if (type === 'marker') return currentStep.value.markers?.[index] || null;
    if (type === 'player') return currentStep.value.players?.[index] || null;
    if (type === 'shape') return currentStep.value.shapes?.[index] || null;
    if (type === 'label') return currentStep.value.labels?.[index] || null;
    if (type === 'arrow') return currentStep.value.arrows?.[index] || null;
    return null;
});

const editElementType = computed(() => {
    if (!editElement.value || !editSel.value) return '';
    const el = editElement.value;
    if (editSel.value.type === 'marker') return el.type;
    return editSel.value.type;
});

const isGroupSelection = computed(() => (editSel.value?.count || 0) > 1);

const groupFormations = [
    { id: 'spread', icon: 'radio_button_unchecked', label: 'Spread' },
    { id: 'stack', icon: 'fiber_manual_record', label: 'Stack' },
    { id: 'line', icon: 'horizontal_rule', label: 'Line' },
    { id: 'vline', icon: 'drag_handle', label: 'Column' },
    { id: 'triangle', icon: 'change_history', label: 'Triangle' },
    { id: 'tworows', icon: 'view_week', label: '2 Rows' },
];

// Rearrange existing linked group into a new formation
const rearrangeGroupFormation = (formationId) => {
    if (!editSel.value?.items || editSel.value.items.length < 2) return;
    pushUndo();
    const items = editSel.value.items.filter(s => s.type === 'marker');
    if (items.length < 2) return;

    const markers = currentStep.value?.markers;
    if (!markers) return;

    // Get current centroid
    let cx = 0, cy = 0;
    items.forEach(s => { cx += markers[s.index].x; cy += markers[s.index].y; });
    cx /= items.length; cy /= items.length;

    const n = items.length;
    const spacing = 35;
    const positions = [];

    if (formationId === 'spread') {
        const r = 20 + n * 12;
        items.forEach((_, i) => { const a = (2 * Math.PI * i) / n - Math.PI / 2; positions.push({ x: cx + Math.cos(a) * r, y: cy + Math.sin(a) * r }); });
    } else if (formationId === 'stack') {
        items.forEach(() => positions.push({ x: cx + (Math.random() - 0.5) * 8, y: cy + (Math.random() - 0.5) * 8 }));
    } else if (formationId === 'line') {
        const startX = cx - ((n - 1) * spacing) / 2;
        items.forEach((_, i) => positions.push({ x: startX + i * spacing, y: cy }));
    } else if (formationId === 'vline') {
        const startY = cy - ((n - 1) * spacing) / 2;
        items.forEach((_, i) => positions.push({ x: cx, y: startY + i * spacing }));
    } else if (formationId === 'triangle') {
        let row = 0, col = 0, rowSize = 1;
        items.forEach(() => {
            positions.push({ x: cx - ((rowSize - 1) * spacing) / 2 + col * spacing, y: cy - ((Math.ceil((-1 + Math.sqrt(1 + 8 * n)) / 2) - 1) * spacing) / 2 + row * spacing });
            col++; if (col >= rowSize) { row++; rowSize++; col = 0; }
        });
    } else if (formationId === 'tworows') {
        const half = Math.ceil(n / 2);
        items.forEach((_, i) => {
            const r = i < half ? 0 : 1;
            const c = r === 0 ? i : i - half;
            const rowLen = r === 0 ? half : n - half;
            positions.push({ x: cx - ((rowLen - 1) * spacing) / 2 + c * spacing, y: cy - spacing / 2 + r * spacing });
        });
    }

    const updated = [...markers];
    items.forEach((s, i) => { updated[s.index] = { ...updated[s.index], x: positions[i].x, y: positions[i].y }; });
    updateStepData({ markers: updated });
};

const updateEditProp = (patch) => {
    if (!editSel.value) return;
    pushUndo();
    const typeKeyMap = { player: 'players', marker: 'markers', label: 'labels', shape: 'shapes', arrow: 'arrows' };
    const arrMap = { player: currentStep.value?.players, marker: currentStep.value?.markers, label: currentStep.value?.labels, shape: currentStep.value?.shapes, arrow: currentStep.value?.arrows };

    // Apply to all selected items
    const allPatch = {};
    (editSel.value.items || [{ type: editSel.value.type, index: editSel.value.index }]).forEach(({ type, index }) => {
        const key = typeKeyMap[type];
        const arr = arrMap[type];
        if (!key || !arr || !arr[index]) return;
        if (!allPatch[key]) allPatch[key] = [...arr];
        allPatch[key][index] = { ...allPatch[key][index], ...patch };
    });
    if (Object.keys(allPatch).length) updateStepData(allPatch);
};

const selectionShowDirection = ref(false);
const selectionColor = ref('#3B82F6');
const selectionFilled = ref(true);
const colorPresets = ['#EF4444','#3B82F6','#10B981','#F59E0B','#8B5CF6','#EC4899','#06B6D4','#FFFFFF'];

const canHaveFill = (item) => {
    if (!item) return false;
    if (item.type === 'shape' && ['circle', 'rect', 'triangle', 'cone'].includes(item.id)) return true;
    return false;
};
const canEditFill = (type, el) => {
    if (type === 'shape' && el && ['circle', 'rect', 'triangle', 'cone'].includes(el.type)) return true;
    return false;
};

const handleSelectIcon = (item) => {
    currentSelection.value = item;
    selectionLabel.value = item.label || '';
    selectionShowDirection.value = defaultShowDirection(item);
    selectionColor.value = item.type === 'shape' ? '#3B82F6' : '#FFFFFF';
    selectionFilled.value = true;
    editSel.value = null; // close edit panel
    clearCanvasTrigger.value++; // deselect on canvas
    // Shapes use their own drawing tool, everything else uses place-icon
    if (item.type === 'shape') {
        activeTool.value = item.id; // 'circle', 'rect', 'arrow', etc.
    } else {
        activeTool.value = 'place-icon';
    }
};

const clearSelection = () => {
    currentSelection.value = null;
    selectionLabel.value = '';
    activeTool.value = 'select';
};

const startSelDrag = (e) => {
    isDraggingSel.value = true;
    selDragStart.value = { x: e.clientX - selectionPos.value.x, y: e.clientY - selectionPos.value.y };
    e.preventDefault();
};

// Wire selection drag into existing window listeners
if (typeof window !== 'undefined') {
    const origMove = (e) => { if (isDraggingSel.value) selectionPos.value = { x: e.clientX - selDragStart.value.x, y: e.clientY - selDragStart.value.y }; };
    const origUp = () => { isDraggingSel.value = false; };
    window.addEventListener('mousemove', origMove);
    window.addEventListener('mouseup', origUp);
}

const currentStep = computed(() => {
    if (!localPlan.value?.steps?.length) return null;
    return localPlan.value.steps[currentStepIndex.value] || localPlan.value.steps[0];
});

// Current step groups (backward compat: default to empty)
const currentGroups = computed(() => {
    return currentStep.value?.groups || {};
});

// Open editor for an encounter
const openEditor = (index) => {
    editorEncounterIndex.value = index;
    const enc = encounters.value[index];
    if (enc?.plan) {
        localPlan.value = JSON.parse(JSON.stringify(enc.plan));
    } else {
        localPlan.value = null;
    }
    currentStepIndex.value = 0;
    activeTool.value = 'select';
    editorTab.value = 'map';
    editorOpen.value = true;
    document.body.style.overflow = 'hidden';
};

const closeEditor = () => {
    editorOpen.value = false;
    editorEncounterIndex.value = null;
    document.body.style.overflow = '';
};

// Keyboard shortcuts
const handleKeydown = (e) => {
    if (e.key === 'Escape' && editorOpen.value) closeEditor();
    // Ctrl+Shift+Z — redo
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'Z' && editorOpen.value) {
        e.preventDefault();
        redo();
        return;
    }
    // Ctrl+Z — undo
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && editorOpen.value) {
        e.preventDefault();
        undo();
    }
};
if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleKeydown);
}

// Create plan
const createPlan = () => {
    localPlan.value = {
        id: null,
        title: null,
        steps: [{ label: 'Phase 1', groups: {}, markers: [], players: [], shapes: [], arrows: [], labels: [] }],
        difficulty: 'mythic',
    };
};

// Group management
const GROUP_COLORS = ['#3B82F6', '#EF4444', '#10B981', '#F59E0B', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];

const handleAddGroup = (group) => {
    if (!currentStep.value) return;
    const groups = { ...(currentStep.value.groups || {}) };
    groups[group.id] = { label: group.label, color: group.color, members: group.members || [] };
    updateStepData({ groups });
};

const handleRemoveGroup = (groupId) => {
    if (!currentStep.value) return;
    const groups = { ...(currentStep.value.groups || {}) };
    // Unset group from players
    const players = (currentStep.value.players || []).map(p =>
        p.group === groupId ? { ...p, group: null } : p
    );
    delete groups[groupId];
    updateStepData({ groups, players });
};

const handleAssignGroup = ({ characterId, groupId }) => {
    if (!currentStep.value) return;
    const groups = JSON.parse(JSON.stringify(currentStep.value.groups || {}));
    // Remove from all other groups
    for (const [id, g] of Object.entries(groups)) {
        g.members = (g.members || []).filter(m => m !== characterId);
    }
    // Add to target group
    if (groups[groupId]) {
        groups[groupId].members.push(characterId);
    }
    // Update player's group field if placed on map
    const players = (currentStep.value.players || []).map(p =>
        p.character_id === characterId ? { ...p, group: groupId } : p
    );
    updateStepData({ groups, players });
};

const handleRemoveFromGroup = ({ characterId, groupId }) => {
    if (!currentStep.value) return;
    const groups = JSON.parse(JSON.stringify(currentStep.value.groups || {}));
    if (groups[groupId]) {
        groups[groupId].members = (groups[groupId].members || []).filter(m => m !== characterId);
    }
    const players = (currentStep.value.players || []).map(p =>
        p.character_id === characterId && p.group === groupId ? { ...p, group: null } : p
    );
    updateStepData({ groups, players });
};

const handlePlaceGroup = (groupId) => {
    if (!currentStep.value) return;
    const group = (currentStep.value.groups || {})[groupId];
    if (!group || !group.members?.length) return;
    // Place group members in a circle around center
    const cx = 480, cy = 270;
    const radius = 40 + group.members.length * 5;
    const existing = [...(currentStep.value.players || [])];
    const placedIds = new Set(existing.map(p => p.character_id));

    group.members.forEach((charId, i) => {
        if (placedIds.has(charId)) return; // skip if already on map
        const angle = (2 * Math.PI * i) / group.members.length - Math.PI / 2;
        const char = props.roster.find(c => c.id === charId);
        existing.push({
            character_id: charId,
            character_name: char?.name || String(charId),
            class_name: char?.playable_class || '',
            role: char?.assigned_role || 'rdps',
            group: groupId,
            x: cx + Math.cos(angle) * radius,
            y: cy + Math.sin(angle) * radius,
        });
    });
    updateStepData({ players: existing });
};

// Save plan
const saving = ref(false);
const saveSuccess = ref(false);
const savePlan = async () => {
    if (!editorEncounter.value || !localPlan.value) return;
    saving.value = true;
    saveSuccess.value = false;
    try {
        const response = await fetch(props.routes.save, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': props.csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({
                encounter_slug: editorEncounter.value.slug,
                title: localPlan.value.title,
                steps: localPlan.value.steps,
                difficulty: localPlan.value.difficulty || 'mythic',
                plan_id: localPlan.value.id || null,
            }),
        });
        if (response.ok) {
            const data = await response.json();
            localPlan.value.id = data.plan.id;
            // Update local encounters data
            const enc = encounters.value[editorEncounterIndex.value];
            if (enc) {
                enc.has_plan = true;
                enc.plan = { ...localPlan.value, updated_at: new Date().toISOString() };
            }
            saveSuccess.value = true;
            setTimeout(() => { saveSuccess.value = false; }, 2000);
        }
    } catch (e) {
        console.error('Failed to save plan:', e);
    } finally {
        saving.value = false;
    }
};

// Step management
const addStep = () => {
    if (!localPlan.value) return;
    localPlan.value.steps.push({
        label: `Phase ${localPlan.value.steps.length + 1}`,
        groups: {}, markers: [], players: [], shapes: [], arrows: [], labels: [],
    });
    currentStepIndex.value = localPlan.value.steps.length - 1;
};
const copyStep = () => {
    if (!localPlan.value || !currentStep.value) return;
    const copy = JSON.parse(JSON.stringify(currentStep.value));
    copy.label = `${copy.label} (copy)`;
    localPlan.value.steps.push(copy);
    currentStepIndex.value = localPlan.value.steps.length - 1;
};
const removeStep = (index) => {
    if (!localPlan.value || localPlan.value.steps.length <= 1) return;
    localPlan.value.steps.splice(index, 1);
    if (currentStepIndex.value >= localPlan.value.steps.length) currentStepIndex.value = localPlan.value.steps.length - 1;
};
// ─── Undo / Redo (Ctrl+Z / Ctrl+Shift+Z) ───
const undoStack = ref([]);
const redoStack = ref([]);
const maxUndoSteps = 50;

const pushUndo = () => {
    if (!localPlan.value) return;
    const snapshot = JSON.stringify(localPlan.value.steps);
    if (undoStack.value.length && undoStack.value[undoStack.value.length - 1] === snapshot) return;
    undoStack.value.push(snapshot);
    if (undoStack.value.length > maxUndoSteps) undoStack.value.shift();
    redoStack.value = []; // new action clears redo
};

const undo = () => {
    if (!localPlan.value || undoStack.value.length === 0) return;
    redoStack.value.push(JSON.stringify(localPlan.value.steps));
    const prev = undoStack.value.pop();
    localPlan.value.steps = JSON.parse(prev);
    if (currentStepIndex.value >= localPlan.value.steps.length) {
        currentStepIndex.value = localPlan.value.steps.length - 1;
    }
};

const redo = () => {
    if (!localPlan.value || redoStack.value.length === 0) return;
    undoStack.value.push(JSON.stringify(localPlan.value.steps));
    const next = redoStack.value.pop();
    localPlan.value.steps = JSON.parse(next);
    if (currentStepIndex.value >= localPlan.value.steps.length) {
        currentStepIndex.value = localPlan.value.steps.length - 1;
    }
};

const renameStep = ({ index, label }) => {
    if (!localPlan.value || !localPlan.value.steps[index]) return;
    localPlan.value.steps[index].label = label;
};

const reorderSteps = ({ from, to }) => {
    if (!localPlan.value) return;
    pushUndo();
    const steps = [...localPlan.value.steps];
    const [moved] = steps.splice(from, 1);
    steps.splice(to, 0, moved);
    localPlan.value.steps = steps;
    currentStepIndex.value = to;
};

const updateStepData = (data) => {
    if (!localPlan.value) return;
    localPlan.value.steps[currentStepIndex.value] = { ...localPlan.value.steps[currentStepIndex.value], ...data };
};
const handlePlayerDrop = (player) => {
    if (!currentStep.value) return;
    const existing = [...(currentStep.value.players || [])];
    // Find which group this player belongs to
    let playerGroup = null;
    for (const [gId, g] of Object.entries(currentStep.value.groups || {})) {
        if ((g.members || []).includes(player.id)) { playerGroup = Number(gId); break; }
    }
    existing.push({
        character_id: player.id, character_name: player.name,
        class_name: player.playable_class, role: player.assigned_role || 'rdps',
        group: playerGroup,
        x: 480 + Math.random() * 60 - 30, y: 270 + Math.random() * 60 - 30,
    });
    updateStepData({ players: existing });
};

// Handle canvas click when in place-icon mode
const handleCanvasPlacement = (point) => {
    if (!currentStep.value || !currentSelection.value) return;
    const sel = currentSelection.value;
    const label = selectionLabel.value || '';

    const sd = selectionShowDirection.value;

    if (sel.type === 'marker') {
        const existing = [...(currentStep.value.markers || [])];
        existing.push({ type: sel.id, x: point.x, y: point.y, label, showDirection: sd, scale: 1, rotation: 0 });
        updateStepData({ markers: existing });
    } else if (sel.type === 'group') {
        const group = (currentStep.value.groups || {})[sel.groupId];
        if (!group) return;
        const existing = [...(currentStep.value.markers || [])];
        existing.push({ type: 'group-token', groupId: sel.groupId, color: sel.color, groupLabel: sel.displayName || sel.label, x: point.x, y: point.y, label, showDirection: sd, scale: 1, rotation: 0 });
        updateStepData({ markers: existing });
    } else if (sel.type === 'player') {
        const char = props.roster.find(c => c.id === sel.characterId);
        const existing = [...(currentStep.value.markers || [])];
        existing.push({
            type: 'icon', src: sel.img || `/images/raidplan/class/${(char?.playable_class || '').toLowerCase().replace(/\s+/g, '')}.png`,
            x: point.x, y: point.y, label: label || sel.label,
            playerData: { id: sel.characterId, name: sel.label, className: sel.className, role: sel.role },
            showDirection: sd, scale: 1, rotation: 0,
        });
        updateStepData({ markers: existing });
    } else if (sel.type === 'emoji') {
        const existing = [...(currentStep.value.markers || [])];
        existing.push({ type: 'emoji', emoji: sel.emoji, x: point.x, y: point.y, label, showDirection: sd, scale: 1, rotation: 0 });
        updateStepData({ markers: existing });
    } else {
        // ability, class, portrait — generic icon
        const existing = [...(currentStep.value.markers || [])];
        existing.push({
            type: 'icon', src: sel.img, x: point.x, y: point.y, label,
            isAbility: sel.type === 'ability',
            showDirection: sd, scale: 1, rotation: 0,
        });
        updateStepData({ markers: existing });
    }
    // Don't clear selection — allow placing more
};

// ─── Formation presets ───
const handlePlaceFormation = ({ groupId, formation }) => {
    if (!currentStep.value) return;
    const group = (currentStep.value.groups || {})[groupId];
    if (!group || !group.members?.length) return;

    pushUndo();
    // Place at canvas center
    const cx = 480, cy = 270;
    // If group members already placed, use their centroid as center
    const existing = [...(currentStep.value.players || [])];
    const alreadyPlaced = existing.filter(p => group.members.includes(p.character_id));
    let centerX = cx, centerY = cy;
    if (alreadyPlaced.length > 0) {
        centerX = alreadyPlaced.reduce((s, p) => s + p.x, 0) / alreadyPlaced.length;
        centerY = alreadyPlaced.reduce((s, p) => s + p.y, 0) / alreadyPlaced.length;
    }
    const members = group.members;
    const n = members.length;
    const spacing = 35;
    const placedIds = new Set(existing.map(p => p.character_id));

    const positions = [];
    if (formation === 'spread') {
        const r = 20 + n * 12;
        members.forEach((_, i) => {
            const a = (2 * Math.PI * i) / n - Math.PI / 2;
            positions.push({ x: centerX + Math.cos(a) * r, y: centerY + Math.sin(a) * r });
        });
    } else if (formation === 'stack') {
        members.forEach(() => {
            positions.push({ x: centerX + (Math.random() - 0.5) * 8, y: centerY + (Math.random() - 0.5) * 8 });
        });
    } else if (formation === 'line') {
        const startX = centerX - ((n - 1) * spacing) / 2;
        members.forEach((_, i) => positions.push({ x: startX + i * spacing, y: centerY }));
    } else if (formation === 'vline') {
        const startY = centerY - ((n - 1) * spacing) / 2;
        members.forEach((_, i) => positions.push({ x: centerX, y: startY + i * spacing }));
    } else if (formation === 'triangle') {
        let row = 0, col = 0, rowSize = 1;
        members.forEach(() => {
            const rowX = centerX - ((rowSize - 1) * spacing) / 2 + col * spacing;
            const rowY = centerY - ((Math.ceil((-1 + Math.sqrt(1 + 8 * n)) / 2) - 1) * spacing) / 2 + row * spacing;
            positions.push({ x: rowX, y: rowY });
            col++;
            if (col >= rowSize) { row++; rowSize++; col = 0; }
        });
    } else if (formation === 'tworows') {
        const half = Math.ceil(n / 2);
        members.forEach((_, i) => {
            const r = i < half ? 0 : 1;
            const c = r === 0 ? i : i - half;
            const rowLen = r === 0 ? half : n - half;
            positions.push({ x: centerX - ((rowLen - 1) * spacing) / 2 + c * spacing, y: centerY - spacing / 2 + r * spacing });
        });
    }

    // Always create new markers with shared linkGroup
    const linkId = Date.now();
    const existingMarkers = [...(currentStep.value.markers || [])];
    members.forEach((charId, i) => {
        const char = props.roster.find(c => c.id === charId);
        if (!char) return;
        existingMarkers.push({
            type: 'icon',
            src: char.avatar_url || `/images/raidplan/class/${(char.playable_class || '').toLowerCase().replace(/\s+/g, '')}.png`,
            x: positions[i].x, y: positions[i].y,
            label: char.name,
            playerData: { id: charId, name: char.name, className: char.playable_class, role: char.assigned_role },
            showDirection: true, scale: 1, rotation: 0, linkGroup: linkId,
        });
    });
    updateStepData({ markers: existingMarkers });
};

// Stats for cards
const plansCount = computed(() => encounters.value.filter(e => e.has_plan).length);
</script>

<template>
    <div class="space-y-6">
        <!-- Page header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-white uppercase tracking-tighter font-headline leading-none">
                    Boss Planner
                </h1>
                <p class="text-xs text-on-surface-variant mt-1">
                    {{ staticGroup.name }} &mdash;
                    <span class="text-primary">{{ plansCount }}</span> of {{ encounters.length }} encounters planned
                </p>
            </div>
        </div>

        <!-- Encounter cards grid -->
        <div v-for="(bosses, instanceName) in instanceGroups" :key="instanceName" class="space-y-3">
            <div class="px-1 flex items-center gap-2">
                <span class="text-[9px] font-black uppercase tracking-[0.2em] text-on-surface-variant/50">{{ instanceName }}</span>
                <div class="flex-1 h-px bg-white/5"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <button
                    v-for="enc in bosses"
                    :key="enc.slug"
                    @click="openEditor(enc._index)"
                    class="group text-left bg-surface-container/60 border rounded-xl p-5 transition-all hover:scale-[1.01] hover:shadow-xl"
                    :class="enc.has_plan
                        ? 'border-white/10 hover:border-orange-500/40'
                        : 'border-white/5 hover:border-white/15'"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="w-12 h-12 rounded-xl overflow-hidden shrink-0 border transition-colors"
                            :class="enc.has_plan ? 'border-orange-500/20' : 'border-white/5'"
                        >
                            <img
                                v-if="enc.portrait"
                                :src="enc.portrait"
                                :alt="enc.name"
                                class="w-full h-full object-cover"
                            >
                            <div v-else class="w-full h-full bg-white/5 flex items-center justify-center">
                                <span class="material-symbols-outlined text-2xl text-on-surface-variant/25">swords</span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-white group-hover:text-orange-300 transition-colors truncate">
                                {{ enc.name }}
                            </div>
                            <div v-if="enc.has_plan" class="mt-1.5 space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-400"></span>
                                    <span class="text-[9px] text-on-surface-variant">
                                        {{ enc.plan.steps?.length || 0 }} phase{{ (enc.plan.steps?.length || 0) !== 1 ? 's' : '' }}
                                    </span>
                                    <span class="text-[9px] text-orange-400/60 font-bold uppercase">{{ enc.plan.difficulty }}</span>
                                </div>
                                <div class="flex items-center gap-1 flex-wrap">
                                    <div
                                        v-for="(step, si) in (enc.plan.steps || []).slice(0, 4)"
                                        :key="si"
                                        class="px-1.5 py-0.5 rounded bg-white/5 text-[7px] font-bold text-on-surface-variant/50"
                                    >
                                        {{ step.label }} &middot; {{ (step.players || []).length }}p
                                    </div>
                                    <span
                                        v-if="(enc.plan.steps || []).length > 4"
                                        class="text-[7px] text-on-surface-variant/30"
                                    >+{{ enc.plan.steps.length - 4 }}</span>
                                </div>
                            </div>
                            <div v-else class="mt-1.5">
                                <span class="text-[9px] text-on-surface-variant/30">No plan yet &mdash; click to create</span>
                            </div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>

    <!-- ============ FULLSCREEN EDITOR OVERLAY ============ -->
    <Teleport to="body">
        <Transition name="editor">
            <div
                v-if="editorOpen && editorEncounter"
                class="fixed inset-0 z-[200] bg-[#0d0d0f] flex flex-col"
            >
                <!-- Top bar -->
                <div class="shrink-0 h-14 border-b border-white/10 bg-[#131315] flex items-center justify-between px-4 gap-4">
                    <!-- Left: encounter name + tabs -->
                    <div class="flex items-center gap-4 min-w-0">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <img
                                v-if="editorEncounter.portrait"
                                :src="editorEncounter.portrait"
                                class="w-8 h-8 rounded-lg object-cover border border-white/10 shrink-0"
                            >
                            <span v-else class="material-symbols-outlined text-lg text-orange-400">swords</span>
                            <h2 class="text-sm font-black text-white uppercase tracking-tight font-headline truncate">
                                {{ editorEncounter.name }}
                            </h2>
                        </div>

                        <div class="flex items-center gap-0.5 bg-white/5 rounded-lg p-0.5">
                            <button
                                @click="editorTab = 'map'"
                                class="px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest transition-all"
                                :class="editorTab === 'map' ? 'bg-orange-500/20 text-orange-400' : 'text-on-surface-variant hover:text-white'"
                            >
                                <span class="material-symbols-outlined text-xs align-middle mr-0.5">map</span> Map
                            </button>
                            <button
                                @click="editorTab = 'cooldowns'"
                                class="px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest transition-all"
                                :class="editorTab === 'cooldowns' ? 'bg-orange-500/20 text-orange-400' : 'text-on-surface-variant hover:text-white'"
                            >
                                <span class="material-symbols-outlined text-xs align-middle mr-0.5">timer</span> Cooldowns
                            </button>
                        </div>
                    </div>

                    <!-- Right: save + close -->
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            v-if="canManage && localPlan"
                            @click="savePlan"
                            :disabled="saving"
                            class="flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all"
                            :class="saveSuccess
                                ? 'bg-green-500/20 text-green-400'
                                : saving
                                    ? 'bg-white/5 text-on-surface-variant cursor-wait'
                                    : 'bg-orange-500/15 text-orange-400 hover:bg-orange-500/25'"
                        >
                            <span class="material-symbols-outlined text-sm">{{ saveSuccess ? 'check' : saving ? 'hourglass_top' : 'save' }}</span>
                            {{ saveSuccess ? 'Saved!' : saving ? 'Saving...' : 'Save' }}
                        </button>

                        <button
                            v-if="myCharacterIds.length > 0 && localPlan"
                            @click="toggleShowMe"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all"
                            :class="showingMe ? 'bg-yellow-500/20 text-yellow-400 animate-pulse' : 'bg-white/5 text-on-surface-variant hover:text-white hover:bg-white/10'"
                            :title="__('Show Me')"
                        >
                            <span class="material-symbols-outlined text-sm">person_pin</span>
                            {{ __('Show Me') }}
                        </button>

                        <button
                            v-if="canManage && localPlan?.id"
                            @click="sharePlan"
                            class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 text-on-surface-variant hover:text-white transition-all flex items-center justify-center"
                            title="Share Plan"
                        >
                            <span class="material-symbols-outlined text-lg">share</span>
                        </button>

                        <button
                            @click="showHelp = true"
                            class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 text-on-surface-variant hover:text-white transition-all flex items-center justify-center"
                            title="Help & Shortcuts"
                        >
                            <span class="material-symbols-outlined text-lg">help</span>
                        </button>

                        <button
                            v-if="localPlan"
                            @click="exportPng"
                            class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 text-on-surface-variant hover:text-white transition-all flex items-center justify-center"
                            title="Export PNG"
                        >
                            <span class="material-symbols-outlined text-lg">download</span>
                        </button>

                        <button
                            @click="closeEditor"
                            class="w-9 h-9 rounded-lg bg-white/5 hover:bg-white/10 text-on-surface-variant hover:text-white transition-all flex items-center justify-center"
                            title="Close (Esc)"
                        >
                            <span class="material-symbols-outlined text-xl">close</span>
                        </button>
                    </div>
                </div>

                <!-- Editor body -->
                <div class="flex-1 overflow-hidden">
                    <!-- Map editor -->
                    <div v-show="editorTab === 'map'" class="h-full flex flex-col">
                        <!-- No plan yet -->
                        <div v-if="!localPlan" class="flex-1 flex items-center justify-center">
                            <div class="text-center">
                                <span class="material-symbols-outlined text-6xl text-on-surface-variant/10">map</span>
                                <p class="text-sm text-on-surface-variant/30 mt-4">{{ __('No plan for') }} this encounter</p>
                                <button
                                    v-if="canManage"
                                    @click="createPlan"
                                    class="mt-5 px-6 py-3 rounded-xl bg-orange-500/10 border border-orange-500/30 text-orange-400 text-[10px] font-black uppercase tracking-widest hover:bg-orange-500/20 transition-all"
                                >
                                    <span class="material-symbols-outlined text-sm align-middle mr-1">add</span>
                                    Create Plan
                                </button>
                            </div>
                        </div>

                        <!-- Plan editor content -->
                        <template v-else>
                            <!-- Toolbar + steps bar -->
                            <div class="shrink-0 px-4 py-2 border-b border-white/5 flex items-center gap-4">
                                <MapToolbar
                                    :active-tool="activeTool"
                                    :abilities="editorEncounter?.abilities || []"
                                    :boss-portraits="editorEncounter?.portraits || []"
                                    :roster="roster"
                                    :groups="currentGroups"
                                    @select-tool="(t) => { activeTool = t; if (t === 'select') clearSelection(); }"
                                    @select-icon="handleSelectIcon"
                                />
                                <div class="h-6 w-px bg-white/10"></div>
                                <StepNavigator
                                    :steps="localPlan.steps"
                                    :current-index="currentStepIndex"
                                    :can-manage="canManage"
                                    @select="currentStepIndex = $event"
                                    @add="addStep"
                                    @copy="copyStep"
                                    @rename="renameStep"
                                    @reorder="reorderSteps"
                                    @remove="removeStep"
                                />
                            </div>

                            <!-- Canvas + palette -->
                            <div class="flex-1 flex overflow-hidden">
                                <!-- Player palette with groups -->
                                <div class="w-56 shrink-0 border-r border-white/5 overflow-y-auto">
                                    <PlayerPalette
                                        :characters="roster"
                                        :groups="currentGroups"
                                        :can-manage="canManage"
                                        @add-group="handleAddGroup"
                                        @remove-group="handleRemoveGroup"
                                        @assign-group="handleAssignGroup"
                                        @remove-from-group="handleRemoveFromGroup"
                                        @place-formation="handlePlaceFormation"
                                    />
                                </div>

                                <!-- Canvas area — fills all remaining space -->
                                <div class="flex-1 overflow-hidden bg-[#0a0a0c]">
                                    <RaidMapCanvas
                                        ref="canvasRef"
                                        :step="currentStep"
                                        :active-tool="activeTool"
                                        :can-manage="canManage"
                                        :groups="currentGroups"
                                        :maps="editorEncounter?.maps || []"
                                        :has-pending-placement="!!currentSelection && currentSelection.type !== 'shape'"
                                        :shape-color="selectionColor"
                                        :shape-filled="selectionFilled"
                                        :clear-selection-trigger="clearCanvasTrigger"
                                        :highlighted-markers="highlightedMarkerIds"
                                        @update="updateStepData"
                                        @place="handleCanvasPlacement"
                                        @action-start="pushUndo"
                                        @request-text="showTextPopup"
                                        @selection-change="handleSelectionChange"
                                    />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Cooldowns placeholder -->
                    <div v-show="editorTab === 'cooldowns'" class="h-full flex items-center justify-center">
                        <div class="text-center">
                            <span class="material-symbols-outlined text-6xl text-on-surface-variant/10">timer</span>
                            <p class="text-lg font-black text-on-surface-variant/20 uppercase tracking-widest mt-4">Cooldown Planner</p>
                            <p class="text-xs text-on-surface-variant/20 mt-2 max-w-md mx-auto">
                                Assign healing and defensive cooldowns to boss ability timelines. Coming in Phase 2.
                            </p>
                            <div class="mt-6 flex items-center justify-center gap-3 opacity-20">
                                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10">
                                    <span class="material-symbols-outlined text-sm text-blue-400">shield</span>
                                    <span class="text-[9px] font-bold text-on-surface-variant">Defensives</span>
                                </div>
                                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10">
                                    <span class="material-symbols-outlined text-sm text-green-400">healing</span>
                                    <span class="text-[9px] font-bold text-on-surface-variant">Healing CDs</span>
                                </div>
                                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10">
                                    <span class="material-symbols-outlined text-sm text-red-400">local_fire_department</span>
                                    <span class="text-[9px] font-bold text-on-surface-variant">DPS CDs</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Current Selection floating window -->
    <Teleport to="body">
        <div
            v-if="currentSelection && editorOpen"
            class="fixed z-[260] w-[220px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
            :style="{ left: selectionPos.x + 'px', top: selectionPos.y + 'px' }"
            @mousedown.stop @click.stop
        >
            <!-- Draggable header -->
            <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                @mousedown="startSelDrag">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-orange-400">{{ __('Current Selection') }}</span>
                </div>
                <button @click="clearSelection" class="text-on-surface-variant/50 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <!-- Preview -->
            <div class="p-3 space-y-3">
                <div class="flex items-center gap-3">
                    <!-- Icon preview -->
                    <div class="w-12 h-12 rounded-lg border border-white/10 flex items-center justify-center shrink-0 overflow-hidden"
                        :style="currentSelection.color ? { borderColor: currentSelection.color + '60', backgroundColor: currentSelection.color + '15' } : {}">
                        <span v-if="currentSelection.type === 'emoji'" class="text-3xl leading-none">{{ currentSelection.emoji }}</span>
                        <img v-else-if="currentSelection.img" :src="currentSelection.img" class="w-9 h-9 rounded object-cover">
                        <div v-else-if="currentSelection.type === 'group'" class="text-center">
                            <span class="text-sm font-black" :style="{ color: currentSelection.color }">{{ currentSelection.displayName || currentSelection.label }}</span>
                        </div>
                        <svg v-else-if="currentSelection.icon === 'sector_svg'" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3B82F6" stroke-width="2">
                            <path d="M12 12 L12 3 A9 9 0 0 0 4.2 7.5 Z"/>
                        </svg>
                        <span v-else-if="currentSelection.icon" class="material-symbols-outlined text-2xl text-blue-400">{{ currentSelection.icon }}</span>
                        <span v-else class="material-symbols-outlined text-2xl text-on-surface-variant/30">image</span>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold text-white truncate">
                            {{ currentSelection.type === 'shape' ? shapeLabels[currentSelection.id] || currentSelection.id : (currentSelection.displayName || currentSelection.label || currentSelection.id) }}
                        </div>
                        <div class="text-[8px] text-on-surface-variant/50 uppercase">{{ currentSelection.type }}</div>
                    </div>
                </div>

                <!-- Label input (not for waypoint/path) -->
                <div v-if="!(currentSelection.type === 'shape' && currentSelection.id === 'waypoint')" class="space-y-1">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Label (optional)</label>
                    <input
                        v-model="selectionLabel"
                        type="text"
                        placeholder="Text under icon..."
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-[10px] text-white focus:ring-1 focus:ring-orange-500 outline-none placeholder-on-surface-variant/30"
                    >
                </div>

                <!-- Direction toggle -->
                <div v-if="canToggleDirection(currentSelection)" class="flex items-center justify-between">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Direction</label>
                    <button
                        @click="selectionShowDirection = !selectionShowDirection"
                        class="w-8 h-4 rounded-full transition-all relative"
                        :class="selectionShowDirection ? 'bg-red-500' : 'bg-white/10'"
                    >
                        <div class="w-3 h-3 rounded-full bg-white absolute top-0.5 transition-all"
                            :class="selectionShowDirection ? 'left-[18px]' : 'left-0.5'"></div>
                    </button>
                </div>

                <!-- Fill toggle (circle, rect, cone) -->
                <div v-if="canHaveFill(currentSelection)" class="flex items-center justify-between">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Fill</label>
                    <button
                        @click="selectionFilled = !selectionFilled"
                        class="w-8 h-4 rounded-full transition-all relative"
                        :class="selectionFilled ? 'bg-blue-500' : 'bg-white/10'"
                    >
                        <div class="w-3 h-3 rounded-full bg-white absolute top-0.5 transition-all"
                            :class="selectionFilled ? 'left-[18px]' : 'left-0.5'"></div>
                    </button>
                </div>

                <!-- Color picker (shapes & text only) -->
                <div v-if="currentSelection.type === 'shape'" class="space-y-1.5">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Color</label>
                    <div class="flex items-center gap-1.5">
                        <button v-for="c in colorPresets" :key="c"
                            @click="selectionColor = c"
                            class="w-5 h-5 rounded-full border-2 transition-all hover:scale-110"
                            :style="{ backgroundColor: c }"
                            :class="selectionColor === c ? 'border-white' : 'border-transparent'"
                        ></button>
                    </div>
                    <input v-model="selectionColor" type="text" placeholder="#FF0000"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1 text-[9px] text-white font-mono focus:ring-1 focus:ring-orange-500 outline-none">
                </div>

                <!-- Instructions -->
                <div class="text-[8px] text-on-surface-variant/40 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-xs">mouse</span>
                    {{ currentSelection.type === 'shape' && currentSelection.id === 'waypoint' ? __('Click to add points, double-click to finish') : currentSelection.type === 'shape' ? __('Click and drag on the map to draw') : __('Click on the map to place') }}
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Edit Selected Element floating window -->
    <Teleport to="body">
        <div
            v-if="editSel && editElement && editorOpen"
            class="fixed z-[260] w-[220px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
            :style="{ left: editPos.x + 'px', top: editPos.y + 'px' }"
            @mousedown.stop @click.stop
        >
            <!-- Header -->
            <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                @mousedown="startEditDrag">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary">{{ __('Edit Element') }}</span>
                </div>
                <button @click="editSel = null" class="text-on-surface-variant/50 hover:text-white transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>

            <div class="p-3 space-y-3">
                <!-- ═══ GROUP SELECTION VIEW ═══ -->
                <template v-if="isGroupSelection">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg border border-primary/30 bg-primary/10 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-xl text-primary">group_work</span>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-white">{{ __('Group') }} ({{ editSel.count }} {{ __('elements') }})</div>
                            <div class="text-[8px] text-on-surface-variant/50 uppercase">{{ __('Linked group') }}</div>
                        </div>
                    </div>

                    <!-- Direction toggle for all -->
                    <div class="flex items-center justify-between">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Direction All') }}</label>
                        <div class="flex gap-1">
                            <button @click="updateEditProp({ showDirection: true })"
                                class="px-2 py-0.5 rounded text-[8px] font-bold bg-red-500/10 text-red-400 hover:bg-red-500/20 transition-all">ON</button>
                            <button @click="updateEditProp({ showDirection: false })"
                                class="px-2 py-0.5 rounded text-[8px] font-bold bg-white/5 text-on-surface-variant hover:bg-white/10 transition-all">OFF</button>
                        </div>
                    </div>

                    <!-- Formation presets -->
                    <div class="space-y-1">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Formation') }}</label>
                        <div class="grid grid-cols-3 gap-1">
                            <button v-for="f in groupFormations" :key="f.id"
                                @click="rearrangeGroupFormation(f.id)"
                                class="flex flex-col items-center gap-0.5 p-1.5 rounded-lg bg-white/5 hover:bg-white/10 transition-all"
                                :title="f.label">
                                <span class="material-symbols-outlined text-sm text-on-surface-variant">{{ f.icon }}</span>
                                <span class="text-[7px] font-bold text-on-surface-variant/60">{{ f.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Color for arrow groups -->
                    <div v-if="editSel.type === 'arrow'" class="space-y-1.5">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Color') }}</label>
                        <div class="flex items-center gap-1.5">
                            <button v-for="c in colorPresets" :key="c"
                                @click="updateEditProp({ color: c })"
                                class="w-5 h-5 rounded-full border-2 transition-all hover:scale-110"
                                :style="{ backgroundColor: c }"
                                :class="editElement.color === c ? 'border-white' : 'border-transparent'"
                            ></button>
                        </div>
                    </div>
                </template>

                <!-- ═══ SINGLE ELEMENT VIEW ═══ -->
                <template v-else>
                    <!-- Preview -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg border border-white/10 flex items-center justify-center shrink-0 overflow-hidden bg-white/5">
                            <svg v-if="editSel.type === 'shape' && editElement.type === 'cone'" width="22" height="22" viewBox="0 0 24 24" fill="none" :stroke="editElement.stroke || '#3B82F6'" stroke-width="2">
                                <path d="M12 12 L12 3 A9 9 0 0 0 4.2 7.5 Z"/>
                            </svg>
                            <span v-else-if="editSel.type === 'shape'" class="material-symbols-outlined text-xl" :style="{ color: editElement.stroke || '#3B82F6' }">{{ {circle:'circle',rect:'rectangle',triangle:'play_arrow'}[editElement.type] || 'shapes' }}</span>
                            <span v-else-if="editSel.type === 'arrow'" class="material-symbols-outlined text-xl" :style="{ color: editElement.color || '#fff' }">{{ editElement.noHead ? 'horizontal_rule' : 'arrow_right_alt' }}</span>
                            <span v-else-if="editSel.type === 'label'" class="material-symbols-outlined text-xl" :style="{ color: editElement.color || '#fff' }">text_fields</span>
                            <span v-else-if="editElement.type === 'emoji'" class="text-2xl">{{ editElement.emoji }}</span>
                            <img v-else-if="editElement.src" :src="editElement.src" class="w-7 h-7 rounded object-cover">
                            <img v-else-if="editElement.type && !['icon','group-token'].includes(editElement.type)"
                                :src="'/images/raidplan/raid-markers/' + (editElement.type || 'skull') + '.png'" class="w-7 h-7">
                            <span v-else-if="editElement.groupLabel" class="text-xs font-black" :style="{ color: editElement.color }">{{ editElement.groupLabel }}</span>
                            <span v-else class="material-symbols-outlined text-lg text-on-surface-variant/30">edit</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold text-white truncate">
                                {{ editElement.playerData?.name || editElement.groupLabel || editElement.label || editElement.text || editElementType }}
                            </div>
                            <div class="text-[8px] text-on-surface-variant/50 uppercase">{{ editSel.type }}</div>
                        </div>
                    </div>

                    <!-- Label edit -->
                    <div v-if="editSel.type === 'marker' || editSel.type === 'player'" class="space-y-1">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Label</label>
                    <input
                        :value="editSel.type === 'player' ? (editElement.customLabel || '') : (editElement.label || '')"
                        @input="updateEditProp(editSel.type === 'player' ? { customLabel: $event.target.value } : { label: $event.target.value })"
                        type="text" placeholder="Label..."
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-[10px] text-white focus:ring-1 focus:ring-primary outline-none"
                    >
                </div>

                <!-- Text edit (for text labels) -->
                <div v-if="editSel.type === 'label'" class="space-y-1">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Text</label>
                    <input
                        :value="editElement.text || ''"
                        @input="updateEditProp({ text: $event.target.value })"
                        type="text"
                        class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-[10px] text-white focus:ring-1 focus:ring-primary outline-none"
                    >
                </div>

                <!-- Fill toggle (for shapes circle/rect/cone) -->
                <div v-if="canEditFill(editSel.type, editElement)" class="flex items-center justify-between">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Fill</label>
                    <button
                        @click="updateEditProp({
                            filled: !(editElement.filled ?? true),
                            color: !(editElement.filled ?? true) ? (editElement.stroke || '#3B82F6') + '4D' : 'none',
                        })"
                        class="w-8 h-4 rounded-full transition-all relative"
                        :class="(editElement.filled ?? true) ? 'bg-blue-500' : 'bg-white/10'"
                    >
                        <div class="w-3 h-3 rounded-full bg-white absolute top-0.5 transition-all"
                            :class="(editElement.filled ?? true) ? 'left-[18px]' : 'left-0.5'"></div>
                    </button>
                </div>

                <!-- Direction toggle -->
                <div v-if="canToggleDirectionForElement(editSel.type, editElement)" class="flex items-center justify-between">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Direction</label>
                    <button
                        @click="updateEditProp({ showDirection: !(editElement.showDirection ?? (editElement.playerData ? true : false)) })"
                        class="w-8 h-4 rounded-full transition-all relative"
                        :class="(editElement.showDirection ?? (editElement.playerData ? true : false)) ? 'bg-red-500' : 'bg-white/10'"
                    >
                        <div class="w-3 h-3 rounded-full bg-white absolute top-0.5 transition-all"
                            :class="(editElement.showDirection ?? (editElement.playerData ? true : false)) ? 'left-[18px]' : 'left-0.5'"></div>
                    </button>
                </div>

                <!-- Font size (for text labels) -->
                <div v-if="editSel.type === 'label'" class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Font Size') }}</label>
                        <span class="text-[9px] font-bold text-white">{{ editElement.fontSize || 14 }}px</span>
                    </div>
                    <input type="range" min="8" max="48" step="1"
                        :value="editElement.fontSize || 14"
                        @input="updateEditProp({ fontSize: Number($event.target.value) })"
                        class="w-full h-1 bg-white/10 rounded-full appearance-none cursor-pointer accent-primary">
                </div>

                <!-- Opacity (shapes and labels only) -->
                <div v-if="editSel.type === 'shape' || editSel.type === 'label'" class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Opacity') }}</label>
                        <span class="text-[9px] font-bold text-white">{{ Math.round((editElement.opacity ?? 1) * 100) }}%</span>
                    </div>
                    <input type="range" min="10" max="100" step="5"
                        :value="Math.round((editElement.opacity ?? 1) * 100)"
                        @input="updateEditProp({ opacity: Number($event.target.value) / 100 })"
                        class="w-full h-1 bg-white/10 rounded-full appearance-none cursor-pointer accent-primary">
                </div>

                <!-- Cone angle (for sector shapes) -->
                <div v-if="editSel.type === 'shape' && editElement.type === 'cone'" class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ __('Spread') }}</label>
                        <span class="text-[9px] font-bold text-white">{{ Math.round((editElement.spread ?? 0.5) * 180 / Math.PI) }}°</span>
                    </div>
                    <input type="range" min="10" max="180" step="5"
                        :value="Math.round((editElement.spread ?? 0.5) * 180 / Math.PI)"
                        @input="updateEditProp({ spread: Number($event.target.value) * Math.PI / 180 })"
                        class="w-full h-1 bg-white/10 rounded-full appearance-none cursor-pointer accent-primary">
                </div>

                <!-- Color (for shapes, labels, arrows) -->
                <div v-if="editSel.type === 'shape' || editSel.type === 'label' || editSel.type === 'arrow'" class="space-y-1.5">
                    <label class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Color</label>
                    <div class="flex items-center gap-1.5">
                        <button v-for="c in colorPresets" :key="c"
                            @click="editSel.type === 'shape'
                                ? updateEditProp({ color: (editElement.filled ?? true) ? c + '4D' : 'none', stroke: c })
                                : updateEditProp({ color: c })"
                            class="w-5 h-5 rounded-full border-2 transition-all hover:scale-110"
                            :style="{ backgroundColor: c }"
                            :class="(editElement.stroke || editElement.color) === c ? 'border-white' : 'border-transparent'"
                        ></button>
                    </div>
                </div>
                </template>
            </div>
        </div>
    </Teleport>

    <!-- Share popup -->
    <Teleport to="body">
        <div v-if="showSharePopup"
            class="fixed inset-0 z-[290] flex items-center justify-center bg-black/50"
            @click.self="showSharePopup = false" @mousedown.stop>
            <div class="bg-[#1e1e22] border border-white/10 rounded-xl shadow-2xl p-5 w-[400px] space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-black uppercase tracking-widest text-white">{{ __('Share Plan') }}</span>
                    <button @click="showSharePopup = false" class="text-on-surface-variant hover:text-white"><span class="material-symbols-outlined text-lg">close</span></button>
                </div>
                <p class="text-[10px] text-on-surface-variant">{{ __('Anyone with this link can view the plan (read-only).') }}</p>
                <div class="flex gap-2">
                    <input :value="shareUrl" readonly
                        class="flex-1 bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-xs text-white font-mono outline-none">
                    <button @click="copyShareUrl"
                        class="px-3 py-2 rounded-lg bg-primary/80 hover:bg-primary text-white text-[10px] font-bold transition-all">
                        {{ __('Copy') }}
                    </button>
                </div>
                <button @click="unsharePlan"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/20 text-red-400 hover:bg-red-500/20 text-[10px] font-bold transition-all">
                    <span class="material-symbols-outlined text-sm">link_off</span>
                    {{ __('Revoke Share Link') }}
                </button>
            </div>
        </div>
    </Teleport>

    <!-- Help modal -->
    <Teleport to="body">
        <div v-if="showHelp"
            class="fixed inset-0 z-[290] flex items-center justify-center bg-black/50"
            @click.self="showHelp = false" @mousedown.stop>
            <div class="bg-[#1e1e22] border border-white/10 rounded-xl shadow-2xl w-[480px] max-h-[80vh] flex flex-col overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/5">
                    <span class="text-sm font-black uppercase tracking-widest text-white">{{ __('Help & Shortcuts') }}</span>
                    <button @click="showHelp = false" class="text-on-surface-variant hover:text-white"><span class="material-symbols-outlined text-lg">close</span></button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-4 text-[11px]">
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Navigation</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Pan canvas</span><span class="font-mono text-white/50">Drag empty space</span></div>
                            <div class="flex justify-between"><span>Pan (always)</span><span class="font-mono text-white/50">Middle mouse drag</span></div>
                            <div class="flex justify-between"><span>Zoom in / out</span><span class="font-mono text-white/50">Scroll wheel</span></div>
                            <div class="flex justify-between"><span>Reset zoom</span><span class="font-mono text-white/50">Fit screen button</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Selection</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Select element</span><span class="font-mono text-white/50">Click</span></div>
                            <div class="flex justify-between"><span>Multi-select</span><span class="font-mono text-white/50">Ctrl + Click</span></div>
                            <div class="flex justify-between"><span>Rectangle select</span><span class="font-mono text-white/50">Shift + Drag</span></div>
                            <div class="flex justify-between"><span>Select linked group</span><span class="font-mono text-white/50">Click any member</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Editing</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Move element</span><span class="font-mono text-white/50">Drag</span></div>
                            <div class="flex justify-between"><span>Resize</span><span class="font-mono text-white/50">Drag blue corner handles</span></div>
                            <div class="flex justify-between"><span>Rotate</span><span class="font-mono text-white/50">Drag green handle</span></div>
                            <div class="flex justify-between"><span>Delete</span><span class="font-mono text-white/50">Del / Backspace</span></div>
                            <div class="flex justify-between"><span>Context menu</span><span class="font-mono text-white/50">Right-click</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Shortcuts</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Undo</span><span class="font-mono text-white/50">Ctrl + Z</span></div>
                            <div class="flex justify-between"><span>Redo</span><span class="font-mono text-white/50">Ctrl + Shift + Z</span></div>
                            <div class="flex justify-between"><span>Copy</span><span class="font-mono text-white/50">Ctrl + C</span></div>
                            <div class="flex justify-between"><span>Paste</span><span class="font-mono text-white/50">Ctrl + V</span></div>
                            <div class="flex justify-between"><span>Duplicate</span><span class="font-mono text-white/50">Ctrl + D</span></div>
                            <div class="flex justify-between"><span>Group selected</span><span class="font-mono text-white/50">Ctrl + G</span></div>
                            <div class="flex justify-between"><span>Close editor</span><span class="font-mono text-white/50">Escape</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Placing Elements</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Place icon/marker</span><span class="font-mono text-white/50">Select from panel → Click canvas</span></div>
                            <div class="flex justify-between"><span>Draw shape</span><span class="font-mono text-white/50">Select shape → Click & drag</span></div>
                            <div class="flex justify-between"><span>Place multiple</span><span class="font-mono text-white/50">Keep clicking (selection stays)</span></div>
                            <div class="flex justify-between"><span>Edit placed element</span><span class="font-mono text-white/50">Click on it (exits placement)</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Steps / Phases</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div class="flex justify-between"><span>Rename step</span><span class="font-mono text-white/50">Double-click tab</span></div>
                            <div class="flex justify-between"><span>Add step</span><span class="font-mono text-white/50">+ button → New / Copy</span></div>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-[9px] font-black uppercase tracking-widest text-primary mb-2">Toolbar Panels</h3>
                        <div class="space-y-1 text-on-surface-variant">
                            <div>Multiple panels can be open simultaneously. Each is draggable.</div>
                            <div><span class="text-white font-bold">Markers</span> — Raid markers (skull, cross, etc.)</div>
                            <div><span class="text-white font-bold">Shapes</span> — Circle, Rectangle, Triangle, Arrow, Line, Sector, Text</div>
                            <div><span class="text-white font-bold">Abilities</span> — Boss abilities + class abilities (Warlock gateway)</div>
                            <div><span class="text-white font-bold">Icons</span> — Roles, Classes, Boss portraits</div>
                            <div><span class="text-white font-bold">Roster</span> — Player avatars + raid groups</div>
                            <div><span class="text-white font-bold">Emoji</span> — Arrows, warnings, shapes, misc + custom emoji input</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Text input tooltip (appears at click point) -->
    <Teleport to="body">
        <div v-if="textPopup && editorOpen"
            class="fixed z-[280] bg-[#1e1e22] border border-white/10 rounded-lg shadow-2xl flex items-center gap-1 p-1"
            :style="{ left: textPopup.screenX + 'px', top: (textPopup.screenY - 40) + 'px' }"
            @mousedown.stop @click.stop>
            <input
                v-model="textPopupInput"
                type="text"
                :placeholder="__('Enter text...')"
                class="bg-white/5 border border-white/10 rounded px-2 py-1 text-xs text-white focus:ring-1 focus:ring-primary outline-none w-[160px]"
                @keydown.enter="confirmTextPopup"
                @keydown.escape="cancelTextPopup"
                autofocus
            >
            <button @click="confirmTextPopup" class="px-2 py-1 rounded bg-primary/80 hover:bg-primary text-white text-[9px] font-bold transition-all">OK</button>
            <button @click="cancelTextPopup" class="px-1 py-1 text-on-surface-variant hover:text-white transition-all">
                <span class="material-symbols-outlined text-xs">close</span>
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.editor-enter-active,
.editor-leave-active {
    transition: all 0.25s ease;
}
.editor-enter-from {
    opacity: 0;
    transform: scale(0.97);
}
.editor-leave-to {
    opacity: 0;
    transform: scale(0.97);
}
</style>
