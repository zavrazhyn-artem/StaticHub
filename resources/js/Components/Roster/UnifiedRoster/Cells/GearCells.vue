<script setup>
import { inject, computed } from 'vue';
import { useWowheadIcons } from '@/composables/useWowheadIcons';

const rowHeights = inject('rowHeights');
const { getIconUrl } = useWowheadIcons();

const props = defineProps({
    char: { type: Object, required: true },
    isAlt: { type: Boolean, default: false },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
});

const rh = computed(() => props.isAlt ? rowHeights.alt : rowHeights.main);
const emit = defineEmits(['audit-click']);

// WoW Equip Slots in order
const slots = [
    'HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'WRIST',
    'HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2',
    'TRINKET_1', 'TRINKET_2', 'MAIN_HAND', 'OFF_HAND'
];

const TIER_SLOTS = new Set(['HEAD', 'SHOULDER', 'CHEST', 'HANDS', 'LEGS']);

const getItem = (slot) => (props.char?.equipment || []).find(i => i.slot === slot);

const setItemIds = computed(() =>
    (props.char?.equipment || []).filter(i => i.is_set_piece).map(i => i.id)
);

// ── Gear cell size — adjust these two values to resize all item cells ──
const CELL_MAIN = 38; // px — main-row cell size
const CELL_ALT  = 22; // px — alt-row cell size

// Track colours — match gear-upgrade scale
const TRACK_COLORS = {
    Myth:       '#FB923C',
    Hero:       '#C084FC',
    Champion:   '#60A5FA',
    Veteran:    '#4ADE80',
    Adventurer: '#2dd4bf',
};
const TRACK_ABBREV = { Myth:'M', Hero:'H', Champion:'C', Veteran:'V', Adventurer:'A' };

const craftedColor = (ilvl) => {
    if (ilvl >= 275) return '#FB923C';
    if (ilvl >= 262) return '#C084FC';
    if (ilvl >= 246) return '#4ADE80';
    return '#9ca3af';
};

const trackColor = (item) => {
    if (item.is_crafted) return craftedColor(item.ilvl);
    return TRACK_COLORS[item.upgrade?.track] ?? '#6b7280';
};

const getWowheadData = (item) => {
    if (!item) return '';
    const parts = [`item=${item.id}`];
    if (item.ilvl) parts.push(`ilvl=${item.ilvl}`);
    if (item.enchant_id) parts.push(`ench=${item.enchant_id}`);
    if (item.gem_ids?.length) parts.push(`gems=${item.gem_ids.join(':')}`);
    if (item.bonus_ids?.length) parts.push(`bonus=${item.bonus_ids.join(':')}`);
    if (props.char?.class_id) parts.push(`class=${props.char.class_id}`);
    if (props.char?.spec_id) parts.push(`spec=${props.char.spec_id}`);
    if (setItemIds.value.length) parts.push(`pcs=${setItemIds.value.join(':')}`);
    return parts.join('&');
};

const isTierPiece  = (slot, item) => item && TIER_SLOTS.has(slot) && item.is_set_piece;
const isMissEnchant = (slot) => (props.char?.missing_enchants_slots ?? []).includes(slot);
const isLowEnchant  = (slot) => (props.char?.low_quality_enchants_slots ?? []).includes(slot);
const hasEmptySock  = (slot, item) => item?.has_empty_socket === true;

const problemDots = (slot, item) => {
    if (!item) return [];
    const dots = [];
    if (isMissEnchant(slot)) dots.push({ icon: 'bolt',    color: '#ff6e84', title: 'Missing enchant' });
    if (isLowEnchant(slot))  dots.push({ icon: 'bolt',    color: '#fbbf24', title: 'Low quality enchant' });
    if (hasEmptySock(slot, item)) dots.push({ icon: 'diamond', color: '#fbbf24', title: 'Empty socket' });
    return dots;
};

// Scale helper — proportional to current cell size vs design reference (44px main / 22px alt)
const sp = (n) => {
    const cell = props.isAlt ? CELL_ALT  : CELL_MAIN;
    const ref  = props.isAlt ? 22        : 44;
    return `${Math.round(n * cell / ref)}px`;
};
</script>

<template>
    <!-- Audit column -->
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center border-l border-white/[0.06]']">
        <button v-if="hasAuditIssues(char)"
                @click="emit('audit-click')"
                class="inline-flex items-center gap-1 font-bold uppercase tracking-[0.06em] rounded-md transition-all cursor-pointer hover:opacity-80"
                :class="isAlt ? 'text-[9px] px-1 py-0.5' : 'text-[10px] px-2 py-1'"
                style="border: 1px solid rgba(255,110,132,0.4); color: rgba(57, 255, 20);"
                :title="auditTitle(char)">
            <span class="material-symbols-outlined leading-none" :class="isAlt ? 'text-xs' : 'text-sm'">warning</span>
            {{ (char.missing_enchants_slots?.length ?? 0) + (char.low_quality_enchants_slots?.length ?? 0) + (char.empty_sockets_count ?? 0) }}
        </button>
        <span v-else class="inline-flex items-center gap-1 font-bold uppercase tracking-[0.06em]"
              :class="isAlt ? 'text-[9px]' : 'text-[10px]'"
              style="color: rgba(57,255,20,0.65);">
            <span class="material-symbols-outlined leading-none" :class="isAlt ? 'text-xs' : 'text-sm'">check_circle</span>
            <span v-if="!isAlt">ALL CLEAR</span>
        </span>
    </td>

    <!-- Upgrades missing -->
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center border-l border-white/5 font-bold text-gray-300']">
        {{ char.upgrades_missing ?? 0 }}
    </td>

    <!-- Item slots -->
    <td v-for="slot in slots" :key="slot" :class="rh" class="border-l border-white/5"
        style="padding: 6px 4px; text-align: center; vertical-align: middle;">
        <div class="inline-flex justify-center">
            <!-- ── Filled slot ── -->
            <div v-if="getItem(slot)"
                 class="relative"
                 :style="{
                     width:  (isAlt ? CELL_ALT : CELL_MAIN) + 'px',
                     height: (isAlt ? CELL_ALT : CELL_MAIN) + 'px',
                 }">
                <!-- Icon / wowhead link -->
                <a :href="`https://www.wowhead.com/item=${getItem(slot).id}`"
                   target="_blank"
                   class="block w-full h-full"
                   :data-wowhead="getWowheadData(getItem(slot))"
                   :title="`${slot} · ${getItem(slot).ilvl}`"
                   :style="{
                       borderRadius: '7px',
                       border: `2px solid ${trackColor(getItem(slot))}aa`,
                       boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.06), 0 0 0 1px rgba(0,0,0,0.4)',
                       overflow: 'hidden',
                       display: 'block',
                       background: '#1a1a1d',
                   }">
                    <img v-if="getIconUrl(getItem(slot).id)"
                         :src="getIconUrl(getItem(slot).id)"
                         class="w-full h-full object-cover"
                         :alt="getItem(slot).name" />
                    <span v-else class="flex items-center justify-center w-full h-full font-black uppercase"
                          :style="{ fontSize: isAlt ? '8px' : '10px', color: 'rgba(255,255,255,0.35)', letterSpacing:'0.04em' }">
                        {{ slot.substring(0, 4).toLowerCase() }}
                    </span>
                </a>

                <!-- ilvl — inside icon, top-left corner overlay -->
                <div v-if="!isAlt"
                     :style="{
                         position:'absolute', top:sp(-5), left:sp(-10),
                         fontSize:sp(10), fontWeight:900,
                         fontFamily:'\'JetBrains Mono\',monospace',
                         color:'#ffffff',
                         background:'rgba(0,0,0,0.62)',
                         padding:`${sp(1)} ${sp(3)}`, borderRadius:sp(3),
                         pointerEvents:'none', whiteSpace:'nowrap', zIndex:4,
                         lineHeight:1.1,
                         border: `1px solid ${trackColor(getItem(slot))}55`,
                     }">
                    {{ getItem(slot).ilvl }}
                </div>
                <div v-else
                     :style="{
                         position:'absolute', top:'1px', left:'1px',
                         fontSize:sp(8), fontWeight:800,
                         color:'#ffffff', background:'rgba(0,0,0,0.6)',
                         padding:`0 ${sp(2)}`, borderRadius:sp(2),
                         pointerEvents:'none', whiteSpace:'nowrap', zIndex:4,
                     }">
                    {{ getItem(slot).ilvl }}
                </div>

                <!-- Track / Craft badge — below icon, with breathing room -->
                <div v-if="!isAlt"
                     :style="{
                         position:'absolute', bottom:sp(-6), left:'50%',
                         transform:'translateX(-50%)',
                         fontSize:sp(10), fontWeight:900, lineHeight:1,
                         background:'#0e0e10',
                         padding:`0 ${sp(5)}`, borderRadius:sp(3),
                         whiteSpace:'nowrap', pointerEvents:'none', zIndex:2,
                         letterSpacing:'0.06em',
                         fontFamily: '\'JetBrains Mono\', monospace',
                         color: trackColor(getItem(slot)),
                         border: `1px solid ${trackColor(getItem(slot))}55`,
                     }">
                    <template v-if="getItem(slot).is_crafted">КРАФТ</template>
                    <template v-else-if="getItem(slot).upgrade">
                        {{ TRACK_ABBREV[getItem(slot).upgrade.track] || getItem(slot).upgrade.track }} {{ getItem(slot).upgrade.level }}/{{ getItem(slot).upgrade.max }}
                    </template>
                </div>

                <!-- Tier T badge — outside top-right -->
                <div v-if="!isAlt && isTierPiece(slot, getItem(slot))"
                     :style="{
                         position:'absolute', top:sp(-3), right:sp(-3),
                         width:sp(14), height:sp(14), borderRadius:sp(4),
                         background:'#fbbf24', color:'#0e0e10',
                         fontSize:sp(8), fontWeight:900,
                         display:'flex', alignItems:'center', justifyContent:'center',
                         border:'1px solid #0e0e10',
                         fontFamily:'\'JetBrains Mono\',monospace',
                         pointerEvents:'none', zIndex:3,
                     }"
                     title="Tier piece">T</div>

                <!-- Problem dots — further outside right -->
                <div v-if="!isAlt && problemDots(slot, getItem(slot)).length"
                     :style="{
                         display:'flex', flexDirection:'column', gap:sp(2),
                         pointerEvents:'none', zIndex:3,
                         position:'absolute',
                         top: isTierPiece(slot, getItem(slot)) ? sp(12) : sp(-3),
                         right: sp(-3),
                     }">
                    <div v-for="(dot, di) in problemDots(slot, getItem(slot)).slice(0, 3)" :key="di"
                         :title="dot.title"
                         :style="{
                             width:sp(13), height:sp(13),
                             borderRadius:'50%', background:'#0e0e10',
                             display:'flex', alignItems:'center', justifyContent:'center',
                             border:`1.5px solid ${dot.color}`,
                         }">
                        <span class="material-symbols-outlined"
                              :style="{ fontSize:sp(8), color:dot.color, lineHeight:1 }">{{ dot.icon }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Empty slot ── -->
            <div v-else
                 class="flex items-center justify-center rounded border border-white/5 bg-black/20"
                 :style="{ width: (isAlt ? CELL_ALT : CELL_MAIN) + 'px', height: (isAlt ? CELL_ALT : CELL_MAIN) + 'px' }"
                 :title="slot">
                <span :class="isAlt ? 'text-5xs' : 'text-4xs'" class="text-gray-700 font-semibold uppercase">{{ slot.substring(0, 3) }}</span>
            </div>
        </div>
    </td>
</template>
