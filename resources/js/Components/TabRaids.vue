<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
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

const getRaidProgression = (character) => {
    const rioData = parseRawData(character?.raw_raiderio_data);
    if (!rioData?.raid_progression) return '-';

    const raids = Object.keys(rioData.raid_progression);
    if (raids.length === 0) return '-';

    const latestRaid = raids[raids.length - 1];
    const progress = rioData.raid_progression[latestRaid];
    return progress?.summary || '-';
};

/**
 * Extract all unique bosses across all characters from WCL data.
 */
const allBosses = computed(() => {
    const bosses = new Set();
    const characters = props.characters || [];
    characters.forEach(char => {
        const wclData = parseRawData(char?.raw_wcl_data);
        const rankings = wclData?.characterData?.character?.zoneRankings?.rankings || [];
        rankings.forEach(ranking => {
            if (ranking.encounter?.name) {
                bosses.add(ranking.encounter.name);
            }
        });
    });
    return Array.from(bosses);
});

const getBossStatus = (character, bossName) => {
    const wclData = parseRawData(character?.raw_wcl_data);
    const rankings = wclData?.characterData?.character?.zoneRankings?.rankings || [];
    const ranking = rankings.find(r => r.encounter?.name === bossName);

    if (!ranking) return null;

    // Difficulty in WCL zoneRankings: 3 = Normal, 4 = Heroic, 5 = Mythic
    const difficultyMap = {
        3: { label: 'N', color: 'text-blue-400' },
        4: { label: 'H', color: 'text-green-400' },
        5: { label: 'M', color: 'text-purple-400' }
    };

    return difficultyMap[ranking.difficulty] || { label: 'K', color: 'text-white' };
};

</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <tr class="bg-black/40 text-cyan-400 text-[10px] uppercase tracking-widest font-bold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[200px] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">Character</th>
            <th class="p-4 text-center border-l border-white/5">Progression</th>
            <th v-for="boss in allBosses" :key="boss" class="p-4 text-center border-l border-white/5 text-[9px] min-w-[80px]">
              {{ boss }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <template v-for="char in characters" :key="char.id">
            <tr class="hover:bg-white/[0.02] transition-colors group" @click="char.alts && char.alts.length > 0 && toggleRow(char.id)" :class="{ 'cursor-pointer': char.alts && char.alts.length > 0 }">
              <!-- Name -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3">
                  <!-- Expand Icon -->
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
                    <div v-else class="w-4"></div>
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
                      {{ formatActiveSpec(char.active_spec) }} {{ char.playable_class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Progression Summary -->
              <td class="p-4 text-center border-l border-white/5">
                <span class="text-[11px] font-bold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getRaidProgression(char) }}
                </span>
              </td>

              <!-- Boss Matrix -->
              <td v-for="boss in allBosses" :key="boss" class="p-4 text-center border-l border-white/5">
                <template v-if="getBossStatus(char, boss)">
                  <span :class="['font-bold font-mono text-sm', getBossStatus(char, boss).color]">
                    {{ getBossStatus(char, boss).label }}
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-800">-</span>
                </template>
              </td>
            </tr>

            <!-- Alts Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 border-b border-white/5 transition-colors text-[0.9em]">
              <!-- Name with connector -->
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3 pl-4">
                  <!-- Visual connector -->
                  <div class="w-4 h-5 border-l-2 border-b-2 border-white/10 -mt-3 rounded-bl"></div>

                  <div class="w-7 h-7 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover opacity-70" />
                    <span v-else class="text-[10px] text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.playable_class] || 'text-white'">
                      {{ alt.name }}
                    </div>
                    <div class="text-[8px] text-gray-600 uppercase font-medium truncate">
                      {{ formatActiveSpec(alt.active_spec) }} {{ alt.playable_class }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Progression Summary (Alt) -->
              <td class="p-4 text-center border-l border-white/5 opacity-80">
                <span class="text-[10px] font-bold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getRaidProgression(alt) }}
                </span>
              </td>

              <!-- Boss Matrix (Alt) -->
              <td v-for="boss in allBosses" :key="'alt-'+boss" class="p-4 text-center border-l border-white/5 opacity-80">
                <template v-if="getBossStatus(alt, boss)">
                  <span :class="['font-bold font-mono text-xs', getBossStatus(alt, boss).color]">
                    {{ getBossStatus(alt, boss).label }}
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-800 text-xs">-</span>
                </template>
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
