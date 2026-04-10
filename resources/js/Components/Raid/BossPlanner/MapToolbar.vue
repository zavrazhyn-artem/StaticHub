<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    activeTool: { type: String, default: 'select' },
    abilities: { type: Array, default: () => [] },
    bossPortraits: { type: Array, default: () => [] },
    roster: { type: Array, default: () => [] },
    groups: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['select-tool', 'select-icon']);

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

const classIcons = [
    { id: 'deathknight', label: 'Death Knight' }, { id: 'demonhunter', label: 'Demon Hunter' },
    { id: 'druid', label: 'Druid' }, { id: 'evoker', label: 'Evoker' },
    { id: 'hunter', label: 'Hunter' }, { id: 'mage', label: 'Mage' },
    { id: 'monk', label: 'Monk' }, { id: 'paladin', label: 'Paladin' },
    { id: 'priest', label: 'Priest' }, { id: 'rogue', label: 'Rogue' },
    { id: 'shaman', label: 'Shaman' }, { id: 'warlock', label: 'Warlock' },
    { id: 'warrior', label: 'Warrior' },
];

const shapeTools = [
    { id: 'circle', icon: 'circle', label: 'Circle' },
    { id: 'rect', icon: 'rectangle', label: 'Rectangle' },
    { id: 'triangle', icon: 'play_arrow', label: 'Triangle' },
    { id: 'arrow', icon: 'arrow_right_alt', label: 'Arrow' },
    { id: 'line', icon: 'horizontal_rule', label: 'Line' },
    { id: 'cone', icon: 'sector_svg', label: 'Sector' },
    { id: 'text', icon: 'text_fields', label: 'Text' },
    { id: 'waypoint', icon: 'route', label: 'Path' },
];

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
onMounted(() => { window.addEventListener('mousemove', onMouseMove); window.addEventListener('mouseup', onMouseUp); });
onUnmounted(() => { window.removeEventListener('mousemove', onMouseMove); window.removeEventListener('mouseup', onMouseUp); });

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
    markers: { title: 'Raid Markers', sections: [
        { id: 'raid', label: 'Raid Markers', items: markerTypes.map(m => ({ ...m, type: 'marker', label: '' })) },
    ]},
    shapes: { title: 'Shapes & Lines', sections: [
        { id: 'shapes', label: 'Shapes & Lines', items: shapeTools.map(s => ({ ...s, type: 'shape', label: '' })) },
    ]},
    abilities: { title: 'Abilities', sections: [
        { id: 'class_abilities', label: 'Class Abilities', items: [
            { id: 'warlock-gateway', type: 'ability', img: '/images/raidplan/stickers/gateway-fs8.png', label: '' },
        ]},
        { id: 'boss', label: 'Boss Abilities', items: props.abilities.map(a => ({
            id: a, type: 'ability', img: `/images/raidplan/icons/${a}.png`, label: '',
        })) },
    ]},
    icons: { title: 'Icons', sections: [
        { id: 'roles', label: 'Roles', items: [
            { id: 'role-tank', type: 'class', img: '/images/raidplan/role/tank.svg', label: '', displayName: 'Tank' },
            { id: 'role-healer', type: 'class', img: '/images/raidplan/role/healer.svg', label: '', displayName: 'Healer' },
            { id: 'role-mdps', type: 'class', img: '/images/raidplan/role/mdps.svg', label: '', displayName: 'Melee DPS' },
            { id: 'role-rdps', type: 'class', img: '/images/raidplan/role/rdps.svg', label: '', displayName: 'Ranged DPS' },
        ]},
        { id: 'class', label: 'Classes', items: classIcons.map(c => ({ ...c, type: 'class', img: `/images/raidplan/class/${c.id}.png`, label: '' })) },
        ...(props.bossPortraits.length ? [{ id: 'portraits', label: 'Boss Portraits', items: props.bossPortraits.map((url, i) => ({
            id: `portrait-${i}`, type: 'portrait', img: url, label: '',
        })) }] : []),
    ]},
    roster: { title: 'Roster', sections: [
        ...(rosterItems.value.length ? [{ id: 'groups', label: 'Groups', items: rosterItems.value }] : []),
        { id: 'players', label: 'Players', items: playerItems.value },
    ]},
    emoji: { title: 'Emoji', sections: (() => {
        const mk = (arr) => arr.map(e => ({ id: 'e-' + e, type: 'emoji', emoji: e, label: '' }));
        return [
            { id: 'arrows', label: 'Arrows & Direction', items: mk(['⬆','⬇','⬅','➡','↗','↘','↙','↖','⤴','⤵','🔄']) },
            { id: 'warning', label: 'Warning & Status', items: mk(['⚠','❌','✅','❓','❗','⛔','🚫','💀','☠','🔥','💥','⚡','🛡','⚔','💣','🎯']) },
            { id: 'shapes_e', label: 'Shapes & Colors', items: mk(['🔴','🟠','🟡','🟢','🔵','🟣','⚫','⚪','🟤','🔶','🔷','🔸','🔹','💠']) },
            { id: 'misc', label: 'Misc', items: mk(['⭐','💎','👑','🏴','🚩','📍','📌','🎪','🌀','💫','🌟','✨','🔮','🧿','👁','🫧']) },
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

const panelButtons = [
    { id: 'markers', icon: null, img: '/images/raidplan/raid-markers/skull.png', label: 'Markers', color: 'orange' },
    { id: 'shapes', icon: 'shapes', label: 'Shapes', color: 'blue' },
    { id: 'abilities', icon: 'auto_fix_high', label: 'Abilities', color: 'purple', needAbilities: true },
    { id: 'icons', icon: 'palette', label: 'Icons', color: 'cyan' },
    { id: 'roster', icon: 'groups', label: 'Roster', color: 'green' },
    { id: 'emoji', emoji: '😀', label: 'Emoji', color: 'yellow' },
];
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
        <!-- Select tool -->
        <div class="flex items-center gap-0.5 bg-surface-container/60 border border-white/5 rounded-xl p-1 backdrop-blur-sm">
            <button @click="emit('select-tool', 'select')"
                class="flex items-center justify-center w-8 h-8 rounded-lg transition-all"
                :class="activeTool === 'select' ? 'bg-primary/20 text-primary' : 'text-on-surface-variant hover:text-white hover:bg-white/5'"
                title="Select & Move">
                <span class="material-symbols-outlined text-lg">arrow_selector_tool</span>
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
                    <span class="text-[8px] font-bold uppercase hidden xl:inline">{{ btn.label }}</span>
                </button>
            </template>
        </div>
    </div>

    <!-- Floating panels (multiple can be open) -->
    <Teleport to="body">
        <template v-for="panelId in openPanels" :key="panelId">
            <div
                class="fixed z-[250] w-[320px] max-h-[420px] bg-[#1a1a1e] border border-white/10 rounded-xl shadow-2xl flex flex-col overflow-hidden"
                :style="{ left: panelPositions[panelId].x + 'px', top: panelPositions[panelId].y + 'px' }"
                @mousedown.stop @click.stop>
                <!-- Header -->
                <div class="shrink-0 flex items-center justify-between px-3 py-2 border-b border-white/5 cursor-move select-none bg-[#222226]"
                    @mousedown="startPanelDrag($event, panelId)">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm text-on-surface-variant/50">drag_indicator</span>
                        <span class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant">{{ panelDefs[panelId]?.title }}</span>
                    </div>
                    <button @click="closePanel(panelId)" class="text-on-surface-variant/50 hover:text-white transition-colors">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>

                <!-- Content -->
                <div class="flex-1 overflow-y-auto p-2 space-y-3 scrollbar-thin">
                    <div v-for="section in panelDefs[panelId]?.sections" :key="section.id">
                        <div class="px-1 pb-1">
                            <span class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">{{ section.label }}</span>
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
                                <span class="text-[7px] font-bold uppercase">{{ item.label }}</span>
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
                                    <span class="text-[10px] font-black" :style="{ color: item.color }">{{ item.displayName || item.label }}</span>
                                </div>
                                <span class="text-[7px] font-bold text-on-surface-variant/50">{{ item.count }}p</span>
                            </button>
                        </div>
                        <!-- Players -->
                        <div v-else-if="section.id === 'players'" class="grid grid-cols-2 gap-1">
                            <button v-for="item in section.items" :key="item.id"
                                @click="handleItemClick(item)"
                                class="flex items-center gap-1.5 px-2 py-1 rounded-lg transition-all hover:bg-white/5 text-left">
                                <img v-if="item.img" :src="item.img" class="w-6 h-6 rounded object-cover border border-white/10 shrink-0">
                                <div class="min-w-0">
                                    <div class="text-[9px] font-bold truncate" :class="'text-wow-' + classSlug(item.className)">{{ item.label }}</div>
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
                            <span class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40">Custom Emoji</span>
                        </div>
                        <div class="flex gap-1">
                            <input v-model="customEmoji" type="text" placeholder="Paste emoji..."
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
