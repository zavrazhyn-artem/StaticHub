<script setup>
defineProps({
  characters: Array
});

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

const formatActiveSpec = (spec) => {
    if (!spec) return 'Unknown';
    if (typeof spec === 'string' && spec.startsWith('{')) {
        try {
            const decoded = JSON.parse(spec);
            return decoded.name || spec;
        } catch (e) {
            return spec;
        }
    }
    return spec;
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

const getRaidProgression = (character) => {
    const rioData = parseRawData(character?.raw_raiderio_data);
    if (!rioData?.raid_progression) return '-';

    const raids = Object.keys(rioData.raid_progression);
    if (raids.length === 0) return '-';

    // Get the most recent raid (the last key in the object)
    const latestRaid = raids[raids.length - 1];
    const progress = rioData.raid_progression[latestRaid];
    return progress?.summary || '-';
};
</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[200px] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">Character</th>
            <th class="p-4 text-center">ilvl</th>
            <th class="p-4 text-center">M+ Rating</th>
            <th class="p-4 text-center">Enchants</th>
            <th class="p-4 text-center">Gems</th>
            <th class="p-4 text-center">Raid Progress</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-for="char in characters" :key="char.id" class="hover:bg-white/[0.02] transition-colors">
            <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                  <img v-if="char.avatar_url" :src="char.avatar_url" :alt="char.name" class="w-full h-full object-cover" />
                  <span v-else class="text-[10px] text-white/20">?</span>
                </div>
                <div class="min-w-0">
                  <div class="font-bold text-sm truncate" :class="classColors[char.playable_class] || 'text-white'">
                    {{ char.name }}
                  </div>
                  <div class="text-[9px] text-gray-500 uppercase font-medium truncate">
                    {{ formatActiveSpec(char.active_spec) }} {{ char.playable_class }}
                  </div>
                </div>
              </div>
            </td>
            <td class="p-4 text-center font-mono font-bold text-cyan-400">
              {{ Math.round(char.ilvl) || '-' }}
            </td>
            <td class="p-4 text-center font-mono font-bold" :class="char.mythic_rating > 0 ? 'text-purple-400' : 'text-gray-600'">
              {{ char.mythic_rating ? Math.round(parseFloat(char.mythic_rating)) : '0' }}
            </td>
            <td class="p-4 text-center font-mono text-sm">
              <span v-if="countMissingEnchants(char) > 0" class="text-red-500 font-bold bg-red-500/10 rounded px-2 py-0.5">
                {{ countMissingEnchants(char) }}
              </span>
              <span v-else class="text-gray-600">-</span>
            </td>
            <td class="p-4 text-center font-mono text-sm">
              <span v-if="countEmptySockets(char) > 0" class="text-red-500 font-bold bg-red-500/10 rounded px-2 py-0.5">
                {{ countEmptySockets(char) }}
              </span>
              <span v-else class="text-gray-600">-</span>
            </td>
            <td class="p-4 text-center">
              <span class="text-[11px] font-bold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                {{ getRaidProgression(char) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
