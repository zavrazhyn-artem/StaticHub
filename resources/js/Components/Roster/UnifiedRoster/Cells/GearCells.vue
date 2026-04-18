<script setup>
import { inject, computed, getCurrentInstance } from 'vue';
import { useWowheadIcons } from '@/composables/useWowheadIcons';

const { proxy } = getCurrentInstance();
const __ = (key, replace = {}) => proxy.__(key, replace);

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

const getItem = (slot) => {
    return (props.char?.equipment || []).find(i => i.slot === slot);
};

const setItemIds = computed(() => {
    return (props.char?.equipment || [])
        .filter(i => i.is_set_piece)
        .map(i => i.id);
});

const trackAbbreviations = {
    Myth: 'M',
    Hero: 'H',
    Champion: 'C',
    Veteran: 'V',
    Adventurer: 'A',
    null: 'Craft'
};

const trackColorMap = {
    Myth: 'text-yellow-400',
    Hero: 'text-purple-400',
    Champion: 'text-blue-400',
    Veteran: 'text-green-400',
    Adventurer: 'text-gray-400'
};

const trackBorderMap = {
    Myth: 'border-yellow-400',
    Hero: 'border-purple-500',
    Champion: 'border-blue-500',
    Veteran: 'border-green-500',
    Adventurer: 'border-gray-500'
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

const getQualityColor = (quality) => {
    const map = {
        'POOR':      'text-gray-400',
        'COMMON':    'text-white',
        'UNCOMMON':  'text-green-400',
        'RARE':      'text-blue-400',
        'EPIC':      'text-purple-400',
        'LEGENDARY': 'text-orange-400',
        'ARTIFACT':  'text-yellow-200',
    };
    return map[quality] ?? 'text-white';
};

const getQualityClass = (quality) => {
    const map = {
        'POOR':      'border-gray-500',
        'COMMON':    'border-white/10',
        'UNCOMMON':  'border-green-500',
        'RARE':      'border-blue-500',
        'EPIC':      'border-purple-500',
        'LEGENDARY': 'border-orange-500',
        'ARTIFACT':  'border-gold-500',
    };
    return map[quality] ?? 'border-white/10';
};

const craftedColorClass = (ilvl) => {
    if (ilvl >= 275) return 'text-yellow-400';
    if (ilvl >= 262) return 'text-purple-400';
    if (ilvl >= 246) return 'text-green-400';
    return 'text-gray-400';
};

const craftedBorderClass = (ilvl) => {
    if (ilvl >= 275) return 'border-yellow-400';
    if (ilvl >= 262) return 'border-purple-500';
    if (ilvl >= 246) return 'border-green-500';
    return 'border-gray-500';
};

const ilvlColorClass = (item) => {
    if (item.is_crafted) return craftedColorClass(item.ilvl);
    if (item.upgrade?.track) return trackColorMap[item.upgrade.track] || 'text-gray-400';
    return 'text-gray-400';
};

const borderClass = (item) => {
    if (item.is_crafted) return craftedBorderClass(item.ilvl);
    if (item.upgrade?.track) return trackBorderMap[item.upgrade.track] || 'border-gray-500';
    return getQualityClass(item.quality);
};
</script>

<template>
    <!-- Audit column -->
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center border-l border-white/5']">
        <span v-if="hasAuditIssues(char)"
              @click="emit('audit-click')"
              :class="[isAlt ? 'text-5xs px-1' : 'text-3xs px-2 py-1', 'inline-flex items-center gap-1 text-amber-400 bg-amber-400/10 border border-amber-400/20 rounded font-semibold cursor-pointer hover:bg-amber-400/20 transition-colors']"
              :title="auditTitle(char)">
            <span class="material-symbols-outlined text-xs">warning</span>
            {{ (char.missing_enchants_slots?.length ?? 0) + (char.low_quality_enchants_slots?.length ?? 0) + (char.empty_sockets_count ?? 0) }}
        </span>
        <span v-else class="material-symbols-outlined text-gray-600 text-xs">check</span>
    </td>
    <td :class="[rh, isAlt ? 'px-1 py-0.5' : 'p-2.5', 'text-center border-l border-white/5 font-bold text-gray-300']">
        {{ char.upgrades_missing ?? 0 }}
    </td>

    <td v-for="slot in slots" :key="slot" :class="rh" class="p-0.5 border-l border-white/5">
        <div v-if="getItem(slot)" class="flex items-center justify-center h-full" :class="isAlt ? 'gap-1' : 'flex-col'">
            <!-- ilvl badge above icon (main) or inline (alt) -->
            <div :class="[isAlt ? 'text-5xs' : 'mb-0.5 text-3xs', 'font-semibold leading-none', ilvlColorClass(getItem(slot))]">
                {{ getItem(slot).ilvl }}
            </div>

            <a :key="getItem(slot).id"
               :href="`https://www.wowhead.com/item=${getItem(slot).id}`"
               :class="[isAlt ? 'w-5 h-5' : 'w-[34px] h-[34px]', 'shrink-0 relative block bg-gray-800 border rounded transition-colors overflow-hidden group', borderClass(getItem(slot))]"
               target="_blank"
               :data-wowhead="getWowheadData(getItem(slot))">
                <img v-if="getIconUrl(getItem(slot).id)"
                     :src="getIconUrl(getItem(slot).id)"
                     class="w-full h-full object-cover rounded"
                     :alt="getItem(slot).name" />
                <span v-else class="flex items-center justify-center w-full h-full text-5xs text-gray-400 font-semibold uppercase">
                    {{ slot.substring(0, 2) }}
                </span>
            </a>

            <!-- Track / Craft badge below icon (main only) -->
            <div v-if="!isAlt" class="mt-0.5 h-[12px] flex items-center">
                <div v-if="getItem(slot).is_crafted"
                     :class="['font-bold text-4xs uppercase tracking-wide', craftedColorClass(getItem(slot).ilvl)]">
                    {{ __('CRAFT') }}
                </div>
                <div v-else-if="getItem(slot).upgrade"
                     :class="['font-semibold text-3xs', trackColorMap[getItem(slot).upgrade.track] || 'text-gray-400']">
                    {{ trackAbbreviations[getItem(slot).upgrade.track] || getItem(slot).upgrade.track }} {{ getItem(slot).upgrade.level }}/{{ getItem(slot).upgrade.max }}
                </div>
            </div>
        </div>
        <div v-else :class="isAlt ? 'w-5 h-5' : 'w-[34px] h-[34px]'" class="mx-auto rounded border border-white/5 bg-black/20 flex items-center justify-center" :title="slot">
            <span :class="isAlt ? 'text-5xs' : 'text-4xs'" class="text-gray-800 font-semibold uppercase">{{ slot.substring(0, 3) }}</span>
        </div>
    </td>
</template>
