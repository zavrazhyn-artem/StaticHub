<script setup>
import { onUpdated, ref } from 'vue';
import GearCell from './GearCell.vue';

defineProps({
  characters: Array
});

const expandedRows = ref(new Set());

const toggleRow = (id) => {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id);
  } else {
    expandedRows.value.add(id);
  }
};

onUpdated(() => {
  if (window.whTooltips && typeof window.whTooltips.refreshLinks === 'function') {
    window.whTooltips.refreshLinks();
  }
});

const GEAR_SLOTS = [
  { key: 'HEAD', name: 'Head' },
  { key: 'NECK', name: 'Neck' },
  { key: 'SHOULDER', name: 'Shoulders' },
  { key: 'BACK', name: 'Back' },
  { key: 'CHEST', name: 'Chest' },
  { key: 'WRIST', name: 'Wrist' },
  { key: 'HANDS', name: 'Hands' },
  { key: 'WAIST', name: 'Waist' },
  { key: 'LEGS', name: 'Legs' },
  { key: 'FEET', name: 'Feet' },
  { key: 'FINGER_1', name: 'Ring 1' },
  { key: 'FINGER_2', name: 'Ring 2' },
  { key: 'TRINKET_1', name: 'Trinket 1' },
  { key: 'TRINKET_2', name: 'Trinket 2' },
  { key: 'MAIN_HAND', name: 'Main Hand' },
  { key: 'OFF_HAND', name: 'Off Hand' }
];

const RIO_SLOT_MAP = {
    HEAD: 'head', NECK: 'neck', SHOULDER: 'shoulder', BACK: 'back',
    CHEST: 'chest', WRIST: 'wrist', HANDS: 'hands', WAIST: 'waist',
    LEGS: 'legs', FEET: 'feet', FINGER_1: 'finger1', FINGER_2: 'finger2',
    TRINKET_1: 'trinket1', TRINKET_2: 'trinket2', MAIN_HAND: 'mainhand', OFF_HAND: 'offhand'
};

const classColors = {
  'Death Knight': 'text-[#C41F3B]',
  'Demon Hunter': 'text-[#A330C9]',
  'Druid': 'text-[#FF7C0A]',
  'Evoker': 'text-[#33937F]',
  'Hunter': 'text-[#ABD473]',
  'Mage': 'text-[#3FC7EB]',
  'Monk': 'text-[#00FF98]',
  'Paladin': 'text-[#F48CBA]',
  'Priest': 'text-[#FFFFFF]',
  'Rogue': 'text-[#FFF468]',
  'Shaman': 'text-[#0070DD]',
  'Warlock': 'text-[#8788EE]',
  'Warrior': 'text-[#C69B6D]',
};

const parseRawData = (data) => {
    if (!data) return null;
    if (typeof data === 'object') return data;
    try {
        return JSON.parse(data);
    } catch (e) {
        return null;
    }
};

const getGearItem = (character, slotKey) => {
    const bnetData = parseRawData(character?.raw_bnet_data);
    console.log("getGearItem for", character?.name, "slot", slotKey, "bnetData keys:", bnetData ? Object.keys(bnetData) : 'null');
    if (!bnetData?.equipped_items) {
        if (bnetData?.equipment?.equipped_items) {
            return bnetData.equipment.equipped_items.find(i => i?.slot?.type === slotKey) || null;
        }
        return null;
    }
    return bnetData.equipped_items.find(i => i?.slot?.type === slotKey) || null;
};

const getRioIcon = (character, slotKey) => {
    if (!character?.raw_raiderio_data) return null;

    let rioData = character.raw_raiderio_data;

    // Якщо RaiderIO дані прийшли як текстовий рядок — парсимо їх!
    if (typeof rioData === 'string') {
        try {
            rioData = JSON.parse(rioData);
        } catch (e) {
            console.error("Failed to parse RaiderIO data for", character.name);
            return null;
        }
    }

    const rioSlot = RIO_SLOT_MAP[slotKey];
    return rioData?.gear?.items?.[rioSlot]?.icon || null;
};

const countMissingEnchants = (character) => {
    const bnetData = parseRawData(character?.raw_bnet_data);
    const equippedItems = bnetData?.equipped_items || bnetData?.equipment?.equipped_items;
    if (!equippedItems) return 0;

    const enchantableSlots = ['BACK', 'CHEST', 'WRIST', 'LEGS', 'FEET', 'FINGER_1', 'FINGER_2', 'MAIN_HAND', 'OFF_HAND'];
    let missingCount = 0;

    enchantableSlots.forEach(slotType => {
        const item = equippedItems.find(i => i?.slot?.type === slotType);
        if (item) {
            if (slotType === 'OFF_HAND' && item.inventory_type?.type !== 'WEAPON') {
                return;
            }
            if (!item.enchantments || item.enchantments.length === 0) {
                missingCount++;
            }
        }
    });

    return missingCount;
};

const countEmptySockets = (character) => {
    const bnetData = parseRawData(character?.raw_bnet_data);
    const equippedItems = bnetData?.equipped_items || bnetData?.equipment?.equipped_items;
    if (!equippedItems) return 0;

    let emptySockets = 0;
    equippedItems.forEach(item => {
        if (item.sockets) {
            item.sockets.forEach(socket => {
                if (!socket.item) {
                    emptySockets++;
                }
            });
        }
    });
    return emptySockets;
};
</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden max-w-[calc(100vw-3rem)] md:max-w-none">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-black/20 text-gray-500 text-[9px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)] min-w-[180px]">Character</th>
            <th colspan="2" class="p-2 text-center border-l border-white/5">Audit</th>
            <th :colspan="GEAR_SLOTS.length" class="p-2 text-center border-l border-white/5">Equipment</th>
          </tr>
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold">
            <th class="p-2 sticky left-0 z-20 bg-[#0e0e10] border-b border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
              <div class="flex justify-between items-center pr-2">
                <span>Character</span>
                <span class="text-gray-500 font-mono text-[9px]">ilvl</span>
              </div>
            </th>
            <th class="p-2 border-b border-white/5 text-center text-[9px]">Ench</th>
            <th class="p-2 border-b border-white/5 text-center text-[9px]">Gems</th>
            <th v-for="slot in GEAR_SLOTS" :key="slot.key" class="p-1 border-b border-white/5 min-w-[42px] text-center">
              {{ slot.name }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <template v-for="char in characters" :key="char.id">
            <tr class="hover:bg-white/[0.02] transition-colors" :class="{ 'cursor-pointer': char.alts && char.alts.length > 0 }" @click="char.alts && char.alts.length > 0 && toggleRow(char.id)">
              <td class="p-2 pl-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-3">
                    <!-- Chevron Icon -->
                    <div class="w-4 flex items-center justify-center">
                      <svg v-if="char.alts && char.alts.length > 0"
                           xmlns="http://www.w3.org/2000/svg"
                           class="w-4 h-4 transition-transform duration-200"
                           :class="{ 'rotate-90': expandedRows.has(char.id) }"
                           viewBox="0 0 24 24"
                           fill="none"
                           stroke="currentColor"
                           stroke-width="2"
                           stroke-linecap="round"
                           stroke-linejoin="round"
                      >
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </div>

                    <div class="w-8 h-8 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                      <img v-if="char.avatar_url" :src="char.avatar_url" :alt="char.name" class="w-full h-full object-cover" />
                      <span v-else class="text-[10px] text-white/20">?</span>
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-sm truncate" :class="classColors[char.playable_class] || 'text-white'">
                        {{ char.name }}
                      </div>
                      <div class="text-[9px] text-gray-500 uppercase font-medium truncate">
                        {{ char.active_spec || 'Unknown' }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <div class="text-xs font-mono font-bold text-cyan-400">
                      {{ Math.floor(char.ilvl) || 'N/A' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="p-2 text-center font-mono text-sm border-l border-white/5">
                <span v-if="countMissingEnchants(char) > 0" class="text-red-500 font-bold">
                  {{ countMissingEnchants(char) }}
                </span>
                <span v-else class="text-gray-600 text-xs">0</span>
              </td>
              <td class="p-2 text-center font-mono text-sm">
                <span v-if="countEmptySockets(char) > 0" class="text-red-500 font-bold">
                  {{ countEmptySockets(char) }}
                </span>
                <span v-else class="text-gray-600 text-xs">0</span>
              </td>
              <td v-for="slot in GEAR_SLOTS" :key="slot.key" class="p-1 text-center">
                <div class="flex justify-center">
                  <GearCell :item="getGearItem(char, slot.key)" :slotName="slot.key" :iconName="getRioIcon(char, slot.key)" />
                </div>
              </td>
            </tr>

            <!-- Alt Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 hover:bg-white/[0.02] transition-colors">
              <td class="p-2 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center justify-between gap-3 pl-8 relative">
                  <!-- L-shape connector -->
                  <div class="absolute left-4 top-0 bottom-1/2 w-3 border-l-2 border-b-2 border-white/10 rounded-bl-sm"></div>

                  <div class="flex items-center gap-3">
                    <div class="w-6 h-6 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                      <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover" />
                      <span v-else class="text-[10px] text-white/20">?</span>
                    </div>
                    <div class="min-w-0">
                      <div class="font-bold text-xs truncate" :class="classColors[alt.playable_class] || 'text-white'">
                        {{ alt.name }}
                      </div>
                      <div class="text-[8px] text-gray-500 uppercase font-medium truncate">
                        {{ alt.active_spec || 'Unknown' }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right shrink-0">
                    <div class="text-[10px] font-mono font-bold text-cyan-400/70">
                      {{ Math.floor(alt.ilvl) || 'N/A' }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="p-2 text-center font-mono text-xs border-l border-white/5">
                <span v-if="countMissingEnchants(alt) > 0" class="text-red-500/70 font-bold">
                  {{ countMissingEnchants(alt) }}
                </span>
                <span v-else class="text-gray-700 text-[10px]">0</span>
              </td>
              <td class="p-2 text-center font-mono text-xs">
                <span v-if="countEmptySockets(alt) > 0" class="text-red-500/70 font-bold">
                  {{ countEmptySockets(alt) }}
                </span>
                <span v-else class="text-gray-700 text-[10px]">0</span>
              </td>
              <td v-for="slot in GEAR_SLOTS" :key="'alt-'+slot.key" class="p-1 text-center opacity-70 scale-90">
                <div class="flex justify-center">
                  <GearCell :item="getGearItem(alt, slot.key)" :slotName="slot.key" :iconName="getRioIcon(alt, slot.key)" />
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.2);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}
</style>
