<script setup>
import { ref } from 'vue';
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

// --- Audit Helpers (Moved from Overview) ---

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

    const latestRaid = raids[raids.length - 1];
    const progress = rioData.raid_progression[latestRaid];
    return progress?.summary || '-';
};

// --- New TabSummary Helpers ---

const getTierInfo = (character) => {
    if (!character) return { count: 0, slots: {} };
    const bnetData = parseRawData(character?.raw_bnet_data);
    const equippedItems = bnetData?.equipped_items || bnetData?.equipment?.equipped_items;
    if (!equippedItems) return { count: 0, slots: {} };

    const tierSlots = {
        'HEAD': 'H',
        'SHOULDER': 'S',
        'CHEST': 'C',
        'HANDS': 'G',
        'LEGS': 'L'
    };

    let count = 0;
    let slots = {};

    equippedItems.forEach(item => {
        const slotType = item?.slot?.type;
        if (tierSlots[slotType]) {
            // In WoW Bnet API, item names are often objects with 'en_US', etc.
            const itemName = (typeof item.name === 'object') ? (item.name.name || item.name.en_US) : item.name;
            if (item.set || (itemName && typeof itemName === 'string' && (itemName.includes('Tier') || item.binding?.type === 'ON_EQUIP'))) {
                 count++;
                 slots[tierSlots[slotType]] = true;
            }
        }
    });

    return { count, slots };
};

const getMythicDungeonsCount = (character) => {
    if (!character) return 0;
    const rioData = parseRawData(character?.raw_raiderio_data);
    // Raider.io current_mythic_plus_weekly_highest_level_runs provides recent runs
    const runs = rioData?.mythic_plus_weekly_highest_level_runs || [];
    return runs.length;
};

const getVaultOptions = (character) => {
    // Vault options are complex to calculate from just profile data without the specialized Vault API
    // We can estimate from M+ weekly runs and Raid progression
    const rioData = parseRawData(character?.raw_raiderio_data);
    const mPlusRuns = rioData?.mythic_plus_weekly_highest_level_runs || [];

    // M+ Vault: 1, 4, 8 runs
    let mPlusOptions = 0;
    if (mPlusRuns.length >= 8) mPlusOptions = 3;
    else if (mPlusRuns.length >= 4) mPlusOptions = 2;
    else if (mPlusRuns.length >= 1) mPlusOptions = 1;

    // Raid Vault: 2, 4, 6 bosses (heuristics based on progression summary)
    const raidProgress = getRaidProgression(character); // e.g. "6/9 H"
    let raidOptions = 0;
    if (raidProgress !== '-') {
        const killed = parseInt(raidProgress.split('/')[0]);
        if (killed >= 6) raidOptions = 3;
        else if (killed >= 4) raidOptions = 2;
        else if (killed >= 2) raidOptions = 1;
    }

    return {
        m: mPlusOptions,
        h: raidOptions, // Using H for Raid in user's prompt
        c: 0,
        v: 0
    };
};

</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <!-- Group Header -->
          <tr class="bg-black/20 text-gray-500 text-[9px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">Character</th>
            <th class="p-2 text-center border-l border-white/5">iLvL</th>
            <th colspan="6" class="p-2 text-center border-l border-white/5">Tier Pieces</th>
            <th class="p-2 text-center border-l border-white/5">M+ Dungeons</th>
            <th colspan="4" class="p-2 text-center border-l border-white/5">Great Vault</th>
            <th class="p-2 text-center border-l border-white/5">M+ Rating</th>
            <th colspan="3" class="p-2 text-center border-l border-white/5">Audit</th>
          </tr>
          <!-- Sub Header -->
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[200px] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">Name</th>
            <th class="p-4 text-center">Avg</th>

            <th class="p-4 text-center border-l border-white/5 w-10">#</th>
            <th class="p-2 text-center w-8">H</th>
            <th class="p-2 text-center w-8">S</th>
            <th class="p-2 text-center w-8">C</th>
            <th class="p-2 text-center w-8">G</th>
            <th class="p-2 text-center w-8">L</th>

            <th class="p-4 text-center border-l border-white/5">This Week</th>

            <th class="p-4 text-center border-l border-white/5 w-8">M</th>
            <th class="p-4 text-center w-8">H</th>
            <th class="p-4 text-center w-8">C</th>
            <th class="p-4 text-center w-8">V</th>

            <th class="p-4 text-center border-l border-white/5">Rating</th>

            <th class="p-4 text-center border-l border-white/5">Enchants</th>
            <th class="p-4 text-center">Gems</th>
            <th class="p-4 text-center">Progress</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <template v-for="(char, index) in characters" :key="char.id">
            <tr class="hover:bg-white/[0.02] transition-colors" :class="{ 'cursor-pointer': char.alts && char.alts.length > 0 }" @click="char.alts && char.alts.length > 0 && toggleRow(char.id)">
              <!-- Name & Index -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3">
                  <span class="text-gray-600 font-mono text-xs w-4">{{ index + 1 }}</span>

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
                  </div>
                </div>
              </td>

              <!-- iLvL -->
              <td class="p-4 text-center font-mono font-bold text-cyan-400 border-l border-white/5">
                {{ char.ilvl ? char.ilvl.toFixed(2) : '-' }}
              </td>

              <!-- Tier Pieces -->
              <td class="p-4 text-center font-bold text-white border-l border-white/5">
                {{ getTierInfo(char).count }}
              </td>
              <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="slot" class="p-2 text-center">
                <span v-if="getTierInfo(char).slots[slot]" class="text-green-500 font-bold">{{ slot }}</span>
                <span v-else class="text-gray-800">-</span>
              </td>

              <!-- Mythic Dungeons -->
              <td class="p-4 text-center font-mono font-bold text-white border-l border-white/5">
                {{ getMythicDungeonsCount(char) }}
              </td>

              <!-- Great Vault -->
              <td class="p-4 text-center font-mono font-bold border-l border-white/5" :class="getVaultOptions(char).m > 0 ? 'text-green-400' : 'text-gray-700'">
                {{ getVaultOptions(char).m }}
              </td>
              <td class="p-4 text-center font-mono font-bold" :class="getVaultOptions(char).h > 0 ? 'text-green-400' : 'text-gray-700'">
                {{ getVaultOptions(char).h }}
              </td>
              <td class="p-4 text-center font-mono font-bold text-gray-700">0</td>
              <td class="p-4 text-center font-mono font-bold text-gray-700">0</td>

              <!-- M+ Rating -->
              <td class="p-4 text-center font-mono font-bold border-l border-white/5" :class="char.mythic_rating > 0 ? 'text-purple-400' : 'text-gray-600'">
                {{ char.mythic_rating ? Math.round(parseFloat(char.mythic_rating)) : '0' }}
              </td>

              <!-- Audit (Moved from Overview) -->
              <td class="p-4 text-center font-mono text-sm border-l border-white/5">
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
                <span class="text-[10px] font-bold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getRaidProgression(char) }}
                </span>
              </td>
            </tr>

            <!-- Alt Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 hover:bg-white/[0.02] transition-colors">
              <!-- Name (Nested Style) -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3 pl-8 relative">
                  <!-- L-shape connector -->
                  <div class="absolute left-4 top-0 bottom-1/2 w-3 border-l-2 border-b-2 border-white/10 rounded-bl-sm"></div>

                  <div class="w-6 h-6 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover" />
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.playable_class] || 'text-white'">
                      {{ alt.name }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- iLvL -->
              <td class="p-4 text-center font-mono text-xs text-cyan-400/70 border-l border-white/5">
                {{ alt.ilvl ? alt.ilvl.toFixed(2) : '-' }}
              </td>

              <!-- Tier Pieces -->
              <td class="p-4 text-center text-sm font-bold text-white/70 border-l border-white/5">
                {{ getTierInfo(alt).count }}
              </td>
              <td v-for="slot in ['H', 'S', 'C', 'G', 'L']" :key="'alt-tier-'+slot" class="p-2 text-center">
                <span v-if="getTierInfo(alt).slots[slot]" class="text-green-500/70 font-bold text-[10px]">{{ slot }}</span>
                <span v-else class="text-gray-800 text-[10px]">-</span>
              </td>

              <!-- Mythic Dungeons -->
              <td class="p-4 text-center font-mono text-sm font-bold text-white/70 border-l border-white/5">
                {{ getMythicDungeonsCount(alt) }}
              </td>

              <!-- Great Vault -->
              <td class="p-4 text-center font-mono text-sm font-bold border-l border-white/5" :class="getVaultOptions(alt).m > 0 ? 'text-green-400/70' : 'text-gray-800'">
                {{ getVaultOptions(alt).m }}
              </td>
              <td class="p-4 text-center font-mono text-sm font-bold" :class="getVaultOptions(alt).h > 0 ? 'text-green-400/70' : 'text-gray-800'">
                {{ getVaultOptions(alt).h }}
              </td>
              <td class="p-4 text-center font-mono text-sm font-bold text-gray-800">0</td>
              <td class="p-4 text-center font-mono text-sm font-bold text-gray-800">0</td>

              <!-- M+ Rating -->
              <td class="p-4 text-center font-mono text-sm font-bold border-l border-white/5" :class="alt.mythic_rating > 0 ? 'text-purple-400/70' : 'text-gray-800'">
                {{ alt.mythic_rating ? Math.round(parseFloat(alt.mythic_rating)) : '0' }}
              </td>

              <!-- Audit -->
              <td class="p-4 text-center font-mono text-[10px] border-l border-white/5">
                <span v-if="countMissingEnchants(alt) > 0" class="text-red-500/70 font-bold bg-red-500/5 rounded px-2 py-0.5">
                  {{ countMissingEnchants(alt) }}
                </span>
                <span v-else class="text-gray-800">-</span>
              </td>
              <td class="p-4 text-center font-mono text-[10px]">
                <span v-if="countEmptySockets(alt) > 0" class="text-red-500/70 font-bold bg-red-500/5 rounded px-2 py-0.5">
                  {{ countEmptySockets(alt) }}
                </span>
                <span v-else class="text-gray-800">-</span>
              </td>
              <td class="p-4 text-center">
                <span class="text-[9px] font-bold text-white/50 whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getRaidProgression(alt) }}
                </span>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
