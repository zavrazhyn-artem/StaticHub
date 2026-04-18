<script setup>
import { computed } from 'vue';

/**
 * Renders a single equipped-item cell.
 *
 * `item` is the compiled shape produced by RosterCompilerService::resolveEquipment():
 * { slot, id, name, ilvl, quality, enchant_id, gem_ids, socket_count, media_id }
 */
const props = defineProps({
    item: {
        type: Object,
        default: null,
    },
    slotName: {
        type: String,
        required: true,
    },
    classId: {
        type: Number,
        default: null,
    },
    specId: {
        type: Number,
        default: null,
    },
    setItemIds: {
        type: Array,
        default: () => [],
    },
});

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const ENCHANTABLE_SLOTS = new Set([
    'BACK', 'CHEST', 'WRIST', 'LEGS', 'FEET',
    'FINGER_1', 'FINGER_2', 'MAIN_HAND', 'OFF_HAND',
]);

const QUALITY_COLORS = {
    POOR:      'text-gray-400',
    COMMON:    'text-white',
    UNCOMMON:  'text-green-400',
    RARE:      'text-blue-400',
    EPIC:      'text-purple-400',
    LEGENDARY: 'text-orange-400',
    ARTIFACT:  'text-yellow-200',
    HEIRLOOM:  'text-blue-200',
};

const trackAbbreviations = {
    Myth: 'M',
    Hero: 'H',
    Champion: 'C',
    Veteran: 'V',
    Adventurer: 'A'
};

// ---------------------------------------------------------------------------
// Computed
// ---------------------------------------------------------------------------

const qualityColor = computed(() =>
    props.item ? (QUALITY_COLORS[props.item.quality] ?? 'text-white') : 'text-gray-500'
);

const isEnchantable = computed(() =>
    ENCHANTABLE_SLOTS.has(props.slotName.toUpperCase())
);

const hasEnchant = computed(() => props.item?.enchant_id != null);

const hasEmptySocket = computed(() => {
    if (!props.item || props.item.socket_count === 0) return false;
    return (props.item.gem_ids?.length ?? 0) < props.item.socket_count;
});

/** Red border + exclamation badge when the item has a fixable optimization gap. */
const hasMissingOptimization = computed(() =>
    !!props.item && ((isEnchantable.value && !hasEnchant.value) || hasEmptySocket.value)
);

/**
 * Blizzard's equipment endpoint exposes only a numeric media ID, not the icon
 * slug required by the render CDN. The iconUrl approach is therefore removed.
 * Wowhead's tooltip script handles icon display via the data-wowhead attribute.
 */
const iconUrl = null;

/**
 * data-wowhead query string consumed by the Wowhead tooltip script.
 * Format: item=ID&ilvl=ILVL&ench=ENCHID&gems=GEM1:GEM2
 */
const wowheadData = computed(() => {
    if (!props.item) return '';

    const parts = [`item=${props.item.id}`];

    if (props.item.ilvl)
        parts.push(`ilvl=${props.item.ilvl}`);

    if (props.item.enchant_id)
        parts.push(`ench=${props.item.enchant_id}`);

    if (props.item.gem_ids?.length)
        parts.push(`gems=${props.item.gem_ids.join(':')}`);

    if (props.item.bonus_ids?.length)
        parts.push(`bonus=${props.item.bonus_ids.join(':')}`);

    if (props.classId) parts.push(`class=${props.classId}`);
    if (props.specId) parts.push(`spec=${props.specId}`);
    if (props.setItemIds.length) parts.push(`pcs=${props.setItemIds.join(':')}`);

    return parts.join('&');
});
</script>

<template>
    <div class="flex flex-col items-center gap-0.5">

        <!-- Equipped item -->
        <a v-if="item"
           :href="`https://www.wowhead.com/item=${item.id}`"
           target="_blank"
           rel="noopener noreferrer"
           :data-wowhead="wowheadData"
           class="w-8 h-8 relative border border-gray-700 rounded bg-gray-900/50 flex items-center justify-center group shrink-0"
           :class="{ 'border-red-500 shadow-[0_0_5px_rgba(239,68,68,0.5)]': hasMissingOptimization }"
           :title="item.name">

            <!-- Item icon -->
            <div class="absolute inset-0 overflow-hidden rounded bg-black/40">
                <img v-if="iconUrl"
                     :src="iconUrl"
                     class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"
                     alt="" />
            </div>

            <!-- ilvl badge -->
            <div class="absolute -top-1 -left-1 bg-black/80 px-0.5 text-5xs font-semibold leading-none rounded shadow-sm z-10"
                 :class="qualityColor">
                {{ item.ilvl }}
            </div>

            <!-- Upgrade track badge -->
            <div v-if="item.upgrade"
                 class="absolute -bottom-1 -left-1 bg-black/90 px-0.5 text-5xs font-semibold tracking-tighter rounded shadow-sm z-10 text-green-400 border border-green-500/30">
                {{ trackAbbreviations[item.upgrade.track] || item.upgrade.track }} {{ item.upgrade.level }}/{{ item.upgrade.max }}
            </div>

            <!-- Audit warning dot -->
            <div v-if="hasMissingOptimization"
                 class="absolute -top-1 -right-1 bg-red-600 rounded-full w-2.5 h-2.5 flex items-center justify-center border border-[#0e0e10] z-20 shadow-sm">
                <span class="text-5xs text-white font-black leading-none">!</span>
            </div>
        </a>

        <!-- Empty slot placeholder -->
        <div v-else
             class="w-8 h-8 border border-white/5 rounded bg-white/[0.02] flex items-center justify-center">
            <span class="text-white/10 text-base font-light">-</span>
        </div>

    </div>
</template>
