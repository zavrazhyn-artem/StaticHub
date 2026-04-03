<script setup>
const props = defineProps({
    char: { type: Object, required: true },
    isAlt: { type: Boolean, default: false },
    hasAuditIssues: { type: Function, required: true },
    auditTitle: { type: Function, required: true },
});

// WoW Equip Slots in order
const slots = [
    'HEAD', 'NECK', 'SHOULDER', 'BACK', 'CHEST', 'WRIST',
    'HANDS', 'WAIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2',
    'TRINKET_1', 'TRINKET_2', 'MAIN_HAND', 'OFF_HAND'
];

const getItem = (slot) => {
    return (props.char?.equipment || []).find(i => i.slot === slot);
};

const getWowheadData = (item) => {
    if (!item) return '';
    const parts = [`item=${item.id}`];
    if (item.ilvl) parts.push(`ilvl=${item.ilvl}`);
    if (item.enchant_id) parts.push(`ench=${item.enchant_id}`);
    if (item.gem_ids?.length) parts.push(`gems=${item.gem_ids.join(':')}`);
    return parts.join('&');
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
</script>

<template>
    <!-- Audit column -->
    <td :class="[isAlt ? 'p-1.5 h-[42px]' : 'p-2.5 h-[72px]', 'text-center border-l border-white/5']">
        <span v-if="hasAuditIssues(char)"
              :class="[isAlt ? 'text-[8px] px-1' : 'text-[9px] px-1.5', 'inline-flex items-center gap-1 text-amber-400 bg-amber-400/10 border border-amber-400/20 rounded py-0.5 font-bold cursor-help']"
              :title="auditTitle(char)">
            <span class="material-symbols-outlined text-[10px]">warning</span>
            {{ (char.missing_enchants_slots?.length ?? 0) + (char.empty_sockets_count ?? 0) }}
        </span>
        <span v-else class="text-gray-600 text-[10px]">✓</span>
    </td>

    <!-- Equipment columns -->
    <td v-for="slot in slots" :key="slot" :class="isAlt ? 'h-[42px]' : 'h-[72px]'" class="p-0.5 border-l border-white/5">
        <div v-if="getItem(slot)" class="flex justify-center">
            <a :href="`https://www.wowhead.com/item=${getItem(slot).id}`"
               class="w-[28px] h-[28px] shrink-0 relative block bg-gray-800 border rounded overflow-hidden transition-colors flex items-center justify-center"
               :class="getQualityClass(getItem(slot).quality)"
               target="_blank"
               :data-wowhead="getWowheadData(getItem(slot))">
                <img v-if="getItem(slot).icon"
                     :src="`https://wow.zamimg.com/images/wow/icons/large/${getItem(slot).icon}.jpg`"
                     class="w-full h-full object-cover rounded"
                     :alt="getItem(slot).name" />
                <span v-else class="text-[8px] text-gray-500 font-bold uppercase">
                    {{ getItem(slot).slot ? getItem(slot).slot.substring(0,2) : '??' }}
                </span>
            </a>
        </div>
        <div v-else class="w-[28px] h-[28px] mx-auto rounded border border-white/5 bg-black/20 flex items-center justify-center" :title="slot">
            <span class="text-[9px] text-gray-800 font-bold uppercase">{{ slot.substring(0, 3) }}</span>
        </div>
    </td>
</template>
