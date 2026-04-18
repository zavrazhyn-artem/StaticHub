<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    activeTool: { type: String, default: 'select' },
    abilities: { type: Array, default: () => [] },
    bossPortraits: { type: Array, default: () => [] },
    roster: { type: Array, default: () => [] },
    groups: { type: Object, default: () => ({}) },
    canUndo: { type: Boolean, default: false },
    canRedo: { type: Boolean, default: false },
});
const emit = defineEmits(['select-tool', 'select-icon', 'undo', 'redo']);

const markerTypes = [
    { id: 'skull', img: '/images/raidplan/raid-markers/skull.png' },
    { id: 'cross', img: '/images/raidplan/raid-markers/cross.png' },
    { id: 'square', img: '/images/raidplan/raid-markers/square.png' },
    { id: 'moon', img: '/images/raidplan/raid-markers/moon.png' },
    { id: 'triangle', img: '/images/raidplan/raid-markers/triangle.png' },
    { id: 'diamond', img: '/images/raidplan/raid-markers/diamond.png' },
    { id: 'circle_m', img: '/images/raidplan/raid-markers/circle.png' },
    { id: 'star', img: '/images/raidplan/raid-markers/star.png' },
];

const classIcons = computed(() => [
    { id: 'deathknight', label: __('Death Knight') }, { id: 'demonhunter', label: __('Demon Hunter') },
    { id: 'druid', label: __('Druid') }, { id: 'evoker', label: __('Evoker') },
    { id: 'hunter', label: __('Hunter') }, { id: 'mage', label: __('Mage') },
    { id: 'monk', label: __('Monk') }, { id: 'paladin', label: __('Paladin') },
    { id: 'priest', label: __('Priest') }, { id: 'rogue', label: __('Rogue') },
    { id: 'shaman', label: __('Shaman') }, { id: 'warlock', label: __('Warlock') },
    { id: 'warrior', label: __('Warrior') },
]);

const shapeTools = computed(() => [
    { id: 'circle', icon: 'circle', label: __('Circle') },
    { id: 'rect', icon: 'rectangle', label: __('Rectangle') },
    { id: 'triangle', icon: 'play_arrow', label: __('Triangle') },
    { id: 'arrow', icon: 'arrow_right_alt', label: __('Arrow') },
    { id: 'line', icon: 'horizontal_rule', label: __('Line') },
    { id: 'cone', icon: 'sector_svg', label: __('Sector') },
    { id: 'text', icon: 'text_fields', label: __('Text') },
    { id: 'waypoint', icon: 'route', label: __('Path') },
]);

// ─── Multi-window panel state ───
const openPanels = ref(new Set());
const panelPositions = ref({
    markers: { x: 100, y: 60 }, shapes: { x: 140, y: 80 },
    abilities: { x: 180, y: 60 }, icons: { x: 220, y: 80 },
    roster: { x: 260, y: 60 }, emoji: { x: 300, y: 80 },
});

const togglePanel = (p) => {
    const s = new Set(openPanels.value);
    if (s.has(p)) s.delete(p); else s.add(p);
    openPanels.value = s;
};
const closePanel = (p) => {
    const s = new Set(openPanels.value);
    s.delete(p);
    openPanels.value = s;
};
const isPanelOpen = (p) => openPanels.value.has(p);

// Draggable per-panel
const draggingPanel = ref(null);
const dragStart = ref({ x: 0, y: 0 });
const startPanelDrag = (e, panelId) => {
    draggingPanel.value = panelId;
    const pos = panelPositions.value[panelId];
    dragStart.value = { x: e.clientX - pos.x, y: e.clientY - pos.y };
    e.preventDefault();
};
const onMouseMove = (e) => {
    if (draggingPanel.value) {
        panelPositions.value[draggingPanel.value] = {
            x: e.clientX - dragStart.value.x, y: e.clientY - dragStart.value.y,
        };
    }
};
const onMouseUp = () => { draggingPanel.value = null; };

// ─── Dodge logic: panels flee from canvas drag ───
const PANEL_W = 320, PANEL_H = 420, DODGE_MARGIN = 30;
const dodgingPanels = ref(new Set());
let prevPointer = null;
let prevPointerClearTimer = null;

const handleDragPointer = (e) => {
    const { x, y } = e.detail;
    const dx = prevPointer ? x - prevPointer.x : 0;
    const dy = prevPointer ? y - prevPointer.y : 0;
    prevPointer = { x, y };
    clearTimeout(prevPointerClearTimer);
    prevPointerClearTimer = setTimeout(() => { prevPointer = null; }, 150);

    for (const panelId of openPanels.value) {
        if (draggingPanel.value === panelId) continue;
        const pos = panelPositions.value[panelId];
        const left = pos.x, top = pos.y, right = pos.x + PANEL_W, bottom = pos.y + PANEL_H;
        const hit = x >= left - DODGE_MARGIN && x <= right + DODGE_MARGIN &&
                    y >= top - DODGE_MARGIN && y <= bottom + DODGE_MARGIN;
        if (!hit) continue;

        const cx = pos.x + PANEL_W / 2, cy = pos.y + PANEL_H / 2;
        let nx, ny;
        const mag = Math.hypot(dx, dy);
        if (mag > 0.5) { nx = dx / mag; ny = dy / mag; }
        else {
            const vx = cx - x, vy = cy - y;
            const vm = Math.hypot(vx, vy) || 1;
            nx = vx / vm; ny = vy / vm;
        }

        const pushDist = Math.max(PANEL_W, PANEL_H) + 60;
        const vw = window.innerWidth, vh = window.innerHeight;
        const clamp = (nxCandidate, nyCandidate) => ({
            x: Math.max(10, Math.min(vw - PANEL_W - 10, nxCandidate)),
            y: Math.max(10, Math.min(vh - PANEL_H - 10, nyCandidate)),
        });

        let next = clamp(pos.x + nx * pushDist, pos.y + ny * pushDist);
        const stillHit = x >= next.x - DODGE_MARGIN && x <= next.x + PANEL_W + DODGE_MARGIN &&
                         y >= next.y - DODGE_MARGIN && y <= next.y + PANEL_H + DODGE_MARGIN;
        if (stillHit) {
            next = clamp(pos.x - ny * pushDist, pos.y + nx * pushDist);
        }

        panelPositions.value[panelId] = next;
        const s = new Set(dodgingPanels.value);
        s.add(panelId);
        dodgingPanels.value = s;
        setTimeout(() => {
            const ns = new Set(dodgingPanels.value);
            ns.delete(panelId);
            dodgingPanels.value = ns;
        }, 350);
    }
};

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
    window.addEventListener('bossplanner-drag-pointer', handleDragPointer);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    window.removeEventListener('bossplanner-drag-pointer', handleDragPointer);
    clearTimeout(prevPointerClearTimer);
});

// Roster items
const rosterItems = computed(() => {
    const items = [];
    for (const [gId, g] of Object.entries(props.groups)) {
        if ((g.members || []).length === 0) continue;
        items.push({ id: `group-${gId}`, type: 'group', groupId: Number(gId), label: '', displayName: g.label, color: g.color, count: g.members.length, img: null });
    }
    return items;
});
const playerItems = computed(() => props.roster.map(c => ({
    id: `player-${c.id}`, type: 'player', characterId: c.id,
    label: c.name, img: c.avatar_url, className: c.playable_class, role: c.assigned_role,
})));

// Panel definitions
const panelDefs = computed(() => ({
    markers: { title: __('Raid Markers'), sections: [
        { id: 'raid', label: __('Raid Markers'), items: markerTypes.map(m => ({ ...m, type: 'marker', label: '' })) },
    ]},
    shapes: { title: __('Shapes & Lines'), sections: [
        { id: 'shapes', label: __('Shapes & Lines'), items: shapeTools.value.map(s => ({ ...s, type: 'shape', label: '' })) },
    ]},
    abilities: { title: __('Abilities'), sections: [
        { id: 'class_abilities', label: __('Class Abilities'), items: [
            { id: 'warlock-gateway', type: 'ability', img: '/images/raidplan/stickers/gateway-fs8.png', label: '' },
        ]},
        { id: 'boss', label: __('Boss Abilities'), items: props.abilities.map(a => ({
            id: a, type: 'ability', img: `/images/raidplan/icons/${a}.png`, label: '',
        })) },
    ]},
    icons: { title: __('Icons'), sections: [
        { id: 'roles', label: __('Roles'), items: [
            { id: 'role-tank', type: 'class', img: '/images/raidplan/role/tank.svg', label: '', displayName: __('Tank') },
            { id: 'role-healer', type: 'class', img: '/images/raidplan/role/healer.svg', label: '', displayName: __('Healer') },
            { id: 'role-mdps', type: 'class', img: '/images/raidplan/role/mdps.svg', label: '', displayName: __('Melee DPS') },
            { id: 'role-rdps', type: 'class', img: '/images/raidplan/role/rdps.svg', label: '', displayName: __('Ranged DPS') },
        ]},
        { id: 'class', label: __('Classes'), items: classIcons.value.map(c => ({ ...c, type: 'class', img: `/images/raidplan/class/${c.id}.png`, label: '' })) },
        ...(props.bossPortraits.length ? [{ id: 'portraits', label: __('Boss Portraits'), items: props.bossPortraits.map((url, i) => ({
            id: `portrait-${i}`, type: 'portrait', img: url, label: '',
        })) }] : []),
    ]},
    roster: { title: __('Roster'), sections: [
        ...(rosterItems.value.length ? [{ id: 'groups', label: __('Groups'), items: rosterItems.value }] : []),
        { id: 'players', label: __('Players'), items: playerItems.value },
    ]},
    emoji: { title: __('Emoji'), sections: (() => {
        const mk = (arr) => arr.map(e => ({ id: 'e-' + e, type: 'emoji', emoji: e, label: '' }));
        return [
            { id: 'arrows', label: __('Arrows & Direction'), items: mk(['⬆','⬇','⬅','➡','↗','↘','↙','↖','⤴','⤵','🔄']) },
            { id: 'warning', label: __('Warning & Status'), items: mk(['⚠','❌','✅','❓','❗','⛔','🚫','💀','☠','🔥','💥','⚡','🛡','⚔','💣','🎯']) },
            { id: 'shapes_e', label: __('Shapes & Colors'), items: mk(['🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔶','🔷','🔸','🔹','💠']) },
            { id: 'misc', label: __('Misc'), items: mk(['⭐','💎','👑','🏴','🚩','📍','📌','🎪','🌀','💫','🌟','✨','🔮','🧿','👁','🫧']) },
        ];
    })() },
}));

const handleItemClick = (item) => { emit('select-icon', item); };

const customEmoji = ref('');
const addCustomEmoji = () => {
    if (!customEmoji.value.trim()) return;
    emit('select-icon', { id: customEmoji.value, type: 'emoji', emoji: customEmoji.value, label: '' });
    customEmoji.value = '';
};

const classSlug = (cls) => (cls || '').toLowerCase().replace(/\s+/g, '-').replace(/'/g, '');

const panelButtons = computed(() => [
    { id: 'markers', icon: null, img: '/images/raidplan/raid-markers/skull.png', label: __('Markers'), color: 'orange' },
    { id: 'shapes', icon: 'shapes', label: __('Shapes'), color: 'blue' },
    { id: 'abilities', icon: 'auto_fix_high', label: __('Abilities'), color: 'purple', needAbilities: true },
    { id: 'icons', icon: 'palette', label: __('Icons'), color: 'cyan' },
    { id: 'roster', icon: 'groups', label: __('Roster'), color: 'green' },
    { id: 'emoji', emoji: '😀', label: __('Emoji'), color: 'yellow' },
]);
const colorClasses = {
    orange: { active: 'bg-orange-500/20 text-orange-400', hover: 'hover:bg-white/5' },
    blue:   { active: 'bg-blue-500/20 text-blue-400',   hover: 'hover:bg-white/5' },
    purple: { active: 'bg-purple-500/20 text-purple-400', hover: 'hover:bg-white/5' },
    cyan:   { active: 'bg-cyan-500/20 text-cyan-400',   hover: 'hover:bg-white/5' },
    green:  { active: 'bg-green-500/20 text-green-400', hover: 'hover:bg-white/5' },
    yellow: { active: 'bg-yellow-500/20 text-yellow-400', hover: 'hover:bg-white/5' },
};
</script>

<template>
    <div class="flex items-center gap-1 flex-wrap relative">
        <!-- Undo / Redo -->
        <div class="flex items-center gap-0.5 bg-surface-container/60 border border-white/5 rounded-xl p-1 backdrop-blur-sm">
            <button @click="emit('undo')"
                :disabled="!canUndo"
                class="flex items-center justify-center w-8 h-8 rounded-lg transition-all"
                :class="canUndo ? 'text-on-surface-variant hover:text-white hover:bg-white/5' : 'text-on-surface-variant/25 cursor-not-allowed'"
                :title="__('Undo') + ' (Ctrl+Z)'">
                <span class="material-symbols-outlined text-lg">undo</span>
            </button>
            <button @click="emit('redo')"
                :disabled="!canRedo"
                class="flex items-center justify-center w-8 h-8 rounded-lg transition-all"
                :class="canRedo ? 'text-on-surface-variant hover:text-white hover:bg-white/5' : 'text-on-surface-variant/25 cursor-not-allowed'"
                :title="__('Redo') + ' (Ctrl+Shift+Z)'">
                <span class="material-symbols-outlined text-lg">redo</span>
            </button>
        </div>

        <!-- Category buttons -->
        <div class="flex items-center gap-0.5 bg-surface-container/60 border border-white/5 rounded-xl p-1 backdrop-blur-sm">
            <template v-for="btn in panelButtons" :key="btn.id">
                <button v-if="!btn.needAbilities || abilities.length > 0"
                    @click="togglePanel(btn.id)"
                    class="flex items-center gap-1 px-2 h-8 rounded-lg transition-all"
                    :class="isPanelOpen(btn.id) ? colorClasses[btn.color].active : 'text-on-surface-variant hover:text-white ' + colorClasses[btn.color].hover"
                    :title="btn.label">
                    <img v-if="btn.img" :src="btn.img" class="w-4 h-4">
                    <span v-else-if="btn.emoji" class="text-sm">{{ btn.emoji }}</span>
                    <span v-else class="material-symbols-outlined text-sm">{{ btn.icon }}</span>
                    <span class="text-5xs font-semibold uppercase hidden xl:inline">{{ btn.label }}</span>
                </button>
            </template>
        </div>
    </div>

    <!-- Floating panels (multiple can be open) -->
    <Teleport to="body">
        <template v-for="panelId in openPanels" :key="panelId">
            <div
                class="fixed z-[250] w-[320px] max-h-[420px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
                :class="dodgingPanels.has(panelId) ? 'panel-dodging' : ''"
                :style="{ left: panelPositions[panelId].x + 'px', top: panelPositions[panelId].y + 'px' }"
                @mousedown.stop @click.stop>
                <!-- Header -->
                <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                    @mousedown="startPanelDrag($event, panelId)">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                        <span class="text-4xs font-bold uppercase tracking-wider text-on-surface-variant">{{ panelDefs[panelId]?.title }}</span>
                    </div>
                    <button @click="closePanel(panelId)" class="text-on-surface-variant/50 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-2 space-y-3 scrollbar-thin">
                    <div v-for="section in panelDefs[panelId]?.sections" :key="section.id">
                        <div class="px-1 pb-1">
                            <span class="text-5xs font-bold uppercase tracking-wider text-on-surface-variant/40">{{ section.label }}</span>
                        </div>
                        <!-- Shapes: grid tiles -->
                        <div v-if="section.id === 'shapes'" class="grid grid-cols-4 gap-1">
                            <button v-for="item in section.items" :key="item.id"
                                @click="handleItemClick(item)"
                                class="flex flex-col items-center justify-center gap-0.5 p-2 rounded-lg transition-all hover:bg-white/10"
                                :class="activeTool === item.id ? 'bg-blue-500/15 text-blue-400 ring-1 ring-blue-500/30' : 'text-on-surface-variant'"
                                :title="item.label">
                                <svg v-if="item.icon === 'sector_svg'" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 12 L12 3 A9 9 0 0 0 4.2 7.5 Z"/>
                                </svg>
                                <span v-else class="material-symbols-outlined text-xl">{{ item.icon }}</span>
                                <span class="text-5xs font-semibold uppercase">{{ item.label }}</span>
                            </button>
                        </div>
                        <!-- Groups -->
                        <div v-else-if="section.id === 'groups'" class="grid grid-cols-4 gap-1">
                            <button v-for="item in section.items" :key="item.id"
                                @click="handleItemClick(item)"
                                class="flex flex-col items-center justify-center gap-0.5 p-2 rounded-lg transition-all hover:bg-white/10 border border-transparent"
                                :title="(item.displayName || item.label) + ' (' + item.count + 'p)'">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center border-2"
                                    :style="{ borderColor: item.color, backgroundColor: item.color + '20' }">
                                    <span class="text-3xs font-bold" :style="{ color: item.color }">{{ item.displayName || item.label }}</span>
                                </div>
                                <span class="text-5xs font-semibold text-on-surface-variant/50">{{ item.count }}p</span>
                            </button>
                        </div>
                        <!-- Players -->
                        <div v-else-if="section.id === 'players'" class="grid grid-cols-2 gap-1">
                            <button v-for="item in section.items" :key="item.id"
                                @click="handleItemClick(item)"
                                class="flex items-center gap-1.5 px-2 py-1 rounded-lg transition-all hover:bg-white/5 text-left">
                                <img v-if="item.img" :src="item.img" class="w-6 h-6 rounded object-cover border border-white/10 shrink-0">
                                <div class="min-w-0">
                                    <div class="text-4xs font-semibold truncate" :class="'text-wow-' + classSlug(item.className)">{{ item.label }}</div>
                                </div>
                            </button>
                        </div>
                        <!-- Grid: icons/emoji -->
                        <div v-else class="grid grid-cols-8 gap-1">
                            <button v-for="item in section.items" :key="item.id"
                                @click="handleItemClick(item)"
                                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:bg-white/10 hover:scale-110 border border-transparent"
                                :title="item.label || item.id">
                                <span v-if="item.type === 'emoji'" class="text-lg leading-none">{{ item.emoji }}</span>
                                <img v-else :src="item.img" class="w-6 h-6 rounded" :alt="item.id">
                            </button>
                        </div>
                    </div>

                    <!-- Custom emoji input -->
                    <div v-if="panelId === 'emoji'" class="px-1 pt-1 border-t border-white/5">
                        <div class="px-1 pb-1">
                            <span class="text-5xs font-bold uppercase tracking-wider text-on-surface-variant/40">{{ __('Custom Emoji') }}</span>
                        </div>
                        <div class="flex gap-1">
                            <input v-model="customEmoji" type="text" :placeholder="__('Paste emoji...')"
                                class="flex-1 bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-sm text-center focus:ring-1 focus:ring-yellow-500 outline-none"
                                @keydown.enter="addCustomEmoji">
                            <button @click="addCustomEmoji"
                                class="px-2 py-1.5 rounded-lg bg-yellow-500/10 text-yellow-400 hover:bg-yellow-500/20 transition-all"
                                :disabled="!customEmoji.trim()">
                                <span class="material-symbols-outlined text-sm">add</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </Teleport>
</template>

<style scoped>
.panel-dodging {
    transition: left 0.3s cubic-bezier(.4, .9, .35, 1), top 0.3s cubic-bezier(.4, .9, .35, 1);
}
.text-wow-warrior      { color: #C69B6D; }
.text-wow-paladin      { color: #F48CBA; }
.text-wow-hunter       { color: #ABD473; }
.text-wow-rogue        { color: #FFF468; }
.text-wow-priest       { color: #FFFFFF; }
.text-wow-death-knight { color: #C41F3B; }
.text-wow-shaman       { color: #0070DD; }
.text-wow-mage         { color: #3FC7EB; }
.text-wow-warlock      { color: #8788EE; }
.text-wow-monk         { color: #00FF98; }
.text-wow-druid        { color: #FF7C0A; }
.text-wow-demon-hunter { color: #A330C9; }
.text-wow-evoker       { color: #33937F; }
</style>
