<script setup>
import { computed } from 'vue';

const props = defineProps({
    item: {
        type: Object,
        default: null
    },
    slotName: {
        type: String,
        required: true
    },
    // ОСЬ ТЕ, ЩО ЗАБУВ ДОДАТИ JUNIE:
    iconName: {
        type: String,
        default: null
    }
});

const QUALITY_COLORS = {
    'POOR': 'text-gray-400',
    'COMMON': 'text-white',
    'UNCOMMON': 'text-green-400',
    'RARE': 'text-blue-400',
    'EPIC': 'text-purple-400',
    'LEGENDARY': 'text-orange-400',
    'ARTIFACT': 'text-yellow-200',
    'HEIRLOOM': 'text-blue-200'
};

const qualityColor = computed(() => {
    if (!props.item?.quality?.type) return 'text-gray-500';
    return QUALITY_COLORS[props.item.quality.type] || 'text-white';
});

const isEnchantable = computed(() => {
    const enchantableSlots = ['BACK', 'CHEST', 'WRIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2', 'MAIN_HAND', 'OFF_HAND'];
    return enchantableSlots.includes(props.slotName.toUpperCase());
});

const hasEnchant = computed(() => {
    return Array.isArray(props.item?.enchantments) && props.item.enchantments.length > 0;
});

const socketInfo = computed(() => {
    if (!props.item?.sockets || !Array.isArray(props.item.sockets)) return null;
    const total = props.item.sockets.length;
    const filled = props.item.sockets.filter(s => s?.item).length;
    return { total, filled };
});

const hasMissingOptimization = computed(() => {
    if (!props.item) return false;
    return (isEnchantable.value && !hasEnchant.value) || (socketInfo.value && socketInfo.value.filled < socketInfo.value.total);
});

// ГЕНЕРУЄМО ПРАВИЛЬНУ КАРТИНКУ
const iconUrl = computed(() => {
    // 1. Беремо іконку з RaiderIO (через пропсу)
    if (props.iconName) {
        return `https://wow.zamimg.com/images/wow/icons/large/${props.iconName}.jpg`;
    }
    // 2. Якщо раптом немає RIO, беремо резервну з Bnet
    if (props.item?.media?.id) {
        return `https://render.worldofwarcraft.com/eu/icons/56/${props.item.media.id}.jpg`;
    }
    return null;
});

// ГЕНЕРУЄМО ДАНІ ДЛЯ WOWHEAD (щоб тултіп знав про чарки і сокети)
const wowheadData = computed(() => {
    if (!props.item) return '';
    const params = [];

    if (props.item.level?.value) params.push(`ilvl=${props.item.level.value}`);

    if (props.item.bonus_list?.length > 0) {
        params.push(`bonus=${props.item.bonus_list.join(':')}`);
    }

    if (props.item.enchantments?.[0]?.enchantment_id) {
        params.push(`ench=${props.item.enchantments[0].enchantment_id}`);
    }

    if (props.item.sockets?.length > 0) {
        const gems = props.item.sockets.filter(s => s.item).map(s => s.item.id).join(':');
        if (gems) params.push(`gems=${gems}`);
    }

    return params.join('&');
});

const upgradeTrack = computed(() => {
    if (!props.item) return null;
    const ilvl = props.item.level?.value || 0;
    const desc = props.item.name_description?.display_string?.en_US?.toUpperCase() || '';

    let letter = '';
    let baseIlvl = 0;
    let maxSteps = 0;
    const ilvlStep = 3;

    // Конфіг треків для 3-го сезону The War Within
    const tracks = {
        M: { letter: 'M', base: 289, max: 6 },
        H: { letter: 'H', base: 276, max: 6 },
        C: { letter: 'C', base: 263, max: 6 },
        V: { letter: 'V', base: 250, max: 8 },
        A: { letter: 'A', base: 224, max: 8 },
        E: { letter: 'E', base: 211, max: 8 }
    };

    // 1. Якщо предмет з ключів (Mythic+), визначаємо трек виключно по iLvl
    if (desc.includes('MYTHIC+')) {
        if (ilvl >= tracks.M.base) { letter = tracks.M.letter; baseIlvl = tracks.M.base; maxSteps = tracks.M.max; }
        else if (ilvl >= tracks.H.base) { letter = tracks.H.letter; baseIlvl = tracks.H.base; maxSteps = tracks.H.max; }
        else if (ilvl >= tracks.C.base) { letter = tracks.C.letter; baseIlvl = tracks.C.base; maxSteps = tracks.C.max; }
        else if (ilvl >= tracks.V.base) { letter = tracks.V.letter; baseIlvl = tracks.V.base; maxSteps = tracks.V.max; }
        else if (ilvl >= tracks.A.base) { letter = tracks.A.letter; baseIlvl = tracks.A.base; maxSteps = tracks.A.max; }
        else { letter = tracks.E.letter; baseIlvl = tracks.E.base; maxSteps = tracks.E.max; }
    }
    // 2. Якщо предмет з рейду або іншого джерела, де є чітка назва
    else if (desc === 'MYTHIC' || desc.includes('MYTHIC ')) {
        letter = tracks.M.letter; baseIlvl = tracks.M.base; maxSteps = tracks.M.max;
    }
    else if (desc.includes('HEROIC') || desc.includes('HERO')) {
        letter = tracks.H.letter; baseIlvl = tracks.H.base; maxSteps = tracks.H.max;
    }
    else if (desc.includes('NORMAL') || desc.includes('CHAMPION') || desc.includes('CHAMP')) {
        letter = tracks.C.letter; baseIlvl = tracks.C.base; maxSteps = tracks.C.max;
    }
    else if (desc.includes('RAID FINDER') || desc.includes('VETERAN') || desc.includes('VET')) {
        letter = tracks.V.letter; baseIlvl = tracks.V.base; maxSteps = tracks.V.max;
    }
    else if (desc.includes('ADVENTURER') || desc.includes('ADV')) {
        letter = tracks.A.letter; baseIlvl = tracks.A.base; maxSteps = tracks.A.max;
    }
    else if (desc.includes('EXPLORER') || desc.includes('EXP')) {
        letter = tracks.E.letter; baseIlvl = tracks.E.base; maxSteps = tracks.E.max;
    }
    else {
        return null; // Крафтові речі або леги не мають треків
    }

    if (!letter) return null;

    // 3. Математичний розрахунок кроку
    let stepString = '';
    if (baseIlvl > 0 && ilvl >= baseIlvl) {
        let currentStep = 1 + Math.round((ilvl - baseIlvl) / ilvlStep);
        if (currentStep > maxSteps) currentStep = maxSteps;
        stepString = ` ${currentStep}/${maxSteps}`;
    }

    return `${letter}${stepString}`;
});
</script>

<template>
    <div class="flex flex-col items-center gap-1">
        <a
            v-if="item"
            :href="`https://www.wowhead.com/item=${item.item.id}`"
            target="_blank"
            :data-wowhead="wowheadData"
            class="w-8 h-8 relative border border-gray-700 rounded bg-gray-900/50 flex items-center justify-center group shrink-0"
            :class="{ 'border-red-500 shadow-[0_0_5px_rgba(239,68,68,0.5)]': hasMissingOptimization }"
        >
            <div class="absolute inset-0 overflow-hidden rounded bg-black/40">
                <img
                    v-if="iconUrl"
                    :src="iconUrl"
                    class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity"
                    alt=""
                />
            </div>

            <div class="absolute -bottom-1 -right-1 bg-black/80 px-0.5 text-[8px] font-bold leading-none rounded shadow-sm z-10" :class="qualityColor">
                {{ item?.level?.value || '' }}
            </div>

            <div v-if="hasMissingOptimization" class="absolute -top-1 -right-1 bg-red-600 rounded-full w-2.5 h-2.5 flex items-center justify-center border border-[#0e0e10] z-20 shadow-sm">
                <span class="text-[7px] text-white font-black leading-none">!</span>
            </div>
        </a>

        <div v-if="item && upgradeTrack" class="text-[9px] font-bold leading-none uppercase mt-0.5" :class="qualityColor">
            {{ upgradeTrack }}
        </div>

        <div v-else-if="!item" class="w-8 h-8 border border-white/5 rounded bg-white/[0.02] flex items-center justify-center">
            <span class="text-white/10 text-base font-light">-</span>
        </div>
    </div>
</template>
