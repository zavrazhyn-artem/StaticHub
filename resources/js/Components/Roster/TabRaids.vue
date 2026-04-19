<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
  characters: Array,
  selectedDifficulty: { type: String, default: null },
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

const diffColors = {
  M: 'text-purple-400',
  H: 'text-orange-400',
  N: 'text-green-400',
  LFR: 'text-blue-400',
};

/**
 * Build structured boss list from raids data.
 * Returns: [ { instance, bosses: [bossName, ...] }, ... ]
 */
const raidInstances = computed(() => {
  const characters = props.characters || [];
  const instanceMap = {};

  for (const char of characters) {
    const raids = char?.raids;
    if (!raids) continue;
    for (const [instanceName, bosses] of Object.entries(raids)) {
      if (!instanceMap[instanceName]) {
        instanceMap[instanceName] = bosses.map(b => b.name);
      }
    }
  }

  return Object.entries(instanceMap).map(([name, bosses]) => ({ name, bosses }));
});

const allBosses = computed(() => raidInstances.value.flatMap(r => r.bosses));

/**
 * Get the best difficulty killed this week for a boss.
 * Priority: M > H > N > LFR
 */
const getBossKill = (char, bossName) => {
  const raids = char?.raids;
  if (!raids) return null;

  for (const bosses of Object.values(raids)) {
    const boss = bosses.find(b => b.name === bossName);
    if (!boss) continue;

    if (boss.M) return { label: 'M', color: diffColors.M };
    if (boss.H) return { label: 'H', color: diffColors.H };
    if (boss.N) return { label: 'N', color: diffColors.N };
    if (boss.LFR) return { label: 'LFR', color: diffColors.LFR };
  }

  return null;
};

const getWeeklyProgression = (char) => {
  const raids = char?.raids;
  if (!raids) return '-';
  let totalKills = { M: 0, H: 0, N: 0, LFR: 0 };
  let totalBosses = 0;
  for (const bosses of Object.values(raids)) {
    totalBosses += bosses.length;
    for (const boss of bosses) {
      if (boss.M) totalKills.M++;
      if (boss.H) totalKills.H++;
      if (boss.N) totalKills.N++;
      if (boss.LFR) totalKills.LFR++;
    }
  }
  if (totalKills.M > 0) return `${totalKills.M}/${totalBosses} M`;
  if (totalKills.H > 0) return `${totalKills.H}/${totalBosses} H`;
  if (totalKills.N > 0) return `${totalKills.N}/${totalBosses} N`;
  if (totalKills.LFR > 0) return `${totalKills.LFR}/${totalBosses} LFR`;
  return '-';
};
</script>

<template>
  <div class="bg-[#0e0e10] border border-white/5 rounded-lg overflow-hidden">
    <div class="overflow-x-auto custom-scrollbar">
      <table class="w-full text-left border-collapse min-w-max">
        <thead>
          <!-- Instance headers -->
          <tr class="bg-black/20 text-gray-400 text-4xs uppercase tracking-wider font-semibold border-b border-white/5">
            <th class="p-2 pl-4 sticky left-0 z-20 bg-[#0e0e10] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Character') }}</th>
            <th class="p-2 text-center border-l border-white/5">{{ __('Weekly') }}</th>
            <template v-for="inst in raidInstances" :key="inst.name">
              <th :colspan="inst.bosses.length" class="p-2 text-center border-l border-white/5">{{ inst.name }}</th>
            </template>
          </tr>
          <!-- Boss name headers -->
          <tr class="bg-black/40 text-emerald-400 text-3xs uppercase tracking-wider font-semibold border-b border-white/5">
            <th class="p-4 sticky left-0 z-20 bg-[#0e0e10] min-w-[12.5rem] shadow-[2px_0_5px_rgba(0,0,0,0.3)]">{{ __('Name') }}</th>
            <th class="p-4 text-center border-l border-white/5">{{ __('Prog') }}</th>
            <th v-for="boss in allBosses" :key="boss" class="p-4 text-center border-l border-white/5 text-4xs min-w-20">
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
                  <div class="w-4 flex items-center justify-center">
                    <svg v-if="char.alts && char.alts.length > 0"
                         xmlns="http://www.w3.org/2000/svg"
                         class="w-4 h-4 transition-transform duration-200"
                         :class="{ 'rotate-90': expandedRows.has(char.id) }"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                    <div v-else class="w-4"></div>
                  </div>
                  <div class="w-8 h-8 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="char.avatar_url" :src="char.avatar_url" :alt="char.name" class="w-full h-full object-cover" />
                    <span v-else class="text-3xs text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-sm truncate" :class="classColors[char.class] || 'text-white'">
                      {{ char.name }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Weekly Progression Summary -->
              <td class="p-4 text-center border-l border-white/5">
                <span class="text-2xs font-semibold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">
                  {{ getWeeklyProgression(char) }}
                </span>
              </td>

              <!-- Boss Matrix -->
              <td v-for="boss in allBosses" :key="boss" class="p-4 text-center border-l border-white/5">
                <template v-if="getBossKill(char, boss)">
                  <span :class="['font-bold font-mono text-sm', getBossKill(char, boss).color]">
                    {{ getBossKill(char, boss).label }}
                  </span>
                </template>
                <template v-else>
                  <span class="text-gray-800">-</span>
                </template>
              </td>
            </tr>

            <!-- Alt Rows -->
            <tr v-for="alt in char.alts" :key="'alt-'+alt.id" v-show="expandedRows.has(char.id)" class="bg-black/40 border-b border-white/5 transition-colors text-[0.9em]">
              <td class="p-4 sticky left-0 z-10 bg-[#0e0e10]/95 backdrop-blur-sm border-r border-white/5 shadow-[2px_0_5px_rgba(0,0,0,0.3)]">
                <div class="flex items-center gap-3 pl-4">
                  <div class="w-4 h-5 border-l-2 border-b-2 border-white/10 -mt-3 rounded-bl"></div>
                  <div class="w-7 h-7 rounded border border-white/10 bg-black/20 flex items-center justify-center overflow-hidden shrink-0">
                    <img v-if="alt.avatar_url" :src="alt.avatar_url" :alt="alt.name" class="w-full h-full object-cover opacity-70" />
                    <span v-else class="text-3xs text-white/20">?</span>
                  </div>
                  <div class="min-w-0">
                    <div class="font-bold text-xs truncate" :class="classColors[alt.class] || 'text-white'">{{ alt.name }}</div>
                  </div>
                </div>
              </td>

              <td class="p-4 text-center border-l border-white/5 opacity-80">
                <span class="text-3xs font-semibold text-white whitespace-nowrap bg-white/5 px-2 py-1 rounded">{{ getWeeklyProgression(alt) }}</span>
              </td>

              <td v-for="boss in allBosses" :key="'alt-'+boss" class="p-4 text-center border-l border-white/5 opacity-80">
                <template v-if="getBossKill(alt, boss)">
                  <span :class="['font-bold font-mono text-xs', getBossKill(alt, boss).color]">{{ getBossKill(alt, boss).label }}</span>
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
